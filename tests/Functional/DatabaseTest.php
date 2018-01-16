<?php
namespace Ipstack\Test\Functional;

use PHPUnit\Framework\TestCase;
use Ipstack\Wizard\Wizard;
use Ipstack\Wizard\Sheet\Register;
use Ipstack\Wizard\Sheet\Network;
use Ipstack\Wizard\Sheet\Field\StringField;
use Ipstack\Wizard\Sheet\Field\NumericField;
use Ipstack\Wizard\Sheet\Field\LatitudeField;
use Ipstack\Wizard\Sheet\Field\LongitudeField;

/**
 * @covers Finder
 * @covers Wizard
 */
class DatabaseTest extends TestCase
{
    /**
     * Compile simple database.
     *
     * @throws \ErrorException
     */
    public function testSimple()
    {
        $time = time();
        $author = 'Unit Test';
        $license = 'Test License';
        $tmpDir = IPSTACK_TEST_TMP_DIR;
        $csvDir = IPSTACK_TEST_CSV_DIR.DIRECTORY_SEPARATOR.'simple';
        $dbFile = IPSTACK_TEST_TMP_DIR.DIRECTORY_SEPARATOR.'ipstack.simple.dat';

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir);
        }

        $intervals = (new Register($csvDir.DIRECTORY_SEPARATOR.'intervals.csv'))
            ->setCsv('UTF-8')
            ->setFirstRow(2)
            ->setId(1)
            ->addField('name', 2, new StringField())
        ;
        $network = (new Network($csvDir.DIRECTORY_SEPARATOR.'networks.csv', Network::IP_TYPE_ADDRESS, 1, 2))
            ->setCsv('UTF-8')
            ->setFirstRow(2)
        ;

        $wizard = (new Wizard($tmpDir))
            ->setAuthor($author)
            ->setTime($time)
            ->setLicense($license)
            ->addRegister('interval', $intervals)
            ->addNetwork(
                $network,
                array(
                    3 => 'interval',
                )
            )
        ;
        $wizard->compile($dbFile);

        $db = $this->parseFile($dbFile);

        $this->assertSame('ISD', $db['header']['control']);
        $this->assertSame(1, $db['header']['RGC']);
        $this->assertSame(0, $db['header']['RLC']);
        $this->assertSame('A10name', $db['meta']['registers']['interval']['pack']);
        $this->assertSame(10, $db['meta']['registers']['interval']['len']);
        $this->assertSame(4, $db['meta']['registers']['interval']['items']);
        $this->assertSame('Cinterval', $db['meta']['networks']['pack']);
        $this->assertSame(5, $db['meta']['networks']['len']);
        $this->assertSame(4, $db['meta']['networks']['items']);
        $this->assertSame(0, $db['index'][0]);
        $this->assertSame(0, $db['index'][63]);
        $this->assertSame(1, $db['index'][64]);
        $this->assertSame(1, $db['index'][65]);
        $this->assertSame(1, $db['index'][127]);
        $this->assertSame(2, $db['index'][128]);
        $this->assertSame(2, $db['index'][129]);
        $this->assertSame(2, $db['index'][171]);
        $this->assertSame(3, $db['index'][172]);
        $this->assertSame(3, $db['index'][173]);
        $this->assertSame(3, $db['index'][255]);
        $this->assertArrayNotHasKey(256, $db['index']);
        $this->assertSame($time, $db['time']);
        $this->assertSame($author, $db['author']);
        $this->assertSame($license, $db['license']);

        $tmpFiles = glob($tmpDir.DIRECTORY_SEPARATOR.'*');
        foreach ($tmpFiles as $tmpFile) {
            unlink($tmpFile);
        }
        rmdir($tmpDir);
    }

    protected function parseFile($dbFile)
    {
        $result = array(
            'header' => array(
                'control' => '',
                'size' => 0,
                'version' => 0,
                'RGC' => 0,
                'RGF' => 0,
                'RGD' => 0,
                'RLC' => 0,
                'RLF' => 0,
                'RLD' => 0,
                'RLUF' => '',
                'RGMUF' => '',

            ),
            'meta' => array(),
            'index' => array(),
            'networks' => array(),
            'registers' => array(),
            'relations' => array(),
            'time' => '',
            'author' => '',
            'license' => '',
        );
        $db = fopen($dbFile,'rb');
        $meta = unpack('A3control/Ssize', fread($db, 5));
        $result['header']['size'] = $meta['size'];
        $result['header']['control'] = $meta['control'];
        $header = fread($db, $result['header']['size']);
        $offset = 0;
        $meta = unpack('Cversion/CRGC/SRGF/SRGD/CRLC/CRLF/SRLD', substr($header,$offset,10));
        $result['header'] = array_replace($result['header'], $meta);
        $offset += 10;
        $unpack = 'A'.$meta['RLF'].'RLUF/A'.$meta['RGF'].'RGMUF';
        $size = $meta['RLF']+$meta['RGF'];
        $meta = unpack($unpack, substr($header, $offset, $size));
        $result['header'] = array_replace($result['header'], $meta);
        $offset += $size;
        for ($i=0;$i<$result['header']['RLC'];$i++) {
            $result['relations'][] = unpack(
                $result['header']['RLUF'],
                substr($header, $offset, $result['header']['RLD'])
            );
            $offset += $result['header']['RLD'];
        }
        for ($i=0;$i<$result['header']['RGC'];$i++) {
            $meta = unpack(
                $result['header']['RGMUF'],
                substr($header, $offset, $result['header']['RGD'])
            );
            $id = $meta['name'];
            unset($meta['name']);
            $result['meta']['registers'][$id] = $meta;
            $offset += $result['header']['RGD'];
        }
        $meta = unpack(
            $result['header']['RGMUF'],
            substr($header, $offset, $result['header']['RGD'])
        );
        unset($meta['name']);
        $result['meta']['networks'] = $meta;
        $offset += $result['header']['RGD'];
        $result['index'] = array_values(unpack('I*',substr($header, $offset)));

        for ($i=0;$i<$result['meta']['networks']['items'];$i++) {
            $data = unpack(
                'N:ip/'.$result['meta']['networks']['pack'],
                fread($db, $result['meta']['networks']['len'])
            );
            $data[':ip'] = long2ip($data[':ip']);
            $result['networks'][] = $data;
        }

        foreach ($result['meta']['registers'] as $id=>$register) {
            for ($i=0;$i<=$register['items'];$i++) {
                $result['registers'][$id][] = unpack(
                    $register['pack'],
                    fread($db, $register['len'])
                );
            }
        }

        $meta = unpack('Itime/A128author/A*license', fread($db, filesize($dbFile)));
        $result = array_replace($result, $meta);
        fclose($db);
        return $result;
    }
}
