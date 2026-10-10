<?php
    // The regular cleanup of the gallery's data, run by its own cron job through
    // inc/run-maintenance.php, so it works when nobody opens the site and does not
    // wait on the bot checks or a long conversion:
    //   1. operations cut short (a process killed between moving a file and
    //      changing the trash's index, see the log of operations in
    //      inc/gallery.php) are finished or undone; an unclear one is reported,
    //      nothing is deleted for it;
    //   2. the trash entries past TRASH_DAYS go for good, the oldest first, for at
    //      most MAINTENANCE_SECONDS a run; a big folder goes in parts, and what is
    //      left (the backlog) is done by the next runs;
    //   3. how it went is kept in inc/data/maintenance.json for the panel.
    // Defining things only: nothing runs when this file is loaded.
    require_once __DIR__ . '/gallery.php';

    // the work of one run, and of one hold of the trash's lock in it (a restore
    // waits at most that long)
    const MAINTENANCE_SECONDS = 20;
    const MAINTENANCE_CHUNK = 2;
    // the panel warns when no run went well for this long (the cron job runs
    // every 10 minutes)
    const MAINTENANCE_LATE = 7200;
    // a note of an operation younger than this may still be in progress
    const OPERATION_STALE = 600;

    // what the last runs left: ['tried', 'succeeded', 'last' => the report of
    // the last run]
    function maintenanceState()
    {
        return readData('maintenance');
    }

    // whether the panel should warn: no run went well for MAINTENANCE_LATE
    function maintenanceLate($now = null)
    {
        $succeeded = (int)(maintenanceState()['succeeded'] ?? 0);

        return ($now ?? time()) - $succeeded > MAINTENANCE_LATE;
    }

    // One run. $dryRun only says what it would do. Returns the report: what was
    // recovered and removed, the backlog, the problems; ['busy' => true] when
    // another run is still at it.
    function maintenanceRun($now, $dryRun = false, $seconds = MAINTENANCE_SECONDS)
    {
        $dir = dataDir() . '/locks';
        if (!is_dir($dir))
            @mkdir($dir, 0750, true);
        $lock = @fopen($dir . '/maintenance-run.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock !== false)
                fclose($lock);
            return ['busy' => true];
        }

        $deadline = microtime(true) + $seconds;
        $report = ['time' => $now, 'dryRun' => $dryRun, 'recovered' => 0, 'removed' => 0, 'bytes' => 0, 'backlog' => 0, 'problems' => []];

        [$report['recovered'], $problems] = recoverOperations($now, $dryRun);
        $report['problems'] = $problems;

        $expiredBefore = $now - TRASH_DAYS * 86400;
        $due = dueTrash($expiredBefore);
        foreach ($due as $id => $item) {
            if (microtime(true) >= $deadline)
                break;
            if ($dryRun) {
                $report['removed']++;
                $report['bytes'] += (int)($item['size'] ?? 0);
                continue;
            }
            do {
                $result = purgeTrashEntry((string)$id, $expiredBefore, min($deadline, microtime(true) + MAINTENANCE_CHUNK));
            } while ($result === 'partial' && microtime(true) < $deadline);
            if ($result === 'done') {
                $report['removed']++;
                $report['bytes'] += (int)($item['size'] ?? 0);
            } else if ($result === 'failed') {
                $report['problems'][] = 'nie udało się usunąć ' . galleryPath($item['from'] ?? $id) . ' z kosza';
            }
        }
        $report['backlog'] = $dryRun ? max(0, count($due) - $report['removed']) : count(dueTrash($expiredBefore));

        // folders in the trash without an entry: nothing says what they are, so
        // they are only reported, for someone to look at
        $known = readData('trash');
        $unknown = 0;
        foreach (@scandir(trashDir()) ?: [] as $name)
            if ($name[0] !== '.' && !isset($known[$name]) && !operationFor($name))
                $unknown++;
        if ($unknown)
            $report['problems'][] = $unknown . ' ' . plural($unknown, 'folder', 'foldery', 'folderów') . ' w koszu bez wpisu (inc/data/trash)';

        // the success is noted only after the work, and only without problems
        if (!$dryRun)
            updateData('maintenance', function (&$state) use ($report) {
                $state['tried'] = $report['time'];
                if (!$report['problems'])
                    $state['succeeded'] = time();
                $state['last'] = $report;
            });

        fclose($lock);

        return $report;
    }

    // the trash entries due to go, [id => entry], the ones being removed first,
    // then the oldest
    function dueTrash($expiredBefore)
    {
        $due = array_filter(readData('trash'), function ($item) use ($expiredBefore) {
            return !empty($item['purging']) || ($item['deleted'] ?? 0) < $expiredBefore;
        });
        uasort($due, function ($a, $b) {
            return [empty($a['purging']), $a['deleted'] ?? 0] <=> [empty($b['purging']), $b['deleted'] ?? 0];
        });

        return $due;
    }

    // whether a note of an operation still names this trash entry
    function operationFor($trashId)
    {
        foreach (glob(operationsDir() . '/*.json') ?: [] as $file)
            if ((string)(loadJsonFile($file)['trash'] ?? '') === (string)$trashId)
                return true;

        return false;
    }

    // Finishes or undoes the operations cut short, by what is on the disk:
    // the file only where it was, it never moved; only where it went, it moved
    // and the trash's index is made to say so; in both places or in neither, it
    // is unclear and only reported. [finished, problems].
    function recoverOperations($now, $dryRun = false)
    {
        $finished = 0;
        $problems = [];
        foreach (glob(operationsDir() . '/*.json') ?: [] as $file) {
            $operation = loadJsonFile($file);
            if (!is_array($operation) || !isset($operation['kind'], $operation['trash'], $operation['src'], $operation['dst'])) {
                $problems[] = 'nieczytelny zapis operacji ' . basename($file);
                continue;
            }
            if ($now - (int)($operation['started'] ?? 0) < OPERATION_STALE)
                continue;  // may still be going

            $atSource = file_exists($operation['src']);
            $atTarget = file_exists($operation['dst']);
            $id = (string)$operation['trash'];
            $what = $operation['kind'] === 'trash' ? 'przeniesienie do kosza' : 'przywrócenie';
            if ($atSource === $atTarget) {
                $problems[] = $what . ' ' . basename((string)$operation['src']) . ' przerwane, plik ' . ($atSource ? 'jest w obu miejscach' : 'zniknął')
                    . ' (zapis ' . basename($file) . ' został do sprawdzenia)';
                continue;
            }
            if ($dryRun) {
                $finished++;
                continue;
            }

            $lock = trashLock();
            if ($lock === null) {
                $problems[] = 'kosz był zajęty, ' . $what . ' dokończy następny przebieg';
                continue;
            }
            if ($operation['kind'] === 'trash') {
                // moved: the entry it did not get is written; not moved: the
                // empty folder it was to go into goes
                $ok = $atTarget
                    ? updateData('trash', function (&$items) use ($id, $operation) {
                        if (!isset($items[$id]) && is_array($operation['item'] ?? null))
                            $items[$id] = $operation['item'];
                    })
                    : (@rmdir(dirname($operation['dst'])) || !is_dir(dirname($operation['dst'])));
            } else {
                // restored: the entry it left goes, with its empty folder
                $ok = !$atTarget || updateData('trash', function (&$items) use ($id) { unset($items[$id]); });
                if ($ok && $atTarget)
                    @rmdir(dirname($operation['src']));
            }
            fclose($lock);

            if ($ok && @unlink($file)) {
                $finished++;
                addHistory('recover', 'Dokończono przerwane ' . $what . ': ' . basename((string)$operation['src']) . '.', 'porządki');
            } else {
                $problems[] = 'nie udało się dokończyć: ' . $what . ' ' . basename((string)$operation['src']);
            }
        }

        return [$finished, $problems];
    }
