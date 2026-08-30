<?php
require_once 'config/database.php';
echo "<h3>PDO Variables</h3><pre>";
$vars = get_defined_vars();
foreach ($vars as $name => $val) {
    if ($val instanceof PDO) {
        echo "FOUND PDO in variable: \$$name\n";
    }
}
echo "</pre><h3>DB-Related Functions</h3><pre>";
foreach (get_defined_functions()['user'] as $f) {
    if (stripos($f, 'db') !== false || stripos($f, 'pdo') !== false || stripos($f, 'conn') !== false) {
        echo "FOUND function: $f()\n";
    }
}
echo "</pre>";
?>