# 1. Установка

## 1.1. Системные требования
* PHP 5.3 или выше
* PHP Расширение PDO с поддержкой SQLite

## 1.2. Установка с помощью composer
1. Добавьте пакет в зависимости:
    ```text
    composer require ipstack/wizard
    ```
2. Подключите автозагрузку классов
    ```php
    <?php
    include('vendor/autoload.php');
    ```
## Ручная установка
1. Скачайте [архив](https://github.com/ipstack/wizard/archive/v2.0.0.zip)
2. Распакуйте в директорию с библиотеками проекта /path/to/libraries/ipstack/wizard/
3. Подключите файлы
    ```php
    <?php
    $path = '/path/to/libraries/ipstack/wizard';
    require_once($path.'/src/Wizard.php');
    require_once($path.'/src/Builder/BuilderInterface.php');
    require_once($path.'/src/Builder/IpstackVersion1.php');
    require_once($path.'/src/Database/Database.php');
    require_once($path.'/src/Database/Rows.php');
    require_once($path.'/src/Exception/IncorrectFieldName.php');
    require_once($path.'/src/Exception/IncorrectFieldType.php');
    require_once($path.'/src/Exception/IncorrectRegisterName.php');
    require_once($path.'/src/Exception/RegisterNotFound.php');
    require_once($path.'/src/Exception/RowNotFound.php');
    require_once($path.'/src/Exception/RelationIsRecursive.php');
    require_once($path.'/src/Exception/FieldNotFound.php');
    ```

# 2. Использование

1. Инициализируйте конвертер.
    ```php
    <?php
    $tmpDir = 'path/to/dir/for/temporary/files';
    $wizard = new \Ipstack\Wizard\Wizard($tmpDir);
    ```
1. Укажите информацию об авторе БД.
    ```php
    <?php
    /**
     * $author - строка длиной не более 64 символов.
     */
    $author = 'Name Surname';
    $wizard->setAuthor($author);
    ```
1. Укажите время создания БД.
    ```php
    <?php
    /**
     * $time - время в формате unix timestamp.
     */
    $wizard->setTime(1507638600); // 2017/10/10 15:30:00
    ```

1. Укажите лицензию базы данных.
    ```php
    <?php
    /**
     * $license - может быть название публичной лицензии, ссылка на лицензионное соглашение или же непосредственно текст лицензии. Длина не лимитирована.
     */
    $license = 'MIT';
    $wizard->setLicense($license);
    ```

1. Опишите справочники
    ```php
    <?php
    /*
     * метод addRegister принимает 2 параметра:
     * - строка, имя справочника
     * - массив, поля справочника (ключ является названием поля, а значение - типом)
     */
    $wizard
        ->addRegister('city', array(
            'id'         => \Ipstack\Wizard\Wizard::TYPE_INT,
            'country_id' => \Ipstack\Wizard\Wizard::TYPE_INT,
            'name'       => \Ipstack\Wizard\Wizard::TYPE_STRING,
        ))->addRegister('country', array(
            'code'     => \Ipstack\Wizard\Wizard::TYPE_CHAR,
            'name'      => \Ipstack\Wizard\Wizard::TYPE_STRING,
        ))
    ;
    ```
    
    ```text
    В зависимости от формата поддерживаются следующие типы:
    - \Ipstack\Wizard\Wizard::TYPE_INT    - целое число
    - \Ipstack\Wizard\Wizard::TYPE_FLOAT  - число с плавающей точкой (4 байта)
    - \Ipstack\Wizard\Wizard::TYPE_DOUBLE - число с плавающей точкой (8 байт)
    - \Ipstack\Wizard\Wizard::TYPE_NUMBER - число с фиксированным количеством знаков после запятой
    - \Ipstack\Wizard\Wizard::TYPE_STRING - строка неопределенной длины
    - \Ipstack\Wizard\Wizard::TYPE_CHAR   - строка фиксированной длины
    ```

1. Опишите связи между справочниками
    ```php
    <?php
    /*
     * Метод addRelation принимает 3 параметра
     * - строка, название родительского справочника
     * - строка, поле родительского справочника, в котором хранится идентификатор записи дочернего справочника
     * - строка, название дочернего справочника
     */
    $wizard->addRelation('city', 'country_id', 'country');
    ```

1. Заполните справочники
    ```php
    <?php
    /*
     * Все методы класса \Ipstack\Wizard\Wizard можно вызывать цепочкой
     */
    /*
     * метод addRow принимает 3 параметра
     * - строка, название справочника
     * - строка, идентификатор записи
     * - массив, запись (ключ массива является название поля, значение - его значением)
     */
    $wizard
        ->addRow('country', '1', array('id'=>'ru', 'name'=>'Russia'))
        ->addRow('country', '2', array('id'=>'kz', 'name'=>'Kazakhstan'))
        ->addRow('country', '3', array('id'=>'us', 'name'=>'USA'))
        ->addRow('city', '1', array('id'=>'1', 'name'=>'Moscow', 'country_id'=>'1'))
        ->addRow('city', '2', array('id'=>'2', 'name'=>'Saint-Peterburg', 'country_id'=>'1'))
        ->addRow('city', '3', array('id'=>'3', 'name'=>'Almaty', 'country_id'=>'2'))
        ->addRow('city', '4', array('id'=>'4', 'name'=>'Astana', 'country_id'=>'2'))
        ->addRow('city', '5', array('id'=>'5', 'name'=>'New York', 'country_id'=>'3'))
        ->addRow('city', '6', array('id'=>'6', 'name'=>'Los-Angeles', 'country_id'=>'3'))
    ;
    ```

1. Заполните интервалы IP-адресов
    ```php
    <?php
    /*
     * метод addInterval принимает 4 параметра
     * - строка, первый IP-адрес интервала
     * - строка, последний IP-адрес интервала
     * - строка, название справочника
     * - строка, идентификатор строки справочника
     */
    $wizard
        ->addInterval('127.0.0.0', '127.0.0.255', 'city', '1')
        ->addInterval('127.0.3.0', '127.0.3.255', 'city', '1')
        ->addInterval('127.0.5.0', '127.0.5.255', 'city', '1')
        ->addInterval('127.0.7.0', '127.0.7.255', 'city', '2')
        ->addInterval('127.0.9.0', '127.0.9.255', 'city', '2')
        ->addInterval('127.0.11.0', '127.0.11.255', 'city', '2')
        ->addInterval('127.0.13.0', '127.0.13.255', 'city', '3')
        ->addInterval('127.0.15.0', '127.0.15.255', 'city', '3')
        ->addInterval('127.0.17.0', '127.0.17.255', 'city', '3')
        ->addInterval('127.0.19.0', '127.0.19.255', 'city', '4')
        ->addInterval('127.0.21.0', '127.0.21.255', 'city', '4')
        ->addInterval('127.0.23.0', '127.0.23.255', 'city', '4')
        ->addInterval('127.0.25.0', '127.0.25.255', 'city', '5')
        ->addInterval('127.0.27.0', '127.0.27.255', 'city', '5')
        ->addInterval('127.0.29.0', '127.0.29.255', 'city', '5')
        ->addInterval('127.0.31.0', '127.0.31.255', 'city', '6')
        ->addInterval('127.0.33.0', '127.0.33.255', 'city', '6')
        ->addInterval('127.0.35.0', '127.0.35.255', 'city', '6')
    ;
    ```

1. Скомпилируйте бинарный файл БД
    ```php
    <?php
    /*
     * Метод build прнимает 2 параметра:
     * - строка, формат файла БД
     * - строка, путь к файлу БД
     */
     $wizard->build(\Ipstack\Wizard\Wizard::FORMAT_IPSTACK_V1, '/path/to/ipstack.dat');
    ```
    
    ```text
    Поддерживаются следующие форматы БД:
    - \Ipstack\Wizard\Wizard::FORMAT_IPSTACK_V1 - IPStack версии 1
    ```

# 3. Форматы баз данных

## 3.1 IPStack v1

|Размер|Описание|
|---|---|
|3|Контрольное слово для проверки принадлености файла к библиотеке. Всегда равно ISD|
|1|Формат unpak для чтения размера заголовка|
|1 или 4|Размер заголовка|
|1|Версия формата Ipstack|
|1|Количество справочников (RC)|
|4|Размер формата unpack описания справочников (RF)|
|RF|Формат unpack описания справочников|
|4|Размер описания одного справочника (RS)|
|RS*(RC+1)|Описания справочников|
|1024|Индекс первых октетов|
|?|БД диапазонов|
|?|БД справочника 1|
|?|БД справочника 2|
|...|...|
|?|БД справочника RC|
|4|Время создания БД в формате Unix Timestamp|
|128|Автор БД|
|?|Лицензия БД|

# 4. Примеры
## 4.1. Создание БД используя данные GeoLite2 Country
```php
<?php
/* Функция для определения крайних IP-адресов диапазона */


    /**
     * Get first and last IP addresses by prefix or inetnum
     *
     * @param string $prefixOrInetnum
     * @return array
     */
    function parseInetnum($prefixOrInetnum)
    {
        $result = array('first'=>null,'last'=>null);
        if (strpos($prefixOrInetnum,'-') !== false) {
            $d = explode('-',$prefixOrInetnum);
            $result['first'] = trim($d[0]);
            $result['last'] = trim($d[1]);
        }
        if (strpos($prefixOrInetnum,'/') !== false) {
            $d = explode('/',$prefixOrInetnum);
            $ipnum = ip2long((string) $d[0]);
            $prefix = filter_var($d[1], \FILTER_VALIDATE_INT, array(
                'options' => array('min_range' => 0, 'max_range' => 32)
            ));
            if (false === $ipnum or false === $prefix) {
                return $result;
            }
            $netsize = pow(2, (32 - $prefix));
            $end_num = $ipnum + $netsize - 1;
            if ($end_num >= pow(2, 32)) {
                return $result;
            }
            $result['first'] = $ipnum;
            $result['last'] = $end_num;
        }
        return $result;
    }

/* Используем директорию для хранения временных файлов. У скрипта должны быть права на запись в эту директорию. */
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . 'tmp';

/* Инициализируем класс Wizard. */
$wizard = new \Ipstack\Wizard\Wizard($tmpDir);

/* Указываем путь для сохранения БД. Скрипт должен иметь права на запись этого файла. */
$dbFile = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'iptool.geo.country.dat';

/* УРЛ для скачивания архива.*/
$url = 'http://geolite.maxmind.com/download/geoip/database/GeoLite2-Country-CSV.zip';

/* Имя временного файла. */
$tmpFile = $tmpDir . DIRECTORY_SEPARATOR . 'geolite2country.zip';

/* Скачиваем архив. */
copy($url, $tmpFile);

/* Ищем в архиве путь к нужным файлам. */
$zip = new ZipArchive();
if ($zip->open($tmpFile) !== true) die;
$i = -1;
$zipPath = null;
do {
    $i++;
    $csv = $zip->getNameIndex($i);
    preg_match('/(?<file>(?<zipPath>.*)\/GeoLite2\-Country\-Blocks\-IPv4\.csv)$/ui', $csv, $m);
} while ($i < $zip->numFiles && empty($m['file']));
$zipPath = $m['zipPath'];
$zip->close();

/* Запоминаем в переменные пути к нужным CSV файлам. */
$locations = 'zip://' . $tmpFile . '#' . $zipPath . DIRECTORY_SEPARATOR . 'GeoLite2-Country-Locations-en.csv';
$networks = 'zip://' . $tmpFile . '#' . $m['file'];

/* Устанавливаем инфорацию об авторе. */
$wizard->setAuthor('Ivan Dudarev');

/* Указываем лицензию. */
$wizard->setLicense('MIT');

/* Добавляем справочник стран */
$wizard->addRegister('country', array(
    'code' => \Ipstack\Wizard\Wizard::TYPE_CHAR,
    'name' => \Ipstack\Wizard\Wizard::TYPE_STRING,
));

/* Парсим страны */
$csv = fopen($locations, 'r');

/* Пропускаем первую строку с заголовком */
$row = fgetcsv($csv, 4096);

/* Сохраняем данные */
while ($row = fgetcsv($csv, 4096)) {
    $id = $row[0];
    $country = array(
        'code' => mb_strtolower($row[4]),
        'name' => $row[5],
    );
    $wizard->addRow('country', $id, $country);
}
fclose($csv);

/* Парсим интервалы */
$csv = fopen($locations, 'r');

/* Пропускаем первую строку с заголовком */
$row = fgetcsv($csv, 4096);

/* Сохраняем данные */
while ($row = fgetcsv($csv, 4096)) {
    $ip = parseInetnum($row[0]);
    $wizard->addInterval($ip['first'], $ip['last'], 'country', $row[1]);
}
fclose($csv);

/* Компилируем БД */

$wizard->build(\Ipstack\Wizard\Wizard::FORMAT_IPSTACK_V1, $dbFile);

/* Удаляем временный файл */
unlink($tmpFile);
