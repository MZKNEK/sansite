<?php
    // Adding files: an upload, and a card saved by the croppers. Part of the
    // gallery, loaded by inc/gallery.php.

    // The folder the croppers save the cards into, inside the folder of the
    // account (CROPPER_DIR), made the first time; an account without a folder
    // of its own gets the answer that it has none.
    function cropperFolder($base)
    {
        $own = ownFolder($base);
        if ($own === null)
            reply(false, 'To konto nie ma swojego folderu w galerii (trzeba mieć rolę na serwerze bota).', 403);

        $rel = $own . '/' . CROPPER_DIR;
        if (!is_dir($base . '/' . $rel)) {
            if (!@mkdir($base . '/' . $rel, 0755) && !is_dir($base . '/' . $rel))
                reply(false, 'Nie udało się utworzyć folderu ' . CROPPER_DIR . ' w galerii.', 500);
            addHistory('mkdir', 'Utworzono folder ' . galleryPath($rel) . '.');
        }

        return $rel;
    }

    // where an uploaded file can be opened and its folder seen, for the page
    // that sent it (the croppers show the link, which also works without a login)
    function uploadedLinks($rel)
    {
        $dir = dirname($rel) === '.' ? '' : dirname($rel);

        return ['url' => siteRoot() . 'i/' . fileUrl($rel), 'folder' => siteRoot() . 'i/' . ($dir === '' ? '' : folderUrl($dir))];
    }

    // one file per request, so every file gets its own progress and its own error
    function uploadFile($base)
    {
        $dir = resolvePath($base, $_POST['dir'] ?? '', true);
        if (!$dir)
            reply(false, 'Nie ma takiego folderu.', 404);

        $file = $_FILES['file'] ?? null;
        if (!$file || is_array($file['name']))
            reply(false, 'Nie wysłano pliku.', 400);
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE)
            reply(false, $file['name'] . ': plik jest za duży (limit serwera: ' . formatSize(uploadLimit()) . ').', 413);
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']))
            reply(false, $file['name'] . ': wysyłanie się nie udało.', 400);

        $name = trim(basename(str_replace('\\', '/', (string)$file['name'])));
        if ($error = nameError($name, true))
            reply(false, $name . ': ' . $error, 400);

        // the extension alone is not enough, the content has to match it
        if (isVideo($name) ? !isVideoContent($name, $file['tmp_name']) : !isImageContent($name, $file['tmp_name']))
            reply(false, $name . ': zawartość nie pasuje do typu pliku.', 400);
        // nor the size of the file: a small one can hold a huge picture, which
        // is refused before anything is kept or decoded
        if (!isVideo($name) && ($why = imageLimitError($file['tmp_name'])) !== null)
            reply(false, $name . ': ' . $why . '.', 413);

        // an account with a folder of its own: only pictures, within its limits,
        // counted over its whole folder with the subfolders; the folder is held
        // until the request ends, so two uploads at once do not both fit
        $owner = folderOwner($dir[1]);
        $own = $owner !== null;
        if ($own) {
            if (accountLock($owner) === null)
                reply(false, $name . ': folder jest teraz zajęty, spróbuj za chwilę.', 503);
            if (isVideo($name) && !galleryIsAdmin())
                reply(false, $name . ': do swojego folderu można dodawać tylko zdjęcia.', 400);
            $inputBytes = in_array(extensionOf($name), CONVERT_IMAGE_TYPES, true) ? 0 : (int)$file['size'];
            if (($why = accountQuotaError($base, $owner, 1, $inputBytes, (int)$file['size'], true)) !== null)
                reply(false, $name . ': ' . $why, 413);
        }

        // "change to WebP" ticked in the page, always in the folder of an account;
        // the original stays when the WebP does not come out smaller. The change
        // runs in the background (below), except a HEIC or HEIF, which is written
        // as WebP now: a browser cannot show it otherwise.
        $note = '';
        $ext = extensionOf($name);
        $wantConvert = $own || ($_POST['webp'] ?? '') === '1';
        // an account's own folder insists on WebP; without it the upload is refused
        if ($own && in_array($ext, WEBP_SOURCE_TYPES, true) && !canConvertImageToWebp($ext))
            reply(false, $name . ': ' . ($ext === 'gif' ? 'serwer nie umie teraz zamienić GIF-a na WebP, dodaj PNG, JPG albo WebP.' : 'serwer nie umie teraz zapisać WebP, spróbuj później.'), 503);

        // a HEIC or HEIF cannot be counted on to show in a browser, so it is
        // always written as WebP; when that cannot be done the upload is refused
        if (in_array($ext, CONVERT_IMAGE_TYPES, true)) {
            if (!canConvertHeif())
                reply(false, $name . ': serwer nie umie zamienić HEIC/HEIF na WebP (brak imagemagick lub ffmpeg). Dodaj plik jako JPG, PNG albo WebP.', 503);
            $target = freeName($dir[0], pathinfo($name, PATHINFO_FILENAME) . '.webp');
            $path = $dir[0] . '/.upload-' . bin2hex(random_bytes(12)) . '.webp';
            if (!heifToWebp($file['tmp_name'], $path)) {
                @unlink($path);
                reply(false, $name . ': nie udało się zamienić na WebP. Dodaj plik jako JPG, PNG albo WebP.', 500);
            }
            @chmod($path, 0644);
            if ($own && ($why = accountQuotaError($base, $owner, 1, filesize($path), filesize($path), true)) !== null) {
                @unlink($path);
                reply(false, $name . ': ' . $why, 413);
            }
            $final = $dir[0] . '/' . $target;
            if (!@rename($path, $final)) {
                @unlink($path);
                reply(false, 'Nie udało się zapisać wyniku.', 500);
            }
            $path = $final;
            $sizes = ' (' . formatSize($file['size']) . ' → ' . formatSize(filesize($path)) . ')';
            done('upload', 'Dodano ' . galleryPath(ltrim($dir[1] . '/' . $target, '/')) . ', zamienione z ' . $name . $sizes . '.',
                'Dodano ' . $name . ' jako ' . $target . $sizes . '.', uploadedLinks(ltrim($dir[1] . '/' . $target, '/')));
        }

        $target = freeName($dir[0], $name);
        if (!@move_uploaded_file($file['tmp_name'], $dir[0] . '/' . $target))
            reply(false, $name . ': nie udało się zapisać pliku.', 500);
        @chmod($dir[0] . '/' . $target, 0644);
        $stripped = !isVideo($name) && stripMetadata($dir[0] . '/' . $target);
        clearstatcache();
        if ($own && ($why = accountQuotaError($base, $owner, 0, 0, filesize($dir[0] . '/' . $target), true)) !== null) {
            @unlink($dir[0] . '/' . $target);
            reply(false, $name . ': ' . $why, 413);
        }
        $rel = ltrim($dir[1] . '/' . $target, '/');

        // a picture or film with "change" on waits its turn as WebP or WebM: the
        // file is saved and the request answers at once, the change runs in the
        // background (a worker started here at once, else inc/check-bot.php from
        // cron) and the gallery shows how it is going
        $convertible = $ext === 'avif' || in_array($ext, WEBP_SOURCE_TYPES, true) || in_array($ext, VIDEO_SOURCE_TYPES, true);
        if ($wantConvert && $convertible) {
            $kind = in_array($ext, VIDEO_SOURCE_TYPES, true) ? 'webm' : 'webp';
            $format = $kind === 'webm' ? 'WebM' : 'WebP';
            if (!($kind === 'webm' ? canConvertVideo() : canConvertImageToWebp($ext))) {
                $note = ' Serwer nie umie teraz zamienić na ' . $format . ', zostawiono oryginał.';
            } else {
                $user = siteUser();
                $queued = addMediaJob($rel, $user['id'] ?? '', $user['name'] ?? '', filesize($dir[0] . '/' . $target), $kind);
                if ($queued !== null)
                    startMediaWorker();
                $note = $queued === null ? ' Plik zapisano, ale nie udało się dodać konwersji do kolejki.'
                    : ($kind === 'webm' ? ' Film' : ' Grafika') . ' zostanie zamieniona na ' . $format . ' w tle; status zobaczysz w galerii.';
            }
        }

        done('upload', 'Dodano ' . galleryPath($rel) . '.',
            ($target === $name ? 'Dodano ' . $name . '.' : 'Dodano ' . $name . ' jako ' . $target . ' (nazwa była zajęta).')
            . ($stripped ? ' Usunięto metadane (np. miejsce i czas zrobienia).' : '') . $note, uploadedLinks($rel));
    }
