<?php

/**
 * @file tests/bootstrap.php
 *
 * PHPUnit bootstrap for the reviewerCertificate plugin test harness.
 *
 * Loads Composer's autoloader (covers PHPUnit, TCPDF, and the plugin's
 * own PSR-4 namespaces), then defines lightweight OJS/PKP stand-in
 * classes BEFORE any plugin class under test is loaded.
 */

if (!defined('PHPUNIT_TEST')) {
    define('PHPUNIT_TEST', true);
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BASE_SYS_DIR', dirname(__FILE__, 2));
define('TESTS_DIR', __DIR__);
define('MOCKS_DIR', TESTS_DIR . '/mocks');

// Try plugin-local vendor first, then the OJS-wide PKP vendor.
$localAutoloader = BASE_SYS_DIR . '/vendor/autoload.php';
$pkpAutoloader = BASE_SYS_DIR . '/../../../../lib/pkp/lib/vendor/autoload.php';

if (file_exists($localAutoloader)) {
    require_once $localAutoloader;
} elseif (file_exists($pkpAutoloader)) {
    require_once $pkpAutoloader;
}

// Define the PKP\core\*, PKP\db\* stand-in classes before any plugin
// class (ReviewerCertificate, ReviewerCertificateDAO, ...) is autoloaded.
require_once MOCKS_DIR . '/OJSMockLoader.php';
require_once MOCKS_DIR . '/DatabaseMock.php';

OJSMockLoader::initialize();
