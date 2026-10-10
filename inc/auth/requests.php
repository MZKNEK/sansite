<?php
    // Requests for access from an account without it. Part of the login, loaded
    // by inc/auth.php.

    // ---- Requests for access ----------------------------------------------------
    // ['gallery' => [id => ['name', 'note', 'time']], 'api' => [...]]

    // the request of an account still waiting, or null
    function pendingRequest($for, $id)
    {
        return readData('requests')[$for][(string)$id] ?? null;
    }

    // a place on this site to go back to after a form: "?p=..." or "./"
    function localBack($back)
    {
        $back = (string)$back;

        return preg_match('/^(\?|\.\/)/', $back) ? $back : './';
    }

    // Saves the request of the logged-in account sent from a locked page and goes
    // back there with a message; one request at a time, again only after an hour.
    function handleAccessRequest($for, $allowed, $back)
    {
        $user = siteUser();

        if ($allowed) {
            setFlash('To konto ma już dostęp do ' . REQUEST_LABELS[$for] . '.');
        } else {
            $note = cutText(trim((string)($_POST['note'] ?? '')), REQUEST_NOTE_LENGTH);
            // whether one waits already is decided on the file as it is now
            $saved = updateData('requests', function (&$requests) use ($for, $user, $note) {
                $pending = $requests[$for][$user['id']] ?? null;
                if ($pending && $pending['time'] > time() - 3600)
                    return false;
                $requests[$for][$user['id']] = ['name' => $user['name'], 'note' => $note, 'time' => time()];
                return true;
            }, $added);
            if ($saved && !$added) {
                setFlash('Prośba już czeka na administratora.');
            } else if ($saved) {
                addHistory('request', 'Prośba o dostęp do ' . REQUEST_LABELS[$for] . ($note === '' ? '.' : ': „' . $note . '”.'));
                setFlash('Wysłano prośbę o dostęp. Administrator zobaczy ją w panelu.');
            } else {
                setFlash('Nie udało się zapisać prośby, spróbuj później.');
            }
        }

        header('Location: ' . localBack($back), true, 303);
        exit;
    }
