<?php
    // What Profilator shows of the logged-in account (profilator/me.php): the
    // bot's data of its profile (api/User/discord/<id>/profile, with the
    // site's key and the permission Info): the code of its look with links to
    // its own pictures, which the bot sends to its trash channel for it, the
    // numbers, the cards and the statistics of its lists on Shinden. Kept
    // PROFILATOR_TTL, as the bot asks Shinden each time. Only the account's
    // own profile, never another's.
    require_once __DIR__ . '/auth.php';

    const PROFILATOR_TTL = 120;

    // the profile of an account as the bot gave it; false when the bot has no
    // profile of it, null when the bot did not answer
    function profilatorData($id, $now)
    {
        $id = (string)$id;
        $kept = readData('profilator')[$id] ?? null;
        if (is_array($kept) && $now - (int)($kept['time'] ?? 0) < PROFILATOR_TTL)
            return $kept['data'];

        if (botAppKey() === '' || !preg_match('/^\d{17,20}$/', $id))
            return null;

        [$json, $status] = httpRaw(botApiBase() . '/api/User/discord/' . rawurlencode($id) . '/profile', [
            'method' => 'GET',
            'timeout' => 15,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nx-app-key: " . botAppKey()
        ]);
        if ($status === 404)
            $data = false;
        else if ($status !== 200 || !is_array($data = json_decode((string)$json, true)))
            return null;

        updateData('profilator', function (&$all) use ($id, $data, $now) {
            foreach ($all as $key => $entry)
                if ($now - (int)($entry['time'] ?? 0) >= PROFILATOR_TTL)
                    unset($all[$key]);
            $all[$id] = ['time' => $now, 'data' => $data];
        });

        return $data;
    }

    // only what the page draws, the links only to the places the page may load
    // pictures from (the site's policy): Discord's and the cards' of Shinden
    function profilatorView($data)
    {
        $link = function ($url, $hosts) {
            return is_string($url) && preg_match('~^https://(' . $hosts . ')/~', $url) ? $url : null;
        };
        $count = function ($value) {
            return is_numeric($value) ? $value + 0 : 0;
        };
        $list = function ($stats) use ($count) {
            if (!is_array($stats))
                return null;
            $keys = ['inProgress', 'completed', 'skipped', 'onHold', 'dropped', 'inPlan', 'total', 'rating', 'count', 'years', 'months', 'days', 'hours', 'minutes'];
            return array_combine($keys, array_map(function ($key) use ($stats, $count) { return $count($stats[$key] ?? 0); }, $keys));
        };

        $view = [
            'code' => is_string($data['code'] ?? null) ? $data['code'] : '',
            'nick' => (string)($data['nick'] ?? ''),
            'avatar' => $link($data['avatar'] ?? null, 'cdn\.discordapp\.com'),
            'color' => preg_match('/^#[0-9a-f]{6}$/i', (string)($data['color'] ?? '')) ? $data['color'] : '#546e7a',
            'rank' => (string)($data['rank'] ?? ''),
            'waifu' => $link($data['waifu'] ?? null, 'cdn2\.shinden\.eu'),
            'gallery' => array_values(array_map(function ($url) use ($link) { return $link($url, 'cdn2\.shinden\.eu'); }, is_array($data['gallery'] ?? null) ? $data['gallery'] : [])),
            'rarities' => [],
            'anime' => $list($data['anime'] ?? null),
            'manga' => $list($data['manga'] ?? null)
        ];
        foreach (['level', 'exp', 'top', 'messages', 'sc', 'tc', 'ac', 'ct', 'pc', 'cards', 'limit', 'karma', 'scalpels', 'scissors', 'ultimates'] as $key)
            $view[$key] = $count($data[$key] ?? 0);
        foreach (['SSS', 'SS', 'S', 'A', 'B', 'C', 'D', 'E'] as $rarity)
            $view['rarities'][$rarity] = (int)($data['rarities'][$rarity] ?? 0);

        return $view;
    }
