<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=UTF-8');

$path = __DIR__ . '/hosting.php';

echo "PHP OK\n";
echo "hosting.php exists: " . (is_file($path) ? 'YES' : 'NO') . "\n";
echo "hosting.php readable: " . (is_readable($path) ? 'YES' : 'NO') . "\n";

if (is_file($path)) {
    $config = require $path;

    echo "config loaded: " . (is_array($config) ? 'YES' : 'NO') . "\n";
    echo "db section: " . (
        isset($config['db']) && is_array($config['db']) ? 'YES' : 'NO'
    ) . "\n";

    if (isset($config['db']) && is_array($config['db'])) {
        echo "host: " . (($config['db']['host'] ?? '') !== '' ? 'SET' : 'EMPTY') . "\n";
        echo "name: " . (($config['db']['name'] ?? '') !== '' ? 'SET' : 'EMPTY') . "\n";
        echo "user: " . (($config['db']['user'] ?? '') !== '' ? 'SET' : 'EMPTY') . "\n";
        echo "password: " . (($config['db']['pass'] ?? '') !== '' ? 'SET' : 'EMPTY') . "\n";
    }
}