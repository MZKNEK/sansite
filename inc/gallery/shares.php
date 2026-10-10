<?php
    // Folders shared by a link. Part of the gallery, loaded by inc/gallery.php.

    // ---- Shared links ---------------------------------------------------------------
    // inc/data/shares.json: [token => ['rel', 'by' (id), 'name' (whose), 'created',
    // 'expires' (null: until taken back)]]; 'rel' as links have it, so the
    // folder of an account stays shared when its name follows a new nick. Opening the link (i/?s=token) puts
    // its token in the visitor's session, so the folder's pages, thumbnails and
    // ZIP open without a login.

    // the shares still valid, expired ones dropped
    function activeShares($shares = null)
    {
        return array_filter($shares ?? readData('shares'), function ($share) {
            return is_array($share) && isset($share['rel']) && ($share['expires'] === null || $share['expires'] > time());
        });
    }

    // a new link for a folder, $days from SHARE_DAYS: its token, or null
    function createShare($rel, $days, $user)
    {
        $token = bin2hex(random_bytes(12));
        $share = ['rel' => publicRel($rel), 'by' => $user['id'], 'name' => $user['name'], 'created' => time(),
            'expires' => $days > 0 ? time() + $days * 86400 : null];
        $saved = updateData('shares', function (&$shares) use ($token, $share) {
            $shares = activeShares($shares);
            $shares[$token] = $share;
        });

        return $saved ? $token : null;
    }

    // the folders shared with this visitor, by the tokens in its session
    function sessionShares($base)
    {
        static $known = [];
        if (array_key_exists($base, $known))
            return $known[$base];
        if (!sessionExists())
            return $known[$base] = [];

        siteSession();
        $shares = activeShares();
        $folders = [];
        foreach ((array)($_SESSION['gallery_shares'] ?? []) as $token)
            if (isset($shares[$token]) && ($folder = resolvePath($base, $shares[$token]['rel'], true)))
                $folders[] = $folder[1];

        return $known[$base] = array_values(array_unique($folders));
    }

    // i/?s=token: the shared folder from now on in this session; the folder, or null
    function openShare($base, $token)
    {
        $share = activeShares()[(string)$token] ?? null;
        $folder = $share === null ? null : resolvePath($base, $share['rel'], true);
        if ($folder === null)
            return null;

        siteSession();
        $_SESSION['gallery_shares'] = array_values(array_unique(array_merge((array)($_SESSION['gallery_shares'] ?? []), [(string)$token])));

        return $folder[1];
    }
