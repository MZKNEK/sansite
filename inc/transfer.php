<?php
    // Sending files between two devices (t/): the server only introduces them.
    // A logged-in account opens a transfer and shows its id as a QR code; the
    // device that scans it joins without a login, once; then the two leave
    // each other the few messages WebRTC needs to connect (the offer, the
    // answer) and the files go straight between them, never through here.
    // The transfers are kept in inc/data/transfers.json: one not joined for
    // TRANSFER_JOIN_TIME, a joined one for TRANSFER_IDLE_TIME after its last
    // call, then dropped by the next call of anyone.
    //
    // A transfer opened by an owner of the site (PANEL_ADMINS) may also go
    // through the server when the devices cannot reach each other (no TURN):
    // the pieces wait in memory (/dev/shm where there is one) for the other
    // side, which takes them away, at most TRANSFER_RELAY_WAITING at a time.
    //
    // Each side is known by a key of its own, given when it opens or joins, so
    // the calls after that need no session.
    require_once __DIR__ . '/auth.php';

    const TRANSFER_JOIN_TIME = 600;
    const TRANSFER_IDLE_TIME = 900;
    // a joined transfer's last call is written at most this often
    const TRANSFER_SEEN_STEP = 30;
    const TRANSFER_PER_ACCOUNT = 5;
    // a side sends an offer or an answer (an SDP of a few KB) and maybe a
    // word of going through the server, so a few small ones are plenty and
    // the file stays small whatever an account puts in
    const TRANSFER_MESSAGES = 4;
    const TRANSFER_MESSAGE_SIZE = 16384;
    // a piece through the server, its first byte telling text from bytes
    const TRANSFER_RELAY_CHUNK = 1048577;
    const TRANSFER_RELAY_WAITING = 8;

    // 128 random bits, URL-safe (22 characters)
    function transferToken()
    {
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    function validTransferToken($token)
    {
        return is_string($token) && preg_match('/^[A-Za-z0-9_-]{22}$/', $token) === 1;
    }

    // where the pieces through the server wait: memory where the system has
    // it, the temporary folder elsewhere (a test sets TRANSFER_RELAY_DIR)
    function transferRelayBase()
    {
        if (defined('TRANSFER_RELAY_DIR'))
            return TRANSFER_RELAY_DIR;
        $base = is_dir('/dev/shm') && is_writable('/dev/shm') ? '/dev/shm' : sys_get_temp_dir();

        return rtrim($base, '/\\') . '/sanakan-transfer';
    }

    function transferRelayDir($id)
    {
        return transferRelayBase() . '/' . $id;
    }

    function removeTransferRelay($id)
    {
        $dir = transferRelayDir($id);
        foreach (glob($dir . '/*') ?: [] as $file)
            @unlink($file);
        @rmdir($dir);
    }

    function transferExpired(array $transfer, $now)
    {
        return $transfer['guest'] === null
            ? $transfer['created'] + TRANSFER_JOIN_TIME < $now
            : $transfer['seen'] + TRANSFER_IDLE_TIME < $now;
    }

    // drops the transfers that ran out, with their pieces, and any folder of
    // pieces left by a transfer no longer listed
    function pruneTransfers(array &$all, $now)
    {
        foreach ($all as $id => $transfer)
            if (!is_array($transfer) || transferExpired($transfer, $now)) {
                unset($all[$id]);
                removeTransferRelay($id);
            }
        foreach (glob(transferRelayBase() . '/*', GLOB_ONLYDIR) ?: [] as $dir)
            if (!isset($all[basename($dir)]) && @filemtime($dir) < $now - TRANSFER_IDLE_TIME)
                removeTransferRelay(basename($dir));
    }

    // 'host' or 'guest' for the key of one side, null for any other
    function transferSide(array $transfer, $key)
    {
        if (!is_string($key) || $key === '')
            return null;
        if (hash_equals($transfer['host'], $key))
            return 'host';

        return $transfer['guest'] !== null && hash_equals($transfer['guest']['key'], $key) ? 'guest' : null;
    }

    // Opens a transfer for $user (siteUser()): ['id', 'key', 'relay', 'ttl'],
    // or an error: 'full' (TRANSFER_PER_ACCOUNT open already), 'failed'.
    function createTransfer(array $user, $now = null)
    {
        $now = $now ?? time();
        $id = transferToken();
        $key = transferToken();
        $relay = isPanelAdminId($user['id']);
        $saved = updateData('transfers', function (&$all) use ($user, $id, $key, $relay, $now) {
            pruneTransfers($all, $now);
            $own = array_filter($all, function ($transfer) use ($user) { return $transfer['owner'] === (string)$user['id']; });
            if (count($own) >= TRANSFER_PER_ACCOUNT)
                return 'full';
            $all[$id] = [
                'owner' => (string)$user['id'],
                'name' => (string)($user['name'] ?? ''),
                'relay' => $relay,
                'created' => $now,
                'seen' => $now,
                'host' => $key,
                'guest' => null,
                'seq' => 0,
                'box' => ['host' => [], 'guest' => []]
            ];
            return 'ok';
        }, $result);
        if (!$saved)
            return 'failed';

        return $result === 'ok' ? ['id' => $id, 'key' => $key, 'relay' => $relay, 'ttl' => TRANSFER_JOIN_TIME] : $result;
    }

    // What the device that scanned the code is asked about before it joins:
    // ['name'] of the account that opened it, or 'gone' (no such transfer, or
    // it ran out) or 'taken' (joined already).
    function peekTransfer($id, $now = null)
    {
        $now = $now ?? time();
        $transfer = validTransferToken($id) ? (readData('transfers')[$id] ?? null) : null;
        if (!is_array($transfer) || transferExpired($transfer, $now))
            return 'gone';

        return $transfer['guest'] !== null ? 'taken' : ['name' => $transfer['name']];
    }

    // Joins a transfer as its other side, once: ['key', 'name', 'relay'], or
    // 'gone', 'taken', 'failed'.
    function joinTransfer($id, $device, $now = null)
    {
        $now = $now ?? time();
        if (!validTransferToken($id))
            return 'gone';
        $key = transferToken();
        $joined = null;
        $saved = updateData('transfers', function (&$all) use ($id, $key, $device, $now, &$joined) {
            pruneTransfers($all, $now);
            if (!isset($all[$id]))
                return 'gone';
            if ($all[$id]['guest'] !== null)
                return 'taken';
            $all[$id]['guest'] = ['key' => $key, 'device' => (string)$device, 'joined' => $now];
            $all[$id]['seen'] = $now;
            $joined = $all[$id];
            return 'ok';
        }, $result);
        if (!$saved)
            return 'failed';

        return $result === 'ok' ? ['key' => $key, 'name' => $joined['name'], 'relay' => $joined['relay']] : $result;
    }

    // Leaves $message (a string, the client's own JSON) for the other side:
    // true, or 'gone', 'denied' (a wrong key), 'alone' (no one joined yet),
    // 'large', 'bad' (control characters, which JSON from a browser never has
    // and which would grow sixfold in the file), 'full' (TRANSFER_MESSAGES
    // waiting), 'failed'.
    function sendTransferMessage($id, $key, $message, $now = null)
    {
        $now = $now ?? time();
        if (!is_string($message) || $message === '' || strlen($message) > TRANSFER_MESSAGE_SIZE)
            return 'large';
        if (preg_match('/[\x00-\x1f\x7f]/', $message))
            return 'bad';
        if (!validTransferToken($id))
            return 'gone';
        $saved = updateData('transfers', function (&$all) use ($id, $key, $message, $now) {
            pruneTransfers($all, $now);
            if (!isset($all[$id]))
                return 'gone';
            $transfer = &$all[$id];
            $side = transferSide($transfer, $key);
            if ($side === null)
                return 'denied';
            if ($transfer['guest'] === null)
                return 'alone';
            $to = $side === 'host' ? 'guest' : 'host';
            if (count($transfer['box'][$to]) >= TRANSFER_MESSAGES)
                return 'full';
            $transfer['box'][$to][] = [++$transfer['seq'], $message];
            $transfer['seen'] = $now;
            return true;
        }, $result);

        return $saved ? $result : 'failed';
    }

    // What waits for one side: ['joined' => the other device's name or null,
    // 'messages' => [[seq, message], ...] after $after], or 'gone', 'denied'.
    // The messages up to $after were read, so they are dropped.
    function pollTransfer($id, $key, $after, $now = null)
    {
        $now = $now ?? time();
        if (!validTransferToken($id))
            return 'gone';
        $after = (int)$after;
        $transfer = readData('transfers')[$id] ?? null;
        if (!is_array($transfer) || transferExpired($transfer, $now))
            return 'gone';
        $side = transferSide($transfer, $key);
        if ($side === null)
            return 'denied';

        $read = array_filter($transfer['box'][$side], function ($entry) use ($after) { return $entry[0] <= $after; });
        if ($read || $transfer['seen'] + TRANSFER_SEEN_STEP < $now)
            updateData('transfers', function (&$all) use ($id, $side, $after, $now) {
                if (!isset($all[$id]))
                    return;
                $all[$id]['box'][$side] = array_values(array_filter($all[$id]['box'][$side], function ($entry) use ($after) { return $entry[0] > $after; }));
                $all[$id]['seen'] = $now;
            });

        return [
            'joined' => $transfer['guest']['device'] ?? null,
            'messages' => array_values(array_filter($transfer['box'][$side], function ($entry) use ($after) { return $entry[0] > $after; }))
        ];
    }

    // Ends a transfer for both sides, with any pieces waiting: true, or 'gone',
    // 'denied'.
    function closeTransfer($id, $key, $now = null)
    {
        $now = $now ?? time();
        if (!validTransferToken($id))
            return 'gone';
        $saved = updateData('transfers', function (&$all) use ($id, $key, $now) {
            pruneTransfers($all, $now);
            if (!isset($all[$id]))
                return 'gone';
            if (transferSide($all[$id], $key) === null)
                return 'denied';
            unset($all[$id]);
            return true;
        }, $result);
        if ($result === true)
            removeTransferRelay($id);

        return $saved ? $result : 'failed';
    }

    // The side of a joined transfer that may go through the server, for a
    // piece's call: 'host' or 'guest', or 'gone', 'denied' (a wrong key, or a
    // transfer that may not), 'alone'. Its last call is noted now and then.
    function transferRelaySide($id, $key, $now)
    {
        if (!validTransferToken($id))
            return 'gone';
        $transfer = readData('transfers')[$id] ?? null;
        if (!is_array($transfer) || transferExpired($transfer, $now))
            return 'gone';
        $side = transferSide($transfer, $key);
        if ($side === null || !$transfer['relay'])
            return 'denied';
        if ($transfer['guest'] === null)
            return 'alone';
        if ($transfer['seen'] + TRANSFER_SEEN_STEP < $now)
            updateData('transfers', function (&$all) use ($id, $now) {
                if (isset($all[$id]))
                    $all[$id]['seen'] = $now;
            });

        return $side;
    }

    // Leaves piece $seq ($data, bytes) for the other side: true, or 'busy'
    // (TRANSFER_RELAY_WAITING not taken yet, to try again), 'large', 'failed'
    // or what transferRelaySide() refused. The same piece sent again replaces
    // itself.
    function putTransferPiece($id, $key, $seq, $data, $now = null)
    {
        $now = $now ?? time();
        if (!is_string($data) || $data === '' || strlen($data) > TRANSFER_RELAY_CHUNK)
            return 'large';
        if (!is_int($seq) || $seq < 0)
            return 'denied';
        $side = transferRelaySide($id, $key, $now);
        if (!in_array($side, ['host', 'guest'], true))
            return $side;

        $to = $side === 'host' ? 'guest' : 'host';
        $dir = transferRelayDir($id);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir))
            return 'failed';
        $file = $dir . '/' . $to . '-' . $seq;
        if (!is_file($file) && count(glob($dir . '/' . $to . '-*') ?: []) >= TRANSFER_RELAY_WAITING)
            return 'busy';

        return replaceFile($file, $data) ? true : 'failed';
    }

    // Gives piece $seq left for this side: ['data' => its bytes], null when it
    // has not come yet, or what transferRelaySide() refused. The pieces are
    // asked for in order, so asking for one means the one before came whole
    // and goes; an answer lost on the way is asked for again.
    function takeTransferPiece($id, $key, $seq, $now = null)
    {
        $now = $now ?? time();
        if (!is_int($seq) || $seq < 0)
            return 'denied';
        $side = transferRelaySide($id, $key, $now);
        if (!in_array($side, ['host', 'guest'], true))
            return $side;

        $prefix = transferRelayDir($id) . '/' . $side . '-';
        if ($seq > 0)
            @unlink($prefix . ($seq - 1));
        $data = @file_get_contents($prefix . $seq);

        return $data === false ? null : ['data' => $data];
    }
