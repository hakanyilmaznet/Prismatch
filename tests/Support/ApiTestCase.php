<?php
declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\TestCase;

abstract class ApiTestCase extends TestCase {
    /**
     * Executes an API endpoint script with simulated inputs and returns structured result:
     * ['status' => int, 'body' => string, 'json' => ?array, 'session' => array]
     */
    protected function callApi(string $relativeScriptPath, array $options = []): array {
        $root = dirname(__DIR__, 2);
        $fullPath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeScriptPath);

        $payload = json_encode([
            'script' => $fullPath,
            'method' => $options['method'] ?? 'GET',
            'get' => $options['get'] ?? [],
            'post' => $options['post'] ?? [],
            'session' => $options['session'] ?? [],
            'server' => $options['server'] ?? [],
            'body' => $options['body'] ?? null,
            'use_sqlite' => $options['use_sqlite'] ?? false,
            'seeds' => $options['seeds'] ?? [],
        ]);

        $binary = PHP_BINARY ?: 'php';
        $cmd = sprintf(
            '%s %s',
            escapeshellarg($binary),
            escapeshellarg(__DIR__ . '/api_runner.php')
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes, $root);
        if (!is_resource($process)) {
            $this->fail('Failed to spawn API test runner process.');
        }

        fwrite($pipes[0], $payload);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        proc_close($process);

        if (preg_match('/---API_RESULT_START---\s*(.*?)\s*---API_RESULT_END---/s', $stdout, $matches)) {
            $result = json_decode($matches[1], true);
            if (is_array($result)) {
                $result['stderr'] = $stderr;
                return $result;
            }
        }

        $this->fail("API execution failed to produce valid result.\nSTDOUT:\n{$stdout}\nSTDERR:\n{$stderr}");
    }
}
