<?php
    // Reentrant locks: release callbacks must run in finally. ZIP does not take
    // this lock: exports are best effort, as agreed for this installation.
    function galleryLock($name = 'mutations', $exclusive = true)
    {
        static $held = [];
        if (!isset($held[$name])) {
            $handle = fileLock(dataLockFile('gallery-' . $name), $exclusive);
            if ($handle === null)
                throw new DataStoreException('lock', $name);
            $held[$name] = [$handle, 0, $exclusive];
        } elseif ($exclusive && !$held[$name][2]) {
            throw new LogicException('Cannot upgrade a gallery lock');
        }
        $held[$name][1]++;
        return function () use (&$held, $name) {
            if (isset($held[$name]) && --$held[$name][1] === 0) {
                fclose($held[$name][0]);
                unset($held[$name]);
            }
        };
    }

    function galleryWrite($callback)
    {
        $release = galleryLock();
        try { return $callback(); } finally { $release(); }
    }

    // Fault injection is enabled only by the isolated test harness.
    function galleryRename($source, $target)
    {
        if (defined('SITE_TEST_IO') && SITE_TEST_IO && isset($GLOBALS['SANAKAN_GALLERY_IO'])) {
            $result = ($GLOBALS['SANAKAN_GALLERY_IO'])('rename', $source, $target);
            if ($result !== null) return $result;
        }
        return @rename($source, $target);
    }
