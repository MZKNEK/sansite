<?php
    // The gallery's numbers and the room inc/data takes, for the admin panel.
    // Counted by inc/check-bot.php in the background (the file is older than
    // PANEL_STATS_TTL once an hour) and only read when the panel opens, so a
    // big gallery does not make the panel wait; when the file is missing or old
    // it is counted on the spot.
    require_once __DIR__ . '/gallery.php';

    const PANEL_STATS_TTL = 3600;
    // from this much trash and WebP previews together the panel warns
    const PANEL_DATA_WARN_BYTES = 536870912;  // 500 MB
    const LARGEST_SHOWN = 10;

    // files and bytes in a folder and its subfolders
    function folderTotals($dir)
    {
        $files = 0;
        $bytes = 0;
        foreach (is_dir($dir) ? (@scandir($dir) ?: []) : [] as $name) {
            if ($name === '.' || $name === '..')
                continue;
            $path = $dir . '/' . $name;
            if (is_dir($path) && !is_link($path)) {
                list($innerFiles, $innerBytes) = folderTotals($path);
                $files += $innerFiles;
                $bytes += $innerBytes;
            } else if (is_file($path)) {
                $files++;
                $bytes += filesize($path);
            }
        }

        return [$files, $bytes];
    }

    // The gallery in numbers: files and bytes in all, per top folder ('' for the
    // files right in i/), per file type, and the biggest files as [rel, size].
    // Hidden files and the gallery script do not count, as in the gallery itself,
    // but the accounts' folders and the private one do: the panel counts the
    // whole gallery, no matter who may see what in it.
    function galleryStats($base)
    {
        $stats = ['files' => 0, 'bytes' => 0, 'folders' => [], 'types' => [], 'largest' => []];
        if (!is_dir($base))
            return $stats;

        foreach (listNames($base, '', true) as $name)
            if (is_dir($base . '/' . $name) && !is_link($base . '/' . $name))
                $stats['folders'][$name] = ['files' => 0, 'bytes' => 0];

        $walk = function ($dirPath, $dirRel, $top, $depth) use (&$walk, &$stats) {
            foreach (listNames($dirPath, $dirRel, true) as $name) {
                $full = $dirPath . '/' . $name;
                $rel = ltrim($dirRel . '/' . $name, '/');
                if (is_dir($full) && !is_link($full)) {
                    if ($depth < 10)
                        $walk($full, $rel, $top ?? $name, $depth + 1);
                    continue;
                }
                if (!is_file($full))
                    continue;

                $size = filesize($full);
                $type = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'bez rozszerzenia';
                $stats['files']++;
                $stats['bytes'] += $size;
                foreach ([['folders', $top ?? ''], ['types', $type]] as [$group, $key]) {
                    $stats[$group][$key]['files'] = ($stats[$group][$key]['files'] ?? 0) + 1;
                    $stats[$group][$key]['bytes'] = ($stats[$group][$key]['bytes'] ?? 0) + $size;
                }

                // only the biggest are kept while walking
                $stats['largest'][] = [$rel, $size];
                if (count($stats['largest']) > 5 * LARGEST_SHOWN)
                    $stats['largest'] = largestFirst($stats['largest']);
            }
        };
        $walk($base, '', null, 0);

        $stats['largest'] = largestFirst($stats['largest']);
        foreach (['folders', 'types'] as $group)
            uasort($stats[$group], function ($a, $b) { return $b['bytes'] <=> $a['bytes']; });

        return $stats;
    }

    function largestFirst($files)
    {
        usort($files, function ($a, $b) { return $b[1] <=> $a[1]; });

        return array_slice($files, 0, LARGEST_SHOWN);
    }

    function panelStatsFile()
    {
        return dataDir() . '/panel-stats.json';
    }

    // everything the panel counts: the gallery, inc/data (trash and WebP
    // previews apart) and the thumbnail cache
    function panelStatsCompute($galleryDir)
    {
        [$thumbFiles, $thumbBytes] = folderTotals(thumbsDir());
        [, $dataBytes] = folderTotals(dataDir());

        return [
            'time' => time(),
            'gallery' => galleryStats($galleryDir),
            'data' => [
                'bytes' => $dataBytes,
                'trash' => treeSize(trashDir()),
                'webp' => treeSize(webpPreviewDir())
            ],
            'thumbs' => ['files' => $thumbFiles, 'bytes' => $thumbBytes]
        ];
    }

    function panelStatsFresh($stats)
    {
        return is_array($stats) && isset($stats['time'], $stats['gallery'], $stats['data'], $stats['thumbs'])
            && $stats['time'] > time() - PANEL_STATS_TTL;
    }

    // the stats, counted again when the file is missing or older than
    // PANEL_STATS_TTL, or forced by cron
    function panelStats($galleryDir, $force = false)
    {
        $stats = readData('panel-stats');
        if (!$force && panelStatsFresh($stats))
            return $stats;

        $stats = panelStatsCompute($galleryDir);
        writeData('panel-stats', $stats);

        return $stats;
    }
