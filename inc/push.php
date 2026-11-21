<?php
    // "Powiadom mnie, gdy wróci" on state/: a visitor may ask for a browser
    // notification (Web Push) once the bot has not answered for PUSH_OPEN_AFTER,
    // never during a planned break. The subscription (the push service's address
    // and the browser's keys) waits in inc/data/bot-return.json, without any
    // account. Once the last PUSH_BACK_CHECKS checks all had an answer,
    // inc/check-bot.php sends every waiting one a single notification and drops
    // it, and the service worker (sw.js) ends the subscription on its side too.
    //
    // Sent without a library: the message encrypted for the browser (RFC 8291,
    // aes128gcm) and the request signed with the site's own key (VAPID, RFC
    // 8292), made once and kept in inc/data/push-keys.json; a new key would cut
    // off every subscription made with the old one.
    require_once __DIR__ . '/bot.php';
    require_once __DIR__ . '/meta.php';

    // subscribing is offered once the bot has not answered this long
    const PUSH_OPEN_AFTER = 300;
    // this many answered checks in a row (one a minute from cron) mean it is back
    const PUSH_BACK_CHECKS = 10;
    // so the file cannot be filled without end
    const PUSH_MAX = 5000;
    // a subscription never sent this long goes unsent
    const PUSH_KEEP = 30 * 86400;
    // the rest waits for the next cron run
    const PUSH_SEND_PER_RUN = 200;
    // how long a push service keeps the message for a device that is off
    const PUSH_TTL = 21600;
    const PUSH_ENDPOINT_MAX = 1024;
    // The push services of the browsers: Chrome, Edge (also FCM on Android),
    // Opera and the like, Firefox, Safari, old Edge. The site sends a POST to the
    // address a browser gives, so only to these.
    const PUSH_HOSTS = ['fcm.googleapis.com', '*.push.services.mozilla.com', '*.push.apple.com', '*.notify.windows.com'];

    function base64UrlEncode($bytes)
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    // the bytes, or null for anything that is not base64url
    function base64UrlDecode($text)
    {
        if (!is_string($text) || !preg_match('/^[A-Za-z0-9_-]*={0,2}$/', $text))
            return null;
        $bytes = base64_decode(strtr(rtrim($text, '='), '-_', '+/'), true);

        return $bytes === false ? null : $bytes;
    }

    // A new P-256 key. PHP on Windows finds no openssl.cnf of its own and then
    // makes no key at all, so the one it ships with is tried next.
    function pushNewKey()
    {
        $options = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        $key = @openssl_pkey_new($options);
        if ($key === false && pushOpensslConfig())
            $key = @openssl_pkey_new($options + pushOpensslConfig());
        while (openssl_error_string() !== false);

        return $key === false ? null : $key;
    }

    // the openssl.cnf PHP ships with on Windows, as an option, or []
    function pushOpensslConfig()
    {
        $config = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';

        return is_file($config) ? ['config' => $config] : [];
    }

    // a P-256 public key as its 65 bytes, 0x04 x y
    function pushRawPublic($key)
    {
        $ec = openssl_pkey_get_details($key)['ec'] ?? null;
        if (!isset($ec['x'], $ec['y']))
            return null;

        return "\x04" . str_pad($ec['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ec['y'], 32, "\0", STR_PAD_LEFT);
    }

    // the 65 bytes of a P-256 public key as a key PHP can use
    function pushPublicKey($raw)
    {
        if (strlen($raw) !== 65 || $raw[0] !== "\x04")
            return null;
        // SubjectPublicKeyInfo of an id-ecPublicKey on prime256v1, then the point
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
        $key = @openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n");
        while (openssl_error_string() !== false);

        return $key === false ? null : $key;
    }

    // The site's VAPID key, ['private' => key, 'public' => 65 bytes], made the
    // first time; null when this PHP cannot make one.
    function pushKeys()
    {
        static $keys = null;
        if ($keys !== null)
            return $keys;

        $stored = readData('push-keys');
        if (empty($stored['private'])) {
            updateData('push-keys', function (&$data) {
                if (!empty($data['private']))
                    return;
                $key = pushNewKey();
                if ($key !== null && (@openssl_pkey_export($key, $pem) || @openssl_pkey_export($key, $pem, null, pushOpensslConfig())))
                    $data = ['private' => $pem, 'made' => time()];
                while (openssl_error_string() !== false);
            });
            $stored = readData('push-keys');
        }
        $key = empty($stored['private']) ? false : @openssl_pkey_get_private($stored['private']);
        $public = $key === false ? null : pushRawPublic($key);
        if ($public === null)
            return null;

        return $keys = ['private' => $key, 'public' => $public];
    }

    // An aes128gcm message for one browser (RFC 8291): $uaPublic and $authSecret
    // are the p256dh and auth keys of its subscription. $asKey and $salt are new
    // each time; a test gives them to compare with the RFC's example.
    function pushEncrypt($plaintext, $uaPublic, $authSecret, $asKey = null, $salt = null)
    {
        $uaKey = pushPublicKey($uaPublic);
        $asKey = $asKey ?? pushNewKey();
        if ($uaKey === null || $asKey === null || strlen($authSecret) !== 16)
            return null;
        $asPublic = pushRawPublic($asKey);
        $secret = @openssl_pkey_derive($uaKey, $asKey);
        while (openssl_error_string() !== false);
        if ($asPublic === null || !is_string($secret) || strlen($secret) !== 32)
            return null;

        $salt = $salt ?? random_bytes(16);
        $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . $uaPublic . $asPublic, $authSecret);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        // one record, the last: the text, then the delimiter 2
        $cipher = openssl_encrypt($plaintext . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false)
            return null;

        return $salt . pack('N', 4096) . chr(65) . $asPublic . $cipher . $tag;
    }

    // An ES256 signature as JWT wants it, r and s of 32 bytes each, from the DER
    // that openssl_sign() gives
    function pushRawSignature($der)
    {
        $parts = [];
        $at = 2 + (ord($der[1] ?? "\0") & 0x80 ? ord($der[1]) & 0x7f : 0);
        for ($i = 0; $i < 2; $i++) {
            if (($der[$at] ?? '') !== "\x02")
                return null;
            $length = ord($der[$at + 1]);
            $number = ltrim(substr($der, $at + 2, $length), "\0");
            if (strlen($number) > 32)
                return null;
            $parts[] = str_pad($number, 32, "\0", STR_PAD_LEFT);
            $at += 2 + $length;
        }

        return implode('', $parts);
    }

    // the VAPID token for one push service ($audience its https://host)
    function pushVapidToken($audience, $expires, $key)
    {
        $input = base64UrlEncode('{"typ":"JWT","alg":"ES256"}') . '.'
            . base64UrlEncode(json_encode(['aud' => $audience, 'exp' => $expires, 'sub' => SITE_URL], JSON_UNESCAPED_SLASHES));
        if (!openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256))
            return null;
        $signature = pushRawSignature($der);

        return $signature === null ? null : $input . '.' . base64UrlEncode($signature);
    }

    // an https address of one of PUSH_HOSTS on its usual port
    function pushEndpointAllowed($endpoint)
    {
        if (!is_string($endpoint) || strlen($endpoint) > PUSH_ENDPOINT_MAX || preg_match('/[\s\x00-\x1f\x7f]/', $endpoint))
            return false;
        $url = parse_url($endpoint);
        if (($url['scheme'] ?? '') !== 'https' || isset($url['port']) || isset($url['user']) || isset($url['pass']) || empty($url['host']))
            return false;
        $host = strtolower($url['host']);
        foreach (PUSH_HOSTS as $allowed) {
            if ($host === $allowed)
                return true;
            if ($allowed[0] === '*' && preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)*$/', $host) && substr($host, -strlen($allowed) + 1) === substr($allowed, 1))
                return true;
        }

        return false;
    }

    // a subscription as the browser gives it (PushSubscription.toJSON()), checked,
    // as ['e' => endpoint, 'k' => p256dh, 'a' => auth], or null
    function pushParseSubscription($subscription)
    {
        $endpoint = $subscription['endpoint'] ?? null;
        $p256dh = base64UrlDecode($subscription['keys']['p256dh'] ?? null);
        $auth = base64UrlDecode($subscription['keys']['auth'] ?? null);
        if (!pushEndpointAllowed($endpoint) || $p256dh === null || $auth === null || strlen($auth) !== 16 || pushPublicKey($p256dh) === null)
            return null;

        return ['e' => $endpoint, 'k' => base64UrlEncode($p256dh), 'a' => base64UrlEncode($auth)];
    }

    // whether subscribing is offered now: the bot has not answered for
    // PUSH_OPEN_AFTER and no planned break is going on ($state: botState())
    function pushOpen($now, $state = null)
    {
        $since = botDownSince();

        return ($state ?? botState())['status'] === 'offline' && !botInMaintenance($now)
            && $since !== null && $now - $since >= PUSH_OPEN_AFTER && pushKeys() !== null;
    }

    function pushWaiting()
    {
        return count(readData('bot-return')['subs'] ?? []);
    }

    // 'ok', 'invalid', 'closed' (the bot answers, a break, or only just went
    // quiet), 'full' or 'error' (the file could not be written)
    function pushSubscribe($subscription, $now, $state = null)
    {
        $sub = pushParseSubscription($subscription);
        if ($sub === null)
            return 'invalid';
        if (!pushOpen($now, $state))
            return 'closed';

        $id = hash('sha256', $sub['e']);
        $ok = updateData('bot-return', function (&$data) use ($id, $sub, $now) {
            $subs = pushFresh($data['subs'] ?? [], $now);
            if (!isset($subs[$id]) && count($subs) >= PUSH_MAX)
                return 'full';
            $subs[$id] = $sub + ['t' => $subs[$id]['t'] ?? $now];
            $data['subs'] = $subs;
            return 'ok';
        }, $result);

        return $ok ? $result : 'error';
    }

    // true also when it was not there
    function pushUnsubscribe($endpoint)
    {
        if (!is_string($endpoint) || strlen($endpoint) > PUSH_ENDPOINT_MAX)
            return false;
        $id = hash('sha256', $endpoint);

        return updateData('bot-return', function (&$data) use ($id) {
            unset($data['subs'][$id]);
        });
    }

    function pushFresh($subs, $now)
    {
        return array_filter(is_array($subs) ? $subs : [], function ($sub) use ($now) {
            return is_array($sub) && $now - (int)($sub['t'] ?? 0) < PUSH_KEEP;
        });
    }

    // the last PUSH_BACK_CHECKS checks of $history all had an answer
    function pushBotBack($history)
    {
        $last = array_slice($history, -PUSH_BACK_CHECKS);

        return count($last) === PUSH_BACK_CHECKS && !in_array(false, array_column($last, 1), true);
    }

    // what the notification says
    function pushMessage($now)
    {
        $incident = botIncidents($now - BOT_HISTORY_SPAN)[0] ?? null;
        $body = 'Bot znów odpowiada.';
        if ($incident !== null && $incident[1] !== null)
            $body = 'Bot znów odpowiada, nie było go ' . duration($incident[1] - $incident[0]) . '.';

        return json_encode(['title' => 'Sanakan wrócił', 'body' => $body, 'url' => '/state/'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    // one notification: the HTTP status of the push service, 0 without an answer
    function pushSend($sub, $message, $keys, $now)
    {
        $body = pushEncrypt($message, (string)base64UrlDecode($sub['k']), (string)base64UrlDecode($sub['a']));
        $url = parse_url($sub['e']);
        $token = pushVapidToken('https://' . $url['host'], $now + 43200, $keys['private']);
        if ($body === null || $token === null)
            return 0;

        [, $status] = httpRaw($sub['e'], [
            'method' => 'POST',
            'timeout' => 5,
            'ignore_errors' => true,
            'follow_location' => 0,
            'header' => implode("\r\n", [
                'Authorization: vapid t=' . $token . ', k=' . base64UrlEncode($keys['public']),
                'Content-Type: application/octet-stream',
                'Content-Encoding: aes128gcm',
                'Content-Length: ' . strlen($body),
                'TTL: ' . PUSH_TTL,
                'Urgency: high'
            ]),
            'content' => $body
        ], 4096);

        return (int)$status;
    }

    // Run by inc/check-bot.php after every check: once the bot is back, the
    // waiting subscriptions are taken out (at most PUSH_SEND_PER_RUN, the rest
    // next run) and each gets its notification, tried once. How many were sent.
    function pushDeliver($now)
    {
        if (!is_file(dataFile('bot-return')) || !pushWaiting() || !pushBotBack(botHistory()))
            return 0;
        $keys = pushKeys();
        if ($keys === null)
            return 0;

        // taken out before sending, so two runs at once never send one twice
        updateData('bot-return', function (&$data) use ($now) {
            $subs = pushFresh($data['subs'] ?? [], $now);
            $data['subs'] = array_slice($subs, PUSH_SEND_PER_RUN, null, true);
            return array_slice($subs, 0, PUSH_SEND_PER_RUN, true);
        }, $batch);

        $message = pushMessage($now);
        $sent = 0;
        $failed = [];
        foreach ($batch ?: [] as $sub) {
            $status = pushSend($sub, $message, $keys, $now);
            if ($status >= 200 && $status < 300)
                $sent++;
            else
                $failed[$status] = ($failed[$status] ?? 0) + 1;
        }
        // 404 and 410 are subscriptions the browser ended meanwhile
        unset($failed[404], $failed[410]);
        if ($failed)
            error_log('sanakan: bot-return notifications not sent: ' . json_encode($failed));

        return $sent;
    }
