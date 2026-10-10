<?php
    // Paths inside the gallery and the kinds of its files: safe paths, names, URLs,
    // the entries of a folder, sizes. Part of the gallery, loaded by inc/gallery.php.

    // [full path, path relative to this folder] for a folder or file inside it, or null;
    // realpath() also resolves "..", so nothing outside this folder can be reached
    function resolvePath($base, $rel, $wantDir)
    {
        $rel = privateRel($base, trim(str_replace('\\', '/', (string)$rel), '/'));
        $full = realpath($base . ($rel === '' ? '' : '/' . $rel));
        if ($full === false)
            return null;

        $full = str_replace('\\', '/', $full);
        if ($full !== $base && strpos($full, $base . '/') !== 0)
            return null;
        if ($wantDir ? !is_dir($full) : !is_file($full))
            return null;

        $relPath = ltrim(substr($full, strlen($base)), '/');
        foreach (explode('/', $relPath) as $part)
            if ($part !== '' && $part[0] === '.')
                return null;
        if ($relPath === 'index.php')
            return null;

        return [$full, $relPath];
    }

    function extensionOf($name)
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    function isImage($name)
    {
        return in_array(extensionOf($name), IMAGE_TYPES, true);
    }

    function isVideo($name)
    {
        return in_array(extensionOf($name), VIDEO_TYPES, true);
    }

    // WebM is Matroska: the file starts with the EBML magic number and names its doc type
    function isWebm($path)
    {
        $head = (string)@file_get_contents($path, false, null, 0, 64);

        return strncmp($head, "\x1A\x45\xDF\xA3", 4) === 0 && strpos($head, 'webm') !== false;
    }

    // MP4 (ISO base media): it names its type in the "ftyp" box near the start
    function isMp4($path)
    {
        $head = (string)@file_get_contents($path, false, null, 0, 12);

        return strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp';
    }

    // the content of an uploaded film matches its extension
    function isVideoContent($name, $path)
    {
        return extensionOf($name) === 'mp4' ? isMp4($path) : isWebm($path);
    }

    // a HEIC, HEIF or AVIF: ISO base media with a still-image brand in its
    // "ftyp" box (an MP4 has a video brand there instead)
    function isHeifImage($path)
    {
        $head = (string)@file_get_contents($path, false, null, 0, 12);
        if (strlen($head) < 12 || substr($head, 4, 4) !== 'ftyp')
            return false;

        return in_array(substr($head, 8, 4), ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'mif1', 'msf1', 'avif', 'avis'], true);
    }

    // whether an upload's content matches what its name promises
    function isImageContent($name, $path)
    {
        $ext = extensionOf($name);
        if ($ext === 'avif' || in_array($ext, CONVERT_IMAGE_TYPES, true))
            return isHeifImage($path);

        return (bool)@getimagesize($path);
    }

    // URL of a file relative to this folder, every part encoded on its own
    function fileUrl($rel)
    {
        return implode('/', array_map('rawurlencode', explode('/', publicRel($rel))));
    }

    function folderUrl($rel)
    {
        return $rel === '' ? './' : '?p=' . rawurlencode(publicRel($rel));
    }

    // visible entries of a folder: no hidden files, no index.php in the top
    // folder, and there the folders of the accounts and the private folder only
    // for those who may see them. $everything drops that last part, for the
    // panel's numbers, which count the whole gallery no matter who asks.
    function listNames($dirPath, $dirRel, $everything = false)
    {
        $names = [];
        foreach (@scandir($dirPath) ?: [] as $name) {
            if ($name[0] === '.' || ($dirRel === '' && ($name === 'index.php'
                    || (!$everything && (($name === USERS_DIR && !galleryIsAdmin())
                    || ($name === PRIVATE_DIR && !galleryCanSeePrivate()))))))
                continue;
            $names[] = $name;
        }
        natcasesort($names);

        return array_values($names);
    }

    // a folder or a file inside the gallery, see resolvePath()
    function resolveAny($base, $rel)
    {
        return resolvePath($base, $rel, true) ?: resolvePath($base, $rel, false);
    }

    // every folder of the gallery, for the move dialog
    function allFolders($base, $rel = '', $depth = 0)
    {
        $list = [$rel];
        $full = $rel === '' ? $base : $base . '/' . $rel;
        if ($depth >= 10)
            return $list;

        foreach (listNames($full, $rel) as $name)
            if (is_dir($full . '/' . $name) && !is_link($full . '/' . $name))
                $list = array_merge($list, allFolders($base, ltrim($rel . '/' . $name, '/'), $depth + 1));

        return $list;
    }

    // ---- Names and files ----------------------------------------------------

    // "i" or "i/folder/file", as paths are written in messages
    function galleryPath($rel)
    {
        return $rel === '' ? 'i' : 'i/' . $rel;
    }

    // what is wrong with a new file or folder name, or null
    function nameError($name, $isFile)
    {
        if ($name === '' || $name === '.' || $name === '..')
            return 'Nazwa nie może być pusta.';
        // not changed into something valid: the name would then point at another file
        if (!preg_match('//u', $name))
            return 'Nazwa ma znaki, których nie da się odczytać (to nie UTF-8).';
        if ($name[0] === '.')
            return 'Nazwa nie może zaczynać się od kropki.';
        if (preg_match('/[\/\\\\:*?"<>|\x00-\x1F]/', $name))
            return 'Nazwa nie może zawierać znaków / \\ : * ? " < > |';
        if (strlen($name) > MAX_NAME_LENGTH)
            return 'Nazwa jest za długa.';
        if ($isFile && !isImage($name) && !isVideo($name) && !in_array(extensionOf($name), CONVERT_IMAGE_TYPES, true))
            return 'Można dodawać tylko obrazki i filmy: ' . implode(', ', array_merge(IMAGE_TYPES, VIDEO_TYPES, CONVERT_IMAGE_TYPES)) . '.';

        return null;
    }

    // the name, or "name (2).png" and so on when it is taken
    function freeName($dirPath, $name)
    {
        if (!file_exists($dirPath . '/' . $name))
            return $name;

        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $stem = $ext === '' ? $name : substr($name, 0, -strlen($ext) - 1);
        for ($i = 2; ; $i++) {
            $candidate = $stem . ' (' . $i . ')' . ($ext === '' ? '' : '.' . $ext);
            if (!file_exists($dirPath . '/' . $candidate))
                return $candidate;
        }
    }

    function removeTree($path, $deadline = null)
    {
        if (is_link($path) || is_file($path))
            return @unlink($path);

        // past $deadline (microtime) it stops, what is removed stays removed
        foreach (@scandir($path) ?: [] as $name)
            if ($name !== '.' && $name !== '..' && (($deadline !== null && microtime(true) >= $deadline) || !removeTree($path . '/' . $name, $deadline)))
                return false;

        return @rmdir($path);
    }

    // bytes in a file or a folder with everything inside
    function treeSize($path)
    {
        if (is_link($path) || is_file($path))
            return (int)@filesize($path);

        $bytes = 0;
        foreach (@scandir($path) ?: [] as $name)
            if ($name !== '.' && $name !== '..')
                $bytes += treeSize($path . '/' . $name);

        return $bytes;
    }

    // the name the gallery shows for a folder or file, and gives it in a ZIP:
    // the folder of an account by its nick alone, without the ID
    function displayName($rel)
    {
        $name = basename($rel);
        if (dirname($rel) !== USERS_DIR || !preg_match('/^(\d+)-?(.*)$/', $name, $match))
            return $name;

        // two accounts with one nick are told apart by the end of their IDs
        $nick = $match[2] !== '' ? $match[2] : 'bez nicku';
        return (userNicks()[lower($nick)] ?? 0) > 1 ? $nick . ' #' . substr($match[1], -4) : $nick;
    }

    // how many folders of accounts have each nick, by the nick in lower case
    function userNicks()
    {
        static $nicks = null;
        if ($nicks === null) {
            $nicks = [];
            foreach (@scandir(str_replace('\\', '/', dirname(__DIR__, 2)) . '/i/' . USERS_DIR) ?: [] as $name)
                if (preg_match('/^\d+-?(.*)$/', $name, $match)) {
                    $nick = lower($match[1] !== '' ? $match[1] : 'bez nicku');
                    $nicks[$nick] = ($nicks[$nick] ?? 0) + 1;
                }
        }

        return $nicks;
    }

    // a path as the gallery shows it, "i/users/Sniku/a.webp"; galleryPath()
    // keeps the real one for the history
    function displayPath($rel)
    {
        if (preg_match('~^(' . USERS_DIR . '/[^/]+)(/.*)?$~', $rel, $match))
            $rel = USERS_DIR . '/' . displayName($match[1]) . ($match[2] ?? '');

        return galleryPath($rel);
    }

    function iniBytes($value)
    {
        $value = trim((string)$value);
        $bytes = (float)$value;
        switch (strtolower(substr($value, -1))) {
            case 'g': $bytes *= 1024;
            case 'm': $bytes *= 1024;
            case 'k': $bytes *= 1024;
        }

        return (int)$bytes;
    }

    // the biggest file the server takes in one upload
    function uploadLimit()
    {
        $limits = array_filter([iniBytes(ini_get('upload_max_filesize')), iniBytes(ini_get('post_max_size'))]);

        return $limits ? min($limits) : 0;
    }

    function countLabel($n)
    {
        return $n . ' ' . plural($n, 'element', 'elementy', 'elementów');
    }
