<?php
    // The changes asked for from the panel (POST to admin/): one function with
    // every action, called by admin/index.php only after it has checked the
    // login, the rights and the CSRF token. Each action answers and ends the
    // request (done(), reply()). Part of the panel.

    function panelAction($action, $user, $logins, $list, $id, $galleryDir, $thumbsDir)
    {
        switch ($action) {
            case 'gallery-limit-defaults':
            case 'account-gallery-limits':
            case 'account-gallery-limits-reset':
            case 'upload-limit':
                $values = [];
                $reset = [];
                foreach (galleryQuotaFields() as $key => $field) {
                    if ($action === 'account-gallery-limits-reset') {
                        $reset[] = $key;
                    } elseif ($action === 'upload-limit') {
                        if ($key === 'files') $values[$key] = $_POST['limit'] ?? null;
                    } elseif ($action === 'gallery-limit-defaults') {
                        $values[$key] = $_POST[$key] ?? null;
                    } else {
                        $mode = $_POST['mode_' . $key] ?? null;
                        if ($mode === 'inherit') $reset[] = $key;
                        elseif ($mode === 'override') $values[$key] = $_POST[$key] ?? null;
                        else reply(false, 'Wybierz dziedziczenie lub nadpisanie każdego pola.', 400);
                    }
                }
                $allowed = ['action', 'csrf', 'id', 'revision', 'limit'];
                foreach (array_keys(galleryQuotaFields()) as $key) { $allowed[] = $key; $allowed[] = 'mode_' . $key; }
                if (array_diff(array_keys($_POST), $allowed)) reply(false, 'Nieznane pole formularza.', 400);
                try {
                    $history = saveGalleryLimits($action === 'gallery-limit-defaults' ? null : $id, $values,
                        $_POST['revision'] ?? null, (string)$user['id'], $reset);
                    reply(true, $history ? 'Zapisano limity.' : 'Zapisano limity, ale nie udało się zapisać historii.');
                } catch (InvalidArgumentException $error) { reply(false, $error->getMessage(), 400); }
                catch (UnexpectedValueException $error) { reply(false, $error->getMessage(), 409); }
                break;
            case 'grant':
                if (!isset(ACCESS_LISTS[$list]))
                    reply(false, 'Nieznana lista.', 400);
                if (!preg_match('/^\d{17,20}$/', $id))
                    reply(false, 'ID konta Discord to 17-20 cyfr.', 400);

                $config = configList(ACCESS_LISTS[$list]);
                if ($config !== true && in_array($id, $config, true))
                    reply(false, 'To konto jest już na tej liście w konfiguracji.', 409);

                $entry = [
                    'note' => cutText(trim((string)($_POST['note'] ?? '')), 60),
                    'added' => time(),
                    'by' => $user['id']
                ];
                $saved = updateData('access', function (&$access) use ($list, $id, $entry) {
                    if (isset($access[$list][$id]))
                        return false;
                    $access[$list][$id] = $entry;
                    return true;
                }, $added);
                if (!$saved)
                    reply(false, dataError(), 500);
                if (!$added)
                    reply(false, 'To konto jest już na tej liście.', 409);
                // a request of this account is answered now; the right is written
                // first, and a request a failure here leaves goes at the next look
                // at the panel (the requests of accounts let in are dropped there)
                $for = $list === 'apiViewers' ? 'api' : 'gallery';
                updateData('requests', function (&$requests) use ($for, $id) {
                    unset($requests[$for][$id]);
                });
                done('access', 'Dodano ' . accountLabel($id, $logins) . ': ' . LIST_LABELS[$list] . '.');

            case 'revoke':
                if (!isset(ACCESS_LISTS[$list]))
                    reply(false, 'Nie ma takiego wpisu (wpisy z konfiguracji zmienia się w inc/config.php).', 404);
                $saved = updateData('access', function (&$access) use ($list, $id) {
                    if (!isset($access[$list][$id]))
                        return false;
                    unset($access[$list][$id]);
                    return true;
                }, $found);
                if (!$saved)
                    reply(false, dataError(), 500);
                if (!$found)
                    reply(false, 'Nie ma takiego wpisu (wpisy z konfiguracji zmienia się w inc/config.php).', 404);
                done('access', 'Usunięto ' . accountLabel($id, $logins) . ': ' . LIST_LABELS[$list] . '.');

            case 'share-revoke':
                $token = (string)($_POST['token'] ?? '');
                $saved = updateData('shares', function (&$shares) use ($token) {
                    $share = $shares[$token] ?? null;
                    unset($shares[$token]);
                    return $share;
                }, $share);
                if (!$saved)
                    reply(false, dataError(), 500);
                if (!isset($share['rel']))
                    reply(false, 'Tego linku już nie ma, odśwież stronę.', 404);
                $rel = (resolvePath($galleryDir, $share['rel'], true) ?? [null, $share['rel']])[1];
                done('share', 'Wyłączono link do ' . galleryPath($rel) . '.');

            case 'test-rights':
                $panel = (string)($_POST['panel'] ?? '');
                $gallery = (string)($_POST['gallery'] ?? '');
                $private = (string)($_POST['private'] ?? '');
                $api = (string)($_POST['api'] ?? '');
                $role = (string)($_POST['role'] ?? '');
                $minutes = (int)($_POST['minutes'] ?? 0);
                if (!in_array($panel, ['', '0', '1'], true) || ($gallery !== '' && !isset(TEST_GALLERY[$gallery])) || !in_array($private, ['', '0', '1'], true)
                        || !in_array($api, ['', '0', '1'], true) || ($role !== '' && $role !== TEST_ROLE_OUT && !isset(BOT_ROLES[$role])) || !in_array($minutes, TEST_MINUTES, true))
                    reply(false, 'Złe ustawienie podglądu, odśwież stronę.', 400);
                $rights = array_filter([
                    'panel' => $panel === '' ? null : $panel === '1',
                    'gallery' => $gallery === '' ? null : $gallery,
                    'private' => $private === '' ? null : $private === '1',
                    'api' => $api === '' ? null : $api === '1',
                    'role' => $role === '' ? null : $role
                ], function ($value) { return $value !== null; });
                if (!$rights)
                    reply(false, 'Wybierz, co zmienić.', 400);
                setTestRights($rights, $minutes);
                done('test', 'Podgląd z innymi uprawnieniami na ' . $minutes . ' min: ' . testRightsText($rights) . '.',
                    'Podgląd włączony do ' . date('H:i', time() + $minutes * 60) . '. Pasek na dole każdej strony przywraca twoje uprawnienia.');

            case 'test-off':
                setTestRights(null);
                done('test', 'Koniec podglądu z innymi uprawnieniami.', 'Wróciły twoje prawdziwe uprawnienia.');

            case 'request-dismiss':
                $for = (string)($_POST['for'] ?? '');
                if (!isset(REQUEST_LABELS[$for]))
                    reply(false, 'Tej prośby już nie ma, odśwież stronę.', 404);
                $saved = updateData('requests', function (&$requests) use ($for, $id) {
                    if (!isset($requests[$for][$id]))
                        return false;
                    unset($requests[$for][$id]);
                    return true;
                }, $found);
                if (!$saved)
                    reply(false, dataError(), 500);
                if (!$found)
                    reply(false, 'Tej prośby już nie ma, odśwież stronę.', 404);
                done('access', 'Odrzucono prośbę o dostęp do ' . REQUEST_LABELS[$for] . ': ' . accountLabel($id, $logins) . '.');

            case 'backup':
                // a plain form: the answer is the ZIP itself, a problem comes back as a message
                $withGallery = ($_POST['gallery'] ?? '') === '1';
                // without the logins of the moment (sessions), which would only log people
                // in again, the thumbnails, which are made again by themselves, and the
                // availability checks of the last days, and the pictures of the cards,
                // which cron mirrors from the bot's repository again (inc/pw.php), and
                // the empty lock files (inc/storage.php)
                $roots = [[dataDir(), 'data', ['sessions', 'thumbs', 'diag', 'pw', 'locks']]];
                if ($withGallery)
                    $roots[] = [$galleryDir, 'i', ['index.php']];
                addHistory('backup', 'Pobrano kopię danych' . ($withGallery ? ' z galerią' : '') . '.');
                sendZip($roots, 'sanakan-kopia-' . date('Y-m-d-His') . '.zip', './', null);

            case 'logout-all':
                $now = time();
                if (!writeData('sessions', ['since' => $now]))
                    reply(false, dataError(), 500);
                // the one who asked for it stays logged in
                $_SESSION['login_time'] = $now;
                done('sessions', 'Wylogowano wszystkich z galerii i panelu (poza sobą).');

            case 'logout-account':
                if (!preg_match('/^\d{17,20}$/', $id))
                    reply(false, 'ID konta Discord to 17-20 cyfr.', 400);
                if ($id === $user['id'])
                    reply(false, 'Siebie wyloguj z menu konta w rogu strony.', 400);
                $saved = updateData('sessions', function (&$sessions) use ($id) {
                    $sessions['accounts'][$id] = time();
                });
                if (!$saved)
                    reply(false, dataError(), 500);
                done('sessions', 'Wylogowano ' . accountLabel($id, $logins) . ' na wszystkich urządzeniach.');

            case 'logout-session':
                $key = (string)($_POST['session'] ?? '');
                if (!preg_match('/^\d{17,20}$/', $id) || !preg_match('/^[0-9a-f]{20}$/', $key))
                    reply(false, 'Nieznana sesja, odśwież stronę.', 400);
                if ($key === sessionKey(session_id()))
                    reply(false, 'To ta sesja, z której teraz korzystasz. Wyloguj się z menu konta.', 400);
                if (!endDevice($id, $key))
                    reply(false, 'Tej sesji już nie ma, odśwież stronę.', 404);
                done('sessions', 'Wylogowano ' . accountLabel($id, $logins) . ' na jednym urządzeniu.');

            case 'notice':
                $text = cutText(trim(str_replace("\r", '', (string)($_POST['text'] ?? ''))), NOTICE_LENGTH);
                if ($text === '')
                    reply(false, 'Wpisz treść ogłoszenia.', 400);

                $from = noticeTime($_POST['from'] ?? '');
                $to = noticeTime($_POST['to'] ?? '');
                $maintenance = null;
                if (($_POST['maintenance'] ?? '') === '1') {
                    $from = $from ?? time();
                    if ($to === null)
                        reply(false, 'Przerwa techniczna potrzebuje godziny końca.', 400);
                    if ($to <= $from)
                        reply(false, 'Koniec przerwy musi być po jej początku.', 400);
                    $maintenance = ['from' => $from, 'to' => $to];
                } else if ($to !== null && $to <= time()) {
                    reply(false, 'Czas zniknięcia ogłoszenia już minął.', 400);
                }

                $notice = ['text' => $text, 'to' => $to, 'maintenance' => $maintenance, 'by' => $user['id'], 'set' => time()];
                botSaveNotice($notice);
                done('notice', 'Ustawiono ogłoszenie: ' . noticeText($notice));

            case 'notice-clear':
                botSaveNotice(null);
                done('notice', 'Usunięto ogłoszenie.');

            case 'quality':
                $qualities = [];
                $parts = [];
                foreach (['jpg' => 'JPG', 'png' => 'PNG', 'gif' => 'GIF', 'avif' => 'AVIF'] as $kind => $label) {
                    $value = max(WEBP_QUALITY_MIN, min(WEBP_QUALITY_MAX, (int)($_POST['quality' . ucfirst($kind)] ?? 0)));
                    $qualities['quality' . ucfirst($kind)] = $value;
                    $parts[] = $label . ' ' . $value;
                }
                if (!updateData('settings', function (&$settings) use ($qualities) { $settings = $qualities + $settings; }))
                    reply(false, dataError(), 500);
                done('quality', 'Zmieniono jakość konwersji na WebP: ' . implode(', ', $parts) . '.');

            case 'clear-thumbs':
                $removed = 0;
                foreach (glob($thumbsDir . '/*') ?: [] as $file)
                    if (is_file($file) && @unlink($file))
                        $removed++;
                done('cache', 'Usunięto ' . $removed . ' ' . plural($removed, 'miniaturę', 'miniatury', 'miniatur') . ' z cache; utworzą się od nowa przy oglądaniu galerii.');

            case 'refresh-spec':
                @unlink(botFile('swagger.json'));
                done('cache', 'Odświeżono specyfikację API; zostanie pobrana przy następnym wejściu do API.');

            case 'refresh-stats':
                panelStats($galleryDir, true);
                done('cache', 'Przeliczono galerię od nowa.');

            case 'restore':
                $item = trashItems()[(string)($_POST['item'] ?? '')] ?? null;
                if (!$item)
                    reply(false, 'Tego już nie ma w koszu, odśwież stronę.', 404);
                $where = restoreFromTrash($galleryDir, (string)$_POST['item'], $error);
                if ($where === null)
                    reply(false, $error, 409);
                done('restore', 'Przywrócono z kosza ' . galleryPath($where) . '.');

            case 'trash-delete':
                $item = trashItems()[(string)($_POST['item'] ?? '')] ?? null;
                if (!$item || !deleteFromTrash((string)$_POST['item']))
                    reply(false, 'Tego już nie ma w koszu, odśwież stronę.', 404);
                done('purge', 'Usunięto na zawsze ' . galleryPath($item['from']) . ' (z kosza).');

            case 'cf-block':
                if (!cloudflareConfigured())
                    reply(false, 'Blokowanie w Cloudflare nie jest ustawione.', 503);
                $ip = trim((string)($_POST['ip'] ?? ''));
                $target = cloudflareTarget($ip);
                if ($target === null || diagIsCloudflare($ip))
                    reply(false, 'Tego adresu nie da się zablokować: to adres lokalny albo Cloudflare.', 400);
                $protected = protectedAddress($ip, accountsAtAddress($ip, readData('addresses')), $logins);
                if ($protected !== null)
                    reply(false, 'Nie blokuję: ' . $protected . '.', 409);
                $note = trim(validText($_POST['note'] ?? ''));
                $error = cloudflareBlock($ip, 'Panel, ' . $user['name'] . ($note !== '' ? ': ' . $note : ''));
                if ($error !== null)
                    reply(false, $error, 502);
                done('cloudflare', 'Zablokowano w Cloudflare ' . $target . ($note !== '' ? ' (' . cutText($note, 80) . ')' : '') . '.');

            case 'cf-unblock':
                if (!cloudflareConfigured())
                    reply(false, 'Blokowanie w Cloudflare nie jest ustawione.', 503);
                $item = (string)($_POST['item'] ?? '');
                if (!preg_match('/^[0-9a-f]{32}$/', $item))
                    reply(false, 'Nieznana pozycja listy, odśwież stronę.', 400);
                $error = cloudflareUnblock($item);
                if ($error !== null)
                    reply(false, $error, 502);
                // not blocked again by the automatic blocking
                $ip = cutText(trim((string)($_POST['ip'] ?? '')), 60);
                if ($ip !== '')
                    autoBlockReleased($ip);
                done('cloudflare', 'Odblokowano w Cloudflare ' . $ip . '.');

            case 'auto-block':
                if (!cloudflareConfigured())
                    reply(false, 'Blokowanie w Cloudflare nie jest ustawione.', 503);
                $on = ($_POST['on'] ?? '') === '1';
                if (!updateData('settings', function (&$settings) use ($on) { $settings['autoBlock'] = $on; }))
                    reply(false, dataError(), 500);
                done('cloudflare', $on ? 'Włączono automatyczne blokowanie skanerów w Cloudflare.' : 'Wyłączono automatyczne blokowanie skanerów w Cloudflare.');

            case 'diag-clear':
                if (!updateData('settings', function (&$settings) { $settings['diagSince'] = time(); }))
                    reply(false, dataError(), 500);
                done('diag', 'Wyczyszczono awarie: karta dostępności liczy od ' . date('j.m.Y H:i') . '.');

            case 'trash-empty':
                $removed = 0;
                foreach (array_keys(trashItems()) as $item)
                    if (deleteFromTrash($item))
                        $removed++;
                done('purge', 'Opróżniono kosz: ' . countLabel($removed) . ' usunięte na zawsze.');
        }

        reply(false, 'Nieznana akcja.', 400);
    }
