<?php
$files = glob('*.php');
foreach ($files as $file) {
    if ($file === 'cleanup.php') continue;
    $content = file_get_contents($file);

    // 1. Remove return types: function foo(...): void { -> function foo(...) {
    // Also handles nullable return types: function foo(...): ?string {
    $content = preg_replace('/(\)\s*):\s*\??[a-zA-Z0-9_|]+\s*(\{)/', '$1$2', $content);

    // 2. Remove parameter types: (string $foo, ?int $bar, array $baz)
    // We target common types followed by a space and $
    $types = '(?:string|int|bool|float|array|iterable|object|callable|void|mixed|PDO)';
    $content = preg_replace('/\b'.$types.'\s+(\$[a-zA-Z0-9_]+)/', '$1', $content);
    $content = preg_replace('/\?\b'.$types.'\s+(\$[a-zA-Z0-9_]+)/', '$1', $content);

    // 3. Replace ?? operator
    // This is hard to do perfectly with regex but we can do the common simple ones
    // We'll do several passes for nested/indexed ones if needed, but start simple.
    $content = preg_replace('/(\$[a-zA-Z0-9_]+(?:\[[\'"]?[a-zA-Z0-9_-]+[\'"]?\])*)\s*\?\?\s*([^\);,\n]+)/', '(isset($1) ? $1 : $2)', $content);

    file_put_contents($file, $content);
    echo "Cleaned up $file\n";
}
