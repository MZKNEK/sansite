<?php
    // What the gallery remembers of its files: their hashes, for duplicates, and
    // the old links of files changed to WebP or WebM. Part of the gallery, loaded
    // by inc/gallery.php.

    // ---- Duplicates ---------------------------------------------------------------
    // SHA-256 of gallery files, cached in inc/data/hashes.json as
    // [rel => ['size', 'mtime', 'hash', 'source' => hash of the uploaded original]].
    // A file turned into WebP differs from what was uploaded, so the original's
    // hash is kept too, and sending the same PNG again is still recognised.

    function fileHash($full, $rel, &$cache)
    {
        $size = filesize($full);
        $mtime = filemtime($full);
        $entry = $cache[$rel] ?? null;
        if (!$entry || $entry['size'] !== $size || $entry['mtime'] !== $mtime)
            $cache[$rel] = ['size' => $size, 'mtime' => $mtime, 'hash' => hash_file('sha256', $full)];

        return $cache[$rel]['hash'];
    }

    // where in the gallery a file with this content already is; only files of the
    // same size get hashed, so this stays quick
    function findDuplicates($base, $size, $hash, $from = '')
    {
        $cache = readData('hashes');
        $before = $cache;
        $matches = [];

        $walk = function ($dirPath, $dirRel, $depth) use (&$walk, &$cache, &$matches, $size, $hash) {
            foreach (listNames($dirPath, $dirRel) as $name) {
                $full = $dirPath . '/' . $name;
                $rel = ltrim($dirRel . '/' . $name, '/');
                if (is_dir($full) && !is_link($full)) {
                    if ($depth < 10)
                        $walk($full, $rel, $depth + 1);
                    continue;
                }

                $source = $cache[$rel]['source'] ?? null;
                if ($source === $hash || (filesize($full) === $size && fileHash($full, $rel, $cache) === $hash))
                    $matches[] = galleryPath($rel);
            }
        };
        $walk($from === '' ? $base : $base . '/' . $from, $from, 0);

        // the files were hashed without holding the file; only the hashes made
        // now go in, over what others wrote meanwhile
        $made = [];
        foreach ($cache as $rel => $entry)
            if (($before[$rel] ?? null) !== $entry)
                $made[$rel] = $entry;
        if ($made)
            updateData('hashes', function (&$fresh) use ($made) {
                foreach ($made as $rel => $entry)
                    $fresh[$rel] = $entry;
            });

        return $matches;
    }

    function recordHash($full, $rel, $source)
    {
        $cache = readData('hashes');
        fileHash($full, $rel, $cache);
        $entry = ['source' => $source] + $cache[$rel];
        updateData('hashes', function (&$fresh) use ($rel, $entry) {
            $fresh[$rel] = $entry;
        });
    }

    // after a move or rename the cached hashes follow the file, or everything in the folder
    function moveHashes($fromRel, $toRel)
    {
        updateData('hashes', function (&$cache) use ($fromRel, $toRel) {
            $moved = [];
            foreach ($cache as $rel => $entry) {
                $rel = (string)$rel;
                if ($rel === $fromRel || strpos($rel, $fromRel . '/') === 0)
                    $rel = $toRel . substr($rel, strlen($fromRel));
                $moved[$rel] = $entry;
            }
            $cache = $moved;
        });
    }

    function dropHashes($fromRel)
    {
        updateData('hashes', function (&$cache) use ($fromRel) {
            foreach (array_keys($cache) as $rel)
                if ((string)$rel === $fromRel || strpos((string)$rel, $fromRel . '/') === 0)
                    unset($cache[$rel]);
        });
    }

    // ---- Pictures changed to WebP -----------------------------------------------
    // A gallery admin can change a PNG, JPG or GIF to WebP; the original goes to
    // the trash and its old link keeps working: nginx sends a gallery picture
    // that is not there to i/index.php (server/nginx/sanakan.conf), which sends
    // it on to the WebP. The links are kept as links have them (publicRel()), so
    // the folder of an account may change its nick.

    // the old link of a picture now goes to the new one (links that went to the old one too)
    function addMovedLink($fromRel, $toRel)
    {
        $from = publicRel($fromRel);
        $to = publicRel($toRel);
        updateData('moved', function (&$links) use ($from, $to) {
            foreach ($links as $old => $new)
                if ($new === $from)
                    $links[$old] = $to;
            $links[$from] = $to;
            unset($links[$to]);
        });
    }

    // after a move or rename the links follow the file, or everything in the folder
    function followMovedLinks($fromRel, $toRel)
    {
        $from = publicRel($fromRel);
        $to = publicRel($toRel);
        updateData('moved', function (&$links) use ($from, $to) {
            foreach ($links as $old => $new)
                if ($new === $from || strpos($new, $from . '/') === 0)
                    $links[$old] = $to . substr($new, strlen($from));
        });
    }

    // a picture that is not there: on to where it went, or a 404
    function sendMovedLink($rel)
    {
        $to = readData('moved')[trim((string)$rel, '/')] ?? null;
        if ($to === null) {
            http_response_code(404);
            return;
        }

        header('Cache-Control: public, max-age=3600');
        header('Location: ' . siteRoot() . 'i/' . implode('/', array_map('rawurlencode', explode('/', $to))), true, 302);
    }
