<?php
    // How an account answers the panel's search (accountSearchMatch() in
    // inc/admin/format.php, used by inc/panel-search.php): by its ID, name or
    // Discord name, "@name" as the search's hint puts it, the whole of it
    // ranked before its start and the start before the rest.
    require_once __DIR__ . '/../inc/admin/format.php';

    test('the panel search finds an account by @name, its Discord name kept without the @', function () {
        $login = ['name' => 'Kasia', 'username' => 'kasia_93'];
        assertSame([0, '@kasia_93'], accountSearchMatch('@kasia_93', '123456789012345678', $login), 'the whole @name');
        assertSame([0, '@kasia_93'], accountSearchMatch(' @Kasia_93 ', '123456789012345678', $login), 'trimmed and in any case');
        assertSame([1, '@kasia_93'], accountSearchMatch('@kasia', '123456789012345678', $login), 'only the Discord name, not the same name');
        assertSame([3, 'nazwa'], accountSearchMatch('@kasia', '123456789012345678', ['name' => 'Kasia', 'username' => 'kmalinowska']), 'the name shown after every Discord name');
        assertSame([0, 'nazwa'], accountSearchMatch('kasia', '123456789012345678', $login), 'without the @ the name comes first');
        assertSame(null, accountSearchMatch('@', '123456789012345678', $login), 'a lone @ finds nothing');
        assertSame(null, accountSearchMatch('@1234', '123456789012345678', $login), 'an @name is never the ID');
    });

    test('the panel search ranks the whole match before its start and the start before the rest', function () {
        assertSame([0, 'ID'], accountSearchMatch('123456789012345678', '123456789012345678', []), 'the whole ID');
        assertSame([1, 'ID'], accountSearchMatch('1234', '123456789012345678', []), 'the start of the ID');
        assertSame([2, 'ID'], accountSearchMatch('5678', '123456789012345678', []), 'part of the ID');
        assertSame([2, 'nazwa'], accountSearchMatch('ola', 'x', ['name' => 'Karola']), 'a name containing it');
        assertSame([1, '@olaf'], accountSearchMatch('ola', 'x', ['name' => 'Karola', 'username' => 'olaf']), 'the better of the two wins');
        assertSame([0, 'nazwa'], accountSearchMatch('ŁUKASZ', 'x', ['name' => 'Łukasz']), 'Polish capitals too');
        assertSame(null, accountSearchMatch('zenon', 'x', ['name' => 'Kasia', 'username' => 'kasia']), 'no match');
    });
