<?php
    // The history of changes, one JSON line each. Part of the login, loaded by
    // inc/auth.php.

    // ---- History of changes ----------------------------------------------------
    // One JSON line per change in the gallery or the panel: when, who, what.
    // Only the newest HISTORY_KEEP entries are kept.

    function historyFile()
    {
        return dataDir() . '/history.log';
    }

    // $who names who did it when no account did, e.g. a cron job
    function addHistory($action, $text, $who = null)
    {
        if (!is_dir(dataDir()))
            @mkdir(dataDir(), 0750, true);

        $user = $who === null ? siteUser() : ['id' => '', 'name' => $who];
        // a log read by people: what is not UTF-8 is replaced, the entry stays
        $line = json_encode([
            'time' => time(),
            'id' => $user['id'] ?? '',
            'name' => $user['name'] ?? '',
            'action' => $action,
            'text' => $text
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . "\n";

        // one lock for adding and trimming, so no line is added to the file a
        // trim is just replacing
        $file = historyFile();
        $lock = fileLock(dataLockFile('history'), true);
        if ($lock === null)
            return false;
        $ok = @file_put_contents($file, $line, FILE_APPEND) === strlen($line);

        // trimmed now and then instead of on every write, the whole file at once
        clearstatcache();
        if ($lock !== null && @filesize($file) > 600000) {
            $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            replaceFile($file, implode("\n", array_slice($lines, -HISTORY_KEEP)) . "\n");
        }
        if ($lock !== null)
            fclose($lock);
        return $ok;
    }

    // the newest entries first
    function historyEntries($limit)
    {
        $lines = @file(historyFile(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $entries = [];
        foreach (array_reverse(array_slice($lines, -$limit)) as $line) {
            $entry = json_decode($line, true);
            if (is_array($entry))
                $entries[] = $entry;
        }

        return $entries;
    }
