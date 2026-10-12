<?php
    // Logging in with Discord (OAuth2), and the list of recent logins. Part of
    // the login, loaded by inc/auth.php.

    // ---- Discord login -------------------------------------------------------------

    // DISCORD_API_URL in the config can point the login at a test server
    function discordApi()
    {
        return defined('DISCORD_API_URL') ? DISCORD_API_URL : 'https://discord.com/api';
    }

    function discordRequest($path, $form = null, $token = null)
    {
        $headers = ['Accept: application/json', 'User-Agent: SanakanSite (https://sanakan.pl, 1.0)'];
        if ($token !== null)
            $headers[] = 'Authorization: Bearer ' . $token;

        $http = ['method' => $form === null ? 'GET' : 'POST', 'timeout' => 10, 'ignore_errors' => true];
        if ($form !== null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $http['content'] = http_build_query($form);
        }
        $http['header'] = implode("\r\n", $headers);

        [$json] = httpRaw(discordApi() . $path, $http);
        $data = $json === false ? null : json_decode($json, true);

        return is_array($data) ? $data : [];
    }

    // ?login starts the login; Discord comes back to DISCORD_REDIRECT_URI with ?state
    // and ?code (or ?error). The gallery and the panel both answer that, so either can
    // be the redirect address. $returnPath is where to go after a login started here.
    function handleLoginRequest($returnPath)
    {
        if (!authConfigured())
            return;

        if (isset($_GET['login'])) {
            startLogin($returnPath);
            exit;
        }
        if (isset($_GET['state']) && (isset($_GET['code']) || isset($_GET['error']))) {
            header('Location: ' . finishLogin());
            exit;
        }
    }

    // Step 1: the visitor goes to Discord; "state" ties the way back to this session
    function startLogin($returnPath)
    {
        siteSession();
        $_SESSION['oauth_state'] = bin2hex(random_bytes(16));
        $_SESSION['oauth_return'] = $returnPath;

        header('Location: https://discord.com/oauth2/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => DISCORD_CLIENT_ID,
            'scope' => 'identify',
            'state' => $_SESSION['oauth_state'],
            'redirect_uri' => DISCORD_REDIRECT_URI,
            'prompt' => 'none'
        ]));
    }

    // Step 2: Discord sends the visitor back with a code, which is exchanged for the
    // account. The account is kept in the session and in the list of recent logins;
    // each page then decides what it may do. Returns the path to go back to.
    function finishLogin()
    {
        siteSession();
        $state = $_SESSION['oauth_state'] ?? '';
        $returnPath = $_SESSION['oauth_return'] ?? siteRoot();
        unset($_SESSION['oauth_state'], $_SESSION['oauth_return']);

        if (isset($_GET['error'])) {
            setFlash('Logowanie zostało anulowane.');
            return $returnPath;
        }
        if ($state === '' || !hash_equals($state, (string)($_GET['state'] ?? ''))) {
            setFlash('Logowanie wygasło, spróbuj jeszcze raz.');
            return $returnPath;
        }

        $token = discordRequest('/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => (string)($_GET['code'] ?? ''),
            'redirect_uri' => DISCORD_REDIRECT_URI,
            'client_id' => DISCORD_CLIENT_ID,
            'client_secret' => DISCORD_CLIENT_SECRET
        ]);
        $account = empty($token['access_token']) ? [] : discordRequest('/users/@me', null, $token['access_token']);
        if (empty($account['id'])) {
            setFlash('Discord nie potwierdził logowania, spróbuj jeszcze raz.');
            return $returnPath;
        }

        $id = (string)$account['id'];
        $name = !empty($account['global_name']) ? $account['global_name'] : ($account['username'] ?? 'Discord');
        $avatar = empty($account['avatar'])
            ? 'https://cdn.discordapp.com/embed/avatars/' . (((int)$id >> 22) % 6) . '.png'
            : 'https://cdn.discordapp.com/avatars/' . rawurlencode($id) . '/' . rawurlencode($account['avatar']) . '.png?size=64';

        session_regenerate_id(true);
        $_SESSION['gallery_user'] = ['id' => $id, 'name' => $name, 'avatar' => $avatar];
        $_SESSION['login_time'] = time();
        $_SESSION['generation'] = sessionGeneration($id);
        // a new session: its address and device are noted at once
        unset($_SESSION['address_seen']);
        siteCsrf();
        setFlash('Zalogowano jako ' . $name . '.');
        recordLogin($id, $name, $avatar, $account['username'] ?? '');

        return $returnPath;
    }

    // recent logins, also of accounts without access, so the panel can let them in
    function recordLogin($id, $name, $avatar, $username)
    {
        updateData('logins', function (&$logins) use ($id, $name, $avatar, $username) {
            $count = isset($logins[$id]['count']) ? (int)$logins[$id]['count'] : 0;
            unset($logins[$id]);
            $logins = array_slice([$id => [
                'name' => $name,
                'username' => $username,
                'avatar' => $avatar,
                'last' => time(),
                'count' => $count + 1
            ]] + $logins, 0, LOGIN_LOG_SIZE, true);
        });
    }
