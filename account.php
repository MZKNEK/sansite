<?php
    // The Discord account for the home page (index.html is static, so it asks
    // here): the account menu of the top right corner (accountMenuHtml() in
    // inc/auth.php) and whether the account may read the API. ?login starts the
    // login and comes back to the home page. A POST with the CSRF token logs
    // out, or ends trying other rights (the bar of testRights()); every page's
    // account menu sends it here, with the page to go back to.
    require __DIR__ . '/inc/auth.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string)($_POST['action'] ?? '');
        if (authConfigured() && siteUser() && checkCsrf()) {
            if ($action === 'logout')
                logout();
            if ($action === 'test-off' && testRights() !== null) {
                setTestRights(null);
                addHistory('test', 'Koniec podglądu z innymi uprawnieniami.');
                setFlash('Wróciły twoje prawdziwe uprawnienia.');
            }
        }
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
    // the HUD colour chosen in the profile also goes into cookies, so the pages
    // that render no account menu (the home page, the privacy notice, the 404,
    // the public status) can apply it too (js/hud.js)
    if ($user !== null) {
        $badge = roleBadge(siteRoles());
        setcookie('hud', hudMode($user['id']), time() + 31536000, '/');
        setcookie('hudrole', hudColorKey($user['id'], $badge), time() + 31536000, '/');
    }
    echo json_encode($user === null ? [
        'login' => authConfigured()
    ] : [
        'menu' => accountMenuHtml($user, siteRoles(), siteRoot()),
        'api' => canViewApiId($user['id']),
        'hud' => hudMode($user['id']),
        'role' => hudColorKey($user['id'], $badge),
        'flash' => takeFlash()
    ], JSON_UNESCAPED_UNICODE);
