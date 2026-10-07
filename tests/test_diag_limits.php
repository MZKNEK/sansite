<?php
    // inc/diag.php: the caps the summaries keep and the range boundaries.

    test('diagIsCloudflare at the range edges', function () {
        assertTrue(diagIsCloudflare('104.16.0.0'));
        assertTrue(diagIsCloudflare('104.23.255.255'), 'the end of 104.16.0.0/13');
        assertTrue(diagIsCloudflare('104.24.0.0'), 'the next range');
        assertTrue(diagIsCloudflare('172.64.0.0'));

        assertFalse(diagIsCloudflare('104.15.255.255'), 'just before');
        assertFalse(diagIsCloudflare('104.28.0.0'), 'just after');
        assertFalse(diagIsCloudflare('172.63.255.255'));
        assertFalse(diagIsCloudflare('172.72.0.0'));
    });

    test('sameAddress on the IPv6 /64 edges', function () {
        assertTrue(sameAddress('2001:db8:0:0::1', '2001:db8:0:0:ffff:ffff:ffff:ffff'));
        assertFalse(sameAddress('2001:db8:0:1::1', '2001:db8:0:2::1'), 'a different /64');
    });

    test('diagRecordScanners caps the probed paths', function () {
        $probes = [];
        for ($i = 0; $i < DIAG_PROBES_KEEP + 5; $i++)
            $probes['/p' . $i] = true;
        diagRecordScanners(['9.9.9.9' => [
            'n' => 1, 'php' => 0, 'cc' => 'US', 'ua' => 'ua', 'reason' => 'skaner',
            'paths' => ['/p0' => 1], 'probes' => $probes,
        ]], time());

        assertSame(DIAG_PROBES_KEEP, count(diagScanners()['9.9.9.9']['probes']));
    });

    test('diagRecordPages keeps the busiest pages', function () {
        $pages = [];
        for ($i = 0; $i < DIAG_PAGES_KEEP + 5; $i++)
            $pages['/p' . $i] = [1, 10, 10];
        diagRecordPages($pages, time());

        assertSame(DIAG_PAGES_KEEP, count(diagPages(1000)));
    });
