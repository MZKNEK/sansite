<?php
    // Adding a folder of files from the server's disk to the gallery, with its
    // subfolders, at the same paths: tools/gallery-upload.sh sends a folder of
    // the local i/ and inc/gallery-import.php adds it here. Each file goes
    // through the checks of an upload (name, content, decoder limits), loses its
    // metadata and gets a line in the history; a file already there is left
    // alone, so the same folder can be sent again. As an upload by an admin it
    // skips the limits of the accounts' folders. Part of the gallery, loaded by
    // inc/gallery.php.

    // who the history names for these changes
    const IMPORT_WHO = 'skrypt';

    // The gallery folder at $rel, made with the folders missing on the way, as
    // [full path, rel], or a string saying what is wrong. An account's own
    // folder is named by its ID and nick, so it is never made here.
    function importFolder($base, $rel, $log)
    {
        $rel = trim(str_replace('\\', '/', (string)$rel), '/');
        $done = '';
        foreach ($rel === '' ? [] : explode('/', $rel) as $part) {
            $next = ltrim($done . '/' . $part, '/');
            if ($error = nameError($part, false))
                return galleryPath($next) . ': ' . $error;
            if (file_exists($base . '/' . $next)) {
                if (!resolvePath($base, $next, true))
                    return galleryPath($next) . ': to nie jest folder galerii.';
            } elseif ($done === USERS_DIR) {
                return galleryPath($next) . ': nie ma takiego folderu konta (zakłada go galeria, gdy konto pierwszy raz ją otworzy).';
            } else {
                $release = galleryLock();
                try {
                    if (!@mkdir($base . '/' . $next, 0755) && !is_dir($base . '/' . $next))
                        return galleryPath($next) . ': nie udało się utworzyć folderu.';
                    addHistory('mkdir', 'Utworzono folder ' . galleryPath($next) . '.', IMPORT_WHO);
                } finally {
                    $release();
                }
                $log('+ ' . galleryPath($next) . '/');
            }
            $done = $next;
        }

        return [$base . ($rel === '' ? '' : '/' . $rel), $rel];
    }

    // The name a file already has in the gallery folder, or null: its own, or
    // the WebP or WebM it was changed to after an earlier upload, unless that
    // WebP or WebM is sent too ($sent tells, by its name), which then goes on
    // its own.
    function importExisting($dirPath, $name, $sent)
    {
        $ext = extensionOf($name);
        $stem = pathinfo($name, PATHINFO_FILENAME);
        $names = [$name];
        if ($ext === 'avif' || in_array($ext, WEBP_SOURCE_TYPES, true) || in_array($ext, CONVERT_IMAGE_TYPES, true))
            $names[] = $stem . '.webp';
        elseif (in_array($ext, VIDEO_SOURCE_TYPES, true))
            $names[] = $stem . '.webm';

        foreach ($names as $i => $candidate)
            if (file_exists($dirPath . '/' . $candidate) && ($i === 0 || !$sent($candidate)))
                return $candidate;

        return null;
    }

    // Of the files of a folder to send (paths relative to it, forward
    // slashes), the ones the gallery folder $rel does not have yet.
    function importMissing($base, $rel, $files)
    {
        $sent = array_flip($files);
        $missing = [];
        foreach ($files as $file) {
            $sub = dirname($file) === '.' ? '' : dirname($file);
            $dirRel = trim($rel . '/' . $sub, '/');
            $has = function ($name) use ($sent, $sub) { return isset($sent[ltrim($sub . '/' . $name, '/')]); };
            if (importExisting($base . ($dirRel === '' ? '' : '/' . $dirRel), basename($file), $has) === null)
                $missing[] = $file;
        }

        return $missing;
    }

    // Adds one file to the gallery folder $dir ([full path, rel]); 'added',
    // 'skipped' (already there) or 'failed'. The source is moved, not copied.
    function importFile($dir, $source, $webp, $log)
    {
        $name = basename($source);
        $shown = galleryPath(ltrim($dir[1] . '/' . $name, '/'));
        $fail = function ($why) use ($log, $shown) {
            $log('! ' . $shown . ': ' . $why);
            return 'failed';
        };
        $sent = function ($other) use ($source) { return file_exists(dirname($source) . '/' . $other); };

        if ($error = nameError($name, true))
            return $fail($error);
        if (isVideo($name) ? !isVideoContent($name, $source) : !isImageContent($name, $source))
            return $fail('zawartość nie pasuje do typu pliku.');
        if (!isVideo($name) && ($why = imageLimitError($source)) !== null)
            return $fail($why . '.');

        $ext = extensionOf($name);
        $heif = in_array($ext, CONVERT_IMAGE_TYPES, true);
        // the encoder before the gallery, in the order of an upload
        $encoder = $heif ? galleryLock('encoder') : null;
        $mutations = galleryLock();
        $policy = galleryLock('policy', false);
        try {
            if (($have = importExisting($dir[0], $name, $sent)) !== null) {
                $log('= ' . $shown . ' (już jest' . ($have === $name ? '' : ' jako ' . $have) . ')');
                return 'skipped';
            }

            // a HEIC or HEIF is written as WebP now, as in an upload
            if ($heif) {
                if (!canConvertHeif())
                    return $fail('serwer nie umie zamienić HEIC/HEIF na WebP (brak imagemagick lub ffmpeg).');
                $target = pathinfo($name, PATHINFO_FILENAME) . '.webp';
                $temp = $dir[0] . '/.upload-' . bin2hex(random_bytes(12)) . '.webp';
                if (!heifToWebp($source, $temp) || !@rename($temp, $dir[0] . '/' . $target)) {
                    @unlink($temp);
                    return $fail('nie udało się zamienić na WebP.');
                }
                @chmod($dir[0] . '/' . $target, 0644);
                $rel = ltrim($dir[1] . '/' . $target, '/');
                $sizes = ' (' . formatSize(filesize($source)) . ' → ' . formatSize(filesize($dir[0] . '/' . $target)) . ')';
                addHistory('upload', 'Dodano ' . galleryPath($rel) . ', zamienione z ' . $name . $sizes . '.', IMPORT_WHO);
                $log('+ ' . galleryPath($rel) . ', zamienione z ' . $name . $sizes);
                return 'added';
            }

            $full = $dir[0] . '/' . $name;
            if (!@rename($source, $full) && !@copy($source, $full))
                return $fail('nie udało się zapisać pliku.');
            @chmod($full, 0644);
            $stripped = !isVideo($name) && stripMetadata($full);
            clearstatcache();
            $rel = ltrim($dir[1] . '/' . $name, '/');

            // the change to WebP or WebM waits its turn for cron, as in an
            // upload; always in the folder of an account
            $note = '';
            $convertible = $ext === 'avif' || in_array($ext, WEBP_SOURCE_TYPES, true) || in_array($ext, VIDEO_SOURCE_TYPES, true);
            if (($webp || folderOwner($dir[1]) !== null) && $convertible) {
                $format = in_array($ext, VIDEO_SOURCE_TYPES, true) ? 'WebM' : 'WebP';
                if (!($format === 'WebM' ? canConvertVideo() : canConvertImageToWebp($ext)))
                    $note = ', serwer nie umie teraz zamienić na ' . $format;
                else
                    $note = addMediaJob($rel, '', IMPORT_WHO, filesize($full), strtolower($format)) === null
                        ? ', nie udało się dodać konwersji do kolejki' : ', w kolejce do ' . $format;
            }

            addHistory('upload', 'Dodano ' . galleryPath($rel) . '.', IMPORT_WHO);
            $log('+ ' . $shown . ($stripped ? ', bez metadanych' : '') . $note);
            return 'added';
        } finally {
            $policy();
            $mutations();
            if ($encoder)
                $encoder();
        }
    }

    // Adds the files of $source to the gallery folder $rel, and its subfolders
    // to the ones of the same names; ['added' => n, 'skipped' => n, 'failed' => n].
    // Hidden files and folders (a dot first) are passed over.
    function importTree($base, $source, $rel, $webp, $log)
    {
        $counts = ['added' => 0, 'skipped' => 0, 'failed' => 0];
        $dir = importFolder($base, $rel, $log);
        if (!is_array($dir)) {
            $log('! ' . $dir);
            $counts['failed']++;
            return $counts;
        }

        $names = array_filter(scandir($source) ?: [], function ($name) { return $name[0] !== '.'; });
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);
        $folders = [];
        foreach ($names as $name) {
            if (is_dir($source . '/' . $name))
                $folders[] = $name;
            elseif (is_file($source . '/' . $name))
                $counts[importFile($dir, $source . '/' . $name, $webp, $log)]++;
        }
        foreach ($folders as $name)
            foreach (importTree($base, $source . '/' . $name, ltrim($rel . '/' . $name, '/'), $webp, $log) as $key => $count)
                $counts[$key] += $count;

        return $counts;
    }
