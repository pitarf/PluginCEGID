<?php
$zipPath = 'c:/Git/Wordpress/ValedoPais/wc-cegid-sync.zip';
$sourceDir = 'c:/Git/Wordpress/ValedoPais/wc-cegid-sync';

if (file_exists($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = 'wc-cegid-sync/' . str_replace('\\', '/', substr($filePath, strlen(realpath($sourceDir)) + 1));
            $zip->addFile($filePath, $relativePath);
            echo "Adicionado ao ZIP: {$relativePath}\n";
        }
    }
    $zip->close();
    echo "SUCCESS: ZIP criado no padrao nativo Linux/WordPress com barras normais!\n";
} else {
    echo "ERROR: Nao foi possivel criar o arquivo ZIP.\n";
}
