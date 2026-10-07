<?php
    // inc/autoblock.php and inc/cloudflare.php (the parts that need no network).
    // The Cloudflare configuration is defined here, so the other tests can use
    // it; automatic blocking still follows readData('settings')['autoBlock'].

    if (!defined('CLOUDFLARE_API_TOKEN')) define('CLOUDFLARE_API_TOKEN', 'test-token');
    if (!defined('CLOUDFLARE_ACCOUNT_ID')) define('CLOUDFLARE_ACCOUNT_ID', 'acc123');
    if (!defined('CLOUDFLARE_LIST')) define('CLOUDFLARE_LIST', 'sanakan_blokada');

    test('automatic blocking follows the panel setting', function () {
        assertTrue(cloudflareConfigured());
        assertFalse(autoBlockEnabled(), 'off until the panel turns it on');
        writeData('settings', ['autoBlock' => true]);
        assertTrue(autoBlockEnabled());
        writeData('settings', ['autoBlock' => false]);
        assertFalse(autoBlockEnabled());
    });

    test('a released address is remembered', function () {
        autoBlockReleased('8.8.8.8');
        assertTrue(isset(readData('autoblock-released')['8.8.8.8']));
    });

    test('cloudflareListsPath uses the account id', function () {
        assertSame('/accounts/acc123/rules/lists', cloudflareListsPath());
    });
