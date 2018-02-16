<?php

const IPSTACK_TEST_CSV_DIR = __DIR__.DIRECTORY_SEPARATOR.'csv';
const IPSTACK_TEST_TMP_DIR = __DIR__.DIRECTORY_SEPARATOR.'tmp';

require_once __DIR__.'/../../../autoload.php';

/*
 * fix for using PHPUnit as composer package and PEAR extension
 */
$composerClassName = '\PHPUnit\Framework\TestCase';
$pearClassName = '\PHPUnit_Framework_TestCase';
if (!class_exists($composerClassName) && class_exists($pearClassName)) {
    class_alias($pearClassName, $composerClassName);
}