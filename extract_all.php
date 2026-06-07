<?php
$phar = new Phar('official_3.8.7.phar');
foreach(new RecursiveIteratorIterator($phar) as $file){
    $pathname = $file->getPathname();
    if(strpos($pathname, 'src/pocketmine/resources/vanilla/') !== false){
        $localPath = __DIR__ . '/' . substr($pathname, strpos($pathname, 'src/pocketmine/resources/vanilla/'));
        @mkdir(dirname($localPath), 0777, true);
        copy($pathname, $localPath);
        echo "Extracted: $localPath\n";
    }
    if(strpos($pathname, 'eng.ini') !== false) {
        $localPath = __DIR__ . '/src/pocketmine/lang/locale/eng.ini';
        @mkdir(dirname($localPath), 0777, true);
        copy($pathname, $localPath);
        echo "Extracted eng.ini\n";
    }
}
