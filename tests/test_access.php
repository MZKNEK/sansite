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
        assertFalse(inAccessList('apiViewers', 'anyone', false), 'never for the gallery folder');
    });

    test('panel admins come from the configuration alone', function () {
        assertTrue(realPanelAdminId('999'));
        assertTrue(isPanelAdminId('999'));
        assertFalse(realPanelAdminId('111'));
        assertFalse(isPanelAdminId('111'));
    });

    test('what each list gives', function () {
        assertTrue(isGalleryAdminId('111'));
        assertFalse(isGalleryAdminId('222'));
        assertTrue(canViewGalleryId('111'));
        assertTrue(canViewGalleryId('222'));
        assertFalse(canViewGalleryId('333'), 'an uploader does not see the whole gallery');
        assertTrue(isGalleryUploaderId('333'));
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

    test('userFilesLimit falls back to the default', function () {
        writeData('access', ['uploadLimits' => ['333' => 5]]);
        assertSame(5, userFilesLimit('333'));
        assertSame(USER_FILES_DEFAULT, userFilesLimit('888'));
    });
