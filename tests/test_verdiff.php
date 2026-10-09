<?php
    // inc/verdiff.php and inc/markdown.php: the bot's changelog from its
    // repository (verdiff.md) and the Markdown it is written in.

    test('verdiffParse reads a version out of the changelog', function () {
        $text = "# 1.4.10.14\n\nData: 2026-10-08\nCommit: 1a2b3c4d\n\n- first\n- second\n\n## Techniczne\n\n- faster\n\n# 1.4.10.13\n\nData: 2026-10-06\n\n- older\n";
        $sections = verdiffParse($text);
        assertSame(2, count($sections));

        $new = $sections['1.4.10.14'];
        assertSame('1.4.10.14', $new['version']);
        assertSame('2026-10-08', $new['date']);
        assertSame('1a2b3c4d', $new['commit']);
        assertContains('- first', $new['changes']);
        assertContains('## Techniczne', $new['changes'], 'a level-2 heading stays in the body');
        assertFalse(strpos($new['changes'], 'Data:'), 'the date line is metadata');
        assertFalse(strpos($new['changes'], 'Commit:'), 'the commit line is metadata');
        assertNull($sections['1.4.10.13']['commit'], 'a version without a commit line');
    });

    test('verdiffKey ignores a leading v', function () {
        assertSame('1.2.3', verdiffKey('v1.2.3'));
        assertSame('1.2.3', verdiffKey(' 1.2.3 '));
    });

    test('verdiffDue asks again for a new version without a section', function () {
        $now = time();
        $cache = ['fetched' => $now - 60, 'tried' => $now - 60, 'sections' => verdiffParse("# 1.4.10.14\n\n- new\n")];
        $known = ['version' => '1.4.10.14', 'since' => $now - 60];
        $missing = ['version' => '1.4.10.15', 'since' => $now - 60];
        assertTrue(verdiffDue(null, null, $now), 'nothing cached yet');
        assertFalse(verdiffDue($cache, $known, $now), 'the newest version is in it');
        assertFalse(verdiffDue($cache, null, $now), 'no version reported');
        assertFalse(verdiffDue($cache, $missing, $now), 'missing, but tried a minute ago');
        assertTrue(verdiffDue($cache, $missing, $now + VERDIFF_RETRY), 'missing and the retry is up');
        assertTrue(verdiffDue($cache, $known, $now + VERDIFF_TTL), 'too old anyway');
        assertFalse(verdiffDue($cache, ['version' => '1.4.10.15', 'since' => $now - VERDIFF_NEW], $now + VERDIFF_RETRY), 'missing for too long, back to VERDIFF_TTL');
        unset($cache['tried']);
        assertTrue(verdiffDue($cache, $missing, $now + VERDIFF_RETRY), 'an old cache without the try time');
    });

    test('verdiff keeps a section the file no longer has', function () {
        $now = time();
        file_put_contents(botFile('verdiff.json'), json_encode([
            'fetched' => $now - VERDIFF_TTL, 'hash' => 'old',
            'sections' => verdiffParse("# 1.4.10.14\n\n- old text\n\n# 1.4.10.15\n\n- draft\n"),
        ]));
        $GLOBALS['SANAKAN_HTTP'] = function () {
            return ["# 1.4.10.15\n\n- final\n", 200];
        };
        $cache = verdiff('https://raw.githubusercontent.com/o/r/master/verdiff.md');
        unset($GLOBALS['SANAKAN_HTTP']);

        assertSame('- old text', $cache['sections']['1.4.10.14']['changes'], 'kept from before');
        assertSame('- final', $cache['sections']['1.4.10.15']['changes'], 'the file wins');
    });

    test('verdiffBackfill reads the missed sections from the history', function () {
        $now = time();
        $a = str_repeat('a', 40);
        $b = str_repeat('b', 40);
        file_put_contents(botFile('verdiff.json'), json_encode([
            'fetched' => $now, 'tried' => $now, 'hash' => 'x',
            'sections' => verdiffParse("# 1.4.10.15\n\n- current\n"),
        ]));
        file_put_contents(botFile('versions.json'), json_encode([
            ['version' => '1.4.10.14', 'since' => $now - 86400],
            ['version' => '1.4.10.15', 'since' => $now - 60],
        ]));
        $asked = [];
        $GLOBALS['SANAKAN_HTTP'] = function ($url) use ($a, $b, &$asked) {
            $asked[] = $url;
            if (strpos($url, 'api.github.com/repos/o/r/commits?path=verdiff.md&sha=master') !== false)
                return [json_encode([['sha' => $a], ['sha' => $b]]), 200];
            if (strpos($url, "/o/r/$a/verdiff.md") !== false)
                return ["# 1.4.10.15\n\n- older text of the current one\n", 200];
            if (strpos($url, "/o/r/$b/verdiff.md") !== false)
                return ["# 1.4.10.14\n\nData: 2026-10-08\n\n- recovered\n", 200];

            return [false, 0];
        };
        $url = 'https://raw.githubusercontent.com/o/r/master/verdiff.md';
        verdiffBackfill($url);
        $first = count($asked);
        verdiffBackfill($url);
        unset($GLOBALS['SANAKAN_HTTP']);

        $cache = verdiffCached();
        assertSame('- recovered', $cache['sections']['1.4.10.14']['changes']);
        assertSame('2026-10-08', $cache['sections']['1.4.10.14']['date']);
        assertSame('- current', $cache['sections']['1.4.10.15']['changes'], 'a known section is kept');
        assertSame([$a, $b], $cache['revisions']);
        assertSame(3, $first, 'the history and two revisions');
        assertSame($first, count($asked), 'nothing missing, nothing asked');
    });

    test('a version before VERDIFF_FROM has its built-in changes', function () {
        $now = time();
        file_put_contents(botFile('verdiff.json'), json_encode([
            'fetched' => $now, 'tried' => $now, 'history' => 0, 'hash' => 'x',
            'sections' => verdiffParse("# 1.4.10.14\n\n- new\n"),
        ]));
        file_put_contents(botFile('versions.json'), json_encode([
            ['version' => '1.4.10.13', 'since' => $now - 120],
            ['version' => '1.4.10.14', 'since' => $now - 60],
        ]));

        $section = verdiffChanges('1.4.10.13');
        assertSame('333405b', $section['commit']);
        assertContains('## Techniczne', $section['changes']);
        assertTrue(versionEntries()[1]['changes'], 'listed with its changes');

        assertFalse(verdiffExpected('1.4.10.13'));
        assertTrue(verdiffExpected('1.4.10.14'));
        assertTrue(verdiffExpected('v1.4.11'));
        assertFalse(verdiffDue(verdiffCached(), ['version' => '1.4.10.12', 'since' => $now], $now + VERDIFF_RETRY), 'an old version is not looked for');

        // an old version without a section does not send cron to the history
        $GLOBALS['SANAKAN_HTTP'] = function ($url) {
            throw new Exception('asked ' . $url);
        };
        file_put_contents(botFile('versions.json'), json_encode([
            ['version' => '1.4.10.12', 'since' => $now - 120],
            ['version' => '1.4.10.14', 'since' => $now - 60],
        ]));
        verdiffBackfill('https://raw.githubusercontent.com/o/r/master/verdiff.md');
        unset($GLOBALS['SANAKAN_HTTP']);
    });

    test('verdiffGithub reads a raw address', function () {
        assertSame(['o', 'r', 'master', 'verdiff.md'], verdiffGithub('https://raw.githubusercontent.com/o/r/master/verdiff.md'));
        assertSame(['o', 'r', 'main', 'docs/v.md'], verdiffGithub('https://raw.githubusercontent.com/o/r/refs/heads/main/docs/v.md'));
        assertNull(verdiffGithub('https://example.com/verdiff.md'));
    });

    test('verdiffChanges and versionEntries match by version', function () {
        file_put_contents(botFile('verdiff.json'), json_encode([
            'fetched' => time(),
            'hash' => 'abc',
            'sections' => verdiffParse("# 1.4.10.14\n\nData: 2026-10-08\nCommit: aaaa111\n\n- new\n\n# 1.4.10.12\n\nData: 2026-10-01\n\n- old\n")
        ]));
        $now = time();
        file_put_contents(botFile('versions.json'), json_encode([
            ['version' => '1.4.10.11', 'since' => $now - 600, 'seen' => $now - 600],
            ['version' => '1.4.10.14', 'since' => $now - 60, 'seen' => $now - 60],
        ]));

        assertSame('aaaa111', verdiffChanges('1.4.10.14')['commit']);
        assertNull(verdiffChanges('9.9.9'));

        $entries = versionEntries();
        assertSame(2, count($entries), 'only the versions the bot reported');
        assertSame('1.4.10.14', $entries[0]['version'], 'newest first');
        assertTrue($entries[0]['changes']);
        assertSame('aaaa111', $entries[0]['commit']);
        assertSame('1.4.10.11', $entries[1]['version'], 'recorded without a section');
        assertFalse($entries[1]['changes']);
    });

    test('markdownToHtml renders the changelog and escapes', function () {
        $html = markdownToHtml("# Title\n\n- one **bold**\n- two `code`\n\n## Sec\n\n> note\n\n[a](https://x) [b](javascript:1)");
        assertContains('<h1>Title</h1>', $html);
        assertContains('<strong>bold</strong>', $html);
        assertContains('<code>code</code>', $html);
        assertContains('<h2>Sec</h2>', $html);
        assertContains('<blockquote>note</blockquote>', $html);
        assertContains('<a href="https://x"', $html);
        assertFalse(strpos($html, 'href="javascript:'), 'a bad link target is not turned into a link');
        assertContains('&lt;script&gt;', markdownToHtml('<script>alert(1)</script>'));
    });

    test('markdownToHtml draws an @nick the site knows', function () {
        $mentions = ['sniku' => ['name' => 'Sniku', 'avatar' => 'https://cdn.discordapp.com/avatars/1/a.png?size=64']];
        $html = markdownToHtml('Dzięki @sniku i @ktos!', $mentions);
        assertContains('<span class="mention"', $html);
        assertContains('>Sniku</span>', $html);
        assertContains('cdn.discordapp.com/avatars/1/a.png', $html);
        assertContains('@ktos', $html, 'an unknown nick stays plain text');

        // a handle with an underscore is not eaten by the italic rule
        $html = markdownToHtml('@some_nick', ['some_nick' => ['name' => 'Some Nick', 'avatar' => '']]);
        assertContains('<span class="mention"', $html);
        assertContains('Some Nick', $html);
        assertFalse(strpos($html, '<em>'), 'the underscore is not emphasis');

        // an e-mail is not a mention
        assertFalse(strpos(markdownToHtml('napisz na a@b.pl', $mentions), 'class="mention"'), 'a mid-word @ is not a mention');
    });

    test('markdownToHtml tags an @nick with its rank on hover', function () {
        $mentions = ['sniku' => ['name' => 'Sniku', 'avatar' => '', 'badge' => ['key' => 'dev', 'level' => 9, 'label' => 'DEV', 'name' => 'dev'], 'roles' => ['dev']]];
        $html = markdownToHtml('@sniku', $mentions);
        assertContains('<button type="button" class="mention-btn"', $html);
        assertContains('mention-pop', $html);
        assertContains('<span class="lv role-dev">LV.9 dev</span>', $html);
        assertFalse(strpos($html, 'mention-roles'), 'just the rank tag');
    });

    test('mentionUsers maps the handle, the nick and the rank', function () {
        writeData('logins', ['7' => ['name' => 'Sniku', 'username' => 'sniku', 'avatar' => 'https://cdn.discordapp.com/avatars/7/a.png?size=64']]);
        writeData('roles', ['7' => ['roles' => ['onGuild' => true, 'dev' => true], 'checked' => time()]]);
        $users = mentionUsers();
        assertSame('Sniku', $users['sniku']['name']);
        assertSame('https://cdn.discordapp.com/avatars/7/a.png?size=64', $users['sniku']['avatar']);
        assertSame(9, $users['sniku']['badge']['level']);
        assertSame(['dev'], $users['sniku']['roles']);
    });
