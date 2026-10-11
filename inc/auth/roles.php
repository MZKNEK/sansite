<?php
    // The roles of an account on the bot's Discord server, asked of the bot,
    // and their badges. Part of the login, loaded by inc/auth.php.

    // ---- Roles on the bot's Discord server ---------------------------------------
    // The bot API says what an account is on its server. The answers are kept in
    // inc/data/roles.json as [id => ['roles' => [...], 'checked' => time,
    // 'tried' => time]], so the panel can show them for the recent logins too; the
    // logged-in account is asked again when its answer is older than BOT_ROLES_TTL.

    // the site's key to the bot API (x-app-key), or '' without one
    function botAppKey()
    {
        authConfigured();

        return defined('BOT_APP_KEY') ? (string)BOT_APP_KEY : '';
    }

    // Base of the bot API. BOT_API_BASE in inc/config.php points it elsewhere (a
    // test bot or a local stub, e.g. the HTTP smoke test); the live address is
    // the default.
    function botApiBase()
    {
        authConfigured();

        return defined('BOT_API_BASE') ? rtrim((string)BOT_API_BASE, '/') : 'https://api.sanakan.pl';
    }

    // the URL of an account's roles on the bot's server
    function botRolesUrl($id)
    {
        return botApiBase() . '/api/User/discord/' . rawurlencode((string)$id) . '/permissions';
    }

    // One outbound HTTP request, so a test can replace it: with the SANAKAN_HTTP
    // callable in $GLOBALS set it is called instead and returns [body, status].
    // A request that cannot be made comes back as [false, 0]. $maxLen cuts the
    // body (0 reads it whole). $socket goes to the stream's socket options
    // (['bindto' => '0:0'] asks over IPv4 only).
    function httpRaw($url, array $options = [], $maxLen = 0, array $socket = [])
    {
        if (isset($GLOBALS['SANAKAN_HTTP']) && is_callable($GLOBALS['SANAKAN_HTTP']))
            return ($GLOBALS['SANAKAN_HTTP'])($url, $options, $maxLen);

        $fp = @fopen($url, 'r', false, stream_context_create(['http' => $options] + ($socket ? ['socket' => $socket] : [])));
        if ($fp === false)
            return [false, 0];
        $body = stream_get_contents($fp, $maxLen > 0 ? $maxLen : -1);
        // the answer's headers, every redirect's among them, the last status
        // line the one that counts ($http_response_header is deprecated from
        // PHP 8.5); a file:// URL has none
        $headers = stream_get_meta_data($fp)['wrapper_data'] ?? [];
        fclose($fp);
        $status = 0;
        foreach (is_array($headers) ? $headers : [] as $line)
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $match))
                $status = (int)$match[1];

        return [$body, $status];
    }

    // GET from the bot API with the site's key: the answer as an array, or null
    function botAppGet($url, $timeout = 4)
    {
        [$json, $status] = httpRaw($url, [
            'method' => 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nx-app-key: " . botAppKey()
        ]);
        $data = $json === false || $status !== 200 ? null : json_decode($json, true);

        return is_array($data) ? $data : null;
    }

    // The flags of an account (onGuild and the keys of BOT_ROLES), or null when
    // the bot was never asked; $refresh asks it again when the answer is old.
    function botRoles($id, $refresh = false)
    {
        // a role being tried: only that one, on the server or off it
        $test = testRights($id);
        if (isset($test['role'])) {
            $roles = ['onGuild' => $test['role'] !== TEST_ROLE_OUT];
            foreach (array_keys(BOT_ROLES) as $role)
                $roles[$role] = $role === $test['role'];
            return $roles;
        }

        $id = (string)$id;
        $entry = readData('roles')[$id] ?? null;
        $age = time() - max($entry['checked'] ?? 0, $entry['tried'] ?? 0);

        if ($refresh && $age >= BOT_ROLES_TTL && botAppKey() !== '' && preg_match('/^\d{17,20}$/', $id)) {
            $asked = time();
            $answer = botAppGet(botRolesUrl($id));
            if ($answer !== null) {
                $roles = ['onGuild' => !empty($answer['onGuild'])];
                foreach (array_keys(BOT_ROLES) as $role)
                    $roles[$role] = !empty($answer[$role]);
                $entry = ['roles' => $roles, 'checked' => time()];
            } else {
                // the bot does not answer: the last roles stay, asked again after BOT_ROLES_TTL
                $entry = ['tried' => time()] + ($entry ?? []);
            }

            // another request may have asked meanwhile: an answer newer than
            // this question is not overwritten by this one
            $saved = updateData('roles', function (&$known) use ($id, $asked, &$entry) {
                $stored = $known[$id] ?? null;
                if ($stored !== null && max($stored['checked'] ?? 0, $stored['tried'] ?? 0) > $asked) {
                    $entry = $stored;
                    return;
                }
                $known[$id] = $entry;
                uasort($known, function ($a, $b) {
                    return max($b['checked'] ?? 0, $b['tried'] ?? 0) <=> max($a['checked'] ?? 0, $a['tried'] ?? 0);
                });
                $known = array_slice($known, 0, BOT_ROLES_KEEP, true);
            });
            if (!$saved) throw new DataStoreException('write', 'roles');
        }

        return $entry['roles'] ?? null;
    }

    // roles of the logged-in account, asked again when old, or null
    function siteRoles()
    {
        $user = siteUser();

        return $user === null ? null : botRoles($user['id'], true);
    }

    // any role on the bot's server, user and up: the logged-in account's asked
    // again when the answer is old (siteRoles()), another's as the bot said last
    function hasServerRole($id)
    {
        $user = siteUser();
        $roles = $user !== null && (string)$user['id'] === (string)$id ? siteRoles() : botRoles($id);
        foreach (array_keys(BOT_ROLES) as $role)
            if (!empty($roles[$role]))
                return true;

        return false;
    }

    function hasBotRole($id, $roles)
    {
        $known = botRoles($id);
        foreach ($roles as $role)
            if (!empty($known[$role]))
                return true;

        return false;
    }

    // the highest role as ['key', 'level', 'label', 'name', 'title'], or null when unknown
    function roleBadge($roles)
    {
        if ($roles === null)
            return null;

        foreach (BOT_ROLES as $key => [$level, $label, $name])
            if (!empty($roles[$key]))
                return ['key' => $key, 'level' => $level, 'label' => $label, 'name' => $name, 'title' => 'Rola na serwerze Sanakana: ' . $name];

        return empty($roles['onGuild'])
            ? ['key' => 'out', 'level' => 0, 'label' => 'POZA SERWEREM', 'name' => 'poza serwerem', 'title' => 'Tego konta nie ma na serwerze Sanakana']
            : ['key' => 'none', 'level' => 0, 'label' => 'BEZ ROLI', 'name' => 'bez roli', 'title' => 'Konto nie ma roli na serwerze Sanakana'];
    }

    // every role of an account, highest first, by its name in the panel
    function roleNames($roles)
    {
        $names = [];
        foreach (BOT_ROLES as $key => $role)
            if (!empty($roles[$key]))
                $names[] = $role[2];

        return $names;
    }

    // the level next to the account name, "LV.9" in the colour of the role,
    // which its title names; the menu below shows the role in full
    function roleBadgeHtml($roles)
    {
        $badge = roleBadge($roles);
        if ($badge === null)
            return '';

        return '<span class="lv role-' . $badge['key'] . '" title="' . htmlspecialchars($badge['title'], ENT_QUOTES, 'UTF-8') . '">'
            . 'LV.' . $badge['level'] . '</span>';
    }
