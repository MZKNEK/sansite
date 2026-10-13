<?php
    // What an account picks for itself: the look of the HUD and its colour.
    // Part of the login, loaded by inc/auth.php.

    // ---- The look of the HUD ---------------------------------------------------
    // Each account picks in its profile (/account/) how much of the HUD takes a
    // colour, and an account with a role on the bot's server (user and up) also
    // which colour. The default is the site's own purple, so nothing changes
    // until it is picked; the setting is kept in inc/data/hud.json.

    const HUD_MODES = [
        'default' => 'Domyślny (fiolet strony)',
        'accent'  => 'Kolor tylko w akcentach',
        'full'    => 'Kolor na całym HUD'
    ];

    // the colours of the HUD: the one of the account's own role, or any of the
    // others. The keys are the roles they come from (the role-* classes of the
    // CSS and what hud.json keeps), the names say the colour itself.
    const HUD_COLORS = [
        'own'       => 'Kolor mojej roli',
        'dev'       => 'Turkus',
        'admin'     => 'Czerwień',
        'semiAdmin' => 'Pomarańcz',
        'tester'    => 'Złoto',
        'moderator' => 'Zieleń',
        'user'      => 'Fiolet',
        'out'       => 'Szarość'
    ];

    // every role colour as a hex, for the swatches in the profile
    const HUD_COLOR_HEX = [
        'dev' => '#4fd1c5', 'admin' => '#e86262', 'semiAdmin' => '#f0a35e', 'tester' => '#e8d36a',
        'moderator' => '#5fd38d', 'user' => '#b670d3', 'out' => '#80848e', 'none' => '#80848e'
    ];

    // what an account picked: the mode and the colour, both with a safe default.
    // The first format kept only the mode, so a plain string still reads as one.
    function hudPrefs($id)
    {
        $entry = readData('hud')[(string)$id] ?? null;
        if (is_string($entry))
            $entry = ['mode' => $entry];
        $entry = is_array($entry) ? $entry : [];

        $mode = $entry['mode'] ?? 'default';
        $color = $entry['color'] ?? 'own';

        return [
            'mode' => isset(HUD_MODES[$mode]) ? $mode : 'default',
            'color' => isset(HUD_COLORS[$color]) ? $color : 'own'
        ];
    }

    function hudMode($id)
    {
        return hudPrefs($id)['mode'];
    }

    function hudColor($id)
    {
        return hudPrefs($id)['color'];
    }

    // the colour key that ends up on the page: the picked one, or the account's
    // own role; '' when nothing is known about the account
    function hudColorKey($id, $badge)
    {
        $color = hudColor($id);

        return $color === 'own' ? (string)($badge['key'] ?? '') : $color;
    }

    // who picks the colour: an account with a role on the bot's server (user
    // and up, $badge of roleBadge()) and the panel admins; the others keep the
    // colour of their own role, which for them is the site's purple
    function canPickHudColor($id, $badge)
    {
        return isPanelAdminId($id) || isset(BOT_ROLES[$badge['key'] ?? '']);
    }

    function setHud($id, $mode, $color)
    {
        if (!isset(HUD_MODES[$mode]) || !isset(HUD_COLORS[$color]))
            return false;

        return updateData('hud', function (&$all) use ($id, $mode, $color) {
            if ($mode === 'default' && $color === 'own')
                unset($all[(string)$id]);
            else
                $all[(string)$id] = ['mode' => $mode, 'color' => $color];
        });
    }

    // ---- The snow ------------------------------------------------------------
    // Snow falls on the pages at Christmas and through the calendar winter
    // (js/safeguard.js); an account may turn it off in its profile. The
    // accounts that did are kept in inc/data/snow.json, and the browser gets
    // the cookie "snow" = "off", so the static pages know it at once too.

    function snowOff($id)
    {
        return !empty(readData('snow')[(string)$id]);
    }

    function setSnowOff($id, $off)
    {
        return updateData('snow', function (&$all) use ($id, $off) {
            if ($off)
                $all[(string)$id] = true;
            else
                unset($all[(string)$id]);
        });
    }

    // the cookie of the snow as the account has it
    function sendSnowCookie($id)
    {
        if (snowOff($id))
            setcookie('snow', 'off', time() + 31536000, '/');
        else if (isset($_COOKIE['snow']))
            setcookie('snow', '', time() - 3600, '/');
    }

    // the attributes for <html> that put the chosen HUD and its colour on the
    // page, and the skin it wears (inc/auth/skins.php). An anonymous page marks
    // its default too, so stale cookies cannot override the server's answer.
    // data-hud-own keeps the account's own role, so the
    // profile can put it back for "Kolor mojej roli".
    function hudHtmlAttributes($user)
    {
        if ($user === null)
            return ' data-hud="default"';

        $badge = roleBadge(siteRoles());
        $key = hudColorKey($user['id'], $badge);
        $html = ' data-hud="' . htmlspecialchars(hudMode($user['id']), ENT_QUOTES, 'UTF-8') . '"'
            . ' data-hud-own="' . htmlspecialchars((string)($badge['key'] ?? ''), ENT_QUOTES, 'UTF-8') . '"'
            . skinHtmlAttribute($user['id']);
        if ($key !== '')
            $html .= ' class="role-' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '"';

        return $html;
    }
