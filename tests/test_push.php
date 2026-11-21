<?php
    // inc/push.php: the encryption and VAPID token of Web Push, which push
    // services the site sends to, when subscribing is open on state/, and the
    // notifications sent once the bot is back.

    // an EC private key from its raw 32 bytes and its public point (SEC1 DER)
    function pushTestPrivate($d, $public)
    {
        $der = hex2bin('30770201010420') . $d . hex2bin('a00a06082a8648ce3d030107a144034200') . $public;

        return openssl_pkey_get_private("-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n");
    }

    // a browser's side: its key and auth secret, and the subscription it gives
    function pushTestBrowser($endpoint = 'https://fcm.googleapis.com/fcm/send/abc')
    {
        $key = pushNewKey();
        $auth = random_bytes(16);

        return ['key' => $key, 'auth' => $auth, 'subscription' => ['endpoint' => $endpoint,
            'keys' => ['p256dh' => base64UrlEncode(pushRawPublic($key)), 'auth' => base64UrlEncode($auth)]]];
    }

    // what the browser reads from an aes128gcm message, or null
    function pushTestDecrypt($body, $browser)
    {
        $salt = substr($body, 0, 16);
        $asPublic = substr($body, 21, 65);
        $secret = openssl_pkey_derive(pushPublicKey($asPublic), $browser['key']);
        $ikm = hash_hkdf('sha256', $secret, 32, "WebPush: info\0" . pushRawPublic($browser['key']) . $asPublic, $browser['auth']);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
        $cipher = substr($body, 86);
        $plain = openssl_decrypt(substr($cipher, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($cipher, -16));

        // padding zeros, then the delimiter 2 of the last record
        return $plain === false || substr(rtrim($plain, "\0"), -1) !== "\x02" ? null : substr(rtrim($plain, "\0"), 0, -1);
    }

    function pushTestOffline($since)
    {
        file_put_contents(botFile('incidents.json'), json_encode([[$since, null]]));

        return ['status' => 'offline', 'uptime' => 90, 'checked' => time(), 'ms' => null, 'issues' => []];
    }

    // $online answered checks, one a minute, ending now, after an outage
    function pushTestBack($online)
    {
        $now = time();
        $lines = [];
        for ($i = 30; $i > $online; $i--)
            $lines[] = ($now - $i * 60) . ' 0';
        for ($i = $online; $i >= 1; $i--)
            $lines[] = ($now - ($i - 1) * 60) . ' 1 40 -';
        file_put_contents(botFile('status-history.txt'), implode("\n", $lines) . "\n");
        file_put_contents(botFile('incidents.json'), json_encode([[$now - 1800, $now - ($online - 1) * 60]]));
    }

    test('pushEncrypt gives the example of RFC 8291 byte for byte', function () {
        $as = pushTestPrivate(base64UrlDecode('yfWPiYE-n46HLnH0KqZOF1fJJU3MYrct3AELtAQ-oRw'),
            base64UrlDecode('BP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8'));
        $body = pushEncrypt(base64UrlDecode('V2hlbiBJIGdyb3cgdXAsIEkgd2FudCB0byBiZSBhIHdhdGVybWVsb24'),
            base64UrlDecode('BCVxsr7N_eNgVRqvHtD0zTZsEc6-VV-JvLexhqUzORcxaOzi6-AYWXvTBHm4bjyPjs7Vd8pZGH6SRpkNtoIAiw4'),
            base64UrlDecode('BTBZMqHH6r4Tts7J_aSIgg'), $as, base64UrlDecode('DGv6ra1nlYgDCS1FRnbzlw'));
        assertSame('DGv6ra1nlYgDCS1FRnbzlwAAEABBBP4z9KsN6nGRTbVYI_c7VJSPQTBtkgcy27mlmlMoZIIgDll6e3vCYLocInmYWAmS6TlzAC8wEqKK6PBru3jl7A8',
            base64UrlEncode(substr($body, 0, 86)), 'the header');
        assertSame('8pfeW0KbunFT06SuDKoJH9Ql87S1QUrdirN6GcG7sFz1y1sqLgVi1VhjVkHsUoEsbI_0LpXMuGvnzQ',
            base64UrlEncode(substr($body, 86)), 'the ciphertext');
    });

    test('the VAPID key is made once and its token verifies', function () {
        $keys = pushKeys();
        assertSame(65, strlen($keys['public']));
        $stored = readData('push-keys')['private'];

        $token = pushVapidToken('https://fcm.googleapis.com', 1234, $keys['private']);
        [$head, $claims, $signature] = explode('.', $token);
        assertSame(['typ' => 'JWT', 'alg' => 'ES256'], json_decode(base64UrlDecode($head), true));
        assertSame(['aud' => 'https://fcm.googleapis.com', 'exp' => 1234, 'sub' => SITE_URL], json_decode(base64UrlDecode($claims), true));
        $raw = base64UrlDecode($signature);
        assertSame(64, strlen($raw));
        $int = function ($bytes) {
            $bytes = ltrim($bytes, "\0");
            if (ord($bytes[0]) & 0x80)
                $bytes = "\0" . $bytes;
            return "\x02" . chr(strlen($bytes)) . $bytes;
        };
        $der = $int(substr($raw, 0, 32)) . $int(substr($raw, 32));
        assertSame(1, openssl_verify($head . '.' . $claims, "\x30" . chr(strlen($der)) . $der, pushPublicKey($keys['public']), OPENSSL_ALGO_SHA256));
        assertSame($stored, readData('push-keys')['private'], 'the key stays');
    });

    test('only the push services of the browsers are sent to', function () {
        foreach (['https://fcm.googleapis.com/fcm/send/x', 'https://updates.push.services.mozilla.com/wpush/v2/x',
            'https://web.push.apple.com/QF', 'https://wns2-par02p.notify.windows.com/w/?token=a'] as $url)
            assertTrue(pushEndpointAllowed($url), $url);
        foreach (['http://fcm.googleapis.com/x', 'https://fcm.googleapis.com:8443/x', 'https://127.0.0.1/x',
            'https://fcm.googleapis.com.example.com/x', 'https://evilnotify.windows.com/x', 'https://a@fcm.googleapis.com/',
            'https://push.apple.com/x', "https://fcm.googleapis.com/x\r\nHost: a", 'https://fcm.googleapis.com/' . str_repeat('a', 1100), null] as $url)
            assertFalse(pushEndpointAllowed($url), (string)$url);
    });

    test('a subscription needs a point on the curve and a 16 byte auth', function () {
        $browser = pushTestBrowser();
        assertTrue(pushParseSubscription($browser['subscription']) !== null);
        $bad = $browser['subscription'];
        $bad['keys']['auth'] = base64UrlEncode(random_bytes(8));
        assertNull(pushParseSubscription($bad));
        $bad = $browser['subscription'];
        $bad['keys']['p256dh'] = base64UrlEncode("\x04" . str_repeat("\x01", 64));
        assertNull(pushParseSubscription($bad), 'not on the curve');
        $bad = $browser['subscription'];
        $bad['endpoint'] = 'https://example.com/push';
        assertNull(pushParseSubscription($bad));
    });

    test('subscribing opens after 5 minutes down, never during a break', function () {
        $now = time();
        $subscription = pushTestBrowser()['subscription'];
        $online = ['status' => 'online', 'uptime' => 100, 'checked' => $now, 'ms' => 40, 'issues' => []];
        assertSame('closed', pushSubscribe($subscription, $now, $online));
        assertSame('closed', pushSubscribe($subscription, $now, pushTestOffline($now - 240)), '4 minutes');

        $offline = pushTestOffline($now - 300);
        assertTrue(pushOpen($now, $offline));
        assertSame('ok', pushSubscribe($subscription, $now, $offline));
        assertSame('ok', pushSubscribe($subscription, $now, $offline), 'again is the same one');
        assertSame(1, pushWaiting());
        assertSame('invalid', pushSubscribe(['endpoint' => 'https://example.com/x'], $now, $offline));

        botSaveNotice(['text' => 'Przerwa', 'to' => null, 'maintenance' => ['from' => $now - 600, 'to' => $now + 600], 'by' => '999', 'set' => $now]);
        assertFalse(pushOpen($now, $offline));
        assertSame('closed', pushSubscribe(pushTestBrowser('https://fcm.googleapis.com/fcm/send/other')['subscription'], $now, $offline));

        assertTrue(pushUnsubscribe($subscription['endpoint']), 'cancelling works also during the break');
        assertSame(0, pushWaiting());
    });

    test('the notifications go once the bot answered 10 checks in a row', function () {
        $now = time();
        $offline = pushTestOffline($now - 1800);
        $browsers = [pushTestBrowser('https://fcm.googleapis.com/fcm/send/one'), pushTestBrowser('https://updates.push.services.mozilla.com/wpush/v2/two')];
        foreach ($browsers as $browser)
            assertSame('ok', pushSubscribe($browser['subscription'], $now, $offline));

        $sent = [];
        $GLOBALS['SANAKAN_HTTP'] = function ($url, $options) use (&$sent) {
            $sent[$url] = $options;
            return ['', strpos($url, 'mozilla') ? 410 : 201];
        };
        try {
            pushTestBack(9);
            assertSame(0, pushDeliver($now), '9 checks are not enough');
            assertSame(2, pushWaiting());

            pushTestBack(10);
            assertSame(1, pushDeliver($now), 'one sent, the other ended by its browser');
            assertSame(0, pushWaiting(), 'both dropped');
            assertSame(0, pushDeliver($now), 'nothing twice');
        } finally {
            unset($GLOBALS['SANAKAN_HTTP']);
        }

        $options = $sent['https://fcm.googleapis.com/fcm/send/one'];
        assertSame('POST', $options['method']);
        assertSame(0, $options['follow_location']);
        assertContains('Content-Encoding: aes128gcm', $options['header']);
        assertContains('TTL: ' . PUSH_TTL, $options['header']);
        assertContains('Authorization: vapid t=', $options['header']);
        assertContains(', k=' . base64UrlEncode(pushKeys()['public']), $options['header']);
        $message = json_decode(pushTestDecrypt($options['content'], $browsers[0]), true);
        assertSame('Sanakan wrócił', $message['title']);
        assertSame('/state/', $message['url']);
        assertContains('nie było go', $message['body']);
    });

    test('a subscription waiting longer than 30 days is dropped unsent', function () {
        $now = time();
        $subscription = pushTestBrowser()['subscription'];
        assertSame('ok', pushSubscribe($subscription, $now - PUSH_KEEP, pushTestOffline($now - PUSH_KEEP - 600)));
        $called = false;
        $GLOBALS['SANAKAN_HTTP'] = function () use (&$called) {
            $called = true;
            return ['', 201];
        };
        try {
            pushTestBack(10);
            assertSame(0, pushDeliver($now));
        } finally {
            unset($GLOBALS['SANAKAN_HTTP']);
        }
        assertFalse($called);
        assertSame(0, pushWaiting());
    });
