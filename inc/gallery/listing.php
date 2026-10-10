<?php
    // What the pages show of the gallery: the tiles of folders and files, the
    // search, the pictures to pick for the croppers, the activity of an account.
    // Part of the gallery, loaded by inc/gallery.php.

    // what an account did in the gallery, by the action in the history: [label, items]
    const GALLERY_ACTIONS = [
        'upload' => 'dodane pliki',
        'mkdir' => 'nowe foldery',
        'move' => 'przeniesione',
        'rename' => 'zmiany nazw',
        'rotate' => 'obrócone',
        'webp' => 'zamienione na WebP',
        'webm' => 'zamienione na WebM',
        'delete' => 'do kosza'
    ];

    // What an account did, from the history (the newest HISTORY_KEEP entries of
    // the whole site): [entries, gallery counts by action, files it added].
    // The entries are its own, and with $aboutIt also the ones of others that
    // name its ID (what the panel changed for it), each with 'own'.
    function accountActivity($id, $aboutIt, $historyMax, $uploadsMax)
    {
        $history = [];
        $gallery = array_fill_keys(array_keys(GALLERY_ACTIONS), 0);
        $uploads = [];
        foreach (historyEntries(HISTORY_KEEP) as $entry) {
            $by = ($entry['id'] ?? '') === $id;
            $text = (string)($entry['text'] ?? '');
            if (($by || ($aboutIt && strpos($text, $id) !== false)) && count($history) < $historyMax)
                $history[] = $entry + ['own' => $by];
            if (!$by || !isset($gallery[$entry['action'] ?? '']))
                continue;

            // "Do kosza: i/a.png, i/b.png." counts each, "Dodano i/a.png." one
            $items = preg_match('/^[^:]+: (.+)\.$/u', $text, $list) && in_array($entry['action'], ['delete', 'move', 'rotate', 'webp'], true) ? count(explode(', ', $list[1])) : 1;
            $gallery[$entry['action']] += $items;
            if ($entry['action'] === 'upload' && count($uploads) < $uploadsMax && preg_match('~^Dodano i/(.+?)(?:, zamienione z .*)?\.$~u', $text, $file))
                $uploads[] = ['rel' => $file[1], 'time' => $entry['time'] ?? 0];
        }

        return [$history, $gallery, $uploads];
    }

    // What a folder tile shows: name, number of items and up to three pictures from inside
    function folderEntry($full, $rel)
    {
        $inside = listNames($full, $rel);
        $previews = [];
        foreach ($inside as $innerName) {
            if (count($previews) == 3)
                break;
            $innerFull = $full . '/' . $innerName;
            if (is_file($innerFull) && (isImage($innerName) || isVideo($innerName)) && ($url = thumbUrl($rel . '/' . $innerName, $innerFull)))
                $previews[] = $url;
        }

        return [
            'name' => displayName($rel),
            'rel' => $rel,
            'count' => count($inside),
            'mtime' => filemtime($full),
            'previews' => $previews
        ];
    }

    // What a file tile shows
    function fileEntry($full, $rel)
    {
        $name = basename($rel);
        $image = isImage($name);
        $dims = $image ? @getimagesize($full) : false;
        // a photo turned by its EXIF orientation is shown the other way round
        if ($dims && $dims[2] === IMAGETYPE_JPEG && jpegOrientation($full) >= 5)
            $dims = [$dims[1], $dims[0]];

        return [
            'name' => $name,
            'rel' => $rel,
            'ext' => extensionOf($name),
            'size' => filesize($full),
            'mtime' => filemtime($full),
            'kind' => $image ? 'image' : (isVideo($name) ? 'video' : 'file'),
            'dims' => $dims ? $dims[0] . '×' . $dims[1] : '',
            'thumb' => $image || isVideo($name) ? thumbUrl($rel, $full) : null
        ];
    }

    // Folders and files anywhere the account sees with every word of the query
    // in their name, as [folders, files, whether there were more than SEARCH_LIMIT]
    function searchGallery($base, $query)
    {
        $words = preg_split('/\s+/', lower(trim($query)), -1, PREG_SPLIT_NO_EMPTY);
        $found = ['folders' => [], 'files' => [], 'more' => false];
        if ($words && galleryCanView())
            searchFolder($base, '', $words, $found, 0);
        // its own folder and the shared ones, where the search above did not go
        foreach ($words ? galleryHomes($base) : [] as $home)
            if (!$found['more'] && !(galleryCanView() && !inUsersDir($home)))
                searchFolder($base . '/' . $home, $home, $words, $found, 1);

        return [$found['folders'], $found['files'], $found['more']];
    }

    function searchFolder($dirPath, $dirRel, $words, &$found, $depth)
    {
        foreach (listNames($dirPath, $dirRel) as $name) {
            $full = $dirPath . '/' . $name;
            $rel = ltrim($dirRel . '/' . $name, '/');
            $folder = is_dir($full) && !is_link($full);

            $matches = true;
            foreach ($words as $word)
                if (strpos(lower($name), $word) === false)
                    $matches = false;

            if ($matches) {
                if (count($found['folders']) + count($found['files']) >= SEARCH_LIMIT) {
                    $found['more'] = true;
                    return;
                }
                if ($folder)
                    $found['folders'][] = folderEntry($full, $rel);
                else if (is_file($full))
                    $found['files'][] = fileEntry($full, $rel);
            }

            if ($folder && $depth < 10)
                searchFolder($full, $rel, $words, $found, $depth + 1);
            if ($found['more'])
                return;
        }
    }

    // The pictures of the account's own folder, subfolders included, newest
    // first, for the croppers' "Z mojej galerii" (i/?pick): [name, folder as
    // the gallery shows it, link, thumbnail or null], at most PICK_MAX; null
    // for an account without a folder of its own. Films are left out, a crop
    // takes a picture.
    const PICK_MAX = 300;

    function pickList($base)
    {
        $own = ownFolder($base);
        if ($own === null)
            return null;

        $found = [];
        $walk = function ($rel) use (&$walk, &$found, $base) {
            foreach (@scandir($base . '/' . $rel) ?: [] as $name) {
                if ($name[0] === '.')
                    continue;
                $childRel = $rel . '/' . $name;
                $full = $base . '/' . $childRel;
                if (is_dir($full) && !is_link($full))
                    $walk($childRel);
                else if (is_file($full) && isImage($name))
                    $found[] = [$childRel, (int)filemtime($full), $full];
            }
        };
        $walk($own);
        usort($found, function ($a, $b) { return $b[1] <=> $a[1]; });

        $pictures = [];
        foreach (array_slice($found, 0, PICK_MAX) as [$rel, $mtime, $full]) {
            $thumb = thumbUrl($rel, $full);
            $pictures[] = [
                'name' => basename($rel),
                'folder' => displayPath(dirname($rel)),
                'url' => siteRoot() . 'i/' . fileUrl($rel),
                'thumb' => $thumb === null ? null : siteRoot() . 'i/' . $thumb,
                'time' => $mtime
            ];
        }

        return $pictures;
    }
