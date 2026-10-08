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
        assertSame(3, count($entries), 'the two recorded plus the changelog-only one');
        assertSame('1.4.10.14', $entries[0]['version'], 'newest first');
        assertTrue($entries[0]['changes']);
        assertSame('aaaa111', $entries[0]['commit']);
        assertSame('1.4.10.13', $entries[1]['version'], 'recorded without a section');
        assertFalse($entries[1]['changes']);
        assertSame('1.4.10.12', $entries[2]['version'], 'only in the changelog');
        assertSame(strtotime('2026-10-01'), $entries[2]['since'], 'its date is used');
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
