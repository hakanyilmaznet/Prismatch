<?php
declare(strict_types=1);

/**
 * Universal PHP Test Runner for Prismatch
 * Usage: php run_tests.php [args]
 */

$phpunitPhar = __DIR__ . '/bin/phpunit.phar';
if (!file_exists($phpunitPhar)) {
    echo "Error: bin/phpunit.phar not found.\n";
    exit(1);
}

$extDir = is_dir('C:\\tools\\php85\\ext') ? ' -d extension_dir="C:\\tools\\php85\\ext"' : '';
$exts = '';
if (PHP_OS_FAMILY === 'Windows') {
    if (!extension_loaded('mbstring')) $exts .= ' -d extension=php_mbstring.dll';
    if (!extension_loaded('pdo_sqlite')) $exts .= ' -d extension=php_pdo_sqlite.dll';
} else {
    if (!extension_loaded('mbstring')) $exts .= ' -d extension=mbstring';
    if (!extension_loaded('pdo_sqlite')) $exts .= ' -d extension=pdo_sqlite';
}

$args = array_slice($argv, 1);
$argString = implode(' ', array_map('escapeshellarg', $args));

$cmd = sprintf('php%s%s %s %s', $extDir, $exts, escapeshellarg($phpunitPhar), $argString);

passthru($cmd, $exitCode);
exit($exitCode);
