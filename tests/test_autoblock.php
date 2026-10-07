<?php
    // inc/autoblock.php and inc/cloudflare.php (the parts that need no network).

    test('automatic blocking is off without Cloudflare and releases are kept', function () {
        assertFalse(autoBlockEnabled(), 'no CLOUDFLARE_* constants');
        autoBlockReleased('8.8.8.8');
        assertTrue(isset(readData('autoblock-released')['8.8.8.8']));
    });

    test('cloudflareListsPath uses the account id', function () {
        if (!defined('CLOUDFLARE_ACCOUNT_ID')) define('CLOUDFLARE_ACCOUNT_ID', 'acc123');
        assertSame('/accounts/acc123/rules/lists', cloudflareListsPath());
    });
