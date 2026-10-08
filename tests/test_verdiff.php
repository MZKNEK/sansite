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

    test('verdiffChanges and versionEntries match by version', function () {
        file_put_contents(botFile('verdiff.json'), json_encode([
            'fetched' => time(),
            'hash' => 'abc',
            'sections' => verdiffParse("# 1.4.10.14\n\nData: 2026-10-08\nCommit: aaaa111\n\n- new\n\n# 1.4.10.12\n\nData: 2026-10-01\n\n- old\n")
        ]));
        $now = time();
        file_put_contents(botFile('versions.json'), json_encode([
            ['version' => '1.4.10.13', 'since' => $now - 600, 'seen' => $now - 600],
            ['version' => '1.4.10.14', 'since' => $now - 60, 'seen' => $now - 60],
        ]));

        assertSame('aaaa111', verdiffChanges('1.4.10.14')['commit']);
        assertNull(verdiffChanges('9.9.9'));

        $entries = versionEntries();
        assertSame(2, count($entries), 'only the versions the bot reported');
        assertSame('1.4.10.14', $entries[0]['version'], 'newest first');
        assertTrue($entries[0]['changes']);
        assertSame('aaaa111', $entries[0]['commit']);
        assertSame('1.4.10.13', $entries[1]['version'], 'recorded without a section');
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

    test('markdownToHtml tags an @nick with its rank', function () {
        $mentions = ['sniku' => ['name' => 'Sniku', 'avatar' => '', 'badge' => ['key' => 'dev', 'level' => 9, 'label' => 'DEV', 'name' => 'dev'], 'roles' => ['dev']]];
        $html = markdownToHtml('@sniku', $mentions);
        assertContains('<span class="lv role-dev">LV.9 dev</span>', $html);
        assertFalse(strpos($html, 'mention-pop'), 'no popup, the rank shows at once');
        assertFalse(strpos($html, 'mention-btn'), 'no button');
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
