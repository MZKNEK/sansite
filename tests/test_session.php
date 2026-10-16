<?php
    // inc/auth.php: the logged-in account in a session, logging out, and trying
    // other rights for a while. A real session is started in the CLI, pointed at
    // the temporary data folder (siteSession()).

    if (!defined('DISCORD_CLIENT_ID')) define('DISCORD_CLIENT_ID', 'test');
    if (!defined('DISCORD_CLIENT_SECRET')) define('DISCORD_CLIENT_SECRET', 'test');
    if (!defined('DISCORD_REDIRECT_URI')) define('DISCORD_REDIRECT_URI', 'https://example.test/auth/discord/');

    test('siteUser reads the account of the session', function () {
        sessionFor('sanakan-test-1', ['id' => '111', 'name' => 'Test', 'avatar' => 'a']);
        assertTrue(authConfigured());
        $user = siteUser();
        assertSame('111', $user['id']);
        assertSame('Test', $user['name']);
        assertSame('csrf-token', siteCsrf());

        $_POST['csrf'] = 'csrf-token';
        assertTrue(checkCsrf());
        $_POST['csrf'] = 'wrong';
        assertFalse(checkCsrf());
        unset($_POST['csrf']);
        sessionEnd();
    });

    test('logout ends the session', function () {
        sessionFor('sanakan-test-2', ['id' => '222', 'name' => 'Out', 'avatar' => 'a']);
        assertSame('222', siteUser()['id']);
        logout();
        assertNull(siteUser());
        sessionEnd();
    });

    test('an admin can try other rights for a while', function () {
        sessionFor('sanakan-test-3', ['id' => '999', 'name' => 'Root', 'avatar' => 'a']);
        assertNull(testRights());

        setTestRights(['gallery' => 'viewer'], 15);
        $test = testRights('999');
        assertSame('viewer', $test['gallery']);
        assertTrue($test['until'] > time());
        assertContains('galeria: oglądający', testRightsText($test));
        assertFalse(isGalleryAdminId('999'), 'the tried gallery right wins');
        assertTrue(canViewGalleryId('999'));
        assertTrue(isPanelAdminId('999'), 'a right not tried stays the real one');

        setTestRights(null);
        assertNull(testRights());
        sessionEnd();
    });

    test('a log out in the very second of a login still ends it', function () {
        foreach ([null, '111'] as $whose) {
            sessionFor('sanakan-gen-' . ($whose ?? 'all'), ['id' => '111', 'name' => 'Test', 'avatar' => 'a']);
            // as the login gives it (inc/auth/oauth.php)
            $_SESSION['generation'] = sessionGeneration('111');
            assertSame('111', siteUser()['id']);
            assertTrue(endSessions($whose));
            assertNull(siteUser(), $whose === null ? 'log out everyone' : 'log out the account');
            sessionEnd();
        }
    });

    test('the session logging the others out stays, the others end', function () {
        foreach ([null, '111'] as $whose) {
            sessionFor('sanakan-keep-' . ($whose ?? 'all'), ['id' => '111', 'name' => 'Test', 'avatar' => 'a']);
            $_SESSION['generation'] = sessionGeneration('111');
            $login = $_SESSION['login_time'];
            $other = $_SESSION['generation'];
            assertTrue(endSessions($whose, true));
            assertSame('111', siteUser()['id'], 'the one asking');
            assertSame($login, $_SESSION['login_time'], 'its login lasts no longer for it');
            // another device, still of the generation before
            $_SESSION['generation'] = $other;
            assertNull(siteUser());
            sessionEnd();
        }
    });

    test('a login lasts SESSION_DAYS however often it is used', function () {
        sessionFor('sanakan-days-1', ['id' => '111', 'name' => 'Test', 'avatar' => 'a']);
        $_SESSION['login_time'] = time() - SESSION_DAYS * 86400 + 60;
        assertSame('111', siteUser()['id']);
        $_SESSION['login_time'] = time() - (SESSION_DAYS + 1) * 86400;
        assertNull(siteUser());
        sessionEnd();
    });

    test('a session from before the generations goes by the times', function () {
        writeData('sessions', ['since' => time() - 100]);
        sessionFor('sanakan-legacy-1', ['id' => '111', 'name' => 'Test', 'avatar' => 'a']);
        $_SESSION['login_time'] = time() - 200;
        assertNull(siteUser(), 'logged in before the log out');
        sessionEnd();
        sessionFor('sanakan-legacy-2', ['id' => '111', 'name' => 'Test', 'avatar' => 'a']);
        $_SESSION['login_time'] = time() - 50;
        assertSame('111', siteUser()['id']);
        assertSame(sessionGeneration('111'), $_SESSION['generation'], 'and takes the generation of now');
        sessionEnd();
    });

    test('a role the bot has not confirmed for a day gives no rights', function () {
        writeData('roles', ['777' => ['roles' => ['onGuild' => true, 'tester' => true, 'user' => true], 'checked' => time() - BOT_ROLES_TRUSTED - 60, 'tried' => time()]]);
        assertFalse(hasBotRole('777', API_ROLES));
        assertFalse(hasServerRole('777'));
        assertTrue(botRoles('777')['tester'], 'it still shows');
        writeData('roles', ['777' => ['roles' => ['onGuild' => true, 'tester' => true, 'user' => true], 'checked' => time() - 60]]);
        assertTrue(hasBotRole('777', API_ROLES));
        assertTrue(hasServerRole('777'));
    });

    test('a cookie naming no stored session starts none and writes no file', function () {
        sessionFor('sanakan-exists-1', ['id' => '111', 'name' => 'Test', 'avatar' => 'a']);
        sessionEnd();
        $before = glob(dataDir() . '/sessions/sess_*');
        foreach (['made-up-session-id', '../../config', 'a/b', str_repeat('a', 200)] as $cookie) {
            $_COOKIE[SITE_SESSION] = $cookie;
            assertFalse(sessionExists(), $cookie);
            assertNull(siteUser());
            assertNull(takeFlash());
            assertSame(PHP_SESSION_NONE, session_status(), 'no session started for ' . $cookie);
        }
        $_COOKIE[SITE_SESSION] = ['an', 'array'];
        assertFalse(sessionExists());
        unset($_COOKIE[SITE_SESSION]);
        assertSame($before, glob(dataDir() . '/sessions/sess_*'));
    });

    test('a session the server keeps is still found by its cookie', function () {
        sessionFor('sanakan-exists-2', ['id' => '111', 'name' => 'Test', 'avatar' => 'a']);
        session_write_close();
        assertTrue(sessionExists());
        assertSame('111', siteUser()['id']);
        logout();
        assertFalse(sessionExists(), 'a session logged out is gone, though the browser still sends its cookie');
        sessionEnd();
    });

    // a session file as PHP writes it, unused for $age seconds
    function sessionFixture($id, array $data, $age)
    {
        sessionFor($id, ['id' => '0', 'name' => '', 'avatar' => '']);
        $_SESSION = $data;
        $encoded = session_encode();
        session_abort();
        unset($_COOKIE[SITE_SESSION]);
        $file = dataDir() . '/sessions/sess_' . $id;
        file_put_contents($file, $encoded);
        touch($file, time() - $age);

        return $file;
    }

    test('the keys of a session are read by walking its values', function () {
        sessionFor('sanakan-keys-1', ['id' => '0', 'name' => '', 'avatar' => '']);
        $_SESSION = ['oauth_return' => '/gallery_user|x;gallery_shares|', 'n' => null, 'b' => true, 'i' => -3, 'd' => 0.5,
            'nested' => ['k' => ['gallery_user' => 1], 2 => 'ż|"'], 'gallery_csrf' => str_repeat('f', 64)];
        $keys = sessionDataKeys(session_encode());
        session_abort();
        unset($_COOKIE[SITE_SESSION]);
        assertSame(['oauth_return', 'n', 'b', 'i', 'd', 'nested', 'gallery_csrf'], $keys);
        assertSame([], sessionDataKeys(''));
        foreach (['gallery_user', 'a|s:9:"short";', 'a|O:8:"stdClass":0:{}', 'a|a:1:{i:0;'] as $broken)
            assertNull(sessionDataKeys($broken), $broken);
    });

    test('maintenance deletes only the sessions without an account left unused', function () {
        $old = ANONYMOUS_SESSION_SECONDS + 60;
        $gone = [
            sessionFixture('sanakan-prune-login', ['oauth_state' => 'abc', 'oauth_return' => '/gallery_user|'], $old),
            sessionFixture('sanakan-prune-csrf', ['gallery_csrf' => 'abc', 'gallery_flash' => 'Sesja wygasła.'], $old),
            sessionFixture('sanakan-prune-empty', [], $old)
        ];
        $kept = [
            sessionFixture('sanakan-prune-fresh', ['oauth_state' => 'abc'], 60),
            sessionFixture('sanakan-prune-user', ['gallery_user' => ['id' => '111'], 'login_time' => time()], $old),
            sessionFixture('sanakan-prune-shares', ['gallery_shares' => ['token']], $old)
        ];
        $broken = dataDir() . '/sessions/sess_sanakan-prune-broken';
        file_put_contents($broken, 'not a session');
        touch($broken, time() - $old);
        $kept[] = $broken;

        assertSame(3, pruneAnonymousSessions(time(), null, true), 'a dry run counts');
        foreach (array_merge($gone, $kept) as $file)
            assertTrue(is_file($file), 'a dry run deletes nothing');
        assertSame(3, maintenanceRun(time())['sessions']);
        foreach ($gone as $file)
            assertFalse(is_file($file), basename($file));
        foreach ($kept as $file)
            assertTrue(is_file($file), basename($file));
        assertSame(0, pruneAnonymousSessions(time()));
    });
