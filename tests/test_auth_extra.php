<?php
    // inc/auth.php: the accounts at an address, the login log, the devices, the
    // history, the requests and the account menu.

    test('accountsAtAddress groups by address and by IPv6 /64', function () {
        $addresses = [
            '1' => ['8.8.8.8' => [100, 200, 3, 'US', 'ua']],
            '2' => ['8.8.8.9' => [100, 150, 1, 'PL', 'ua2']],
            '3' => ['2001:db8:1:2::5' => [100, 300, 2, 'DE', 'ua3']],
        ];
        $at = accountsAtAddress('8.8.8.8', $addresses);
        assertTrue(isset($at['1']));
        assertFalse(isset($at['2']), 'a different address in the same /24');
        assertSame('8.8.8.8', $at['1'][0]);
        assertSame(200, $at['1'][1]);

        $v6 = accountsAtAddress('2001:db8:1:2::99', $addresses);
        assertTrue(isset($v6['3']), 'the whole /64 is one machine');
    });

    test('history keeps the newest entries first', function () {
        addHistory('upload', 'Dodano i/a.png.');
        addHistory('mkdir', 'Utworzono folder i/x.');
        $entries = historyEntries(10);
        assertSame('mkdir', $entries[0]['action']);
        assertSame('upload', $entries[1]['action']);
        assertSame('', $entries[0]['name'], 'no account for a test');
    });

    test('pendingRequest reads the waiting request', function () {
        writeData('requests', ['gallery' => ['111' => ['name' => 'X', 'note' => 'proszę', 'time' => 10]]]);
        assertSame('proszę', pendingRequest('gallery', '111')['note']);
        assertNull(pendingRequest('gallery', '222'));
        assertNull(pendingRequest('api', '111'));
    });

    test('recordLogin counts and moves the newest to the front', function () {
        recordLogin('111', 'One', 'avatar', 'one');
        recordLogin('111', 'One', 'avatar', 'one');
        recordLogin('222', 'Two', 'avatar', 'two');
        $logins = readData('logins');
        assertSame(2, $logins['111']['count']);
        assertSame('One', $logins['111']['name']);
        assertSame('222', (string)array_key_first($logins), 'the newest first');
    });

    test('accountDevices lists a session and endDevice drops it', function () {
        if (!is_dir(dataDir() . '/sessions'))
            mkdir(dataDir() . '/sessions', 0700, true);
        $key = sessionKey('abc');
        file_put_contents(dataDir() . '/sessions/sess_abc', 'x');
        writeData('devices', ['111' => [$key => [time(), time(), '8.8.8.8', 'PL', 'ua']]]);

        assertTrue(isset(accountDevices('111')[$key]));
        assertTrue(endDevice('111', $key));
        assertFalse(is_file(dataDir() . '/sessions/sess_abc'));
        assertSame([], accountDevices('111'));
    });

    test('the account menu carries the places, the level and the logout', function () {
        sessionFor('sanakan-auth-1', ['id' => '999', 'name' => 'Root', 'avatar' => 'https://cdn/a.png']);
        $menu = accountMenuHtml(['id' => '999', 'name' => 'Root', 'avatar' => 'https://cdn/a.png'],
            ['onGuild' => true, 'dev' => true], '/i/');
        assertContains('account-menu', $menu);
        assertContains('admin/', $menu, 'a panel admin gets the panel link');
        assertContains('Wyloguj', $menu);
        assertContains('csrf-token', $menu);
        assertContains('LV.9', $menu);
        sessionEnd();
    });

    test('flash messages go through the session', function () {
        sessionFor('sanakan-auth-2', ['id' => '111', 'name' => 'T', 'avatar' => 'a']);
        setFlash('Cześć');
        assertSame('Cześć', takeFlash());
        assertNull(takeFlash(), 'taken once');
        sessionEnd();
    });

    test('apiCanView follows the list, siteRoot the script', function () {
        sessionFor('sanakan-auth-3', ['id' => '111', 'name' => 'T', 'avatar' => 'a']);
        assertTrue(apiCanView(), 'API_VIEWERS is true');
        sessionEnd();

        $saved = $_SERVER['SCRIPT_NAME'] ?? null;
        $savedFile = $_SERVER['SCRIPT_FILENAME'] ?? null;
        $_SERVER['SCRIPT_NAME'] = '/i/index.php';
        assertSame('/', siteRoot());
        $_SERVER['SCRIPT_NAME'] = '/sub/i/index.php';
        assertSame('/sub/', siteRoot());

        // with the page's file: any depth, also a page in the root
        $site = str_replace('\\', '/', dirname(__DIR__));
        foreach (['/state/wersje/index.php', '/cmd/zmiany/index.php', '/admin/index.php', '/account.php'] as $page) {
            $_SERVER['SCRIPT_FILENAME'] = $site . $page;
            $_SERVER['SCRIPT_NAME'] = $page;
            assertSame('/', siteRoot(), $page);
            $_SERVER['SCRIPT_NAME'] = '/sub' . $page;
            assertSame('/sub/', siteRoot(), '/sub' . $page);
        }

        foreach (['SCRIPT_NAME' => $saved, 'SCRIPT_FILENAME' => $savedFile] as $key => $value) {
            if ($value === null)
                unset($_SERVER[$key]);
            else
                $_SERVER[$key] = $value;
        }
    });

    test('noteAccountAddress notes the address and the device', function () {
        sessionFor('sanakan-note-1', ['id' => '111', 'name' => 'T', 'avatar' => 'a']);
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'PL';
        $_SERVER['HTTP_USER_AGENT'] = 'UA';
        unset($_SESSION['address_seen']);
        noteAccountAddress('111');

        $addresses = readData('addresses');
        assertSame('PL', $addresses['111']['8.8.8.8'][3]);
        assertSame(1, count(readData('devices')['111']));
        assertSame('UA', $addresses['111']['8.8.8.8'][4]);

        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_CF_IPCOUNTRY'], $_SERVER['HTTP_USER_AGENT'], $_SESSION['address_seen']);
        sessionEnd();
    });

    test('roleBadgeHtml writes the level in the role colour', function () {
        $html = roleBadgeHtml(['onGuild' => true, 'dev' => true]);
        assertContains('role-dev', $html);
        assertContains('LV.9', $html);
        assertSame('', roleBadgeHtml(null));
    });
