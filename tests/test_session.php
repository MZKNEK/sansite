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
