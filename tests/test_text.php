<?php
    // inc/text.php: escaping, lower case, Polish plurals, sizes and thousands.

    test('e escapes HTML', function () {
        assertSame('&lt;b&gt;&amp;&quot;&#039;', e('<b>&"\''));
        assertSame('', e(null));
        assertSame('zwykły tekst', e('zwykły tekst'));
    });

    test('lower handles UTF-8 and ASCII', function () {
        assertSame('łowiec', lower('ŁOWIEC'));
        assertSame('abc', lower('ABC'));
        assertSame('', lower(''));
    });

    // the servers run without mbstring, so its stand-in is tested on its own
    test('lowerWithoutMb lowers what mbstring would', function () {
        assertSame('zażółć gęślą jaźń', lowerWithoutMb('ZAŻÓŁĆ GĘŚLĄ JAŹŃ'));
        assertSame('łowiec abc 123', lowerWithoutMb('Łowiec ABC 123'));
        assertSame('àéîõüýþ ÷×', lowerWithoutMb('ÀÉÎÕÜÝÞ ÷×'));
        assertSame('ďĺňřšťůž ÿ', lowerWithoutMb('ĎĹŇŘŠŤŮŽ Ÿ'));
        assertSame('αβγ ωϊ', lowerWithoutMb('ΑΒΓ ΩΪ'));
        assertSame('привет ёђ', lowerWithoutMb('ПРИВЕТ ЁЂ'));
        assertSame('już małe', lowerWithoutMb('już małe'));
        assertSame('', lowerWithoutMb(null));
        if (function_exists('mb_strtolower')) {
            $text = 'ZAŻÓŁĆ Ĳ Ŋ Ő Ű ŒŠŽ ΆΈ ΣΤ ЀЏ АЯ ĂĊĠĦĨĪĬĮ';
            assertSame(mb_strtolower($text, 'UTF-8'), lowerWithoutMb($text));
        }
    });

    test('plural follows the Polish rules', function () {
        assertSame('plik', plural(1, 'plik', 'pliki', 'plików'));
        assertSame('pliki', plural(2, 'plik', 'pliki', 'plików'));
        assertSame('pliki', plural(4, 'plik', 'pliki', 'plików'));
        assertSame('plików', plural(5, 'plik', 'pliki', 'plików'));
        assertSame('plików', plural(12, 'plik', 'pliki', 'plików'));
        assertSame('plików', plural(14, 'plik', 'pliki', 'plików'));
        assertSame('pliki', plural(22, 'plik', 'pliki', 'plików'), '22 pliki');
        assertSame('pliki', plural(23, 'plik', 'pliki', 'plików'), '23 pliki');
        assertSame('plików', plural(25, 'plik', 'pliki', 'plików'), '25 plików');
    });

    test('formatSize picks the unit', function () {
        assertSame('512 B', formatSize(512));
        assertSame('2 KB', formatSize(2048));
        assertSame('5 MB', formatSize(5 * 1048576));
        assertSame('3 GB', formatSize(3 * 1073741824));
    });

    test('formatCount splits the thousands', function () {
        assertSame('41 234', formatCount(41234));
        assertSame('0', formatCount(0));
        assertSame('1 000 000', formatCount(1000000));
    });

    test('textDiff finds what changed, a word at a time', function () {
        assertSame(['pozwala wyświetlić ', 'liste figurę', 'listę figurek', '/ustawić aktywną figurkę'],
            textDiff('pozwala wyświetlić liste figurę/ustawić aktywną figurkę', 'pozwala wyświetlić listę figurek/ustawić aktywną figurkę'));
        assertSame(['a · b', '', ' · c', ''], textDiff('a · b', 'a · b · c'), 'a parameter added at the end');
        assertSame(['używa ', 'przedmiot', 'przedmiotu', ' na karcie'], textDiff('używa przedmiot na karcie', 'używa przedmiotu na karcie'));
        assertSame(['', '', 'nowe', ''], textDiff('', 'nowe'));
        assertSame(['', 'ab', 'aXb', ''], textDiff('ab', 'aXb'), 'never half a word');
        assertSame(['zażółć ', 'gęślą', 'jaźń', ''], textDiff('zażółć gęślą', 'zażółć jaźń'));
    });
