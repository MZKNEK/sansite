<?php
    // Included by tests/http.php, against its disposable site and data folder.
    if (!defined('SITE_DATA_DIR')) define('SITE_DATA_DIR', $data);
    require_once $root . '/inc/auth.php';
    require_once $root . '/inc/megastructure.php';

    function megaHttpCheck($ok, $name)
    {
        global $failures;
        if (!$ok) $failures++;
        echo ($ok ? 'ok   ' : 'FAIL '), $name, "\n";
    }

    function megaHttpRequest($client, $url, $post = null)
    {
        curl_setopt($client, CURLOPT_URL, $url);
        if ($post === null)
            curl_setopt($client, CURLOPT_HTTPGET, true);
        else
            curl_setopt_array($client, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]);
        $body = curl_exec($client);
        return [curl_getinfo($client, CURLINFO_RESPONSE_CODE), json_decode((string)$body, true)];
    }

    // A valid game following a cycle of the grid, so fixtures never rely on
    // a bot luckily avoiding a dead end. Only the server computes the score.
    function megaHttpSnakeWay($ticket)
    {
        $cycle = [];
        for ($y = 0; $y < SNAKE_ROWS; $y++)
            foreach ($y % 2 === 0 ? range(1, SNAKE_COLS - 1) : range(SNAKE_COLS - 1, 1) as $x)
                $cycle[] = [$x, $y];
        for ($y = SNAKE_ROWS - 1; $y >= 0; $y--)
            $cycle[] = [0, $y];
        $at = array_search([6, intdiv(SNAKE_ROWS, 2)], $cycle, true);
        $runs = [];
        for ($tick = 1; $tick <= SNAKE_TICKS_MAX; $tick++) {
            $next = ($at + 1) % count($cycle);
            $dx = $cycle[$next][0] - $cycle[$at][0];
            $dy = $cycle[$next][1] - $cycle[$at][1];
            $dir = $dx === 1 ? 'R' : ($dx === -1 ? 'L' : ($dy === 1 ? 'D' : 'U'));
            $last = count($runs) - 1;
            if ($last >= 0 && $runs[$last][0] === $dir) $runs[$last][1]++;
            else $runs[] = [$dir, 1];
            $at = $next;
            if ($tick % count($cycle) !== 0) continue;
            $way = implode('', array_map(function ($run) { return $run[0] . $run[1]; }, $runs));
            $game = snakeReplay(snakeSeed($ticket), $way);
            if ($game !== null && $game['eaten'] >= SNAKE_FED)
                return [$way, $game['eaten']];
        }
        throw new RuntimeException('Could not construct the snake fixture');
    }

    $client = curl_init();
    $otherClient = curl_init();
    foreach ([$client, $otherClient] as $handle)
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_TIMEOUT => 10]);
    try {
        $megaUrl = $base . '/__mega';
        megaHttpRequest($client, $base . '/__mega-login');
        [$status, $auth] = megaHttpRequest($client, $megaUrl . '?csrf');
        $csrf = $auth['csrf'] ?? '';
        megaHttpCheck($status === 200 && strlen($csrf) === 64, 'register issues a session CSRF token');
        $sessionFiles = function () use ($data) { return glob($data . '/sessions/sess_*') ?: []; };
        $sessionsBefore = $sessionFiles();
        [, $otherAuth] = megaHttpRequest($otherClient, $megaUrl . '?csrf');
        megaHttpCheck(strlen($otherAuth['csrf'] ?? '') === 64 && $sessionFiles() === $sessionsBefore, 'an anonymous player gets a token without a session file');
        $madeUp = curl_init();
        curl_setopt_array($madeUp, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_COOKIE => SITE_SESSION . '=madeupsession' . bin2hex(random_bytes(8))]);
        megaHttpRequest($madeUp, $megaUrl . '?csrf');
        megaHttpRequest($madeUp, $base . '/__mega-account');
        megaHttpCheck($sessionFiles() === $sessionsBefore, 'a made-up session cookie writes no session file');

        $born = time() - 3600;
        $ticket = megaProof('smoke-register-secret', 'ticket:snake', $born, '0000000100000000');
        [$way, $score] = megaHttpSnakeWay($ticket);
        $gamePost = ['snake' => $way, 'ticket' => $ticket];
        [$status] = megaHttpRequest($client, $megaUrl . '?' . http_build_query($gamePost));
        megaHttpCheck($status === 405, 'GET cannot write a snake record or spend a ticket');
        [$status] = megaHttpRequest($client, $megaUrl, $gamePost);
        megaHttpCheck($status === 403, 'snake POST without CSRF is refused');
        [$status] = megaHttpRequest($client, $megaUrl, $gamePost + ['csrf' => $otherAuth['csrf'] ?? '']);
        megaHttpCheck($status === 403, 'a different session token cannot authorize a snake game');
        [$status, $first] = megaHttpRequest($client, $megaUrl, $gamePost + ['csrf' => $csrf]);
        megaHttpCheck($status === 200 && ($first['best'] ?? null) === $score && isset($first['proof']), 'a valid protected snake POST records its replayed score');
        [$status, $retry] = megaHttpRequest($client, $megaUrl, $gamePost + ['csrf' => $csrf]);
        megaHttpCheck($status === 200 && $retry === $first, 'retrying a game recovers the exact same proof');

        // Anonymous play and ticket-based finds use the same protection.
        $idle = megaProof('smoke-register-secret', 'ticket:idle', time() - 180);
        $idlePost = ['witness' => 'idle', 'ticket' => $idle];
        [$status] = megaHttpRequest($otherClient, $megaUrl . '?' . http_build_query($idlePost));
        megaHttpCheck($status === 405, 'GET cannot redeem an anonymous ticket');
        [$status] = megaHttpRequest($madeUp, $megaUrl, $idlePost + ['csrf' => $otherAuth['csrf']]);
        megaHttpCheck($status === 403, 'an anonymous token without its cookie is refused');
        [$status, $idleFirst] = megaHttpRequest($otherClient, $megaUrl, $idlePost + ['csrf' => $otherAuth['csrf']]);
        [, $idleRetry] = megaHttpRequest($otherClient, $megaUrl, $idlePost + ['csrf' => $otherAuth['csrf']]);
        megaHttpCheck($status === 200 && isset($idleFirst['proof']) && $idleRetry === $idleFirst, 'anonymous ticket redemption is protected and retryable');

        [, $me] = megaHttpRequest($client, $base . '/__mega-account?finds');
        $start = new DateTimeImmutable('yesterday 03:02:00', new DateTimeZone('Europe/Warsaw'));
        $keys = ['builders', 'floor0', 'maze', 'paper', 'gene', 'admin'];
        $proofs = [];
        foreach ($keys as $i => $key)
            $proofs[$key] = megaProof('smoke-register-secret', $key, $start->getTimestamp() + 31 * $i, null, '900000000000000010');
        $claim = ['action' => 'finds', 'csrf' => $me['csrf'], 'found' => json_encode(array_fill_keys($keys, 1)), 'proofs' => json_encode($proofs)];
        [$status, $answer] = megaHttpRequest($client, $base . '/__mega-account', $claim);
        megaHttpCheck($status === 200 && ($answer['rejected'] ?? null) === $keys && empty($answer['finds']), 'another account cannot claim a fresh donated chain of proofs');
        foreach ($keys as $i => $key)
            $proofs[$key] = megaProof('smoke-register-secret', $key, $start->getTimestamp() + 31 * $i, null, '900000000000000011');
        $claim['proofs'] = json_encode($proofs);
        [$status, $answer] = megaHttpRequest($client, $base . '/__mega-account', $claim);
        megaHttpCheck($status === 200 && empty($answer['rejected']) && array_keys($answer['finds'] ?? []) === $keys, 'the issuing account can claim its full valid chain');

        for ($i = 0; $i < MEGA_TICKET_RATE; $i++) {
            [$status] = megaHttpRequest($client, $megaUrl . '?ticket=idle');
            if ($status !== 200) break;
        }
        megaHttpCheck($i === MEGA_TICKET_RATE, 'normal ticket issuance fits the address budget');
        [$status] = megaHttpRequest($client, $megaUrl . '?ticket=idle');
        megaHttpCheck($status === 429, 'excessive ticket issuance receives HTTP 429');
        foreach (['witness', 'snake'] as $decoy) {
            [$status] = megaHttpRequest($client, $megaUrl . '?' . http_build_query(['ticket' => 'idle', $decoy => ['ignored']]));
            megaHttpCheck($status === 429, 'an array-valued ' . $decoy . ' cannot bypass issuance throttling');
        }
        [$status, $afterLimit] = megaHttpRequest($client, $megaUrl, $gamePost + ['csrf' => $csrf]);
        megaHttpCheck($status === 200 && $afterLimit === $first, 'issuance throttling leaves existing game retries available');
    } catch (Throwable $error) {
        megaHttpCheck(false, 'register HTTP regression: ' . $error->getMessage());
    }
