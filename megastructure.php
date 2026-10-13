<?php
    // The hidden ways of the Megastructure that lead to the Net Terminal Gene
    // (inc/megastructure.php): asked by the 404 page (js/404.js), the motto of
    // the home page (js/home.js), the console and the Netsphere's terminal
    // (js/safeguard.js, js/netsphere.js). Its secret is SAFEGUARD_SECRET in
    // inc/config.php; without it every answer is empty.
    require __DIR__ . '/inc/auth.php';
    require __DIR__ . '/inc/megastructure.php';
    // it keeps the tickets used and the snake's games (inc/data)
    installDataErrorHandler();

    // where the Safeguard's terminal leads once told to remove the
    // Megastructure (js/netsphere.js): the site's 500 page, as if it had worked
    if (isset($_GET['awaria'])) {
        sendErrorPage(500);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    sendSafeguardHeader();

    $secret = safeguardSecret();
    $now = new DateTimeImmutable('now');
    // the preview also skips the waits and the days of the finds the server
    // witnesses (megaWitness())
    $preview = defined('SAFEGUARD_PRETEND_TIME') && SAFEGUARD_PRETEND_TIME;
    // only the preview (tools/preview/config.php) lets the cookie "safeguard-time",
    // "HH:MM" Polish time, pretend the hour, to try out the Builders' door
    if ($preview
        && preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string)($_COOKIE['safeguard-time'] ?? ''), $time))
        $now = new DateTimeImmutable('today ' . $time[1] . ':' . $time[2], new DateTimeZone('Europe/Warsaw'));
    // a game of the snake comes as a POST, as its way may be long (js/500.js);
    // the account asking keeps its best game
    $user = siteUser();
    echo json_encode(megastructureAnswer($secret, $_POST + $_GET, $now, $preview, $user['id'] ?? ''), JSON_UNESCAPED_UNICODE);
