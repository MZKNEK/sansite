<?php
    // inc/auth.php: text, local links, address matching, sessions and roles.

    test('cutText cuts in characters, also without mbstring', function () {
        assertSame('abc', cutText('abcdef', 3));
        assertSame('ąć', cutText('ąćę', 2));
        assertSame('', cutText('abc', 0));
        assertSame('abc', cutText('abc', 10));
    });

    test('localPath refuses anything that is not a local path', function () {
        $saved = $_SERVER['SCRIPT_NAME'] ?? null;
        $_SERVER['SCRIPT_NAME'] = '/i/index.php';
        assertSame('/i/?p=x', localPath('/i/?p=x'));
        assertSame('/', localPath('//evil.example/x'), 'a protocol-relative path');
        assertSame('/', localPath("/a\nb"), 'a header injection');
        assertSame('/', localPath('http://evil.example'));
        assertSame('/', localPath(''));
        assertSame('/', localPath('/\\evil'), 'a leading backslash');
        assertSame('/a\\b', localPath('/a\\b'), 'a backslash inside is fine');
        if ($saved === null)
            unset($_SERVER['SCRIPT_NAME']);
        else
            $_SERVER['SCRIPT_NAME'] = $saved;
    });

    test('localBack keeps only local forms', function () {
        assertSame('?a=1', localBack('?a=1'));
        assertSame('./x', localBack('./x'));
        assertSame('./', localBack('http://evil.example'));
        assertSame('./', localBack('/abs'));
        assertSame('./', localBack(''));
    });

    test('sameAddress treats an IPv6 /64 as one', function () {
        assertTrue(sameAddress('8.8.8.8', '8.8.8.8'));
        assertFalse(sameAddress('8.8.8.8', '8.8.4.4'));
        assertTrue(sameAddress('2001:db8:1:2::1', '2001:db8:1:2::ffff'));
        assertFalse(sameAddress('2001:db8:1:2::1', '2001:db8:1:3::1'));
        assertFalse(sameAddress('8.8.8.8', '2001:db8::1'));
        assertFalse(sameAddress('', '8.8.8.8'));
    });

    test('sessionKey is a short hash of the id', function () {
        assertMatches('/^[0-9a-f]{20}$/', sessionKey('abc'));
        assertSame(sessionKey('abc'), sessionKey('abc'));
        assertTrue(sessionKey('abc') !== sessionKey('abd'));
    });

    test('deviceName reads the user agent', function () {
        assertSame('Firefox 130 · Windows', deviceName('Mozilla/5.0 (Windows NT 10.0; rv:130.0) Gecko/20100101 Firefox/130.0'));
        assertSame('Chrome 120 · Android', deviceName('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/120.0 Mobile'));
        assertSame('nieznane urządzenie', deviceName(''));
    });

    test('roleBadge and roleNames follow BOT_ROLES', function () {
        $roles = ['onGuild' => true, 'dev' => true, 'admin' => false, 'semiAdmin' => false,
            'tester' => false, 'moderator' => false, 'user' => true];
        $badge = roleBadge($roles);
        assertSame('dev', $badge['key']);
        assertSame(9, $badge['level']);
        assertSame(['dev', 'user'], roleNames($roles));

        assertSame('out', roleBadge(['onGuild' => false])['key']);
        assertSame('none', roleBadge(['onGuild' => true])['key']);
        assertNull(roleBadge(null));
    });
