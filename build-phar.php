<?php

$projectName = "PocketMineJiafei";
$sourceDir = __DIR__ . "/";
$outFile = "PocketMineJiafei.phar";

if(file_exists($outFile)){
    unlink($outFile);
}

$phar = new Phar($outFile);
$phar->setSignatureAlgorithm(Phar::SHA1);

$phar->startBuffering();

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach($files as $file){
    $filePath = $file->getPathname();
    $relativePath = substr($filePath, strlen($sourceDir));

    // Skip unnecessary files and the output file itself
    if(strpos($relativePath, "tests/") === 0 || 
       strpos($relativePath, ".github/") === 0 || 
       strpos($relativePath, ".git/") === 0 ||
       $relativePath === ".travis.yml" ||
       $relativePath === "phpstan.neon.dist" ||
       $relativePath === "CONTRIBUTING.md" ||
       $relativePath === "LICENSE" ||
       $relativePath === "README.md" ||
       $relativePath === "build-phar.php" ||
       $relativePath === $outFile ||
       basename($relativePath) === "PocketMineJiafei.phar"
    ){
        continue;
    }

    if($file->isFile()){
        echo "Adding $relativePath\n";
        $phar->addFile($filePath, $relativePath);
    }
}

$phar->setStub('<?php require_once("phar://" . __FILE__ . "/src/pocketmine/PocketMine.php"); __HALT_COMPILER();');

$phar->stopBuffering();

echo "\nBuilt $projectName into $outFile\n";
