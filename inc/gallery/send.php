<?php
    // Giving out the files PHP serves itself (of the folders of the accounts and
    // of the private folder), with ranges for films. Part of the gallery, loaded
    // by inc/gallery.php.

    // A file of the folder of an account for its link, i/u/<token>/<name> or in
    // a subfolder, i/u/<token>/<folder>/<name>: anyone with the link gets it,
    // as with every file of the gallery, but nobody can work out the link from
    // the account's ID.
    function sendUserFile($base, $rel)
    {
        $file = resolvePath($base, $rel, false);
        if (!$file) {
            sendMovedLink($rel);
            return;
        }

        // The token grants a link into one account's folder. Resolve that root
        // separately so traversal and symlinks cannot reach a neighbouring one.
        $home = preg_match('~^(' . USERS_URL . '/' . USER_TOKEN . ')/~', $rel, $match)
            ? resolvePath($base, $match[1], true) : null;
        if (!$home || !inUsersDir($home[1]) || !inFolder($file[1], $home[1])) {
            http_response_code(404);
            return;
        }

        sendMedia($file[0], 'public, max-age=86400');
    }

    // A file of the private folder for its link, i/private/<name>: only those
    // who may see the folder get it, everyone else the site's own 404 page.
    // Unlike the rest of the gallery, nginx never gives these files straight
    // from the disk (server/nginx/sanakan.conf sends them to i/index.php).
    function sendPrivateFile($base, $rel)
    {
        $file = resolvePath($base, $rel, false);
        if (!$file || !inPrivateDir($file[1]) || !galleryCanSee($base, $file[1])) {
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
            header('Cache-Control: no-store');
            readfile(str_replace('\\', '/', dirname(__DIR__, 2)) . '/404.html');
            return;
        }

        sendMedia($file[0], 'private, max-age=3600');
    }

    // a picture or film as it is, 404 for any other file; a film may be asked
    // for in parts (Range), so it can be seeked and streamed instead of coming
    // whole
    function sendMedia($path, $cache)
    {
        $type = MEDIA_MIME[extensionOf($path)] ?? null;
        if ($type === null || !is_file($path)) {
            http_response_code(404);
            return;
        }

        $time = filemtime($path);
        $size = filesize($path);
        $etag = '"' . dechex($time) . '-' . dechex($size) . '"';
        header('Content-Type: ' . $type);
        header('Cache-Control: ' . $cache);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $time) . ' GMT');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        header('Accept-Ranges: bytes');
        if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            return;
        }

        // "bytes=..." from a player: one part is served, the whole file otherwise
        $range = mediaRange((string)($_SERVER['HTTP_RANGE'] ?? ''), $size);
        if ($range === false) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            return;
        }
        if ($range !== null) {
            [$start, $end] = $range;
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
            header('Content-Length: ' . ($end - $start + 1));
            $handle = @fopen($path, 'rb');
            if ($handle === false)
                return;
            fseek($handle, $start);
            $left = $end - $start + 1;
            while ($left > 0 && !feof($handle)) {
                $chunk = fread($handle, (int)min(262144, $left));
                if ($chunk === false || $chunk === '')
                    break;
                echo $chunk;
                $left -= strlen($chunk);
            }
            fclose($handle);
            return;
        }

        header('Content-Length: ' . $size);
        readfile($path);
    }

    // [start, end] of a single requested range, null when the request asks for
    // none (or for several, which is answered with the whole file), or false when
    // it is one this file cannot satisfy (416)
    function mediaRange($header, $size)
    {
        $header = trim($header);
        if ($header === '' || stripos($header, 'bytes=') !== 0)
            return null;

        $spec = trim(substr($header, 6));
        if (strpos($spec, ',') !== false || !preg_match('/^(\d*)-(\d*)$/', $spec, $match) || ($match[1] === '' && $match[2] === ''))
            return null;

        if ($match[1] === '') {
            // the last N bytes
            $length = (int)$match[2];
            if ($length <= 0 || $size === 0)
                return false;
            $start = max(0, $size - $length);
            $end = $size - 1;
        } else {
            $start = (int)$match[1];
            $end = $match[2] === '' ? $size - 1 : (int)$match[2];
        }
        if ($size === 0 || $start >= $size || $start > $end)
            return false;

        return [$start, min($end, $size - 1)];
    }
