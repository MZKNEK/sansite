<?php
    // Blocking addresses in Cloudflare from the admin panel, so their requests
    // never reach the server. The panel puts them on an IP list of the
    // Cloudflare account (CLOUDFLARE_LIST, e.g. sanakan_blokada), and one custom
    // rule of the zone blocks everything on it: (ip.src in $sanakan_blokada).
    // An IPv6 address is blocked with its whole /64, which one machine usually
    // has to itself. Needs in inc/config.php CLOUDFLARE_API_TOKEN (a token with
    // Account Filter Lists: Edit), CLOUDFLARE_ACCOUNT_ID and CLOUDFLARE_LIST.
    // Cloudflare changes a list in the background: the automatic blocking
    // (cron) waits a few seconds for a change to finish, while the panel does
    // not keep its request (and the admin's session) open for it, but notes
    // the change as waiting and asks about it in short requests of its own and
    // from cron (cloudflarePanelChange(), cloudflareCheckPending()).
    //
    // Cloudflare limits how fast its API may be asked, and answers too many
    // requests with "Please wait and consider throttling your request speed"
    // (HTTP 429, error 971). So the list is read at most every
    // CLOUDFLARE_ITEMS_FRESH seconds (not at every opening of the panel), the
    // addresses of one change go in one request, a change is asked about
    // less and less often, and after such an answer nothing is asked for
    // CLOUDFLARE_PAUSE seconds.
    require_once __DIR__ . '/auth.php';

    const CLOUDFLARE_API = 'https://api.cloudflare.com/client/v4';
    const CLOUDFLARE_TIMEOUT = 8;
    const CLOUDFLARE_COMMENT_LENGTH = 100;
    // how many times a change asks whether Cloudflare has finished it, after
    // 0.5 s, 1 s, 1.5 s and then every 2 s (17 s in all); Cloudflare changes a
    // list in the background, so a change usually takes a few seconds, and a
    // slower one is reported as unconfirmed rather than done
    const CLOUDFLARE_WAIT_STEPS = 10;
    // how long the list read from Cloudflare is shown without asking again
    const CLOUDFLARE_ITEMS_FRESH = 120;
    // how long nothing is asked after Cloudflare said to slow down
    const CLOUDFLARE_PAUSE = 300;
    // a change sent from the panel: asked about at most every this many
    // seconds, reported as unconfirmed when not done in CLOUDFLARE_PENDING_TIMEOUT,
    // and its outcome shown in the panel for CLOUDFLARE_PENDING_SHOWN after that
    const CLOUDFLARE_POLL_GAP = 2;
    const CLOUDFLARE_PENDING_TIMEOUT = 300;
    const CLOUDFLARE_PENDING_SHOWN = 600;

    function cloudflareConfigured()
    {
        authConfigured();

        return defined('CLOUDFLARE_API_TOKEN') && CLOUDFLARE_API_TOKEN !== ''
            && defined('CLOUDFLARE_ACCOUNT_ID') && CLOUDFLARE_ACCOUNT_ID !== ''
            && defined('CLOUDFLARE_LIST') && CLOUDFLARE_LIST !== '';
    }

    // until when Cloudflare is not asked, after it said to slow down; 0 when it may be
    function cloudflarePausedUntil()
    {
        $until = (int)(readData('cloudflare-pause')['until'] ?? 0);

        return $until > time() ? $until : 0;
    }

    function cloudflarePauseText($until)
    {
        return 'Cloudflare prosi o wolniejsze zapytania, strona nie pyta go do ' . date('H:i', $until) . '.';
    }

    // A call to the Cloudflare API: [true, result, result_info] or [false, error
    // message, HTTP status]. While Cloudflare asked to slow down it is not asked.
    function cloudflareApi($method, $path, $body = null)
    {
        if ($until = cloudflarePausedUntil())
            return [false, cloudflarePauseText($until), 429];

        $http = [
            'method' => $method,
            'timeout' => CLOUDFLARE_TIMEOUT,
            'ignore_errors' => true,
            'header' => implode("\r\n", [
                'Authorization: Bearer ' . CLOUDFLARE_API_TOKEN,
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: SanakanSite (https://sanakan.pl, 1.0)'
            ])
        ];
        if ($body !== null)
            $http['content'] = json_encode($body);

        // over IPv4: Cloudflare counts the API's per-address limit for an IPv6
        // address by a large prefix the server shares with other customers of
        // its provider, and answers error 971 to the site's few requests
        [$json, $status] = httpRaw(CLOUDFLARE_API . $path, $http, 0, ['bindto' => '0:0']);
        $data = $json === false ? null : json_decode($json, true);
        if ($status === 429 || in_array(971, array_column($data['errors'] ?? [], 'code'), true)) {
            $until = time() + CLOUDFLARE_PAUSE;
            writeData('cloudflare-pause', ['until' => $until]);

            return [false, cloudflarePauseText($until), 429];
        }
        if (!is_array($data))
            return [false, 'Cloudflare nie odpowiedział.', $status];
        if (empty($data['success']))
            return [false, 'Cloudflare: ' . ($data['errors'][0]['message'] ?? 'nieznany błąd') . '.', $status];

        return [true, $data['result'] ?? null, $data['result_info'] ?? null];
    }

    function cloudflareListsPath()
    {
        return '/accounts/' . rawurlencode(CLOUDFLARE_ACCOUNT_ID) . '/rules/lists';
    }

    // the id of the IP list named CLOUDFLARE_LIST, remembered in inc/data: [id, null] or [null, error]
    function cloudflareListId()
    {
        $known = readData('cloudflare-list');
        if (($known['name'] ?? '') === CLOUDFLARE_LIST && !empty($known['id']))
            return [$known['id'], null];

        $answer = cloudflareApi('GET', cloudflareListsPath());
        if (!$answer[0])
            return [null, $answer[1]];
        foreach ($answer[1] ?: [] as $list)
            if (($list['name'] ?? '') === CLOUDFLARE_LIST && ($list['kind'] ?? '') === 'ip') {
                writeData('cloudflare-list', ['name' => CLOUDFLARE_LIST, 'id' => $list['id']]);
                return [$list['id'], null];
            }

        return [null, 'Na koncie Cloudflare nie ma listy IP o nazwie ' . CLOUDFLARE_LIST . '.'];
    }

    // What is on the list, newest first: [[['id', 'ip', 'comment', 'created'], ...], null]
    // or [null, error]. Read from Cloudflare at most every CLOUDFLARE_ITEMS_FRESH
    // seconds, and again at once after a change of the site's own; when it
    // cannot be read, the list read last comes with the error.
    function cloudflareBlocked()
    {
        $cached = readData('cloudflare-items');
        if (isset($cached['time'], $cached['items']) && $cached['time'] > time() - CLOUDFLARE_ITEMS_FRESH)
            return [$cached['items'], null];

        $asked = time();
        [$items, $error] = cloudflareReadBlocked();
        if ($items !== null) {
            // a change of the site's own made while the list was being read
            // (cloudflareListChanged()) keeps it to be read again
            updateData('cloudflare-items', function (&$stored) use ($asked, $items) {
                $stored = ($stored['changed'] ?? 0) >= $asked ? ['items' => $items] + $stored : ['time' => time(), 'items' => $items];
            });

            return [$items, null];
        }

        return [$cached['items'] ?? null, $error];
    }

    // after a change of the site's own the list is read again at the next
    // cloudflareBlocked(), the one read before kept for when that fails
    function cloudflareListChanged()
    {
        updateData('cloudflare-items', function (&$cached) {
            if (isset($cached['items']))
                $cached = ['time' => 0, 'changed' => time(), 'items' => $cached['items']];
        });
    }

    // the list as Cloudflare has it now (cloudflareBlocked())
    function cloudflareReadBlocked()
    {
        [$id, $error] = cloudflareListId();
        if ($id === null)
            return [null, $error];

        $items = [];
        $cursor = null;
        for ($page = 0; $page < 10; $page++) {
            $answer = cloudflareApi('GET', cloudflareListsPath() . '/' . rawurlencode($id) . '/items?per_page=500' . ($cursor !== null ? '&cursor=' . rawurlencode($cursor) : ''));
            if (!$answer[0]) {
                // a list deleted and made again has a new id; a request
                // Cloudflare did not answer or asked to slow down says nothing of it
                if ($answer[2] >= 400 && $answer[2] !== 429)
                    writeData('cloudflare-list', []);
                return [null, $answer[1]];
            }
            foreach ($answer[1] ?: [] as $item)
                $items[] = ['id' => $item['id'], 'ip' => $item['ip'] ?? '', 'comment' => $item['comment'] ?? '', 'created' => strtotime($item['created_on'] ?? '') ?: null];
            $cursor = $answer[2]['cursors']['after'] ?? null;
            if ($cursor === null)
                break;
        }
        usort($items, function ($a, $b) { return $b['created'] <=> $a['created']; });

        return [$items, null];
    }

    // What goes on the list for an address: the address itself, the /64 of an
    // IPv6 one; null for an address that is not public.
    function cloudflareTarget($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)
            return null;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
            return $ip;

        $network = substr(inet_pton($ip), 0, 8) . str_repeat("\0", 8);

        return inet_ntop($network) . '/64';
    }

    // Waits until Cloudflare has made a change of the list: null when it is
    // confirmed done, or a message why it is not. A failed poll is not a failed
    // change, so it keeps waiting; only the operation's own "failed" is one. A
    // change Cloudflare has not confirmed in time comes back as a message too,
    // so a caller never takes it for done; the list shows the truth on a refresh.
    function cloudflareWait($result, $steps = CLOUDFLARE_WAIT_STEPS)
    {
        $operation = $result['operation_id'] ?? null;
        if ($operation === null)
            return null;

        for ($i = 0; $i < $steps; $i++) {
            usleep(min(4, $i + 1) * 500000);
            $answer = cloudflareApi('GET', cloudflareListsPath() . '/bulk_operations/' . rawurlencode($operation));
            // a failed poll says nothing about the change, but one Cloudflare
            // asked to slow down is not repeated
            if (!$answer[0] && $answer[2] === 429)
                break;
            if (!$answer[0])
                continue;
            $status = $answer[1]['status'] ?? '';
            if ($status === 'completed')
                return null;
            if ($status === 'failed')
                return 'Cloudflare: ' . ($answer[1]['error'] ?? 'zmiana się nie udała') . '.';
        }

        return 'Cloudflare nie potwierdził zmiany na czas; odśwież listę, żeby sprawdzić, czy doszła.';
    }

    // puts an address on the list: null, or an error message
    function cloudflareBlock($ip, $comment)
    {
        return cloudflareBlockMany([[$ip, $comment]]);
    }

    // puts addresses on the list in one change, [[ip, comment], ...], and
    // waits for it: null, or an error message
    function cloudflareBlockMany($entries)
    {
        [$result, $error] = cloudflareSendBlock($entries);

        return $error ?? cloudflareWait($result);
    }

    // takes items (by their ids from cloudflareBlocked, one or a list) off the
    // list in one change, and waits for it: null, or an error message
    function cloudflareUnblock($itemIds)
    {
        [$result, $error] = cloudflareSendUnblock($itemIds);

        return $error ?? cloudflareWait($result);
    }

    // sends the change of cloudflareBlockMany() without waiting for it:
    // [Cloudflare's answer, null] (with the operation_id to ask about), or
    // [null, error message]
    function cloudflareSendBlock($entries)
    {
        $items = [];
        foreach ($entries as [$ip, $comment]) {
            $target = cloudflareTarget($ip);
            if ($target === null)
                return [null, 'To nie jest publiczny adres IP.'];
            $items[] = ['ip' => $target, 'comment' => cutText($comment, CLOUDFLARE_COMMENT_LENGTH)];
        }
        if (!$items)
            return [[], null];
        [$id, $error] = cloudflareListId();
        if ($id === null)
            return [null, $error];

        return cloudflareSend('POST', cloudflareListsPath() . '/' . rawurlencode($id) . '/items', $items);
    }

    // sends the change of cloudflareUnblock() without waiting, as cloudflareSendBlock()
    function cloudflareSendUnblock($itemIds)
    {
        [$id, $error] = cloudflareListId();
        if ($id === null)
            return [null, $error];

        $items = array_map(function ($itemId) { return ['id' => $itemId]; }, (array)$itemIds);

        return cloudflareSend('DELETE', cloudflareListsPath() . '/' . rawurlencode($id) . '/items', ['items' => $items]);
    }

    function cloudflareSend($method, $path, $body)
    {
        $answer = cloudflareApi($method, $path, $body);
        if (!$answer[0])
            return [null, $answer[1]];
        cloudflareListChanged();

        return [is_array($answer[1]) ? $answer[1] : [], null];
    }

    // ---- Changes sent from the panel ----------------------------------------------
    // The panel sends a change and answers at once; the change waits in
    // inc/data/cloudflare-pending.json under Cloudflare's operation id: 'text'
    // what it is ("zablokowanie 1.2.3.4"), 'done' the history line once
    // Cloudflare has made it, 'by' who asked (['id', 'name']), 'release' an
    // address the automatic blocking leaves alone once it is off the list,
    // 'sent' and 'checked' when it was sent and last asked about, 'status'
    // (pending, completed, failed, unconfirmed), 'message' and 'finished'.

    // Notes a change the panel sent, $sent being what cloudflareSendBlock() or
    // cloudflareSendUnblock() answered: null when it is on its way (or already
    // made), or an error message.
    function cloudflarePanelChange($sent, $text, $done, $by, $release = '')
    {
        [$result, $error] = $sent;
        if ($error !== null)
            return $error;
        $now = microtime(true);
        $op = ['text' => $text, 'done' => $done, 'by' => $by, 'release' => (string)$release, 'sent' => $now, 'checked' => $now, 'status' => 'pending', 'message' => ''];
        $operation = (string)($result['operation_id'] ?? '');
        // nothing to wait for: made already
        if ($operation === '') {
            $operation = 'done-' . bin2hex(random_bytes(6));
            $op = cloudflareFinished($op, 'completed');
        }
        if (!updateData('cloudflare-pending', function (&$pending) use ($operation, $op) { $pending[$operation] = $op; }))
            return dataError();
        if ($op['status'] === 'completed')
            cloudflareMade($op);

        return null;
    }

    // The changes of the panel to show: the waiting ones and the ones finished
    // in the last CLOUDFLARE_PENDING_SHOWN seconds, the newest first, each with
    // its 'id'.
    function cloudflarePending()
    {
        $now = time();
        $shown = [];
        foreach (readData('cloudflare-pending') as $id => $op)
            if (($op['status'] ?? '') === 'pending' || ($op['finished'] ?? 0) > $now - CLOUDFLARE_PENDING_SHOWN)
                $shown[] = ['id' => (string)$id] + $op;
        usort($shown, function ($a, $b) { return $b['sent'] <=> $a['sent']; });

        return $shown;
    }

    // Asks Cloudflare once about each change of the panel still waiting (one
    // not asked about in the last CLOUDFLARE_POLL_GAP seconds), notes what it
    // says, drops the ones finished long ago, and returns cloudflarePending().
    // Run by the panel's ?cloudflare while it shows a waiting change, and by
    // cron (inc/check-site.php), so a change is confirmed with the panel shut.
    function cloudflareCheckPending()
    {
        $now = microtime(true);
        $claimed = [];
        // a change is asked about by one request at a time
        $paused = cloudflarePausedUntil() > 0;
        updateData('cloudflare-pending', function (&$pending) use ($now, $paused, &$claimed) {
            foreach ($pending as $id => $op) {
                if (($op['status'] ?? '') !== 'pending') {
                    if (($op['finished'] ?? 0) < $now - CLOUDFLARE_PENDING_SHOWN)
                        unset($pending[$id]);
                    continue;
                }
                if ($paused || ($op['checked'] ?? 0) > $now - CLOUDFLARE_POLL_GAP)
                    continue;
                $pending[$id]['checked'] = $now;
                $claimed[] = (string)$id;
            }
        });

        foreach ($claimed as $id) {
            $answer = cloudflareApi('GET', cloudflareListsPath() . '/bulk_operations/' . rawurlencode($id));
            $status = $answer[0] ? (string)($answer[1]['status'] ?? '') : '';
            $error = 'Cloudflare: ' . ($answer[1]['error'] ?? 'zmiana się nie udała') . '.';
            $finished = null;
            updateData('cloudflare-pending', function (&$pending) use ($id, $status, $error, &$finished) {
                $op = $pending[$id] ?? null;
                if (($op['status'] ?? '') !== 'pending')
                    return;
                if ($status === 'completed')
                    $finished = cloudflareFinished($op, 'completed');
                else if ($status === 'failed')
                    $finished = cloudflareFinished($op, 'failed', $error);
                else if ($op['sent'] < microtime(true) - CLOUDFLARE_PENDING_TIMEOUT)
                    $finished = cloudflareFinished($op, 'unconfirmed', 'Cloudflare nie potwierdził zmiany w ' . intdiv(CLOUDFLARE_PENDING_TIMEOUT, 60) . ' minut; odśwież listę, żeby sprawdzić, czy doszła.');
                if ($finished !== null)
                    $pending[$id] = $finished;
            });
            if (($finished['status'] ?? '') === 'completed')
                cloudflareMade($finished);
        }

        return cloudflarePending();
    }

    function cloudflareFinished($op, $status, $message = '')
    {
        return ['status' => $status, 'message' => $status === 'completed' ? $op['done'] : $message, 'finished' => time()] + $op;
    }

    // what follows a change Cloudflare has made: the list read again, the
    // history, and an address taken off by hand left alone by the automatic blocking
    function cloudflareMade($op)
    {
        cloudflareListChanged();
        addHistory('cloudflare', $op['done'], $op['by']);
        if ($op['release'] !== '' && function_exists('autoBlockReleased'))
            autoBlockReleased($op['release']);
    }
