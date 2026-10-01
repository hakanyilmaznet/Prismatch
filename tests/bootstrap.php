<?php
declare(strict_types=1);

if (!defined('PUSHER_DISABLE')) {
    define('PUSHER_DISABLE', true);
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../i18n.php';

// Autoloader for Tests\ namespace
spl_autoload_register(function (string $class) {
    $prefix = 'Tests\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});
