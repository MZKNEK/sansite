<?php
    // The pages only for the members of the bot's server, 0x40 (hues/) and WAP
    // (wap/): an account logged in with Discord that has a role on the bot's
    // server (user and up, hasServerRole()), or an owner of the site
    // (PANEL_ADMINS, isPanelAdminId(), so the rights an admin tries in the
    // panel count). Everyone else, logged in or not, gets the site's 403 page.
    // Their media lie in dot folders (hues/.pack/, wap/.mu/), which nginx
    // refuses like any dot folder, so they go out only through
    // sendMemberFile() (hues/pack.php, wap/song.php).
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/gallery/send.php'; // mediaRange()

    function memberAllowed()
    {
        if (!authConfigured())
            return false;
        $user = siteUser();

        return $user !== null && (isPanelAdminId($user['id']) || hasServerRole($user['id']));
    }

    // an owner of the site only (hues/centres.php)
    function ownerAllowed()
    {
        if (!authConfigured())
            return false;
        $user = siteUser();

        return $user !== null && isPanelAdminId($user['id']);
    }

    // Returns only for those let in ($allowed, memberAllowed() by default); a
    // page refused is the 403 page, a list or a file a bare 403. The session is
    // closed either way, so the files of one page do not wait in line for each
    // other's lock.
    function memberGate($page = true, $allowed = null)
    {
        $allowed = $allowed ?? memberAllowed();
        if (session_status() === PHP_SESSION_ACTIVE)
            session_write_close();
        if ($allowed) {
            header('Cache-Control: private, no-cache');
            return;
        }
        if ($page) {
            sendErrorPage(403);
        } else {
            http_response_code(403);
            header('Cache-Control: no-store');
        }
        exit;
    }

    // A file of $dir by its path in it ($name, from the request), with the
    // type of its extension in $types, a 404 for anything else (a dot file or
    // folder, a way out of $dir). One byte range is served (mediaRange()), so
    // a player can wind and read a length without the whole file. Kept only in
    // the visitor's browser (private), never by Cloudflare, for $maxAge
    // seconds.
    function sendMemberFile($dir, $name, array $types, $maxAge = 86400)
    {
        $name = str_replace('\\', '/', (string)$name);
        $type = $types[strtolower(pathinfo($name, PATHINFO_EXTENSION))] ?? null;
        $base = realpath($dir);
        $path = $type === null || $base === false || strpos($name, "\0") !== false || preg_match('~(^|/)\.~', $name)
            ? false : realpath($base . '/' . $name);
        if ($path === false || !is_file($path) || strpos(str_replace('\\', '/', $path), str_replace('\\', '/', $base) . '/') !== 0) {
            http_response_code(404);
            header('Cache-Control: no-store');
            return;
        }

        $size = filesize($path);
        $etag = '"' . dechex(filemtime($path)) . '-' . dechex($size) . '"';
        header('Content-Type: ' . $type);
        header('Cache-Control: private, max-age=' . (int)$maxAge);
        header('ETag: ' . $etag);
        header('Accept-Ranges: bytes');
        header('X-Content-Type-Options: nosniff');
        if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            return;
        }

        $range = mediaRange((string)($_SERVER['HTTP_RANGE'] ?? ''), $size);
        if ($range === false) {
            http_response_code(416);
            header('Content-Range: bytes */' . $size);
            return;
        }
        [$start, $end] = $range ?? [0, $size - 1];
        if ($range !== null) {
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }
        header('Content-Length: ' . ($end - $start + 1));
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD')
            return;

        $handle = @fopen($path, 'rb');
        if ($handle === false)
            return;
        fseek($handle, $start);
        for ($left = $end - $start + 1; $left > 0 && !feof($handle) && !connection_aborted(); $left -= strlen($chunk)) {
            $chunk = fread($handle, (int)min(262144, $left));
            if ($chunk === false || $chunk === '')
                break;
            echo $chunk;
        }
        fclose($handle);
    }
