<?php
    // The Discord account for the home page (index.html is static, so it asks
    // here): the account menu of the top right corner (accountMenuHtml() in
    // inc/auth.php) and whether the account may read the API. ?login starts the
    // login and comes back to the home page. A POST with the CSRF token logs
    // out; every page's account menu sends it here, with the page to go back to.
    require __DIR__ . '/inc/auth.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (authConfigured() && siteUser() && checkCsrf() && ($_POST['action'] ?? '') === 'logout')
            logout();
        header('Location: ' . localPath($_POST['back'] ?? ''), true, 303);
        exit;
    }

    if (isset($_GET['login']) && authConfigured()) {
        startLogin(siteRoot());
        exit;
    }

    // only for this visitor, never kept by a browser or Cloudflare
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');

    $user = siteUser();
    echo json_encode($user === null ? [
        'login' => authConfigured()
    ] : [
        'menu' => accountMenuHtml($user, siteRoles(), siteRoot()),
        'api' => canViewApiId($user['id']),
        'flash' => takeFlash()
    ], JSON_UNESCAPED_UNICODE);
