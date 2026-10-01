@echo off
setlocal
echo ====================================================
echo  Running Prismatch Test Suite (PHPUnit)
echo ====================================================

php -d extension_dir="C:\tools\php85\ext" -d extension=php_mbstring.dll -d extension=php_pdo_sqlite.dll bin\phpunit.phar %*

exit /b %ERRORLEVEL%
