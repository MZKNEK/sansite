<?php
    // The changes asked for from the gallery's page (POST to i/): who may make
    // them, and the answer. Part of the gallery, loaded by inc/gallery.php.

    // a change went fine: it goes to the history, then the page gets the answer,
    // where the folders of the accounts go by the nick alone (displayPath())
    function done($action, $history, $message = null, $extra = [])
    {
        addHistory($action, $history);
        reply(true, $message ?? preg_replace('~i/' . USERS_DIR . '/\d+-(?=[^/])~', 'i/' . USERS_DIR . '/', $history), 200, $extra);
    }

    function reply($ok, $message, $status = 200, $extra = [])
    {
        http_response_code($ok ? 200 : $status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $ok, 'message' => $message] + $extra);
        exit;
    }

    // the items picked in the page, each as [full path, rel]; the top folder itself
    // cannot be one, nor what the visitor does not see, and when none of them is
    // in the gallery nothing is done
    function postedItems($base)
    {
        $items = [];
        foreach ((array)($_POST['items'] ?? []) as $rel) {
            $item = resolveAny($base, $rel);
            if ($item && $item[1] !== '' && galleryCanSee($base, $item[1]))
                $items[] = $item;
        }

        if (!$items)
            reply(false, 'Nie znaleziono zaznaczonych elementów, odśwież stronę.', 404);

        return $items;
    }

    function handlePost($base)
    {
        // a request bigger than post_max_size arrives with no fields at all
        if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0)
            reply(false, 'Plik jest za duży (limit serwera: ' . formatSize(uploadLimit()) . ').', 413);

        if (!authConfigured())
            reply(false, 'Logowanie nie jest włączone na serwerze.', 403);

        $action = (string)($_POST['action'] ?? '');

        if (!siteUser())
            reply(false, 'Trzeba się zalogować.', 401);
        if (!checkCsrf())
            reply(false, 'Sesja wygasła, odśwież stronę.', 403);
        if ($action === 'upload' && is_string($_FILES['file']['name'] ?? null)
            && in_array(extensionOf($_FILES['file']['name']), CONVERT_IMAGE_TYPES, true))
            $uploadEncoder = galleryLock('encoder');

        // Serializes writers, never ZIP. Preview preparation releases its own
        // short lock before running the encoder.
        if (!in_array($action, ['zip', 'webp-preview', 'duplicates', 'request-access'], true))
            $mutationRelease = galleryLock();
        if (!in_array($action, ['zip', 'webp-preview', 'duplicates', 'request-access'], true))
            $policyRelease = galleryLock('policy', false);

        if ($action === 'request-access')
            handleAccessRequest('gallery', galleryCanView(), $_POST['back'] ?? '');

        // a card from Skalpelator or USkalpelator: into the folder of the
        // account, in the folder of the croppers there, and from then on an
        // upload like any other, with the same limits and the change to WebP
        if ($action === 'cropper') {
            $_POST['dir'] = publicRel(cropperFolder($base));
            $action = 'upload';
        }

        // an account with a folder of its own changes only what is in it, and
        // there only adds, renames, turns and deletes pictures
        if (!galleryIsAdmin()) {
            $own = ownFolder($base);
            if ($own === null)
                reply(false, 'To konto nie może zarządzać galerią.', 403);
            if (!in_array($action, ['upload', 'mkdir', 'delete', 'rotate', 'rename', 'move', 'duplicates', 'restore', 'trash-delete', 'media-clear', 'zip'], true))
                reply(false, 'W swoim folderze możesz tworzyć foldery, dodawać zdjęcia, zmieniać ich nazwy, obracać je i usuwać.', 403);
            // new folders and uploads go only inside its own folder, subfolders included
            if (in_array($action, ['upload', 'mkdir'], true)) {
                $dir = resolvePath($base, $_POST['dir'] ?? '', true);
                if (!$dir || !inFolder($dir[1], $own))
                    reply(false, $action === 'mkdir' ? 'Możesz tworzyć foldery tylko w swoim folderze.' : 'Możesz dodawać zdjęcia tylko do swojego folderu.', 403);
            }
            if (isset($_POST['items']))
                foreach (postedItems($base) as $item)
                    if (strpos($item[1], $own . '/') !== 0)
                        reply(false, 'Możesz zmieniać tylko pliki w swoim folderze.', 403);
        }

        // the folder of an account goes by its ID in the name, so it keeps it and stays where it is
        if (in_array($action, ['rename', 'move'], true) && isset($_POST['items']))
            foreach (postedItems($base) as $item)
                if (dirname($item[1]) === USERS_DIR || $item[1] === USERS_DIR)
                    reply(false, 'Folderu konta (' . displayPath($item[1]) . ') nie da się przenieść ani zmienić mu nazwy: nazwa idzie za kontem.', 400);

        switch ($action) {

            // the picked items as one ZIP; the non-admin check above has already
            // made sure they are all in the account's own folder
            case 'zip':
                if (!galleryCanView() && !galleryHomes($base))
                    reply(false, 'To konto nie ma dostępu do galerii.', 403);
                $dir = resolvePath($base, $_POST['dir'] ?? '', true);
                sendZip(galleryZipRoots($base, postedItems($base)), (!$dir || $dir[1] === '' ? 'galeria' : displayName($dir[1])) . ' - wybrane.zip',
                    localBack($_POST['back'] ?? ''));

            case 'upload':
                uploadFile($base);

            case 'mkdir':
                $dir = resolvePath($base, $_POST['dir'] ?? '', true);
                $name = trim((string)($_POST['name'] ?? ''));
                if (!$dir)
                    reply(false, 'Nie ma takiego folderu.', 404);
                if ($error = nameError($name, false))
                    reply(false, $error, 400);
                if (file_exists($dir[0] . '/' . $name))
                    reply(false, 'Coś o nazwie ' . $name . ' już tu jest.', 409);
                if (!@mkdir($dir[0] . '/' . $name, 0755))
                    reply(false, 'Nie udało się utworzyć folderu.', 500);
                done('mkdir', 'Utworzono folder ' . galleryPath(ltrim($dir[1] . '/' . $name, '/')) . '.');

            case 'delete':
                $items = postedItems($base);
                $trashed = [];
                $failed = [];
                $refused = false;
                foreach ($items as $item) {
                    if (moveToTrash($item[0], $item[1], $why)) {
                        $trashed[] = galleryPath($item[1]);
                        dropHashes($item[1]);
                    } else {
                        $failed[] = basename($item[1]) . ($why !== null ? ' (' . $why . ')' : '');
                        $refused = $refused || $why !== null;
                    }
                }

                if ($trashed)
                    addHistory('delete', 'Do kosza: ' . implode(', ', $trashed) . '.');
                $message = 'Przeniesiono do kosza ' . countLabel(count($trashed)) . ' (na ' . TRASH_DAYS . ' dni, można je przywrócić w koszu).';
                if ($failed)
                    reply(false, $message . ' Nie udało się: ' . implode(', ', $failed) . '.', $refused ? 409 : 500);
                reply(true, $message);

            case 'rotate':
                // GD turns counter-clockwise
                $left = ($_POST['direction'] ?? '') === 'left';
                $rotated = [];
                $skipped = [];
                foreach (postedItems($base) as $item) {
                    if (is_file($item[0]) && rotateImage($item[0], $left ? 90 : -90, $base, $item[1]))
                        $rotated[] = galleryPath($item[1]);
                    else
                        $skipped[] = basename($item[1]) . (is_file($item[0]) && isImage($item[0]) && ($why = imageLimitError($item[0])) !== null ? ' (' . $why . ')' : '');
                }

                if ($rotated)
                    addHistory('rotate', 'Obrócono w ' . ($left ? 'lewo' : 'prawo') . ': ' . implode(', ', $rotated) . '.');
                $message = 'Obrócono ' . countLabel(count($rotated)) . '.';
                if ($skipped)
                    reply(count($rotated) > 0, $message . ' Pominięto (obracać można obrazki PNG, JPG i WebP bez animacji): ' . implode(', ', $skipped) . '.', 400);
                reply(true, $message);

            // the manual change to WebP: the server makes the result aside and the
            // page shows it next to the original; only webp-accept puts it in place
            case 'webp-preview':
                $items = postedItems($base);
                if (count($items) !== 1)
                    reply(false, 'Zamieniać z podglądem można po jednym pliku naraz.', 400);
                $quality = max(WEBP_QUALITY_MIN, min(WEBP_QUALITY_MAX, (int)($_POST['quality'] ?? WEBP_QUALITY_DEFAULT)));
                list($full, $rel) = $items[0];
                $preview = startWebpPreview($full, $rel, $quality, siteUser());
                if (is_string($preview))
                    reply(true, '', 200, ['skipped' => basename($rel) . ' (' . $preview . ')']);
                reply(true, '', 200, ['preview' => $preview]);

            case 'webp-accept':
                $result = acceptWebpPreview($base, (string)($_POST['token'] ?? ''), siteUser());
                if (is_string($result))
                    reply(false, $result, 500);
                reply(true, 'Zapisano WebP ' . basename($result['target']) . '.');

            case 'webp-reject':
                rejectWebpPreview((string)($_POST['token'] ?? ''), siteUser());
                reply(true, 'Odrzucono wynik, oryginał zostaje.');

            case 'duplicates':
                $hash = strtolower((string)($_POST['hash'] ?? ''));
                if (!preg_match('/^[0-9a-f]{64}$/', $hash))
                    reply(false, 'Zły skrót pliku.', 400);
                // only the folder the page is in (an account with a folder of its
                // own: that folder), so the check does not walk the whole gallery
                $dir = resolvePath($base, $_POST['dir'] ?? '', true);
                // and never outside it for that account, or it would learn of
                // files in folders it may not see (the private one, others')
                if ($dir && !galleryIsAdmin() && !inFolder($dir[1], $own))
                    $dir = null;
                $from = $dir ? $dir[1] : (galleryIsAdmin() ? '' : ownFolder($base));
                reply(true, '', 200, ['matches' => findDuplicates($base, (int)($_POST['size'] ?? -1), $hash, $from)]);

            // clears the account's finished conversion jobs without waiting for
            // them to age out of the gallery panel
            case 'media-clear':
                $removed = clearFinishedMediaJobs((string)siteUser()['id']);
                setFlash($removed
                    ? 'Wyczyszczono ' . countLabel($removed) . ' zakończonych zadań konwersji.'
                    : 'Nie ma czego czyścić.');
                header('Location: ' . localBack($_POST['back'] ?? ''), true, 303);
                exit;

            // the trash of the account's own folder, in the gallery (the panel
            // does the same for everything); a non-admin only its own entries
            case 'restore':
                $item = trashItems()[(string)($_POST['item'] ?? '')] ?? null;
                if (!$item)
                    reply(false, 'Tego już nie ma w koszu, odśwież stronę.', 404);
                if (!galleryIsAdmin() && !inUserFolder($item['from'] ?? '', siteUser()['id']))
                    reply(false, 'Możesz przywracać tylko rzeczy ze swojego folderu.', 403);
                $where = restoreFromTrash($base, (string)$_POST['item'], $error);
                if ($where === null)
                    reply(false, $error, 409);
                done('restore', 'Przywrócono z kosza ' . galleryPath($where) . '.');

            case 'trash-delete':
                $item = trashItems()[(string)($_POST['item'] ?? '')] ?? null;
                if (!$item)
                    reply(false, 'Tego już nie ma w koszu, odśwież stronę.', 404);
                // a gallery admin drops it for good; an account with a folder of
                // its own only takes the entry out of its own trash, the file
                // stays and the panel can still bring it back
                if (galleryIsAdmin()) {
                    if (!deleteFromTrash((string)$_POST['item']))
                        reply(false, 'Nie udało się usunąć.', 500);
                    done('purge', 'Usunięto na zawsze ' . galleryPath($item['from']) . ' (z kosza).');
                }
                if (!inUserFolder($item['from'] ?? '', siteUser()['id']))
                    reply(false, 'Możesz usuwać tylko rzeczy ze swojego folderu.', 403);
                if (!hideTrashItem((string)$_POST['item']))
                    reply(false, 'Nie udało się usunąć z kosza.', 500);
                done('trash-hide', 'Usunięto z kosza ' . galleryPath($item['from']) . ' (plik zostaje do ' . TRASH_DAYS . ' dni, administrator może go przywrócić).');

            case 'share':
                $dir = resolvePath($base, $_POST['dir'] ?? '', true);
                $days = (int)($_POST['days'] ?? -1);
                if (!$dir)
                    reply(false, 'Nie ma takiego folderu.', 404);
                if (!galleryCanSee($base, $dir[1]))
                    reply(false, 'Nie masz dostępu do tego folderu.', 403);
                if ($dir[1] === '' || $dir[1] === USERS_DIR)
                    reply(false, 'Udostępnić można jeden folder, nie całą galerię ani wszystkie foldery kont.', 400);
                if (!in_array($days, SHARE_DAYS, true))
                    reply(false, 'Zły czas ważności linku.', 400);
                $token = createShare($dir[1], $days, siteUser());
                if ($token === null)
                    reply(false, 'Nie udało się zapisać linku.', 500);
                addHistory('share', 'Udostępniono linkiem ' . galleryPath($dir[1]) . ($days ? ' na ' . $days . ' ' . plural($days, 'dzień', 'dni', 'dni') : ' bez końca') . '.');
                reply(true, 'Link gotowy.', 200, ['token' => $token]);

            case 'rename':
                $items = postedItems($base);
                if (count($items) !== 1)
                    reply(false, 'Zmienić nazwę można tylko jednemu elementowi naraz.', 400);

                list($full, $rel) = $items[0];
                $isFile = is_file($full);
                $old = basename($rel);
                $name = trim((string)($_POST['name'] ?? ''));
                if ($error = nameError($name, $isFile))
                    reply(false, $error, 400);
                // the name does not change what the file is, so the extension stays
                if ($isFile && extensionOf($name) !== extensionOf($old))
                    reply(false, 'Rozszerzenie musi zostać .' . extensionOf($old) . ', zmiana nazwy nie zmienia formatu pliku.', 400);
                if ($name === $old)
                    reply(true, 'Nazwa się nie zmieniła.');
                // A case-only rename may refer to the same directory entry on
                // Windows, but a separate existing file on Linux must stay intact.
                if (file_exists(dirname($full) . '/' . $name)
                    && realpath(dirname($full) . '/' . $name) !== realpath($full))
                    reply(false, 'Coś o nazwie ' . $name . ' już tu jest.', 409);
                if (!@rename($full, dirname($full) . '/' . $name))
                    reply(false, 'Nie udało się zmienić nazwy.', 500);
                moveHashes($rel, ltrim((dirname($rel) === '.' ? '' : dirname($rel)) . '/' . $name, '/'));
                followMovedLinks($rel, ltrim((dirname($rel) === '.' ? '' : dirname($rel)) . '/' . $name, '/'));

                done('rename', 'Zmieniono nazwę ' . galleryPath($rel) . ' na ' . $name . '.');

            case 'move':
                $target = resolvePath($base, $_POST['target'] ?? '', true);
                if (!$target)
                    reply(false, 'Nie ma takiego folderu docelowego.', 404);
                if (!galleryIsAdmin() && !inFolder($target[1], $own))
                    reply(false, 'Możesz przenosić tylko pomiędzy folderami swojej galerii.', 403);

                // into the folder of an account only within its limits, as for
                // its own uploads; held until the request ends
                $owner = folderOwner($target[1]);
                if ($owner !== null && accountLock($owner) === null)
                    reply(false, 'Folder konta jest teraz zajęty, spróbuj za chwilę.', 503);

                $moved = [];
                $problems = [];
                foreach (postedItems($base) as $item) {
                    $name = basename($item[1]);
                    $room = null;
                    if ($owner !== null && folderOwner($item[1]) !== $owner) {
                        [$count, $bytes, $biggest] = treeUse($item[0]);
                        $room = accountRoomError($base, $owner, $count, $bytes, $biggest);
                    }
                    if ($target[0] === $item[0] || strpos($target[0] . '/', $item[0] . '/') === 0) {
                        $problems[] = $name . ' (folderu nie da się przenieść do niego samego)';
                    } else if (dirname($item[0]) === $target[0]) {
                        $problems[] = $name . ' (już tam jest)';
                    } else if (file_exists($target[0] . '/' . $name)) {
                        $problems[] = $name . ' (w folderze docelowym jest już coś o tej nazwie)';
                    } else if ($room !== null) {
                        $problems[] = $name . ' (' . $room . ')';
                    } else if (!@rename($item[0], $target[0] . '/' . $name)) {
                        $problems[] = $name . ' (nie udało się)';
                    } else {
                        $moved[] = galleryPath($item[1]);
                        moveHashes($item[1], ltrim($target[1] . '/' . $name, '/'));
                        followMovedLinks($item[1], ltrim($target[1] . '/' . $name, '/'));
                    }
                }

                $where = galleryPath($target[1]);
                if ($moved)
                    addHistory('move', 'Przeniesiono do ' . $where . ': ' . implode(', ', $moved) . '.');
                if ($problems)
                    reply(count($moved) > 0, 'Przeniesiono ' . countLabel(count($moved)) . ' do ' . $where . '. Pominięto: ' . implode(', ', $problems) . '.', 409);
                reply(true, 'Przeniesiono ' . countLabel(count($moved)) . ' do ' . $where . '.');
        }

        reply(false, 'Nieznana akcja.', 400);
    }
