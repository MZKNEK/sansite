<?php
    // inc/diag.php and inc/cloudflare.php: probe states, log parsing, scanners
    // and Cloudflare ranges.

    test('diagIsCloudflare knows the Cloudflare ranges', function () {
        assertTrue(diagIsCloudflare('173.245.48.1'));
        assertTrue(diagIsCloudflare('104.16.5.5'));
        assertTrue(diagIsCloudflare('2606:4700::1111'));
        assertFalse(diagIsCloudflare('8.8.8.8'));
        assertFalse(diagIsCloudflare('2001:db8::1'));
        assertFalse(diagIsCloudflare('not an ip'));
    });

    test('diagProbeState and diagProbeText', function () {
        assertSame('fail', diagProbeState([0, 0]));
        assertSame('ok', diagProbeState([200, 500]));
        assertSame('slow', diagProbeState([200, DIAG_SLOW_MS]));
        assertSame('ok', diagProbeState([404, 100]));
        assertSame('fail', diagProbeState([500, 100]));
        assertNull(diagProbeState(null));

        assertSame('bez odpowiedzi: brak odpowiedzi w czasie', diagProbeText([0, 0, 28]));
        assertSame('522: Cloudflare nie połączył się z serwerem', diagProbeText([522, 10]));
        assertSame('200, 1,2 s', diagProbeText([200, 1200]));
        assertSame('404, 100 ms', diagProbeText([404, 100]));
    });

    test('diagUpstreamMs adds the PHP times', function () {
        assertSame(12, diagUpstreamMs('0.012'));
        assertSame(16, diagUpstreamMs('0.012, 0.004'));
        assertSame(1500, diagUpstreamMs('1.5'));
        assertSame(0, diagUpstreamMs('-'));
        assertSame(0, diagUpstreamMs(''));
    });

    test('diagPath cuts the query off', function () {
        assertSame('/i/', diagPath('/i/?p=x'));
        assertSame('/wp-login.php', diagPath('/wp-login.php?a=1'));
        assertSame('?x', diagPath('?x'));
        assertSame('', diagPath(''));
    });

    test('diagProbePath knows what no visitor asks for', function () {
        assertTrue(diagProbePath('/.env'));
        assertTrue(diagProbePath('/wp-login.php'));
        assertTrue(diagProbePath('/evil.php'));
        assertFalse(diagProbePath('/i/index.php'), 'a PHP file of the site');
        assertFalse(diagProbePath('/status.php'));
        assertFalse(diagProbePath('/robots.txt'));
        assertFalse(diagProbePath('/'));
    });

    test('diagScannerReason names the reason', function () {
        assertContains('sqlmap', (string)diagScannerReason(['ua' => 'sqlmap/1.7'], '/'));
        assertSame('zepsute zapytanie', diagScannerReason(['ua' => '', 's' => 400], ''));
        assertSame('szuka /.env', diagScannerReason(['ua' => 'Mozilla'], '/.env'));
        assertNull(diagScannerReason(['ua' => 'Mozilla/5.0 (X11)'], '/i/'));
    });

    test('diagAddressKey groups IPv6 by its /64', function () {
        assertSame('8.8.8.8', diagAddressKey('8.8.8.8'));
        assertSame(bin2hex(substr(inet_pton('2001:db8::'), 0, 8)), diagAddressKey('2001:db8::1'));
        assertSame('not-ip', diagAddressKey('not-ip'));
    });

    test('cloudflareTarget blanks private and old addresses', function () {
        assertSame('8.8.8.8', cloudflareTarget('8.8.8.8'));
        assertNull(cloudflareTarget('192.168.1.1'));
        assertNull(cloudflareTarget('127.0.0.1'));
        assertNull(cloudflareTarget('nope'));
        assertSame('2606:4700:4700::/64', cloudflareTarget('2606:4700:4700::1111'));
    });
