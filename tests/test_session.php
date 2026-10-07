<?php
    // inc/auth.php: the logged-in account in a session, logging out, and trying
    // other rights for a while. A real session is started in the CLI, pointed at
    // the temporary data folder (siteSession()).

    if (!defined('DISCORD_CLIENT_ID')) define('DISCORD_CLIENT_ID', 'test');
    if (!defined('DISCORD_CLIENT_SECRET')) define('DISCORD_CLIENT_SECRET', 'test');
    if (!defined('DISCORD_REDIRECT_URI')) define('DISCORD_REDIRECT_URI', 'https://example.test/i/');

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
