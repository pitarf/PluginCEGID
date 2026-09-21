Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zipPath = "c:\Git\Wordpress\ValedoPais\wc-cegid-sync.zip"
$sourceDir = "c:\Git\Wordpress\ValedoPais\wc-cegid-sync"

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

$zipArchive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

$allFiles = Get-ChildItem -Path $sourceDir -Recurse -File

foreach ($file in $allFiles) {
    $relPath = "wc-cegid-sync/" + ($file.FullName.Substring($sourceDir.Length + 1) -replace '\\', '/')
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
        $zipArchive,
        $file.FullName,
        $relPath,
        [System.IO.Compression.CompressionLevel]::Optimal
    )
    Write-Host "Adicionado: $relPath"
}

$zipArchive.Dispose()
Write-Host "ZIP_SUCESSO_CRIADO"
