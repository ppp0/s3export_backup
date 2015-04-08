<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Guzzle\Tests\GuzzleTestCase;

$bootloader = new CM_Bootloader_Testing(dirname(__DIR__) . '/');
$bootloader->load();

$suite = new CMTest_TestSuite();
$suite->setDirTestData(__DIR__ . '/data/');
$suite->bootstrap();

// Register services with the GuzzleTestCase
GuzzleTestCase::setMockBasePath(__DIR__ . '/mock');

// Instantiate the service builder
$serviceConfig = $_SERVER['CONFIG'] = dirname(__DIR__) . '/tests/test_services.json';
$_SERVER['CONFIG'] = $serviceConfig;

if (!is_readable($_SERVER['CONFIG'])) {
    die("Unable to read service configuration from '{$_SERVER['CONFIG']}'\n");
}
GuzzleTestCase::setServiceBuilder(Aws\Common\Aws::factory($_SERVER['CONFIG']));
