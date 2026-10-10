<?php
    // The Discord account for the static pages (the home page, the privacy
    // notice, the 404, Skalpelator and USkalpelator cannot render it, so they
    // ask here, js/account.js): the account menu of the top right corner
    // (accountMenuHtml() in inc/auth.php), whether the account may read the API,
    // and for the croppers whether it has a folder of its own in the gallery to
    // save a card into, with the CSRF token that saving needs. ?back= is the
    // page asking, where logging out comes back to. ?login starts the login
    // and comes back to ?back=, or to the home page. A POST with the CSRF token
    // logs out, or ends trying other rights (the bar of testRights()); every
    // page's account menu sends it here, with the page to go back to.
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
        startLogin(localPath($_GET['back'] ?? ''));
        exit;
    }

    // only for this visitor, never kept by a browser or Cloudflare
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');

    $user = siteUser();
    // the HUD colour chosen in the profile also goes into cookies, so the static
    // pages (the home page, the privacy notice, the 404) can apply it before
    // the first paint, not only once this answer is back (js/hud.js); a
    // session that ended without logging out (expired, or ended in the panel)
    // takes them along too
    if ($user !== null) {
        $badge = roleBadge(siteRoles());
        setcookie('hud', hudMode($user['id']), time() + 31536000, '/');
        setcookie('hudrole', hudColorKey($user['id'], $badge), time() + 31536000, '/');
    } else {
        forgetHudCookies();
    }
    echo json_encode($user === null ? [
        'login' => authConfigured()
    ] : [
        'menu' => accountMenuHtml($user, siteRoles(), localPath($_GET['back'] ?? '')),
        'api' => canViewApiId($user['id']),
        'own' => isGalleryUploaderId($user['id']),
        'csrf' => siteCsrf(),
        'hud' => hudMode($user['id']),
        'role' => hudColorKey($user['id'], $badge),
        'flash' => takeFlash()
    ], JSON_UNESCAPED_UNICODE);
