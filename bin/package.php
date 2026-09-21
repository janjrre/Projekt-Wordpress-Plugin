<?php
// Run after composer install --no-dev and npm run build in a clean checkout.
$root = dirname(__DIR__);
$installed = json_decode(file_get_contents($root . '/vendor/composer/installed.json'), true, 512, JSON_THROW_ON_ERROR);
if ($installed['dev'] ?? true) { throw new RuntimeException('Install production dependencies before packaging.'); }
$files = ['uop-core.php', 'readme.txt', 'LICENSE', 'THIRD-PARTY-NOTICES.md', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json'];
foreach (['src', 'vendor', 'build', 'bin', 'schema'] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isFile()) { $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1)); }
    }
}
sort($files, SORT_STRING);
@mkdir($root . '/dist', 0775, true);
$zip = new ZipArchive();
if ($zip->open($root . '/dist/uop-core.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { throw new RuntimeException('Cannot create archive'); }
foreach ($files as $file) {
    $path = 'uop-core/' . $file;
    $zip->addFile($root . '/' . $file, $path);
    $zip->setMtimeName($path, 946684800);
    $zip->setExternalAttributesName($path, ZipArchive::OPSYS_UNIX, 0100644 << 16);
}
$zip->close();
echo hash_file('sha256', $root . '/dist/uop-core.zip') . PHP_EOL;
