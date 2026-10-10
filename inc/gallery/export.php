<?php
    // Downloading folders and picked files as one ZIP, holding only what the
    // visitor may see. Part of the gallery, loaded by inc/gallery.php.

    // ---- ZIP downloads ----------------------------------------------------------------

    function canZip()
    {
        return class_exists('ZipArchive');
    }

    // files under a path for the ZIP as [full path or null for an empty folder,
    // path in the ZIP]; hidden files, links and the names in $skip (only right in
    // the given folder, e.g. the gallery script) stay out, and so does every file
    // or folder (with all in it) $allow, given its full path, says no to
    function zipCollect($full, $local, $skip, &$files, &$bytes, $depth = 0, $allow = null)
    {
        if (is_link($full) || ($allow !== null && !$allow($full)))
            return;
        if (is_file($full)) {
            $files[] = [$full, $local];
            $bytes += filesize($full);
            return;
        }
        if (!is_dir($full) || $depth > 10)
            return;

        $names = [];
        foreach (@scandir($full) ?: [] as $name)
            if ($name[0] !== '.' && !in_array($name, $skip, true))
                $names[] = $name;
        if (!$names)
            $files[] = [null, $local . '/'];
        foreach ($names as $name)
            zipCollect($full . '/' . $name, $local . '/' . $name, [], $files, $bytes, $depth + 1, $allow);
    }

    // gallery items ([full path, rel] each) as what sendZip() takes: the top
    // folder is "i" and loses its index.php, the others keep their own name. Each
    // file and folder in them goes by the visitor's rights (galleryAccess()), so
    // the ZIP of a folder holds what the gallery shows of it and no more: no
    // private folder, no folders of other accounts, no siblings of a shared one.
    function galleryZipRoots($base, $items)
    {
        $access = galleryAccess($base);
        $base = str_replace('\\', '/', $base);
        $allow = function ($full) use ($base, $access) {
            $full = str_replace('\\', '/', $full);
            if ($full !== $base && strpos($full, $base . '/') !== 0)
                return false;

            return $access(ltrim(substr($full, strlen($base)), '/'));
        };

        $roots = [];
        foreach ($items as $item)
            $roots[] = [$item[0], $item[1] === '' ? 'i' : displayName($item[1]), $item[1] === '' ? ['index.php'] : [], $allow];

        return $roots;
    }

    // Sends files and folders ([full path, name in the ZIP, names to leave out
    // right in it, optionally what zipCollect() lets in] each) as one ZIP, stored
    // without compression, since pictures and videos are compressed already.
    // $maxBytes null means no limit but the free disk space. A problem goes back
    // to $backUrl as a message.
    function sendZip($roots, $zipName, $backUrl, $maxBytes = ZIP_MAX_BYTES)
    {
        $fail = function ($message) use ($backUrl) {
            setFlash($message);
            header('Location: ' . $backUrl, true, 303);
            exit;
        };
        if (!canZip())
            $fail('Serwer nie ma modułu ZIP (pakiet php-zip), pobieranie jest wyłączone.');

        $files = [];
        $bytes = 0;
        foreach ($roots as $root)
            zipCollect($root[0], $root[1], $root[2], $files, $bytes, 0, $root[3] ?? null);
        if (!array_filter(array_column($files, 0)))
            $fail('Nie ma tu żadnych plików do pobrania.');
        if ($maxBytes !== null && $bytes > $maxBytes)
            $fail('To za dużo naraz (' . formatSize($bytes) . ', limit ' . formatSize($maxBytes) . '). Pobierz mniejsze foldery osobno.');
        // the ZIP is put together on the disk first
        $free = @disk_free_space(sys_get_temp_dir());
        if ($free !== false && $free < $bytes + 100 * 1024 * 1024)
            $fail('Za mało wolnego miejsca na dysku serwera na złożenie ZIP (' . formatSize($bytes) . ').');

        // the visitor's other pages need not wait while the ZIP is put together
        // and sent; a later problem opens the session again for its message
        if (session_status() === PHP_SESSION_ACTIVE)
            session_write_close();

        @set_time_limit(0);
        $tmp = @tempnam(sys_get_temp_dir(), 'sanakan-zip-');
        if ($tmp === false || !writeZip($files, $tmp)) {
            if ($tmp !== false)
                @unlink($tmp);
            $fail('Nie udało się utworzyć pliku ZIP.');
        }

        $ascii = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $zipName);
        header('Content-Type: application/zip');
        header('Content-Length: ' . filesize($tmp));
        header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($zipName));
        header('Cache-Control: no-store');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    // Writes the files of zipCollect() ([full path or null for an empty folder,
    // path in the ZIP] each) into a ZIP at $path, stored without compression;
    // false when it could not be written.
    function writeZip($files, $path)
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true)
            return false;
        foreach ($files as $file) {
            if ($file[0] === null) {
                $zip->addEmptyDir($file[1]);
            } else {
                $zip->addFile($file[0], $file[1]);
                $zip->setCompressionName($file[1], ZipArchive::CM_STORE);
            }
        }

        return $zip->close();
    }
