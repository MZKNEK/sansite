<?php
    // The account in the corner of every page. Part of the login, loaded by
    // inc/auth.php.

    // ---- The account in the corner of every page ----------------------------------

    const ACCOUNT_ICONS = [
        'profile' => '<circle cx="12" cy="8" r="4" /><path d="M4 20a8 8 0 0 1 16 0" />',
        'gallery' => '<rect x="3" y="4" width="18" height="16" rx="1" /><circle cx="9" cy="10" r="2" /><path d="m21 16-5-5-9 9" />',
        'panel' => '<path d="M4 6h16M4 12h10M4 18h16" />',
        'api' => '<path d="m8 8-4 4 4 4M16 8l4 4-4 4" />',
        'logout' => '<path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10" />',
        'caret' => '<path d="m6 9 6 6 6-6" />'
    ];

    // A path on this site to go back to, e.g. "/i/?p=x", or the site root
    function localPath($path)
    {
        $path = (string)$path;

        // no control characters at all: a browser drops a tab or a newline
        // from a URL, so "/<tab>/evil.com" would go to //evil.com
        return preg_match('~^/(?![/\\\\])[^\x00-\x1F\x7F]*$~', $path) ? $path : siteRoot();
    }

    // The logged-in account for the top right corner: its avatar ringed in the
    // accent of the HUD, the name and the level in the colour of its role. A click (js/account.js) opens
    // a menu with its profile, the places it may open, none it may not, and logging out,
    // which comes back to $back. Below them a dim line with the role and the ID.
    function accountMenuHtml($user, $roles, $back)
    {
        $text = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); };
        $icon = function ($name) { return '<svg class="account-icon" viewBox="0 0 24 24" aria-hidden="true">' . ACCOUNT_ICONS[$name] . '</svg>'; };
        $root = siteRoot();
        $badge = roleBadge($roles);
        $here = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?');

        // the account's own profile for everyone, the rest only where it may go
        $places = [['profile', 'Profil', 'account/']];
        // also for an account that sees only a folder of its own, where i/ opens it
        if (canViewGalleryId($user['id']) || isGalleryUploaderId($user['id']))
            $places[] = ['gallery', 'Galeria', 'i/'];
        if (isPanelAdminId($user['id']))
            $places[] = ['panel', 'Panel', 'admin/'];

        $html = '<div class="account-menu">'
            . '<button type="button" class="account-toggle" aria-expanded="false" aria-haspopup="true">'
            . '<img src="' . $text($user['avatar']) . '" alt="" width="28" height="28" />'
            . '<span class="account-name">' . $text($user['name']) . '</span>' . roleBadgeHtml($roles) . $icon('caret')
            . '</button><div class="account-drop hud-corners" hidden>';
        foreach ($places as [$key, $label, $path])
            $html .= '<a href="' . $text($root . $path) . '"' . (strpos($here, $root . $path) === 0 ? ' aria-current="page"' : '') . '>'
                . $icon($key) . $label . '<small>' . $path . '</small></a>';
        $html .= '<form method="post" action="' . $text($root . 'account.php') . '">'
            . '<input type="hidden" name="csrf" value="' . $text(siteCsrf()) . '" />'
            . '<input type="hidden" name="action" value="logout" />'
            . '<input type="hidden" name="back" value="' . $text(localPath($back)) . '" />'
            . '<button type="submit" class="account-out">' . $icon('logout') . 'Wyloguj</button></form>'
            . '<div class="account-scan">' . ($badge ? '<em>LV.' . $badge['level'] . ' ' . $badge['label'] . '</em> · ' : '') . 'ID ' . $text($user['id']) . '</div>'
            . '</div></div>';

        // other rights being tried: a bar at the bottom of every page, with the way back
        $test = testRights($user['id']);
        if ($test !== null)
            $html .= '<div class="test-bar" role="status"><span><b>Podgląd z innymi uprawnieniami</b> ' . $text(testRightsText($test))
                . ' · do ' . date('H:i', $test['until']) . '</span>'
                . '<form method="post" action="' . $text($root . 'account.php') . '">'
                . '<input type="hidden" name="csrf" value="' . $text(siteCsrf()) . '" />'
                . '<input type="hidden" name="action" value="test-off" />'
                . '<input type="hidden" name="back" value="' . $text(localPath($back)) . '" />'
                . '<button type="submit">Wróć do swoich</button></form></div>';

        return $html;
    }
