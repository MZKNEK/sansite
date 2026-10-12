<?php
    // inc/megastructure.php: the way to the Net Terminal Gene, all of it from
    // a secret, given out by megastructure.php each where it belongs.

    function megaAt($hour, $minute)
    {
        return new DateTimeImmutable(sprintf('2026-10-12 %02d:%02d:30', $hour, $minute), new DateTimeZone('Europe/Warsaw'));
    }

    test('the gene, its corridor and its words come from the secret alone', function () {
        assertSame(geneSequence('a'), geneSequence('a'), 'the same secret, the same gene');
        assertTrue(geneSequence('a') !== geneSequence('b'), 'another secret, another gene');
        assertTrue((bool)preg_match('/^[0-9A-F]{4}(-[0-9A-F]{4}){3}$/', geneSequence('a')));
        $route = megaRoute('a');
        assertSame(MEGA_ROUTE_STEPS + 1, count($route));
        assertSame(count($route), count(array_unique($route)), 'every address its own');
        foreach ($route as $path)
            assertTrue((bool)preg_match('~^/[a-z]+/[0-9a-f]{6}$~', $path), $path);
        assertTrue((bool)preg_match('/^[a-z]{5}$/', megaConsoleWord('a')));
        assertSame(megaMaze('a'), megaMaze('a'));
        assertTrue(megaMaze('a')['rows'] !== megaMaze('b')['rows'], 'another secret, another maze');
    });

    test('the Builders\' door opens from 3:00 to 3:07 and from 15:00 to 15:07, Polish time', function () {
        assertSame(false, buildersHour(megaAt(2, 59)));
        assertSame(true, buildersHour(megaAt(3, 0)));
        assertSame(true, buildersHour(megaAt(3, 6)));
        assertSame(false, buildersHour(megaAt(3, 7)));
        assertSame(false, buildersHour(megaAt(14, 59)));
        assertSame(true, buildersHour(megaAt(15, 0)));
        assertSame(true, buildersHour(megaAt(15, 6)));
        assertSame(false, buildersHour(megaAt(15, 7)));
        assertSame(false, buildersHour(megaAt(4, 3)));
        assertSame(true, buildersHour(new DateTimeImmutable('2026-10-12 01:03:00', new DateTimeZone('UTC'))), 'the server\'s own zone does not matter');
        assertSame(['door' => false], megastructureAnswer('a', ['at' => '/nope'], megaAt(12, 0)), 'a plain dead end');
        assertSame(['closed' => true], megastructureAnswer('a', ['door' => '1'], megaAt(12, 0)));
        assertSame(['fragment' => geneFragment('a', 1), 'corridor' => megaRoute('a')[0]], megastructureAnswer('a', ['door' => '1'], megaAt(3, 2)));
    });

    test('the corridor leads from one address to the next, down to floor zero', function () {
        $route = megaRoute('a');
        $noon = megaAt(12, 0);
        for ($i = 0; $i < MEGA_ROUTE_STEPS; $i++)
            assertSame(['door' => false, 'draught' => $route[$i + 1]], megastructureAnswer('a', ['at' => $route[$i]], $noon));
        assertSame(['door' => false, 'draught' => $route[1]], megastructureAnswer('a', ['at' => strtoupper($route[0]) . '/'], $noon), 'as the 404 page may show it');
        $zero = megastructureAnswer('a', ['at' => end($route)], $noon);
        assertSame(geneFragment('a', 2), $zero['zero']['fragment']);
        assertSame(megaMaze('a'), $zero['zero']['maze']);
    });

    // the shortest walk through a maze, from its way in to its terminal
    function megaSolve($maze)
    {
        $steps = ['U' => [0, -1], 'D' => [0, 1], 'L' => [-1, 0], 'R' => [1, 0]];
        $from = [implode(',', $maze['start']) => null];
        $queue = [$maze['start']];
        for ($i = 0; $i < count($queue); $i++) {
            [$x, $y] = $queue[$i];
            foreach ($steps as $move => [$dx, $dy]) {
                $key = ($x + $dx) . ',' . ($y + $dy);
                if (($maze['rows'][$y + $dy][$x + $dx] ?? '#') === ' ' && !array_key_exists($key, $from)) {
                    $from[$key] = [$x . ',' . $y, $move];
                    $queue[] = [$x + $dx, $y + $dy];
                }
            }
        }
        $walk = '';
        for ($at = implode(',', $maze['end']); $from[$at] !== null; $at = $from[$at][0])
            $walk = $from[$at][1] . $walk;

        return $walk;
    }

    test('the maze of floor zero has a way in on the left and its terminal deep inside', function () {
        $maze = megaMaze('a');
        assertSame(2 * MEGA_MAZE_H + 1, count($maze['rows']));
        assertSame(2 * MEGA_MAZE_W + 1, strlen($maze['rows'][0]));
        [$x, $y] = $maze['start'];
        assertSame(0, $x, 'on the left edge');
        assertSame(' ', $maze['rows'][$y][0]);
        [$ex, $ey] = $maze['end'];
        assertSame([1, 1], [$ex % 2, $ey % 2], 'in a cell');
        assertTrue(strlen(megaSolve($maze)) > MEGA_MAZE_W, 'far from the way in');
        $open = 0;
        foreach ($maze['rows'] as $row)
            $open += substr_count($row, ' ');
        assertSame(2 * MEGA_MAZE_W * MEGA_MAZE_H, $open, 'a perfect maze: every cell, one way between them, and the way in');
    });

    test('the maze walked to its terminal gives the word for the console, a walk through a wall nothing', function () {
        $noon = megaAt(12, 0);
        $walk = megaSolve(megaMaze('a'));
        assertSame(['word' => megaConsoleWord('a')], megastructureAnswer('a', ['walk' => strtolower($walk)], $noon));
        assertSame(['word' => megaConsoleWord('a')], megastructureAnswer('a', ['walk' => 'RL' . $walk], $noon), 'steps back are fine');
        assertSame(['reached' => false], megastructureAnswer('a', ['walk' => substr($walk, 0, -1)], $noon), 'short of the terminal');
        assertSame(['reached' => false], megastructureAnswer('a', ['walk' => 'L' . $walk], $noon), 'out of the maze');
        assertSame(['reached' => false], megastructureAnswer('a', ['walk' => 'R' . str_repeat('U', 2 * MEGA_MAZE_H) . $walk], $noon), 'through a wall');
        assertSame(['reached' => false], megastructureAnswer('a', ['walk' => str_repeat('RL', MEGA_WALK_MAX) . $walk], $noon), 'too long');
        assertSame(['reached' => false], megastructureAnswer('a', ['walk' => ''], $noon));
        assertSame(['reached' => false], megastructureAnswer('a', ['walk' => 'X'], $noon));
        assertTrue(megaWalk('b', megaSolve(megaMaze('b'))), 'every secret its own maze, each walkable');
    });

    test('the console word on paper gives the fourth fragment', function () {
        $noon = megaAt(12, 0);
        assertSame(['fragment' => geneFragment('a', 4)], megastructureAnswer('a', ['paper' => strtoupper(megaConsoleWord('a'))], $noon));
        assertSame(['known' => false], megastructureAnswer('a', ['paper' => 'toha'], $noon));
        assertSame([], megastructureAnswer('', ['paper' => megaConsoleWord('a')], $noon));
    });

    test('the console word gives the third fragment', function () {
        $noon = megaAt(12, 0);
        assertSame(['known' => false], megastructureAnswer('a', ['ask' => 'toha'], $noon));
        assertSame(['fragment' => geneFragment('a', 3)], megastructureAnswer('a', ['ask' => strtoupper(megaConsoleWord('a'))], $noon));
    });

    test('the terminal takes the gene however it is written', function () {
        $noon = megaAt(12, 0);
        $gene = geneSequence('a');
        assertSame(['ok' => true], megastructureAnswer('a', ['gene' => $gene], $noon));
        assertSame(['ok' => true], megastructureAnswer('a', ['gene' => strtolower(str_replace('-', ' ', $gene))], $noon));
        assertSame(['ok' => false], megastructureAnswer('a', ['gene' => substr($gene, 0, -1)], $noon));
        assertSame(['ok' => false], megastructureAnswer('a', ['gene' => ''], $noon));
        assertSame(['ok' => false], megastructureAnswer('a', ['gene' => geneSequence('b')], $noon));
    });

    test('without the secret there is nothing to find', function () {
        foreach ([['at' => '/x'], ['door' => '1'], ['walk' => 'R'], ['ask' => 'x'], ['gene' => 'x'], []] as $query)
            assertSame([], megastructureAnswer('', $query, megaAt(3, 1)));
        assertSame([], megastructureAnswer('a', ['nothing' => '1'], megaAt(3, 1)));
    });

    // a cipher of megaCipher() undone with its key
    function megaDecipher($text, $key)
    {
        $out = '';
        $k = 0;
        foreach (str_split($text) as $char) {
            if ($char >= 'A' && $char <= 'Z') {
                $char = chr(65 + (ord($char) - 65 - (ord($key[$k % strlen($key)]) - 65) + 26) % 26);
                $k++;
            }
            $out .= $char;
        }

        return $out;
    }

    test('only the gene opens the way to the archive', function () {
        $noon = megaAt(12, 0);
        assertSame(['denied' => true], megastructureAnswer('a', ['enter' => geneSequence('b')], $noon));
        assertSame(['denied' => true], megastructureAnswer('a', ['enter' => ''], $noon));
        $open = megastructureAnswer('a', ['enter' => strtolower(str_replace('-', '', geneSequence('a')))], $noon);
        assertSame(megaArchivePath('a'), $open['archive']);
        assertTrue((bool)preg_match('~^/netsfera/[0-9a-f]{8}$~', $open['archive']));
        assertTrue(megaArchivePath('a') !== megaArchivePath('b'));
    });

    test('the archive\'s record deciphers with the letters the register shows', function () {
        $noon = megaAt(12, 0);
        $archive = megastructureAnswer('a', ['at' => megaArchivePath('a')], $noon)['archive'];
        assertSame(MEGA_KEY_LENGTH, count($archive['key']));
        // the key read as a hunter would, from what the register says
        $names = [];
        foreach (SAFEGUARD_FINDS as $key => [$name, $what])
            $names[$name] = $what;
        $key = '';
        foreach ($archive['key'] as [$name, $word, $letter]) {
            assertTrue(isset($names[$name]), 'a find of the register: ' . $name);
            $words = preg_split('/\s+/u', trim($names[$name]));
            $key .= strtoupper(mb_str_split($words[$word - 1])[$letter - 1]);
        }
        assertTrue((bool)preg_match('/^[A-Z]+$/', $key), 'only A to Z: ' . $key);
        $record = megaDecipher($archive['record'], $key);
        assertSame(megaRecord('a'), $record);
        assertTrue($archive['record'] !== $record, 'it is ciphered');
        assertSame(['ok' => true], megastructureAnswer('a', ['record' => strtolower($record)], $noon));
        assertSame(['ok' => true], megastructureAnswer('a', ['record' => '  ' . str_replace(' ', '  ', $record) . ' '], $noon));
        assertSame(['ok' => false], megastructureAnswer('a', ['record' => $archive['record']], $noon));
        assertSame(['ok' => false], megastructureAnswer('a', ['record' => ''], $noon));
    });

    test('the archive\'s key comes only from the finds on the way to the gene', function () {
        $allowed = array_map(function ($key) { return SAFEGUARD_FINDS[$key][0]; }, MEGA_KEY_FINDS);
        foreach (['a', 'b', 'c', 'preview'] as $secret)
            foreach (megaKey($secret)['places'] as [$name])
                assertTrue(in_array($name, $allowed, true), $name);
        assertSame([], megastructureAnswer('', ['enter' => geneSequence('a')], megaAt(12, 0)));
        assertSame([], megastructureAnswer('', ['record' => megaRecord('a')], megaAt(12, 0)));
    });
