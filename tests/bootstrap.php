<?php

declare(strict_types=1);

$pluginRoot = dirname(__DIR__);

$pkpAutoloader = $pluginRoot . '/../../../../lib/pkp/lib/vendor/autoload.php';
if (file_exists($pkpAutoloader)) {
    require_once $pkpAutoloader;
}

$localComposerAutoloader = $pluginRoot . '/vendor/autoload.php';
if (file_exists($localComposerAutoloader)) {
    require_once $localComposerAutoloader;
}
