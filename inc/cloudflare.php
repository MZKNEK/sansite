<?php
    // Blocking addresses in Cloudflare from the admin panel, so their requests
    // never reach the server. The panel puts them on an IP list of the
    // Cloudflare account (CLOUDFLARE_LIST, e.g. sanakan_blokada), and one custom
    // rule of the zone blocks everything on it: (ip.src in $sanakan_blokada).
    // An IPv6 address is blocked with its whole /64, which one machine usually
    // has to itself. Needs in inc/config.php CLOUDFLARE_API_TOKEN (a token with
    // Account Filter Lists: Edit), CLOUDFLARE_ACCOUNT_ID and CLOUDFLARE_LIST.
    // Cloudflare changes a list in the background, so a change waits a few
    // seconds for it to finish.
    require_once __DIR__ . '/auth.php';

    const CLOUDFLARE_API = 'https://api.cloudflare.com/client/v4';
    const CLOUDFLARE_TIMEOUT = 8;
    const CLOUDFLARE_COMMENT_LENGTH = 100;
    // how long a change waits for Cloudflare to finish it, in half seconds;
    // Cloudflare changes a list in the background, so a change usually takes a
    // few seconds, and a slower one is reported as unconfirmed rather than done
    const CLOUDFLARE_WAIT_STEPS = 20;

    function cloudflareConfigured()
    {
        authConfigured();

        return defined('CLOUDFLARE_API_TOKEN') && CLOUDFLARE_API_TOKEN !== ''
            && defined('CLOUDFLARE_ACCOUNT_ID') && CLOUDFLARE_ACCOUNT_ID !== ''
            && defined('CLOUDFLARE_LIST') && CLOUDFLARE_LIST !== '';
    }

    // a call to the Cloudflare API: [true, result, result_info] or [false, error message]
    function cloudflareApi($method, $path, $body = null)
    {
        $http = [
            'method' => $method,
            'timeout' => CLOUDFLARE_TIMEOUT,
            'ignore_errors' => true,
            'header' => implode("\r\n", [
                'Authorization: Bearer ' . CLOUDFLARE_API_TOKEN,
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: SanakanSite (https://sanakan.pl, 1.0)'
            ])
        ];
        if ($body !== null)
            $http['content'] = json_encode($body);

        [$json] = httpRaw(CLOUDFLARE_API . $path, $http);
        $data = $json === false ? null : json_decode($json, true);
        if (!is_array($data))
            return [false, 'Cloudflare nie odpowiedział.'];
        if (empty($data['success']))
            return [false, 'Cloudflare: ' . ($data['errors'][0]['message'] ?? 'nieznany błąd') . '.'];

        return [true, $data['result'] ?? null, $data['result_info'] ?? null];
    }

    function cloudflareListsPath()
    {
        return '/accounts/' . rawurlencode(CLOUDFLARE_ACCOUNT_ID) . '/rules/lists';
    }

    // the id of the IP list named CLOUDFLARE_LIST, remembered in inc/data: [id, null] or [null, error]
    function cloudflareListId()
    {
        $known = readData('cloudflare-list');
        if (($known['name'] ?? '') === CLOUDFLARE_LIST && !empty($known['id']))
            return [$known['id'], null];

        $answer = cloudflareApi('GET', cloudflareListsPath());
        if (!$answer[0])
            return [null, $answer[1]];
        foreach ($answer[1] ?: [] as $list)
            if (($list['name'] ?? '') === CLOUDFLARE_LIST && ($list['kind'] ?? '') === 'ip') {
                writeData('cloudflare-list', ['name' => CLOUDFLARE_LIST, 'id' => $list['id']]);
                return [$list['id'], null];
            }

        return [null, 'Na koncie Cloudflare nie ma listy IP o nazwie ' . CLOUDFLARE_LIST . '.'];
    }

    // What is on the list, newest first: [[['id', 'ip', 'comment', 'created'], ...], null]
    // or [null, error]
    function cloudflareBlocked()
    {
        [$id, $error] = cloudflareListId();
        if ($id === null)
            return [null, $error];

        $items = [];
        $cursor = null;
        for ($page = 0; $page < 10; $page++) {
            $answer = cloudflareApi('GET', cloudflareListsPath() . '/' . rawurlencode($id) . '/items?per_page=500' . ($cursor !== null ? '&cursor=' . rawurlencode($cursor) : ''));
            if (!$answer[0]) {
                // a list deleted and made again has a new id
                writeData('cloudflare-list', []);
                return [null, $answer[1]];
            }
            foreach ($answer[1] ?: [] as $item)
                $items[] = ['id' => $item['id'], 'ip' => $item['ip'] ?? '', 'comment' => $item['comment'] ?? '', 'created' => strtotime($item['created_on'] ?? '') ?: null];
            $cursor = $answer[2]['cursors']['after'] ?? null;
            if ($cursor === null)
                break;
        }
        usort($items, function ($a, $b) { return $b['created'] <=> $a['created']; });

        return [$items, null];
    }

    // What goes on the list for an address: the address itself, the /64 of an
    // IPv6 one; null for an address that is not public.
    function cloudflareTarget($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)
            return null;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
            return $ip;

        $network = substr(inet_pton($ip), 0, 8) . str_repeat("\0", 8);

        return inet_ntop($network) . '/64';
    }

    // Waits until Cloudflare has made a change of the list: null when it is
    // confirmed done, or a message why it is not. A failed poll is not a failed
    // change, so it keeps waiting; only the operation's own "failed" is one. A
    // change Cloudflare has not confirmed in time comes back as a message too,
    // so a caller never takes it for done; the list shows the truth on a refresh.
    function cloudflareWait($result, $steps = CLOUDFLARE_WAIT_STEPS)
    {
        $operation = $result['operation_id'] ?? null;
        if ($operation === null)
            return null;

        for ($i = 0; $i < $steps; $i++) {
            usleep(500000);
            $answer = cloudflareApi('GET', cloudflareListsPath() . '/bulk_operations/' . rawurlencode($operation));
            if (!$answer[0])
                continue;   // the poll failed, which says nothing about the change
            $status = $answer[1]['status'] ?? '';
            if ($status === 'completed')
                return null;
            if ($status === 'failed')
                return 'Cloudflare: ' . ($answer[1]['error'] ?? 'zmiana się nie udała') . '.';
        }

        return 'Cloudflare nie potwierdził zmiany na czas; odśwież listę, żeby sprawdzić, czy doszła.';
    }

    // puts an address on the list: null, or an error message
    function cloudflareBlock($ip, $comment)
    {
        $target = cloudflareTarget($ip);
        if ($target === null)
            return 'To nie jest publiczny adres IP.';
        [$id, $error] = cloudflareListId();
        if ($id === null)
            return $error;

        $answer = cloudflareApi('POST', cloudflareListsPath() . '/' . rawurlencode($id) . '/items', [
            ['ip' => $target, 'comment' => cutText($comment, CLOUDFLARE_COMMENT_LENGTH)]
        ]);

        return $answer[0] ? cloudflareWait($answer[1]) : $answer[1];
    }

    // takes an item (by its id from cloudflareBlocked) off the list: null, or an error message
    function cloudflareUnblock($itemId)
    {
        [$id, $error] = cloudflareListId();
        if ($id === null)
            return $error;

        $answer = cloudflareApi('DELETE', cloudflareListsPath() . '/' . rawurlencode($id) . '/items', ['items' => [['id' => $itemId]]]);

        return $answer[0] ? cloudflareWait($answer[1]) : $answer[1];
    }
