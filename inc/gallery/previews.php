<?php
    // The change to WebP by hand, with a preview the admin accepts or rejects.
    // Part of the gallery, loaded by inc/gallery.php.

    // ---- Manual change to WebP with a preview -----------------------------------
    // A gallery admin picks a picture, chooses a quality and the server makes a
    // WebP aside; the result is shown next to the original and only what the
    // admin accepts takes its place. It may also re-encode a still WebP.
    // inc/data/webp-previews.json keeps the waiting results, their files in
    // inc/data/webp-preview/ (out of the web, like the rest of inc/data).

    // the quality a manual change may use, and how long a waiting result is kept
    const WEBP_QUALITY_MIN = 40;

    const WEBP_QUALITY_MAX = 100;

    const WEBP_QUALITY_DEFAULT = 80;

    const WEBP_PREVIEW_KEEP = 7200;

    function webpPreviewDir()
    {
        return dataDir() . '/webp-preview';
    }

    function webpPreviews()
    {
        return readData('webp-previews');
    }

    // the kinds a manual change may take (a still WebP included, to re-encode it)
    function canChangeToWebp($ext)
    {
        if ($ext === 'gif')
            return canConvertGifToWebp();
        if ($ext === 'avif')
            return canConvertImageToWebp('avif');
        if ($ext === 'webp')
            return canConvertToWebp();

        return in_array($ext, WEBP_SOURCE_TYPES, true) && canConvertToWebp();
    }

    // forgets the previews older than WEBP_PREVIEW_KEEP and their files
    function pruneWebpPreviews()
    {
        $before = time() - WEBP_PREVIEW_KEEP;
        updateDataFile('webp-previews.json', function ($data) use ($before) {
            foreach ($data as $token => $preview) {
                if (is_array($preview) && ($preview['created'] ?? 0) >= $before)
                    continue;
                if (is_array($preview) && !empty($preview['candidate']) && !conversionArtifactInUse(webpPreviewDir() . '/' . $preview['candidate']))
                    @unlink(webpPreviewDir() . '/' . $preview['candidate']);
                unset($data[$token]);
            }
            return $data;
        });
    }

    // Makes the WebP aside and returns what the page shows, or a string why not.
    // It is kept only when it is smaller; a bigger one is dropped here already.
    function startWebpPreview($full, $rel, $quality, $user)
    {
        try {
            $encoder = galleryLock('encoder');
            try { return startWebpPreviewLocked($full, $rel, $quality, $user); }
            finally { $encoder(); }
        } catch (DataStoreException $error) { return 'Konwersja jest teraz zajęta. Spróbuj za chwilę.'; }
    }

    function startWebpPreviewLocked($full, $rel, $quality, $user)
    {
        $ext = extensionOf($rel);
        if (!is_file($full) || !canChangeToWebp($ext))
            return 'tego pliku nie można zamienić na WebP';
        if ($ext === 'webp' && isAnimatedWebp($full))
            return 'animowanego WebP nie da się przeliczyć';
        if (($why = imageLimitError($full)) !== null)
            return $why;

        $dir = webpPreviewDir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true))
            return 'serwer nie może zapisać podglądu';
        pruneWebpPreviews();

        $token = bin2hex(random_bytes(12));
        $candidate = $token . '.webp';
        $candidateFull = $dir . '/' . $candidate;
        try { $snapshot = conversionSnapshot($full); }
        catch (Throwable $error) { return $error->getMessage(); }
        $full = $snapshot['input'];
        $sizeFrom = $snapshot['fingerprint']['size'];

        if ($ext === 'gif')
            $ok = gifToWebp($full, $candidateFull, $quality);
        else if ($ext === 'avif')
            $ok = function_exists('imagecreatefromavif') ? convertToWebp($full, $candidateFull, $quality) : heifToWebp($full, $candidateFull, $quality);
        else
            $ok = convertToWebp($full, $candidateFull, $quality);

        @unlink($snapshot['input']);
        clearstatcache();
        if (!$ok || !is_file($candidateFull) || @filesize($candidateFull) <= 0) {
            @unlink($candidateFull);
            return 'nie udało się zamienić na WebP';
        }
        $sizeTo = (int)filesize($candidateFull);
        if ($sizeTo >= $sizeFrom) {
            @unlink($candidateFull);
            return 'WebP wyszedł większy (' . formatSize($sizeFrom) . ' → ' . formatSize($sizeTo) . ')';
        }

        $saved = updateDataFile('webp-previews.json', function ($data) use ($token, $rel, $candidate, $quality, $sizeFrom, $sizeTo, $user, $snapshot) {
            $data[$token] = [
                'rel' => $rel,
                'fingerprint' => $snapshot['fingerprint'],
                'candidate' => $candidate,
                'by' => (string)$user['id'],
                'quality' => (int)$quality,
                'sizeFrom' => $sizeFrom,
                'sizeTo' => $sizeTo,
                'created' => time()
            ];
            return $data;
        });

        if (!$saved) {
            @unlink($candidateFull);
            return 'nie udało się zapisać podglądu';
        }
        return [
            'token' => $token,
            'name' => basename($rel),
            'original' => fileUrl($rel),
            'preview' => '?preview=' . $token,
            'info' => basename($rel) . ' · jakość ' . (int)$quality . ' · ' . formatSize($sizeFrom) . ' → ' . formatSize($sizeTo)
        ];
    }

    // only what the admin accepts takes the original's place
    function acceptWebpPreview($base, $token, $user)
    {
        $preview = webpPreviews()[(string)$token] ?? null;
        if (!is_array($preview) || (string)($preview['by'] ?? '') !== (string)$user['id'])
            return 'podgląd wygasł, spróbuj jeszcze raz';
        $file = resolvePath($base, $preview['rel'], false);
        $candidateFull = webpPreviewDir() . '/' . $preview['candidate'];
        if (!$file || !is_file($candidateFull))
            return 'podgląd wygasł, spróbuj jeszcze raz';
        list($full, $rel) = $file;

        $name = basename($rel);
        $dir = dirname($full);
        $target = extensionOf($name) === 'webp' ? $name : freeName($dir, pathinfo($name, PATHINFO_FILENAME) . '.webp');
        $targetFull = $dir . '/' . $target;
        $targetRel = ltrim((dirname($rel) === '.' ? '' : dirname($rel)) . '/' . $target, '/');

        $ok = swapInConverted($full, $rel, $candidateFull, $targetFull, $targetRel,
            (int)filesize($full), (int)filesize($candidateFull), 'WebP', (string)($user['name'] ?? ''), false, $why, $preview['fingerprint'] ?? null, $user['id']);
        if (!$ok)
            return 'nie udało się zapisać WebP' . ($why !== null ? ': ' . $why : '');
        dropWebpPreview((string)$token);

        return ['target' => basename($targetRel)];
    }

    function rejectWebpPreview($token, $user)
    {
        $preview = webpPreviews()[(string)$token] ?? null;
        if (!is_array($preview) || (string)($preview['by'] ?? '') !== (string)$user['id'])
            return false;
        @unlink(webpPreviewDir() . '/' . $preview['candidate']);
        dropWebpPreview((string)$token);

        return true;
    }

    function dropWebpPreview($token)
    {
        updateDataFile('webp-previews.json', function ($data) use ($token) {
            unset($data[$token]);
            return $data;
        });
    }
