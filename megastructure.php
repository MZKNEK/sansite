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

    // Anonymous players need CSRF protection too, without a session of their
    // own (megaCsrf()); ordinary reads need no token at all.
    $secret = safeguardSecret();
    if (isset($_GET['csrf'])) {
        echo json_encode(['csrf' => megaCsrf($secret)]);
        exit;
    }
    // Match the dispatcher's input filtering before choosing the guards;
    // array-valued decoy parameters must not bypass ticket throttling.
    $query = array_filter($_POST + $_GET, 'is_string');
    $changes = isset($query['snake']) || (isset($query['witness']) && is_string($query['witness']) && isset(MEGA_TICKETS[$query['witness']]));
    if ($changes && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['error' => 'method']);
        exit;
    }
    if ($changes && !megaCheckCsrf($secret, $_POST['csrf'] ?? null)) {
        http_response_code(403);
        echo json_encode(['error' => 'csrf']);
        exit;
    }
    if (isset($query['ticket']) && !isset($query['snake']) && !isset($query['witness'])
        && !megaTicketAllowed($_SERVER['REMOTE_ADDR'] ?? '', time())) {
        http_response_code(429);
        header('Retry-After: ' . MEGA_TICKET_RATE_SECONDS);
        echo json_encode(['error' => 'rate']);
        exit;
    }

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
    echo json_encode(megastructureAnswer($secret, $query, $now, $preview, $user['id'] ?? ''), JSON_UNESCAPED_UNICODE);
