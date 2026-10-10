<?php
    define('SITE_DATA_DIR', getenv('SANAKAN_TEST_DATA'));
    $mode = $argv[1] ?? 'migrate';
    array_splice($argv, 1, 1);
    require __DIR__ . '/../inc/' . ($mode === 'maintenance' ? 'run-maintenance.php' : 'migrate-gallery.php');
