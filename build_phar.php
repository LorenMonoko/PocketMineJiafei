<?php
$pharName = "PMJ-1.11.4.phar";
if (file_exists($pharName)) {
    unlink($pharName);
}
$phar = new Phar($pharName, 0, $pharName);
$phar->startBuffering();

echo "Adding src/...\n";
$srcDir = realpath(__DIR__ . '/src');
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getRealPath();
    $localPath = 'src/' . substr($path, strlen($srcDir) + 1);
    $phar->addFile($path, $localPath);
}

echo "Adding vendor/...\n";
$vendorDir = realpath(__DIR__ . '/vendor');
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vendorDir, FilesystemIterator::SKIP_DOTS)) as $file) {
    $path = $file->getRealPath();
    $localPath = 'vendor/' . substr($path, strlen($vendorDir) + 1);
    $phar->addFile($path, $localPath);
}

$phar->setStub('<?php require "phar://" . __FILE__ . "/src/pocketmine/PocketMine.php"; __HALT_COMPILER();');
$phar->stopBuffering();
echo "Phar rebuilt successfully: $pharName\n";
