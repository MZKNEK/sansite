<?php
    // The folders of the accounts: where they are, their links, their limits
    // and the lock that keeps two requests from both using the last place. Part
    // of the gallery, loaded by inc/gallery.php.

    // ---- Folders of their own -----------------------------------------------------

    function galleryIsUploader()
    {
        $user = siteUser();
        return $user !== null && isGalleryUploaderId($user['id']);
    }

    // how many files an account may keep in its folder, as set in the panel
    function userFilesLimit($id)
    {
        $limit = readData('access')['uploadLimits'][(string)$id] ?? null;

        return is_int($limit) && $limit > 0 ? $limit : USER_FILES_DEFAULT;
    }

    // the random name a folder of an account goes by in links, i/u/<token>
    const USER_TOKEN = '[0-9a-f]{16}';

    // the folder of an account, "users/<id>-<nick>", found by the ID alone as the
    // nick can change; null while it has none
    function userFolder($base, $id)
    {
        foreach (@scandir($base . '/' . USERS_DIR) ?: [] as $name)
            if (($name === (string)$id || strpos($name, $id . '-') === 0) && is_dir($base . '/' . USERS_DIR . '/' . $name))
                return USERS_DIR . '/' . $name;

        return null;
    }

    // [id => token] of the folders of the accounts (inc/data/user-folders.json);
    // with $id that account gets one when it has none yet
    function userTokens($id = null)
    {
        static $tokens = null;
        if ($tokens === null)
            $tokens = readData('user-folders');
        // a token that could not be written is not given out: the link would
        // not lead anywhere after this request
        if ($id !== null && !isset($tokens[$id]) && updateDataFile('user-folders.json', function ($data) use ($id, &$made) {
            $data[$id] = $data[$id] ?? bin2hex(random_bytes(8));
            return $made = $data;
        }))
            $tokens = $made;

        return $tokens;
    }

    // a path as links show it: users/<id>-<nick>/... is u/<token>/...
    function publicRel($rel)
    {
        if (!preg_match('~^' . USERS_DIR . '/(\d+)(?:-[^/]*)?(/.*)?$~', $rel, $match))
            return $rel;
        $token = userTokens($match[1])[$match[1]] ?? null;

        return $token === null ? $rel : USERS_URL . '/' . $token . ($match[2] ?? '');
    }

    // and back: u/<token>/... is the folder of its account as it is on the disk
    function privateRel($base, $rel)
    {
        if (!preg_match('~^' . USERS_URL . '/(' . USER_TOKEN . ')(/.*)?$~', $rel, $match))
            return $rel;
        $id = array_search($match[1], userTokens(), true);
        $folder = $id === false ? null : userFolder($base, (string)$id);

        return $folder === null ? $rel : $folder . ($match[2] ?? '');
    }

    // The rel of a file of an account's folder from a request path,
    // /i/u/<token>/<file> or /i/u/<token>/<folder>/<file>, or null. The three
    // places that know how those links look (publicRel(), privateRel() and this
    // one) sit together, so the format lives in one place.
    function userLinkRel($uri)
    {
        if (!preg_match('~/' . USERS_URL . '/(' . USER_TOKEN . '(?:/[^/]+)+)$~', (string)parse_url((string)$uri, PHP_URL_PATH), $match))
            return null;

        return USERS_URL . '/' . rawurldecode($match[1]);
    }

    // the folder of the logged-in account when it may have one, made the first time
    function ownFolder($base)
    {
        static $known = [];
        if (array_key_exists($base, $known))
            return $known[$base];

        $user = siteUser();
        if ($user === null || !isGalleryUploaderId($user['id']))
            return $known[$base] = null;

        // the nick only as far as a name in the gallery may have it
        $nick = ltrim(trim(cutText(preg_replace('/[\/\\\\:*?"<>|\x00-\x1F]+/', '', (string)($user['name'] ?? '')), 40)), '.');
        $named = USERS_DIR . '/' . $user['id'] . ($nick !== '' ? '-' . $nick : '');
        $rel = userFolder($base, $user['id']);
        if ($rel === null) {
            if (!is_dir($base . '/' . USERS_DIR))
                @mkdir($base . '/' . USERS_DIR, 0755);
            if (!@mkdir($base . '/' . $named, 0755) && !is_dir($base . '/' . $named))
                return $known[$base] = null;
            addHistory('mkdir', 'Utworzono własny folder ' . galleryPath($named) . '.');
            $rel = $named;
        } else if ($rel !== $named && !file_exists($base . '/' . $named) && @rename($base . '/' . $rel, $base . '/' . $named)) {
            // the account changed its nick on Discord: the folder follows, its links
            // stay as they go by the ID
            moveHashes($rel, $named);
            addHistory('rename', 'Własny folder ' . galleryPath($rel) . ' nazywa się teraz ' . basename($named) . ' (nowy nick).');
            $rel = $named;
        }

        return $known[$base] = $rel;
    }

    // files right in a folder and their bytes: [count, bytes]
    function folderUse($dirPath)
    {
        $count = 0;
        $bytes = 0;
        foreach (@scandir($dirPath) ?: [] as $name)
            if ($name[0] !== '.' && is_file($dirPath . '/' . $name)) {
                $count++;
                $bytes += filesize($dirPath . '/' . $name);
            }

        return [$count, $bytes];
    }

    // files in a folder and all its subfolders, their bytes and the biggest one:
    // [count, bytes, biggest]; a file alone counts as itself
    function treeUse($dirPath)
    {
        if (is_file($dirPath) && !is_link($dirPath)) {
            $size = (int)filesize($dirPath);
            return [1, $size, $size];
        }

        $count = 0;
        $bytes = 0;
        $biggest = 0;
        $walk = function ($path) use (&$walk, &$count, &$bytes, &$biggest) {
            foreach (@scandir($path) ?: [] as $name) {
                if ($name[0] === '.')
                    continue;
                $full = $path . '/' . $name;
                if (is_dir($full) && !is_link($full)) {
                    $walk($full);
                } else if (is_file($full)) {
                    $size = (int)filesize($full);
                    $count++;
                    $bytes += $size;
                    $biggest = max($biggest, $size);
                }
            }
        };
        $walk($dirPath);

        return [$count, $bytes, $biggest];
    }

    // the ID of the account whose folder a gallery path is in, or null
    function folderOwner($rel)
    {
        return preg_match('~^' . USERS_DIR . '/(\d+)(?:-[^/]*)?(?:/|$)~', (string)$rel, $match) ? $match[1] : null;
    }

    // Holds the folder of an account and its trash while something makes them
    // grow (an upload, a restore, a delete into the trash, the conversion by
    // cron), so two devices of one account, or a device and cron, do not both
    // see room for one more. The same request may take it again; the function
    // returned lets go of it, and the end of the request does too. Null when it
    // could not be had in DATA_LOCK_WAIT seconds.
    function accountLock($id)
    {
        static $held = [];
        $id = (string)$id;
        if (!isset($held[$id])) {
            $handle = fileLock(dataDir() . '/locks/account-' . $id . '.lock', true);
            if ($handle === null)
                return null;
            $held[$id] = [$handle, 0];
        }
        $held[$id][1]++;

        return function () use (&$held, $id) {
            if (isset($held[$id]) && --$held[$id][1] === 0) {
                fclose($held[$id][0]);
                unset($held[$id]);
            }
        };
    }

    // Why $count more files of $bytes (the biggest $biggest) do not fit in the
    // folder of the account $id, or null when they do. A folder over its limits
    // already (from before the limits were checked everywhere) keeps its files,
    // it only takes no more.
    function accountRoomError($base, $id, $count, $bytes, $biggest)
    {
        $folder = userFolder($base, $id);
        [$has, $used] = $folder === null ? [0, 0] : treeUse($base . '/' . $folder);
        $limit = userFilesLimit($id);
        if ($biggest > USER_FILE_MAX_BYTES)
            return 'plik ma ' . formatSize($biggest) . ', a limit jednego to ' . formatSize(USER_FILE_MAX_BYTES);
        if ($has + $count > $limit)
            return 'w folderze konta jest ' . $has . ' z ' . $limit . ' ' . plural($limit, 'zdjęcia', 'zdjęć', 'zdjęć') . ', a to ' . $count . ' ' . plural($count, 'plik', 'pliki', 'plików');
        if ($used + $bytes > USER_TOTAL_MAX_BYTES)
            return 'w folderze konta zostało ' . formatSize(max(0, USER_TOTAL_MAX_BYTES - $used)) . ' wolnego z ' . formatSize(USER_TOTAL_MAX_BYTES) . ', a to zajmuje ' . formatSize($bytes);

        return null;
    }

    // a file just saved in the folder of an account goes away again when the
    // folder, subfolders included, would hold more than USER_TOTAL_MAX_BYTES with it
    function checkUserTotal($dirPath, $path, $name)
    {
        clearstatcache();
        [, $bytes] = treeUse($dirPath);
        if ($bytes <= USER_TOTAL_MAX_BYTES)
            return;

        $free = max(0, USER_TOTAL_MAX_BYTES - ($bytes - filesize($path)));
        @unlink($path);
        reply(false, $name . ': nie mieści się, w folderze zostało ' . formatSize($free) . ' wolnego z ' . formatSize(USER_TOTAL_MAX_BYTES) . '. Usuń coś, żeby zrobić miejsce.', 413);
    }
