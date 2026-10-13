<?php
    // Build browser metadata from the PHP picker's catalog. asset-versions.php
    // calls this too, so its existing hook stages the generated file.
    require_once __DIR__ . '/../inc/skin-catalog.php';
    $path = dirname(__DIR__) . '/js/skin-catalog.js';
    $script = skinCatalogScript();
    if (@file_get_contents($path) !== $script) {
        if (file_put_contents($path, $script) === false)
            throw new RuntimeException('Nie udało się zapisać katalogu skórek.');
        echo "js/skin-catalog.js\n";
    }
