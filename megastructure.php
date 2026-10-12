<?php
    // The hidden ways of the Megastructure that lead to the Net Terminal Gene
    // (inc/megastructure.php): asked by the 404 page (js/404.js), the motto of
    // the home page (js/home.js), the console and the Netsphere's terminal
    // (js/safeguard.js, js/netsphere.js). Its secret is SAFEGUARD_SECRET in
    // inc/config.php; without it every answer is empty.
    require __DIR__ . '/inc/auth.php';
    require __DIR__ . '/inc/megastructure.php';

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');

    $secret = safeguardSecret();
    $now = new DateTimeImmutable('now');
    // only the preview (tools/preview/config.php) lets the cookie "safeguard-time",
    // "HH:MM" Polish time, pretend the hour, to try out the Builders' door
    if (defined('SAFEGUARD_PRETEND_TIME') && SAFEGUARD_PRETEND_TIME
        && preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string)($_COOKIE['safeguard-time'] ?? ''), $time))
        $now = new DateTimeImmutable('today ' . $time[1] . ':' . $time[2], new DateTimeZone('Europe/Warsaw'));
    echo json_encode(megastructureAnswer($secret, $_GET, $now), JSON_UNESCAPED_UNICODE);
