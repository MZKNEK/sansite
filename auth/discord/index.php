<?php
    // One public endpoint for Discord login and its OAuth2 callback. The
    // session remembers which page or gallery folder started the login.
    require __DIR__ . '/../../inc/auth.php';
    installDataErrorHandler();
    header('Cache-Control: no-store, private');

    if (!authConfigured()) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Logowanie przez Discord nie jest skonfigurowane.';
        exit;
    }

    if (isset($_GET['state']) || isset($_GET['code']) || isset($_GET['error'])) {
        header('Location: ' . finishLogin());
        exit;
    }

    startLogin(localPath($_GET['back'] ?? ''));
