<?php
    // Account skin preferences and unlock checks. Metadata is defined in
    // inc/skin-catalog.php; colours in css/skins.css, home rendering in js/skins.js.

    // whether the skin speaks words on the home page (js/skin-catalog.js): the
    // profile offers its translation switch only for the ones that do
    function skinSpeaks($skin)
    {
        return $skin !== '' && (SKINS[$skin]['words'] ?? false) === true;
    }

    // whether the account may wear the skin: its find in the register, or the
    // owner outside a permissions preview; an unknown skin: no
    function skinUnlocked($skin, $id)
    {
        $entry = SKINS[$skin] ?? null;
        if ($entry === null)
            return false;
        if (realPanelAdminId($id) && testRights($id) === null)
            return true;
        if ($skin === 'custom')
            return !array_diff_key(SAFEGUARD_FINDS + SAFEGUARD_SEASONAL, safeguardFinds($id));

        return $entry['find'] === null || isset(safeguardFinds($id)[$entry['find']]);
    }

    // what the account picked: the skin and whether it speaks its words on the
    // home page (its colours and logo stay either way). The first format kept
    // only the id, so a plain string still reads as a skin that speaks, as the
    // first format of the HUD colour did.
    function skinPrefs($id)
    {
        $entry = readData('skins')[(string)$id] ?? null;
        if (is_string($entry))
            $entry = ['skin' => $entry];
        $entry = is_array($entry) ? $entry : [];

        $skin = $entry['skin'] ?? '';
        $skin = is_string($skin) && skinUnlocked($skin, $id) ? $skin : '';

        return ['skin' => $skin, 'words' => ($entry['words'] ?? true) !== false];
    }

    // what the account wears: its pick, while it may still wear it (a find
    // taken back stops the skin); '' for none
    function skinPref($id)
    {
        return skinPrefs($id)['skin'];
    }

    // whether the skin speaks its words on the home page (js/skins.js)
    function skinWords($id)
    {
        return skinPrefs($id)['words'];
    }

    // keeps or clears the pick and its words; refuses an unknown or locked skin
    function setSkin($id, $skin, $words = true, $custom = null)
    {
        $skin = (string)$skin;
        if ($skin !== '' && !skinUnlocked($skin, $id))
            return false;
        if ($skin === 'custom' && $custom !== null && !setCustomSkin($id, $custom))
            return false;

        return updateData('skins', function (&$all) use ($id, $skin, $words) {
            if ($skin === '')
                unset($all[(string)$id]);
            else if ($words)
                $all[(string)$id] = $skin;
            else
                $all[(string)$id] = ['skin' => $skin, 'words' => false];
        });
    }

    // The cookies of the skin as the account has it (account.php), so the home
    // page can wear it at once and take it off when it is gone (a session that
    // ended, a find taken back); "skinwords" = "off" keeps the skin from
    // speaking there
    function sendSkinCookie($id)
    {
        $prefs = skinPrefs($id);
        if ($prefs['skin'] !== '')
            setcookie('skin', $prefs['skin'], time() + 31536000, '/');
        else if (isset($_COOKIE['skin']))
            setcookie('skin', '', time() - 3600, '/');

        if ($prefs['skin'] !== '' && !$prefs['words'])
            setcookie('skinwords', 'off', time() + 31536000, '/');
        else if (isset($_COOKIE['skinwords']))
            setcookie('skinwords', '', time() - 3600, '/');

        if ($prefs['skin'] === 'custom')
            setcookie('customskin', json_encode(customSkinAppearance($id), JSON_UNESCAPED_SLASHES), time() + 31536000, '/');
        else if (isset($_COOKIE['customskin']))
            setcookie('customskin', '', time() - 3600, '/');
    }

    // the attribute for <html> of a page that knows the account, so it wears
    // the skin before the first paint (hudHtmlAttributes() in
    // inc/auth/preferences.php); '' when there is none
    function skinHtmlAttribute($id)
    {
        $skin = $id === null ? '' : skinPref($id);

        $html = $skin === '' ? '' : ' data-skin="' . htmlspecialchars($skin, ENT_QUOTES, 'UTF-8') . '"';
        if ($skin === 'custom')
            $html .= ' data-custom-skin="' . htmlspecialchars(json_encode(customSkinAppearance($id), JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') . '"';

        return $html;
    }
