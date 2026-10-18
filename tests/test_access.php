<?php
    // inc/auth.php: the access lists, the roles on the bot server and what each
    // account may open. authConfigured() finds no inc/config.php, so the
    // constants below are the whole configuration (define() works the same as
    // const for constant()).

    if (!defined('PANEL_ADMINS')) define('PANEL_ADMINS', ['999']);
    if (!defined('GALLERY_ADMINS')) define('GALLERY_ADMINS', ['111']);
    if (!defined('GALLERY_VIEWERS')) define('GALLERY_VIEWERS', ['222']);
    if (!defined('GALLERY_UPLOADERS')) define('GALLERY_UPLOADERS', ['333']);
    if (!defined('GALLERY_PRIVATE')) define('GALLERY_PRIVATE', ['444']);
    if (!defined('API_VIEWERS')) define('API_VIEWERS', true);

    test('configList and panelList read the configuration and the panel', function () {
        assertSame(['111'], configList('GALLERY_ADMINS'));
        assertTrue(configList('API_VIEWERS'), 'a list set to true');
        assertTrue(inAccessList('galleryAdmins', '111', false));
        assertFalse(inAccessList('galleryAdmins', '222', false));

        writeData('access', ['galleryViewers' => ['777' => ['note' => 'z panelu', 'added' => 1, 'by' => '999']]]);
        assertTrue(inAccessList('galleryViewers', '777', true), 'an entry added in the panel');
        assertFalse(inAccessList('galleryViewers', '888', true));
        assertSame('777', (string)array_key_first(panelList('galleryViewers')), 'the key comes back as a number');
    });

    test('a list set to true lets in everyone only where allowAll', function () {
        assertTrue(inAccessList('apiViewers', 'anyone', true));
        assertFalse(inAccessList('apiViewers', 'anyone', false), 'not where allowAll is false');
    });

    test('panel admins come from the configuration alone', function () {
        assertTrue(realPanelAdminId('999'));
        assertTrue(isPanelAdminId('999'));
        assertFalse(realPanelAdminId('111'));
        assertFalse(isPanelAdminId('111'));
    });

    test('what each list gives', function () {
        writeData('roles', array_fill_keys(['111', '222', '333', '555'], ['roles' => ['onGuild' => true, 'user' => true], 'checked' => time()]));
        assertTrue(isGalleryAdminId('111'));
        assertFalse(isGalleryAdminId('222'));
        assertTrue(canViewGalleryId('111'));
        assertTrue(canViewGalleryId('222'));
        assertFalse(canViewGalleryId('333'), 'an account with only its own folder does not see the whole gallery');
        assertTrue(isGalleryUploaderId('555'), 'every account with a role has a folder of its own');
        assertTrue(isGalleryUploaderId('222'), 'a viewer too');
        assertFalse(isGalleryUploaderId('333'), 'GALLERY_UPLOADERS blocks it');
        assertTrue(isGalleryUploaderId('111'), 'a gallery admin too, so it stays should the rights go');
        assertTrue(canSeePrivateGalleryId('444'));
        assertTrue(canSeePrivateGalleryId('999'), 'a panel admin always');
        assertFalse(canSeePrivateGalleryId('111'), 'a gallery admin is not a private viewer');
        assertTrue(canViewApiId('111'), 'API_VIEWERS is true');
        assertTrue(canViewApiId('999'));
    });

    test('a role on the bot server opens the API and the private commands', function () {
        writeData('roles', [
            '666' => ['roles' => ['onGuild' => true, 'tester' => true], 'checked' => time()],
            '700' => ['roles' => ['onGuild' => true, 'dev' => true], 'checked' => time()],
        ]);
        assertTrue(canViewApiId('666'), 'a tester may read the API');
        assertTrue(canSeePrivateCommandsId('700'), 'a dev sees the moderator commands');
        assertFalse(canSeePrivateCommandsId('666'), 'a tester does not');
    });

    test('a folder of its own needs a role on the bot server', function () {
        writeData('roles', [
            '560' => ['roles' => ['onGuild' => true, 'user' => true], 'checked' => time()],
            '561' => ['roles' => ['onGuild' => true], 'checked' => time()],
            '562' => ['roles' => ['onGuild' => false], 'checked' => time()],
        ]);
        assertTrue(isGalleryUploaderId('560'), 'user is enough');
        assertFalse(isGalleryUploaderId('561'), 'on the server without a role');
        assertFalse(isGalleryUploaderId('562'), 'off the server');
        assertFalse(isGalleryUploaderId('563'), 'never asked the bot');
    });

    test('the panel blocks a folder of its own, and unblocking gives it back', function () {
        writeData('roles', ['556' => ['roles' => ['onGuild' => true, 'moderator' => true], 'checked' => time()]]);
        writeData('access', ['galleryUploaders' => ['556' => ['note' => '', 'added' => 1, 'by' => '999']]]);
        assertFalse(isGalleryUploaderId('556'));
        writeData('access', []);
        assertTrue(isGalleryUploaderId('556'));
    });

    test('userFilesLimit falls back to the default', function () {
        writeData('access', ['uploadLimits' => ['333' => 5]]);
        assertSame(5, userFilesLimit('333'));
        assertSame(USER_FILES_DEFAULT, userFilesLimit('888'));
    });

    test('LV.2 VIP counts only with a role on the bot server, under a higher one on the badge', function () {
        writeData('roles', [
            '580' => ['roles' => ['onGuild' => true, 'user' => true], 'checked' => time()],
            '581' => ['roles' => ['onGuild' => true, 'moderator' => true, 'user' => true], 'checked' => time()],
            '582' => ['roles' => ['onGuild' => true], 'checked' => time()],
            '583' => ['roles' => ['onGuild' => true, 'user' => true], 'checked' => time()],
        ]);
        $entry = ['note' => '', 'added' => 1, 'by' => '999'];
        writeData('access', ['vipAccounts' => ['580' => $entry, '581' => $entry, '582' => $entry]]);

        assertTrue(isVipId('580'));
        $badge = roleBadge(botRoles('580'));
        assertSame(['vip', 2, 'VIP'], [$badge['key'], $badge['level'], $badge['label']]);
        assertSame(['user'], roleNames(botRoles('580')), 'not a role of the server');

        assertTrue(isVipId('581'), 'a higher role keeps the level');
        assertSame('moderator', roleBadge(botRoles('581'))['key'], 'and shows instead of it');

        assertFalse(isVipId('582'), 'on the list without a role');
        assertSame('none', roleBadge(botRoles('582'))['key']);
        assertFalse(isVipId('583'), 'a role without the list');
        assertSame('user', roleBadge(botRoles('583'))['key']);
        assertSame('vip', roleBadge(withVip('580', ['onGuild' => true, 'user' => true]))['key'], 'roles.json as it is read');
        assertNull(withVip('580', null));
    });
