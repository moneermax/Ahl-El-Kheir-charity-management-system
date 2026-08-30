<?php
// list_structure.php
header('Content-Type: text/html; charset=utf-8');

function getDirTree($dir, $prefix = '', $depth = 0) {
    // Limit depth to prevent browser timeout on huge folders
    if ($depth > 4) return $prefix . "└── [Max depth reached]\n";

    // Ignore system/dependency folders
    $ignore = ['.', '..', 'vendor', 'node_modules', '.git', '.idea', '.vscode'];
    // Hide contents of heavy asset folders to keep the list clean
    $hide_contents = ['uploads', 'assets', 'images', 'css', 'js', 'fonts', 'files'];
    
    $files = scandir($dir);
    if ($files === false) return '';
    
    $folders = [];
    $filesOnly = [];
    
    foreach ($files as $file) {
        if (in_array($file, $ignore)) continue;
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            $folders[] = $file;
        } else {
            $filesOnly[] = $file;
        }
    }
    
    sort($folders);
    sort($filesOnly);
    
    $all = array_merge($folders, $filesOnly);
    $lastIndex = count($all) - 1;
    $tree = '';
    
    foreach ($all as $index => $file) {
        $isLast = ($index === $lastIndex);
        $connector = $isLast ? '└── ' : '├── ';
        $nextPrefix = $prefix . ($isLast ? '    ' : '│   ');
        
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            $tree .= $prefix . $connector . '📁 ' . $file . "/\n";
            if (in_array($file, $hide_contents)) {
                $tree .= $nextPrefix . "└── [Contents hidden to save space]\n";
            } else {
                $tree .= getDirTree($path, $nextPrefix, $depth + 1);
            }
        } else {
            $tree .= $prefix . $connector . $file . "\n";
        }
    }
    
    return $tree;
}

echo "<pre style='font-family: Consolas, 'Courier New', monospace; font-size: 14px; line-height: 1.4; background: #2d2d2d; color: #f8f8f2; padding: 20px; border-radius: 8px; overflow-x: auto;'>";
echo "📂 AhlElKheir (Root: " . __DIR__ . ")\n";
echo getDirTree(__DIR__);
echo "</pre>";