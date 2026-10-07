<?php
    // The outbound HTTP, with httpRaw() replaced by a stub (SANAKAN_HTTP): the
    // Discord login, the bot API, the Cloudflare API and the service checks.

    // answers a request by the first matching needle; [body, status]
    function withHttp(array $responses)
    {
        $GLOBALS['SANAKAN_HTTP'] = function ($url, $options, $maxLen) use ($responses) {
            foreach ($responses as $needle => $answer)
                if (strpos($url, (string)$needle) !== false)
                    return $answer;

            return [false, 0];
        };
    }

    function withoutHttp()
    {
        unset($GLOBALS['SANAKAN_HTTP']);
    }

    test('botFetch measures and reads the status', function () {
        withHttp(['/api/health' => ['body', 200]]);
        [$body, $status, $ms] = botFetch('https://x/api/health');
        withoutHttp();
        assertSame('body', $body);
        assertSame(200, $status);
        assertTrue($ms >= 0);

        withHttp([]);
        [$body, $status] = botFetch('https://x/api/health');
        withoutHttp();
        assertFalse($body);
        assertSame(0, $status);
    });

    test('httpRaw reads the whole body, or the given number of bytes', function () {
        // a file:// URL stands in for the network, so the real httpRaw() runs
        $file = tempDir() . '/body.txt';
        file_put_contents($file, 'hello world');
        $url = 'file://' . (DIRECTORY_SEPARATOR === '\\' ? '/' : '') . $file;

        [$body] = httpRaw($url);
        assertSame('hello world', $body, 'no max length reads it whole');

        [$part] = httpRaw($url, [], 5);
        assertSame('hello', $part, 'a max length cuts it');
    });

    test('botAppGet needs a 200 with JSON', function () {
        withHttp(['/permissions' => [json_encode(['onGuild' => true]), 200]]);
        assertSame(['onGuild' => true], botAppGet('https://x/permissions'));
        withoutHttp();

        withHttp(['/permissions' => ['{}', 500]]);
        assertNull(botAppGet('https://x/permissions'), 'a 500 is not an answer');
        withoutHttp();
    });

    test('discordRequest decodes the answer', function () {
        withHttp(['/users/@me' => [json_encode(['id' => '1']), 200]]);
        assertSame(['id' => '1'], discordRequest('/users/@me', null, 'token'));
        withoutHttp();

        withHttp([]);
        assertSame([], discordRequest('/users/@me'), 'a request that cannot be made');
        withoutHttp();
    });

    test('dialogLogin exchanges the code and stores the account', function () {
        sessionFor('sanakan-http-login', ['id' => 'x', 'name' => 'x', 'avatar' => 'x']);
        $_SESSION['oauth_state'] = 'st';
        $_SESSION['oauth_return'] = '/i/';
        $_GET = ['state' => 'st', 'code' => 'abc'];
        withHttp([
            '/oauth2/token' => [json_encode(['access_token' => 'tok']), 200],
            '/users/@me' => [json_encode(['id' => '123', 'global_name' => 'Nick', 'username' => 'nick', 'avatar' => 'hash']), 200],
        ]);
        $back = finishLogin();
        withoutHttp();
        $_GET = [];

        assertSame('/i/', $back);
        assertSame('123', $_SESSION['gallery_user']['id']);
        assertSame('Nick', $_SESSION['gallery_user']['name']);
        assertContains('cdn.discordapp.com/avatars/123/hash.png', $_SESSION['gallery_user']['avatar']);
        assertTrue(isset(readData('logins')['123']), 'the login is remembered');
        assertContains('Zalogowano', takeFlash());
        sessionEnd();
    });

    test('finishLogin refuses a wrong state, an error and a broken token', function () {
        sessionFor('sanakan-http-login2', ['id' => 'x', 'name' => 'x', 'avatar' => 'x']);
        $_SESSION['oauth_state'] = 'st';
        $_SESSION['oauth_return'] = '/i/';
        $_GET = ['state' => 'wrong', 'code' => 'abc'];
        assertSame('/i/', finishLogin());
        assertContains('wygasło', takeFlash());
        sessionEnd();

        sessionFor('sanakan-http-login3', ['id' => 'x', 'name' => 'x', 'avatar' => 'x']);
        $_SESSION['oauth_state'] = 'st';
        $_SESSION['oauth_return'] = '/account/';
        $_GET = ['state' => 'st', 'error' => 'access_denied'];
        assertSame('/account/', finishLogin());
        assertContains('anulowane', takeFlash());
        sessionEnd();

        sessionFor('sanakan-http-login4', ['id' => 'x', 'name' => 'x', 'avatar' => 'x']);
        $_SESSION['oauth_state'] = 'st';
        $_SESSION['oauth_return'] = '/i/';
        $_GET = ['state' => 'st', 'code' => 'abc'];
        withHttp(['/oauth2/token' => [json_encode([]), 200]]);
        assertSame('/i/', finishLogin());
        withoutHttp();
        assertContains('nie potwierdził', takeFlash());
        sessionEnd();
        $_GET = [];
    });

    test('botAskHealth reads api/health', function () {
        withHttp(['/api/health' => [json_encode(['status' => 'ok', 'discord' => ['state' => 'Connected', 'latencyMs' => 30]]), 200]]);
        [$online, $ms, $health] = botAskHealth();
        withoutHttp();
        assertTrue($online);
        assertSame(30, $ms);
        assertSame('ok', $health['status']);

        withHttp(['/api/health' => ['nope', 404]]);
        assertNull(botAskHealth(), 'a bot without api/health');
        withoutHttp();

        withHttp(['/api/health' => [false, 0]]);
        assertSame([false, null, null], botAskHealth());
        withoutHttp();
    });

    test('botState follows the report', function () {
        withHttp(['/api/health' => [json_encode(['status' => 'ok', 'discord' => ['state' => 'Connected', 'latencyMs' => 10]]), 200]]);
        $state = botState(true);
        withoutHttp();
        assertSame('online', $state['status']);

        withHttp(['/api/health' => [false, 0]]);
        $state = botState(true);
        withoutHttp();
        assertSame('offline', $state['status']);
    });

    test('cloudflareApi maps success and errors', function () {
        withHttp(['/rules/lists' => [json_encode(['success' => true, 'result' => [['id' => 'L1']]]), 200]]);
        $answer = cloudflareApi('GET', '/acc/rules/lists');
        withoutHttp();
        assertTrue($answer[0]);
        assertSame('L1', $answer[1][0]['id']);

        withHttp(['/rules/lists' => [json_encode(['success' => false, 'errors' => [['message' => 'no']]]), 200]]);
        $answer = cloudflareApi('GET', '/acc/rules/lists');
        withoutHttp();
        assertFalse($answer[0]);
        assertContains('no', $answer[1]);

        withHttp([]);
        $answer = cloudflareApi('GET', '/acc/rules/lists');
        withoutHttp();
        assertFalse($answer[0]);
        assertContains('nie odpowiedział', $answer[1]);
    });

    test('cloudflareListId finds and remembers the list', function () {
        withHttp(['/rules/lists' => [json_encode(['success' => true, 'result' => [['id' => 'L9', 'name' => CLOUDFLARE_LIST, 'kind' => 'ip']]]), 200]]);
        [$id, $error] = cloudflareListId();
        withoutHttp();
        assertSame('L9', $id);
        assertNull($error);
        assertSame('L9', readData('cloudflare-list')['id']);

        // the remembered id is used without a request (the stub would fail one)
        withHttp([]);
        [$id2] = cloudflareListId();
        withoutHttp();
        assertSame('L9', $id2);
    });

    test('cloudflareBlocked lists the items', function () {
        writeData('cloudflare-list', ['name' => CLOUDFLARE_LIST, 'id' => 'L9']);
        withHttp(['/L9/items' => [json_encode([
            'success' => true,
            'result' => [['id' => 'i1', 'ip' => '8.8.8.8', 'comment' => 'a', 'created_on' => '2026-01-01T00:00:00Z']],
            'result_info' => ['cursors' => ['after' => null]],
        ]), 200]]);
        [$items, $error] = cloudflareBlocked();
        withoutHttp();
        assertNull($error);
        assertSame('8.8.8.8', $items[0]['ip']);
        assertSame('a', $items[0]['comment']);
    });

    test('cloudflareWait tells done, failed and unconfirmed apart', function () {
        withHttp(['/bulk_operations/op1' => [json_encode(['success' => true, 'result' => ['status' => 'completed']]), 200]]);
        assertNull(cloudflareWait(['operation_id' => 'op1'], 1));
        withoutHttp();

        withHttp(['/bulk_operations/op2' => [json_encode(['success' => true, 'result' => ['status' => 'failed', 'error' => 'boom']]), 200]]);
        assertContains('boom', cloudflareWait(['operation_id' => 'op2'], 1));
        withoutHttp();

        withHttp(['/bulk_operations/op3' => [json_encode(['success' => true, 'result' => ['status' => 'running']]), 200]]);
        assertContains('nie potwierdził', cloudflareWait(['operation_id' => 'op3'], 1));
        withoutHttp();

        assertNull(cloudflareWait([], 1), 'nothing to wait for');
    });

    test('serviceCheck counts a page that answers', function () {
        withHttp(['sanakan.pl' => ['<html>', 200]]);
        [$up, $ms] = serviceCheck('https://sanakan.pl/');
        withoutHttp();
        assertTrue($up);
        assertTrue($ms >= 0);

        withHttp(['sanakan.pl' => ['nope', 404]]);
        [$notFound] = serviceCheck('https://sanakan.pl/');
        withoutHttp();
        assertFalse($notFound);

        withHttp(['sanakan.pl' => [false, 0]]);
        [$down] = serviceCheck('https://sanakan.pl/');
        withoutHttp();
        assertFalse($down);
    });
