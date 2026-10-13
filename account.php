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
    //
    // The Safeguard's register (inc/auth/finds.php, js/safeguard.js): ?finds
    // says whether there is an account, its finds and the CSRF token; a POST
    // of action=finds with the token and the browser's finds as JSON (key =>
    // time) notes them, with the account's name and avatar for the ranking of
    // the hunters (/lowcy/), and answers with all the account's finds. Those of
    // the way to the gene need the proofs megastructure.php gave with them
    // (proofs, as JSON, key => proof); the ones without, and the steps of the
    // way to the gene the account has not come to yet (megaChainFinds()), are
    // left out and listed as rejected, for the browser to forget. New finds
    // that could not have been made (safeguardCheat()) flag the account's
    // finds as cheated.
    // It sends the password of the X-Safeguard header too, a find of the
    // register (sendSafeguardHeader()), as every static page asks here.
    require __DIR__ . '/inc/auth.php';
    require __DIR__ . '/inc/megastructure.php';
    installDataErrorHandler();

    if (isset($_GET['finds']) || ($_POST['action'] ?? '') === 'finds') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        $user = authConfigured() ? siteUser() : null;
        if ($user === null) {
            echo json_encode(['login' => false]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['login' => true, 'csrf' => siteCsrf(), 'finds' => (object)safeguardFinds($user['id'])]);
            exit;
        }
        if (!checkCsrf()) {
            http_response_code(403);
            echo json_encode(['login' => true, 'error' => 'csrf']);
            exit;
        }
        $proofs = json_decode((string)($_POST['proofs'] ?? ''), true);
        [$found, $rejected] = megaProvenFinds(safeguardSecret(), json_decode((string)($_POST['found'] ?? ''), true), $proofs, time());
        // a proof another account sent first gives this one nothing
        $claimed = $found ? megaClaimProofs($user['id'], $found, $proofs, time()) : [[], []];
        if ($claimed === null) {
            http_response_code(503);
            echo json_encode(['login' => true, 'finds' => (object)safeguardFinds($user['id'])]);
            exit;
        }
        [$found, $taken] = $claimed;
        $before = safeguardFinds($user['id']);
        // a step of the way to the gene only after those before it
        [$found, $skipped] = megaChainFinds($found, $before);
        $rejected = array_merge($rejected, $taken, $skipped);
        $finds = addSafeguardFinds($user['id'], $found, time());
        $cheat = $finds ? safeguardCheat($finds, array_keys(array_diff_key($finds, $before))) : null;
        if ($cheat !== null && safeguardFlag($user['id']) === null && setSafeguardFlag($user['id'], $cheat))
            addHistory('safeguard', 'Safeguard oflagował wpisy w rejestrze jako oszukane: ' . $cheat);
        if ($finds === null)
            http_response_code(503);
        // the name and avatar the ranking of the hunters shows (/lowcy/)
        else if ($finds)
            noteHunter($user);
        echo json_encode(['login' => true, 'finds' => (object)($finds ?? safeguardFinds($user['id'])), 'rejected' => $rejected]);
        exit;
    }

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
        sendSnowCookie($user['id']);
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
