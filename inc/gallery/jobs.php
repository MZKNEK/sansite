<?php
    // Uploaded pictures and films changed to WebP and WebM in the background:
    // their queue, the change itself, and how the pages show it. Part of the
    // gallery, loaded by inc/gallery.php.

    // ---- Pictures and films changed in the background ---------------------------
    // An uploaded PNG, JPG, GIF or AVIF waits its turn as WebP, an MP4 as WebM;
    // the file is saved first and the request answers at once, so adding many at
    // once does not hold the upload open. inc/data/media-jobs.json keeps what is
    // waiting and how it went, and the gallery shows the status to whoever
    // uploaded the file.

    // the jobs, newest first
    function mediaJobs()
    {
        $jobs = readData('media-jobs');
        uasort($jobs, function ($a, $b) {
            return ($b['created'] ?? 0) - ($a['created'] ?? 0);
        });

        return $jobs;
    }

    // what an account uploaded: the jobs still going, and the ones that finished
    // within MEDIA_JOB_DONE_SHOW, newest first; with $all everything still kept
    function userMediaJobs($id, $limit = 12, $all = false)
    {
        $jobs = [];
        foreach (readData('media-jobs') as $job) {
            if (!is_array($job) || empty($job['rel']) || (string)($job['by'] ?? '') !== (string)$id)
                continue;
            if (!$all && !mediaJobActive($job) && ($job['updated'] ?? 0) < time() - MEDIA_JOB_DONE_SHOW)
                continue;
            $jobs[] = $job;
        }
        usort($jobs, function ($a, $b) {
            // what is still going first, then the newest
            $activeA = mediaJobActive($a) ? 1 : 0;
            $activeB = mediaJobActive($b) ? 1 : 0;
            if ($activeA !== $activeB)
                return $activeB - $activeA;

            return ($b['updated'] ?? 0) - ($a['updated'] ?? 0);
        });

        return array_slice($jobs, 0, $limit);
    }

    // drops the jobs of this account that are done, so its list is clean without
    // waiting for the ones that finished to age out; what is still going stays
    function clearFinishedMediaJobs($id)
    {
        $removed = 0;
        $saved = updateDataFile('media-jobs.json', function ($data) use ($id, &$removed) {
            foreach ($data as $jobId => $job) {
                if (!is_array($job) || (string)($job['by'] ?? '') !== (string)$id || mediaJobActive($job))
                    continue;
                unset($data[$jobId]);
                $removed++;
            }
            return $data;
        });

        return $saved ? $removed : 0;
    }

    // Adds a file to the queue; the request answers without waiting for it.
    // $kind is 'webp' or 'webm'. Null when it could not be queued (the file
    // then stays as it was uploaded).
    function addMediaJob($rel, $id, $name, $size, $kind)
    {
        $jobId = date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $job = [
            'rel' => $rel,
            'name' => basename($rel),
            'kind' => $kind,
            'by' => (string)$id,
            'byName' => (string)$name,
            'created' => time(),
            'updated' => time(),
            'status' => 'pending',
            'message' => '',
            'targetRel' => null,
            'sizeFrom' => (int)$size,
            'sizeTo' => null
        ];
        $saved = updateDataFile('media-jobs.json', function ($data) use ($jobId, $job) {
            $data[$jobId] = $job;
            return $data;
        });

        return $saved ? $jobId : null;
    }

    function updateMediaJob($id, $changes)
    {
        return updateDataFile('media-jobs.json', function ($data) use ($id, $changes) {
            if (isset($data[$id]))
                $data[$id] = array_merge($data[$id], $changes, ['updated' => time()]);
            return $data;
        });
    }

    // the oldest file still waiting, taken for conversion; null when there is
    // none, or when taking it could not be written (it is not taken then)
    function claimMediaJob()
    {
        $claimed = null;
        $saved = updateDataFile('media-jobs.json', function ($data) use (&$claimed) {
            $best = null;
            foreach ($data as $id => $job) {
                if (!is_array($job) || ($job['status'] ?? '') !== 'pending')
                    continue;
                if ($best === null || ($job['created'] ?? 0) < ($data[$best]['created'] ?? 0))
                    $best = (string)$id;
            }
            if ($best !== null) {
                $data[$best]['status'] = 'converting';
                $data[$best]['updated'] = time();
                $data[$best]['message'] = '';
                $claimed = $data[$best];
                $claimed['id'] = $best;
            }
            return $data;
        });

        return $saved ? $claimed : null;
    }

    // drops jobs that finished long ago and takes back the ones a restart left
    // half done, so they are tried again
    function pruneMediaJobs()
    {
        $now = time();
        return updateDataFile('media-jobs.json', function ($data) use ($now) {
            foreach ($data as $id => $job) {
                if (!is_array($job)) {
                    unset($data[$id]);
                    continue;
                }
                $status = $job['status'] ?? '';
                $updated = $job['updated'] ?? 0;
                if ($status === 'converting' && $updated < $now - MEDIA_JOB_STALE)
                    $data[$id] = array_merge($job, ['status' => 'pending', 'updated' => $now, 'message' => 'Wznowiono po przerwie.']);
                else if (!in_array($status, ['pending', 'converting'], true) && $updated < $now - MEDIA_JOB_KEEP_DAYS * 86400)
                    unset($data[$id]);
            }
            return $data;
        });
    }

    // turns one claimed job's file into WebP or WebM; the new file takes its
    // place when it is smaller and the original goes to the trash, as when a
    // picture is changed by hand
    function convertMediaJob($base, $job, $timeout = 0)
    {
        $encoder = galleryLock('encoder');
        try { convertMediaJobLocked($base, $job, $timeout); }
        finally { $encoder(); }
    }

    function convertMediaJobLocked($base, $job, $timeout = 0)
    {
        $file = resolvePath($base, $job['rel'] ?? '', false);
        if (!$file) {
            finishMediaJob($job, 'failed', 'plik zniknął przed konwersją');
            return;
        }
        list($full, $rel) = $file;
        try {
            $snapshot = conversionSnapshot($full);
            $job = $snapshot + $job;
            try {
                if (($job['kind'] ?? 'webm') === 'webm')
                    convertJobToWebm($snapshot['input'], $rel, $job, $timeout > 0 ? $timeout : MEDIA_JOB_TIMEOUT);
                else
                    convertJobToWebp($snapshot['input'], $rel, $job);
            } finally { @unlink($snapshot['input']); }
        } catch (Throwable $error) {
            finishMediaJob($job, 'failed', $error->getMessage());
        }
    }

    // the changed file takes the original's place: it keeps the date, the
    // original goes to the trash and its old link opens the new one; false when
    // it could not be put in place. $candidateFull is where the new file is now
    // (the same place as the target for a background job, a preview file for a
    // manual change); a re-encoded WebP keeps its own name and link.
    function swapInConverted($full, $rel, $candidateFull, $targetFull, &$targetRel, $sizeFrom, $sizeTo, $format, $byName, $background = false, &$error = null, $fingerprint = null, $actor = null)
    {
        $actual = commitConversion($full, $rel, $candidateFull, $targetFull, $fingerprint, $error, $actor);
        if ($actual === false) return false;
        $targetFull = dirname($full) . '/' . basename($actual);
        $targetRel = $actual;
        $action = $format === 'WebM' ? 'webm' : 'webp';
        addHistory($action, 'Zamieniono na ' . $format . ($background ? ' w tle' : '') . ', oryginał do kosza: '
            . galleryPath($rel) . ' na ' . basename($targetRel) . ' (' . formatSize($sizeFrom) . ' → ' . formatSize($sizeTo) . ').',
            $byName !== '' ? $byName : 'konwersja');

        return true;
    }

    // the background job's version: it finishes the job with the outcome; a
    // job queued by no account (inc/gallery-import.php) has no rights to lose
    function replaceWithConverted($full, $rel, $targetFull, $targetRel, $sizeFrom, $sizeTo, $job)
    {
        $candidate = $targetFull;
        $full = $job['originalFull'] ?? $full;
        $wanted = dirname($full) . '/' . pathinfo(basename($rel), PATHINFO_FILENAME) . '.' . (($job['kind'] ?? '') === 'webm' ? 'webm' : 'webp');
        $format = ($job['kind'] ?? 'webm') === 'webm' ? 'WebM' : 'WebP';
        if (!swapInConverted($full, $rel, $candidate, $wanted, $targetRel, $sizeFrom, $sizeTo, $format, (string)($job['byName'] ?? ''), true, $why, $job['fingerprint'] ?? null, ($job['by'] ?? '') === '' ? null : $job['by']))
            finishMediaJob($job, 'failed', $why ?? 'nie udało się zatwierdzić konwersji', ['sizeFrom' => $sizeFrom]);
        else
            finishMediaJob($job, 'done', '', ['targetRel' => $targetRel, 'sizeFrom' => $sizeFrom, 'sizeTo' => $sizeTo]);
    }

    // an MP4 as WebM, kept when it is smaller
    function convertJobToWebm($full, $rel, $job, $timeout)
    {
        if (!canConvertVideo()) {
            finishMediaJob($job, 'failed', 'serwer nie ma ffmpeg');
            return;
        }

        $dir = dirname($full);
        $target = pathinfo(basename($full), PATHINFO_FILENAME) . '.result.webm';
        $targetFull = $dir . '/' . $target;
        $targetRel = ltrim((dirname($rel) === '.' ? '' : dirname($rel)) . '/' . $target, '/');
        $sizeFrom = (int)filesize($full);

        if (!mp4ToWebm($full, $targetFull, $timeout)) {
            @unlink($targetFull);
            finishMediaJob($job, 'failed', 'nie udało się zamienić na WebM', ['sizeFrom' => $sizeFrom]);
            return;
        }
        clearstatcache();
        $sizeTo = (int)@filesize($targetFull);
        if ($sizeTo >= $sizeFrom) {
            @unlink($targetFull);
            finishMediaJob($job, 'skipped', 'WebM nie wyszedł mniejszy (' . formatSize($sizeFrom) . ' → ' . formatSize($sizeTo) . ')', ['sizeFrom' => $sizeFrom, 'sizeTo' => $sizeTo]);
            return;
        }
        replaceWithConverted($full, $rel, $targetFull, $targetRel, $sizeFrom, $sizeTo, $job);
        if (!conversionArtifactInUse($targetFull)) @unlink($targetFull);
    }

    // a PNG, JPG, GIF or AVIF as WebP, kept when it is smaller
    function convertJobToWebp($full, $rel, $job)
    {
        $name = basename($rel);
        $ext = extensionOf($name);
        $isGif = $ext === 'gif';
        if ($ext !== 'avif' && !in_array($ext, WEBP_SOURCE_TYPES, true)) {
            finishMediaJob($job, 'failed', 'tego pliku nie da się zamienić na WebP');
            return;
        }
        if (!canConvertImageToWebp($ext)) {
            finishMediaJob($job, 'failed', $isGif ? 'serwer nie umie zamienić GIF-a (brak gif2webp)' : 'serwer nie umie zapisać WebP');
            return;
        }
        // one from before the limits stays as it is
        if (($why = imageLimitError($full)) !== null) {
            finishMediaJob($job, 'failed', $why . '; oryginał zostaje');
            return;
        }

        $dir = dirname($full);
        $target = freeName($dir, pathinfo($name, PATHINFO_FILENAME) . '.webp');
        $targetFull = $dir . '/' . $target;
        $targetRel = ltrim((dirname($rel) === '.' ? '' : dirname($rel)) . '/' . $target, '/');
        $sizeFrom = (int)filesize($full);

        $tmp = $dir . '/' . pathinfo(basename($full), PATHINFO_FILENAME) . '.result.webp';
        $converted = $ext === 'avif'
            ? (function_exists('imagecreatefromavif') ? convertToWebp($full, $tmp) : heifToWebp($full, $tmp))
            : ($isGif ? gifToWebp($full, $tmp) : convertToWebp($full, $tmp));
        clearstatcache();
        if (!$converted || !is_file($tmp) || @filesize($tmp) <= 0) {
            @unlink($tmp);
            finishMediaJob($job, 'failed', 'nie udało się zamienić na WebP', ['sizeFrom' => $sizeFrom]);
            return;
        }
        $sizeTo = (int)filesize($tmp);
        if ($sizeTo >= $sizeFrom) {
            @unlink($tmp);
            finishMediaJob($job, 'skipped', 'WebP nie wyszedł mniejszy (' . formatSize($sizeFrom) . ' → ' . formatSize($sizeTo) . ')', ['sizeFrom' => $sizeFrom, 'sizeTo' => $sizeTo]);
            return;
        }
        replaceWithConverted($full, $rel, $tmp, $targetRel, $sizeFrom, $sizeTo, $job);
        if (!conversionArtifactInUse($tmp)) @unlink($tmp);
    }

    function finishMediaJob($job, $status, $message, $extra = [])
    {
        if (!updateMediaJob((string)($job['id'] ?? ''), $extra + ['status' => $status, 'message' => $message]))
            throw new DataStoreException('write', 'media-jobs');
    }

    // Runs the waiting conversions, at most $budget seconds of them (0: until
    // none is left), one at a time; a second call while one runs does nothing.
    // Returns how many were done.
    function runMediaJobs($base, $budget = 0, $jobTimeout = 0)
    {
        if (!dataWritable())
            return 0;
        $lock = @fopen(dataDir() . '/media-jobs.lock', 'ce');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock !== false)
                fclose($lock);
            return 0;
        }

        try {
        if (!pruneMediaJobs()) throw new DataStoreException('write', 'media-jobs');
        $done = 0;
        $deadline = $budget > 0 ? microtime(true) + $budget : 0.0;
        while (!$deadline || microtime(true) < $deadline) {
            $job = claimMediaJob();
            if ($job === null)
                break;
            // the whole budget is not given to one job: what is left bounds it, so
            // a long film cannot make the run overstay and hold the next one off
            $timeout = $jobTimeout;
            if ($deadline) {
                $left = (int)max(1, $deadline - microtime(true));
                $timeout = $jobTimeout > 0 ? min($jobTimeout, $left) : $left;
            }
            convertMediaJob($base, $job, $timeout);
            $done++;
        }
        return $done;
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    // Starts inc/media-worker.php on its own, out of the request, so a job just
    // queued begins now and not at cron's next minute. While a run holds the
    // lock nothing starts: it takes the new job after its own (should it have
    // just found the queue empty, cron does at the next minute). It starts when
    // the request ends, after the session is written and closed: a child gets
    // the request's open files, and PHP's session file is locked as long as it
    // is open (the lock files are opened close-on-exec, see fileLock()). True
    // when it is to start.
    function startMediaWorker()
    {
        if (!dataWritable())
            return false;
        $lock = @fopen(dataDir() . '/media-jobs.lock', 'ce');
        if ($lock === false)
            return false;
        $free = flock($lock, LOCK_EX | LOCK_NB);
        if ($free)
            flock($lock, LOCK_UN);
        fclose($lock);
        if (!$free)
            return false;

        return startPhpScript('inc/media-worker.php');
    }

    // A script of the site ($script, from the site's folder) started by the
    // command-line PHP in the background when the request ends, as
    // startMediaWorker() starts the worker; also the admin panel's tasks (the
    // pictures of the bot, the cleanup). The same command once a request.
    // True when it is to start.
    function startPhpScript($script, $args = [])
    {
        if (!function_exists('proc_open') || ($php = phpCommandLine()) === null)
            return false;

        $command = escapeshellarg($php);
        // the local preview's php -S has its settings prepended and may load GD
        // by hand (tools/preview.sh), which its worker needs as well
        if (PHP_SAPI === 'cli-server') {
            if (extension_loaded('gd'))
                $command .= ' -d extension=gd';
            if (($prepend = (string)ini_get('auto_prepend_file')) !== '')
                $command .= ' -d ' . escapeshellarg('auto_prepend_file=' . $prepend);
        }
        $command .= ' ' . escapeshellarg(str_replace('\\', '/', dirname(__DIR__, 2)) . '/' . $script);
        foreach ($args as $arg)
            $command .= ' ' . escapeshellarg($arg);
        $null = DIRECTORY_SEPARATOR === '/' ? '/dev/null' : 'NUL';
        // the shell puts it in the background and returns at once
        $command = DIRECTORY_SEPARATOR === '/' ? $command . ' > /dev/null 2>&1 &' : 'start "" /B ' . $command;
        static $queued = [];
        if (!isset($queued[$command]))
            register_shutdown_function(function () use ($command, $null) {
                if (session_status() === PHP_SESSION_ACTIVE)
                    session_write_close();
                $process = @proc_open($command, [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes);
                if (is_resource($process))
                    proc_close($process);
            });
        $queued[$command] = true;
        return true;
    }

    // Path of the command-line PHP, or null: PHP-FPM's own binary is not it,
    // so the one of the same version is looked for beside it (php8.3 on Debian)
    function phpCommandLine()
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server')
            return PHP_BINARY;
        foreach (['php' . PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, 'php'] as $name) {
            $path = PHP_BINDIR . '/' . $name;
            if (is_file($path) && is_executable($path))
                return $path;
        }
        return findTool('php');
    }

    // short label of a job's status, for the badge on its tile
    function mediaJobLabel($job)
    {
        $kind = ($job['kind'] ?? 'webm') === 'webm' ? 'webm' : 'webp';
        switch ($job['status'] ?? '') {
            case 'pending': return 'W kolejce';
            case 'converting': return 'Konwersja…';
            case 'done': return $kind === 'webm' ? 'WebM' : 'WebP';
            case 'skipped': return $kind === 'webm' ? 'MP4' : 'Bez zmian';
            case 'failed': return 'Błąd';
        }

        return '';
    }

    // the line the gallery shows about one job
    function mediaJobText($job)
    {
        $name = (string)($job['name'] ?? '');
        $format = ($job['kind'] ?? 'webm') === 'webm' ? 'WebM' : 'WebP';
        switch ($job['status'] ?? '') {
            case 'pending':
                return $name . ': w kolejce do zamiany na ' . $format;
            case 'converting':
                return $name . ': zamienianie na ' . $format . ' w toku…';
            case 'done':
                $sizes = !empty($job['sizeFrom']) && !empty($job['sizeTo'])
                    ? ' (' . formatSize($job['sizeFrom']) . ' → ' . formatSize($job['sizeTo']) . ')' : '';
                return $name . ': gotowe (' . $format . ')' . $sizes;
            case 'skipped':
                return $name . ': zostawiono oryginał' . (($job['message'] ?? '') !== '' ? ' (' . $job['message'] . ')' : '');
            case 'failed':
                return $name . ': konwersja się nie udała' . (($job['message'] ?? '') !== '' ? ' (' . $job['message'] . ')' : '');
        }

        return $name;
    }

    // a short state for the panel row; the whole reason stays in its tooltip
    function mediaJobShort($job)
    {
        $format = ($job['kind'] ?? 'webm') === 'webm' ? 'WebM' : 'WebP';
        switch ($job['status'] ?? '') {
            case 'pending': return 'w kolejce';
            case 'converting': return 'konwersja na ' . $format . '…';
            case 'done':
                $sizes = !empty($job['sizeFrom']) && !empty($job['sizeTo'])
                    ? ' (' . formatSize($job['sizeFrom']) . ' → ' . formatSize($job['sizeTo']) . ')' : '';
                return $format . ' gotowe' . $sizes;
            case 'skipped': return 'bez zmian, oryginał zostaje';
            case 'failed': return 'błąd konwersji';
        }

        return '';
    }

    // how the panel sums the jobs up, e.g. "2 w toku · 13 bez zmian"
    function mediaJobsSummary($jobs)
    {
        $counts = ['pending' => 0, 'converting' => 0, 'done' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($jobs as $job)
            if (isset($counts[$job['status'] ?? '']))
                $counts[$job['status']]++;

        $parts = [];
        $active = $counts['pending'] + $counts['converting'];
        if ($active)
            $parts[] = $active . ' w toku';
        if ($counts['done'])
            $parts[] = $counts['done'] . ' ' . plural($counts['done'], 'gotowe', 'gotowe', 'gotowych');
        if ($counts['skipped'])
            $parts[] = $counts['skipped'] . ' bez zmian';
        if ($counts['failed'])
            $parts[] = $counts['failed'] . ' ' . plural($counts['failed'], 'błąd', 'błędy', 'błędów');

        return implode(' · ', $parts);
    }

    // whether a job is still on its way
    function mediaJobActive($job)
    {
        return in_array($job['status'] ?? '', ['pending', 'converting'], true);
    }
