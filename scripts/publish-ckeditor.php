<?php

declare(strict_types=1);

/**
 * Publish the CKEditor runtime into public/vendor/ckeditor.
 *
 * Only the directories the editor actually loads at runtime are copied: lang,
 * plugins, skins, adapters and vendor. Everything else in the package is
 * developer-only and stays out of the document root.
 *
 * `samples/` in particular MUST NOT be published. It contains two executable PHP
 * files (samples/old/sample_posteddata.php and samples/old/assets/posteddata.php)
 * which then answer requests at /vendor/ckeditor/samples/old/sample_posteddata.php
 * from inside the DocumentRoot, reflecting $_POST through htmlspecialchars. The
 * whole-tree copy this replaced was the reason they were reachable at all.
 */
$source = __DIR__.'/../vendor/ckeditor/ckeditor';
$target = __DIR__.'/../public/vendor/ckeditor';

if (! is_dir($source)) {
    fwrite(STDERR, "CKEditor source not found at {$source}. Run `composer require ckeditor/ckeditor` first.\n");
    exit(1);
}

/**
 * Allow-list of top-level entries. Anything not named here is not published, so
 * a future package release cannot quietly add a new directory full of scripts to
 * the document root.
 */
$publishable = ['lang', 'plugins', 'skins', 'adapters', 'vendor'];

$runtimeFiles = [
    'ckeditor.js',
    'config.js',
    'styles.js',
    'contents.css',
];

$updated = 0;
$copied = 0;

$copy = function (string $from, string $to) use (&$updated, &$copied): void {
    if (! is_file($to) || filemtime($from) > filemtime($to)) {
        if (! is_dir(dirname($to))) {
            mkdir(dirname($to), 0755, true);
        }

        copy($from, $to);
        $updated++;
    }

    $copied++;
};

foreach ($publishable as $directory) {
    $from = $source.DIRECTORY_SEPARATOR.$directory;

    if (! is_dir($from)) {
        continue;
    }

    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($rii as $file) {
        $relative = substr($file->getPathname(), strlen($source) + 1);

        if ($file->isDir()) {
            $destination = $target.DIRECTORY_SEPARATOR.$relative;

            if (! is_dir($destination)) {
                mkdir($destination, 0755, true);
            }

            continue;
        }

        $copy($file->getPathname(), $target.DIRECTORY_SEPARATOR.$relative);
    }
}

foreach ($runtimeFiles as $file) {
    $from = $source.DIRECTORY_SEPARATOR.$file;

    if (is_file($from)) {
        $copy($from, $target.DIRECTORY_SEPARATOR.$file);
    }
}

// Remove anything previously published that the allow-list now excludes, so an
// install that ran the old whole-tree copy does not keep serving it.
$stale = glob($target.DIRECTORY_SEPARATOR.'samples', GLOB_ONLYDIR);

if ($stale !== false) {
    foreach ($stale as $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}

fwrite(STDOUT, "CKEditor assets published to public/vendor/ckeditor ({$copied} file(s) present, {$updated} updated).\n");
