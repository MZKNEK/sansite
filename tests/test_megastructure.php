<?php
    // inc/megastructure.php: the way to the Net Terminal Gene, all of it from
    // a secret, given out by megastructure.php each where it belongs.

    function megaAt($hour, $minute)
    {
        return new DateTimeImmutable(sprintf('2026-10-12 %02d:%02d:30', $hour, $minute), new DateTimeZone('Europe/Warsaw'));
    }

    // an answer with its proof replaced by whether it is the server's, of the
    // find $key at $time (every proof has a nonce of its own, megaProof())
    function proven($answer, $key, $time, $secret = 'a')
    {
        if (isset($answer['proof']))
            $answer['proof'] = megaProofTime($secret, $key, $answer['proof']) === $time;

        return $answer;
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
        assertSame(['fragment' => geneFragment('a', 1), 'corridor' => megaRoute('a')[0], 'proof' => true], proven(megastructureAnswer('a', ['door' => '1'], megaAt(3, 2)), 'builders', megaAt(3, 2)->getTimestamp()));
    });

    test('the corridor leads from one address to the next, down to floor zero', function () {
        $route = megaRoute('a');
        $noon = megaAt(12, 0);
        for ($i = 0; $i < MEGA_ROUTE_STEPS; $i++)
            assertSame(['door' => false, 'draught' => $route[$i + 1]], megastructureAnswer('a', ['at' => $route[$i]], $noon));
        assertSame(['door' => false, 'draught' => $route[1]], megastructureAnswer('a', ['at' => strtoupper($route[0]) . '/'], $noon), 'as the 404 page may show it');
        $zero = megastructureAnswer('a', ['at' => end($route)], $noon);
        assertSame(geneFragment('a', 2), $zero['zero']['fragment']);
        $maze = megaMaze('a');
        assertSame(['width' => 2 * MEGA_MAZE_W + 1, 'height' => 2 * MEGA_MAZE_H + 1, 'start' => $maze['start'], 'sight' => megaSight('a', $maze['start'])], $zero['zero']['maze'], 'only what is seen from the way in');
        assertSame($noon->getTimestamp(), megaProofTime('a', 'floor0', $zero['zero']['proof']));
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
        $maze = megaMaze('a');
        $walk = megaSolve($maze);
        $zero = megastructureAnswer('a', ['at' => megaRoute('a')[MEGA_ROUTE_STEPS]], $noon)['zero']['proof'];
        $go = function ($moves) use ($noon, $zero) {
            return megastructureAnswer('a', ['walk' => $moves, 'zero' => $zero], $noon);
        };
        $there = ['sight' => megaSight('a', $maze['end']), 'word' => megaConsoleWord('a')];
        assertSame($there, $go(strtolower($walk)));
        assertSame($there, $go('RL' . $walk), 'steps back are fine');
        $short = $go(substr($walk, 0, -1));
        assertTrue(!isset($short['word']) && isset($short['sight']['end']), 'short of the terminal: it is seen, no word');
        assertSame(['reached' => false], $go('L' . $walk), 'out of the maze');
        assertSame(['reached' => false], $go('R' . str_repeat('U', 2 * MEGA_MAZE_H) . $walk), 'through a wall');
        assertSame(['reached' => false], $go(str_repeat('RL', MEGA_WALK_MAX) . $walk), 'too long');
        assertSame(['sight' => megaSight('a', $maze['start'])], $go(''), 'no step: the way in');
        assertSame(['reached' => false], $go('X'));
        assertSame([], megastructureAnswer('a', ['walk' => ['R'], 'zero' => $zero], $noon), 'only text counts');
        // only from floor zero
        assertSame(['denied' => true], megastructureAnswer('a', ['walk' => $walk], $noon), 'without its proof');
        assertSame(['denied' => true], megastructureAnswer('a', ['walk' => $walk, 'zero' => megaProof('a', 'builders', $noon->getTimestamp())], $noon), 'the proof of another find');
        assertSame(['denied' => true], megastructureAnswer('a', ['walk' => $walk, 'zero' => megaProof('b', 'floor0', $noon->getTimestamp())], $noon), 'of another secret');
        assertSame(['denied' => true], megastructureAnswer('a', ['walk' => $walk, 'zero' => $zero], $noon->modify('-1 minute')), 'from the future');
        assertSame(false, megaWalk('a', ''));
        assertTrue(megaWalk('b', megaSolve(megaMaze('b'))), 'every secret its own maze, each walkable');
    });

    test('the console word on paper gives the fourth fragment', function () {
        $noon = megaAt(12, 0);
        assertSame(['fragment' => geneFragment('a', 4), 'proof' => true], proven(megastructureAnswer('a', ['paper' => strtoupper(megaConsoleWord('a'))], $noon), 'paper', $noon->getTimestamp()));
        assertSame(['known' => false], megastructureAnswer('a', ['paper' => 'toha'], $noon));
        assertSame([], megastructureAnswer('', ['paper' => megaConsoleWord('a')], $noon));
    });

    test('the console word gives the third fragment', function () {
        $noon = megaAt(12, 0);
        assertSame(['known' => false], megastructureAnswer('a', ['ask' => 'toha'], $noon));
        assertSame(['fragment' => geneFragment('a', 3), 'proof' => true], proven(megastructureAnswer('a', ['ask' => strtoupper(megaConsoleWord('a'))], $noon), 'maze', $noon->getTimestamp()));
    });

    test('the terminal takes the gene however it is written', function () {
        $noon = megaAt(12, 0);
        $gene = geneSequence('a');
        $right = ['ok' => true, 'proof' => true];
        assertSame($right, proven(megastructureAnswer('a', ['gene' => $gene], $noon), 'gene', $noon->getTimestamp()));
        assertSame($right, proven(megastructureAnswer('a', ['gene' => strtolower(str_replace('-', ' ', $gene))], $noon), 'gene', $noon->getTimestamp()));
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
        $right = ['ok' => true, 'proof' => true];
        assertSame($right, proven(megastructureAnswer('a', ['record' => strtolower($record)], $noon), 'admin', $noon->getTimestamp()));
        assertSame($right, proven(megastructureAnswer('a', ['record' => '  ' . str_replace(' ', '  ', $record) . ' '], $noon), 'admin', $noon->getTimestamp()));
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

    test('a find of the way to the gene counts only with the proof the server gave', function () {
        $noon = megaAt(12, 0);
        $time = $noon->getTimestamp();
        $proof = megastructureAnswer('a', ['gene' => geneSequence('a')], $noon)['proof'];
        assertSame($time, megaProofTime('a', 'gene', $proof));
        assertSame(null, megaProofTime('a', 'admin', $proof), 'a proof is of one find');
        assertSame(null, megaProofTime('b', 'gene', $proof), 'and of one secret');
        assertSame(null, megaProofTime('', 'gene', $proof), 'without a secret nothing is proven');
        assertSame(null, megaProofTime('a', 'gene', ($time - 3600) . substr($proof, strlen((string)$time))), 'its time cannot be moved');
        foreach ([null, '', 'x', 123, ['a'], $time . '.' . str_repeat('0', 32), $time . '.' . str_repeat('0', 16) . '.' . str_repeat('0', 32)] as $wrong)
            assertSame(null, megaProofTime('a', 'gene', $wrong));
        // every proof its own, so two visitors in the same second never share one
        $again = megastructureAnswer('a', ['gene' => geneSequence('a')], $noon)['proof'];
        assertTrue($again !== $proof);
        assertSame($time, megaProofTime('a', 'gene', $again));
        $nonce = explode('.', $proof)[1];
        assertSame(null, megaProofTime('a', 'gene', $time . '.' . strrev($nonce) . '.' . explode('.', $proof)[2]), 'nor its nonce changed');
        // the proofs given before the nonces still count
        assertSame($time, megaProofTime('a', 'gene', megaProof('a', 'gene', $time, '')));
        assertSame(1, preg_match('/^\d+\.[0-9a-f]{32}$/', megaProof('a', 'gene', $time, '')));
    });

    test('account.php takes a find only with the proof the server gave, at its time', function () {
        $then = megaAt(3, 2)->getTimestamp();
        $now = $then + 60;
        // what the console trick sends: finds without a proof
        $found = ['builders' => 1, 'floor0' => 1, 'gene' => 1, 'gbe' => 5];
        assertSame([[], ['builders', 'floor0', 'gene', 'gbe']], megaProvenFinds('a', $found, null, $now));
        assertSame([[], ['builders', 'floor0', 'gene', 'gbe']], megaProvenFinds('a', $found, ['builders' => megaProof('b', 'builders', $then), 'gene' => megaProof('a', 'floor0', $then)], $now));
        assertSame([['builders' => $then, 'gbe' => $then - 5], ['floor0', 'gene']],
            megaProvenFinds('a', $found, ['builders' => megaProof('a', 'builders', $then), 'gbe' => megaProof('a', 'gbe', $then - 5)], $now), 'the time of the proof, not the one of the browser');
        assertSame([[], ['gbe']], megaProvenFinds('a', ['gbe' => 1], ['gbe' => megaProof('a', 'gbe', $now + 60)], $now), 'not from the future');
        assertSame([[], ['gbe']], megaProvenFinds('a', ['gbe' => 1], ['gbe' => megaProof('a', 'gbe', $now - (SAFEGUARD_FIND_DAYS + 1) * 86400)], $now), 'nor too old');
        assertSame([[], []], megaProvenFinds('a', 'not an array', [], $now));
        assertSame([[], ['gene']], megaProvenFinds('', ['gene' => 1], ['gene' => megaProof('', 'gene', $then)], $now), 'no secret, no gene');
    });

    test('a proof counts for the first account that sends it', function () {
        clearData();
        $now = 1760000000;
        $proofs = ['gbe' => megaProof('a', 'gbe', $now - 10), 'lost' => megaProof('a', 'lost', $now - 5)];
        $finds = ['gbe' => $now - 10, 'lost' => $now - 5];
        assertSame([$finds, []], megaClaimProofs('111', $finds, $proofs, $now));
        assertSame([$finds, []], megaClaimProofs('111', $finds, $proofs, $now), 'its own again');
        assertSame([[], ['gbe', 'lost']], megaClaimProofs('222', $finds, $proofs, $now), 'passed on, worth nothing');
        $later = $now + (SAFEGUARD_FIND_DAYS + 1) * 86400;
        megaClaimProofs('333', [], [], $later);
        assertSame([], readData('proofs'), 'forgotten once too old to count anyway');
    });

    test('two accounts with the same find in the same second both get it', function () {
        clearData();
        $now = time();
        $finds = ['console' => $now];
        $first = megaClaimProofs('111', $finds, ['console' => megaProof('a', 'console', $now)], $now);
        $second = megaClaimProofs('222', $finds, ['console' => megaProof('a', 'console', $now)], $now);
        assertSame([$finds, []], $first);
        assertSame([$finds, []], $second);
    });

    test('a find the account has already is not claimed again', function () {
        clearData();
        $now = time();
        addSafeguardFinds('111', ['console' => $now - 60], $now);
        for ($i = 0; $i < 5; $i++)
            assertSame([[], []], megaClaimProofs('111', ['console' => $now], ['console' => megaProof('a', 'console', $now)], $now));
        assertSame([], readData('proofs'), 'the file does not grow');
        $earlier = ['console' => $now - 120];
        assertSame([$earlier, []], megaClaimProofs('111', $earlier, ['console' => megaProof('a', 'console', $now - 120)], $now), 'an earlier one still counts');
    });

    test('a step of the way to the gene counts only after those before it', function () {
        $t = 1760000000;
        $way = ['builders' => $t, 'floor0' => $t + 60, 'maze' => $t + 600, 'paper' => $t + 700, 'gene' => $t + 800, 'admin' => $t + 2000];
        assertSame([$way, []], megaChainFinds($way, []), 'all of it in its order');
        assertSame([['gene' => $t + 800], []], megaChainFinds(['gene' => $t + 800], array_diff_key($way, ['gene' => 1, 'admin' => 1])), 'the steps before it the account has');
        assertSame([['gbe' => $t], ['floor0', 'maze', 'paper', 'gene', 'admin']], megaChainFinds(['gbe' => $t] + array_diff_key($way, ['builders' => 1]), []), 'without the door, nothing after it');
        assertSame([['builders' => $t, 'floor0' => $t + 60, 'maze' => $t + 600], ['gene']], megaChainFinds(['builders' => $t, 'floor0' => $t + 60, 'maze' => $t + 600, 'gene' => $t + 800], []), 'the gene without the paper');
        assertSame([['builders' => $t + 100], ['floor0']], megaChainFinds(['builders' => $t + 100, 'floor0' => $t + 60], []), 'nor before the step before it');
        assertSame([['floor0' => $t + 60], []], megaChainFinds(['floor0' => $t + 60], ['builders' => $t, 'gbe' => 1]), 'the account had the step before');
        assertSame([['floor0' => $t + 60, 'builders' => $t + 100], []], megaChainFinds(['floor0' => $t + 60, 'builders' => $t + 100], ['builders' => $t]), 'an earlier one the account had counts');
        assertSame([[], ['admin']], megaChainFinds(['admin' => $t], []), 'the record known from someone else');
    });

    test('a ticket is no find, though signed the same way', function () {
        $now = time();
        $ticket = megaProof('a', 'ticket:idle', $now);
        assertSame([[], ['ticket:idle']], megaProvenFinds('a', ['ticket:idle' => $now], ['ticket:idle' => $ticket], $now));
    });

    test('the finds made in the browser get a proof only when the server can believe them', function () {
        $noon = megaAt(12, 0);
        $time = $noon->getTimestamp();
        $witness = function ($query, $at = null, $preview = false) use ($noon) {
            return megastructureAnswer('a', $query, $at ?? $noon, $preview);
        };
        foreach (MEGA_PLAIN_FINDS as $key)
            assertSame(['proof' => true], proven($witness(['witness' => $key]), $key, $time), $key);
        assertSame(['denied' => true], $witness(['witness' => 'made-up']));
        assertSame(['denied' => true], $witness(['witness' => 'gene']), 'the way to the gene has its own answers');

        // the seasonal ones on their day only, give or take the time zones
        assertSame(['denied' => true], $witness(['witness' => 'halloween']));
        $halloween = new DateTimeImmutable('2026-10-31 12:00', new DateTimeZone('Europe/Warsaw'));
        assertTrue(isset($witness(['witness' => 'halloween'], $halloween)['proof']));
        assertTrue(isset($witness(['witness' => 'halloween'], $halloween->modify('+20 hours'))['proof']), 'still 31 October in America');
        assertSame(['denied' => true], $witness(['witness' => 'halloween'], $halloween->modify('+2 days')));
        assertTrue(isset($witness(['witness' => 'xmas'], null, true)['proof']), 'the preview pretends any day');

        // the header's password, from the secret
        assertTrue((bool)preg_match('/^toha-[0-9a-f]{4}$/', megaHeaderWord('a')));
        assertTrue(megaHeaderWord('a') !== megaHeaderWord('b'));
        assertSame(['denied' => true], $witness(['witness' => 'header', 'word' => 'toha']));
        assertSame(['proof' => true], proven($witness(['witness' => 'header', 'word' => strtoupper(megaHeaderWord('a'))]), 'header', $time));
    });

    test('the idle page and the Emitter need the time they take since their ticket', function () {
        $noon = megaAt(12, 0);
        $ticket = function ($kind, $at) { return megastructureAnswer('a', ['ticket' => $kind], $at)['ticket'] ?? null; };
        $witness = function ($key, $ticket, $at, $preview = false) {
            return isset(megastructureAnswer('a', ['witness' => $key, 'ticket' => $ticket], $at, $preview)['proof']);
        };
        $idle = $ticket('idle', $noon);
        assertSame(false, $witness('idle', $idle, $noon->modify('+60 seconds')), 'too soon');
        assertSame(false, $witness('idle', $idle, $noon->modify('+2 days')), 'too old');
        assertSame(false, $witness('idle', $idle, $noon->modify('-10 seconds')), 'from the future');
        assertSame(true, $witness('idle', $idle, $noon->modify('+3 minutes')));
        assertSame(true, $witness('idle', $ticket('idle', $noon), $noon->modify('+5 seconds'), true), 'the preview waits less');
        $gbe = $ticket('gbe', $noon);
        assertSame(false, $witness('gbe', $gbe, $noon));
        assertSame(true, $witness('gbe', $gbe, $noon->modify('+3 seconds')));
        assertSame(false, $witness('regen', $gbe, $noon->modify('+3 seconds')), 'three shots take longer');
        assertSame(true, $witness('regen', $gbe, $noon->modify('+13 seconds')));
        assertSame(false, $witness('gbe', $idle, $noon->modify('+3 minutes')), 'a ticket of another kind');
        assertSame(false, $witness('idle', null, $noon->modify('+3 minutes')));
        assertSame(['none' => true], megastructureAnswer('a', ['ticket' => 'gene'], $noon));

        // the wait at a closed door (the 403 page) and the feeding in the failure (the 500 page)
        $door = $ticket('denied', $noon);
        assertSame(false, $witness('denied', $door, $noon->modify('+60 seconds')), 'too soon');
        assertSame(true, $witness('denied', $door, $noon->modify('+3 minutes')));
        assertSame(false, $witness('denied', $ticket('idle', $noon), $noon->modify('+3 minutes')), 'a ticket of another kind');
        assertSame(false, $witness('snake', $ticket('snake', $noon), $noon->modify('+30 seconds')), 'the snake only by a game played again');
    });

    test('a ticket gives its find once, so passed on it gives nobody another', function () {
        $noon = megaAt(12, 0);
        $idle = megastructureAnswer('a', ['ticket' => 'idle'], $noon)['ticket'];
        $witness = function ($key, $at) use ($idle) {
            return megastructureAnswer('a', ['witness' => $key, 'ticket' => $idle], $at);
        };
        assertTrue(isset($witness('idle', $noon->modify('+3 minutes'))['proof']));
        assertSame(['denied' => true], $witness('idle', $noon->modify('+4 minutes')), 'the same ticket again');
        $gbe = megastructureAnswer('a', ['ticket' => 'gbe'], $noon)['ticket'];
        assertTrue(isset(megastructureAnswer('a', ['witness' => 'gbe', 'ticket' => $gbe], $noon->modify('+3 seconds'))['proof']));
        assertTrue(isset(megastructureAnswer('a', ['witness' => 'regen', 'ticket' => $gbe], $noon->modify('+13 seconds'))['proof']), 'the first shot of a row is the regen\'s too');
        assertSame(['denied' => true], megastructureAnswer('a', ['witness' => 'regen', 'ticket' => $gbe], $noon->modify('+14 seconds')));

        // forgotten once too old to count anyway
        megaSpendTicket('idle', 'x', $noon->getTimestamp() + 2 * SNAKE_LIFE, $noon->getTimestamp() + 2 * SNAKE_LIFE);
        assertSame(['idle:x'], array_keys(readData('tickets')));
        // only the account that used it may use it again
        assertSame(true, megaSpendTicket('score', 'y', 1, 2, '111'));
        assertSame(true, megaSpendTicket('score', 'y', 1, 2, '111'));
        assertSame(false, megaSpendTicket('score', 'y', 1, 2, '222'));
        assertSame(false, megaSpendTicket('idle', 'x', 1, 2), 'nor without an account');
    });

    // a game of the snake played by a program, tick by tick the way nearest
    // the food that does not end it, until fed so many times: "R12D3…"
    function snakePlay($ticket, $fed)
    {
        $seed = snakeSeed($ticket);
        $steps = ['U' => [0, -1], 'D' => [0, 1], 'L' => [-1, 0], 'R' => [1, 0]];
        $back = ['U' => 'D', 'D' => 'U', 'L' => 'R', 'R' => 'L'];
        $encode = function ($runs) {
            return implode('', array_map(function ($run) { return $run[0] . $run[1]; }, $runs));
        };
        $head = [6, intdiv(SNAKE_ROWS, 2)];
        $last = 'R';
        $runs = [];
        // the food before the first tick, placed as the game places it
        $rng = $seed;
        $food = snakePlace(['6,' . $head[1] => true, '5,' . $head[1] => true, '4,' . $head[1] => true], $rng);
        for ($tick = 0; $tick < 3000; $tick++) {
            $choices = [];
            foreach ($steps as $dir => [$dx, $dy])
                if ($dir !== $back[$last])
                    $choices[$dir] = abs($head[0] + $dx - $food[0]) + abs($head[1] + $dy - $food[1]);
            asort($choices);
            $taken = null;
            foreach (array_keys($choices) as $dir) {
                $try = $runs;
                if ($try && $try[count($try) - 1][0] === $dir)
                    $try[count($try) - 1][1]++;
                else
                    $try[] = [$dir, 1];
                $game = snakeReplay($seed, $encode($try));
                if ($game !== null && !$game['over']) {
                    $taken = $dir;
                    $runs = $try;
                    break;
                }
            }
            if ($taken === null)
                return null;
            $last = $taken;
            $head = [$head[0] + $steps[$taken][0], $head[1] + $steps[$taken][1]];
            $food = $game['food'];
            if ($game['eaten'] >= $fed)
                return $encode($runs);
        }

        return null;
    }

    test('the snake is played again by the server, which gives the find only for a game it believes', function () {
        $noon = megaAt(12, 0);
        $time = $noon->getTimestamp();
        // the same xorshift as js/500.js (worked out there for these seeds)
        foreach ([[1, [270369, 67634689, 2647435461]], [0xdeadbeef, [1199382711, 2384302402, 3129746520]], [0xffffffff, [253983, 4228382207, 1958451267]]] as [$seed, $numbers]) {
            $x = $seed;
            assertSame($numbers, [snakeNext($x), snakeNext($x), snakeNext($x)]);
        }
        $js = file_get_contents(dirname(__DIR__) . '/js/500.js');
        assertSame(1, preg_match('/x \^= x << 13;\s+x \^= x >>> 17;\s+x \^= x << 5;\s+x >>>= 0;/', $js), 'the page runs the same');

        $ticket = megastructureAnswer('a', ['ticket' => 'snake'], $noon)['ticket'];
        $way = snakePlay($ticket, SNAKE_FED);
        assertTrue($way !== null, 'the program fed it');
        $game = snakeReplay(snakeSeed($ticket), $way);
        assertSame(SNAKE_FED, $game['eaten']);
        $play = function ($way, $after, $id = '', $ticketOf = null) use ($noon, $ticket) {
            return megastructureAnswer('a', ['snake' => $way, 'ticket' => $ticketOf ?? $ticket], $noon->modify(($after < 0 ? '' : '+') . $after . ' seconds'), false, $id);
        };
        $took = (int)ceil($game['ms'] / 1000);
        // sooner than its ticks: a game made up and sent at once
        assertSame(['denied' => true], $play($way, 1));
        assertSame(['denied' => true], $play($way, (int)floor($game['ms'] * 0.9 / 1000) - 1), 'a tenth faster than its ticks');
        assertSame(['eaten' => SNAKE_FED, 'proof' => true, 'best' => SNAKE_FED], proven($play($way, $took, '111'), 'snake', $time + $took));
        assertSame(['eaten' => SNAKE_FED, 'best' => SNAKE_FED], $play($way, $took + 5, '111'), 'its find once');
        assertSame(['eaten' => SNAKE_FED, 'best' => 0], $play($way, $took + 5, '222'), 'passed on, it scores for nobody else');
        assertSame(SNAKE_FED, snakeBest('111'));
        assertSame(['eaten' => SNAKE_FED], $play($way, $took + 5), 'without an account no best');

        // a smaller game of another ticket does not lower the best
        $other = megastructureAnswer('a', ['ticket' => 'snake'], $noon)['ticket'];
        $small = snakePlay($other, 3);
        assertSame(3, megastructureAnswer('a', ['snake' => $small, 'ticket' => $other], $noon->modify('+1 hour'), false, '111')['eaten']);
        assertSame(SNAKE_FED, snakeBest('111'));
        assertSame(SNAKE_FED, megastructureAnswer('a', ['ticket' => 'snake'], $noon, false, '111')['best'], 'a game\'s ticket says the account\'s best');
        assertTrue(!isset(megastructureAnswer('a', ['ticket' => 'snake'], $noon)['best']), 'without an account none');

        // what the page could not have sent
        foreach (['', 'R0', 'X5', 'R', 'R3L1', 'R14U1', '15', 'R1 ', str_repeat('R1D1', 3000)] as $wrong)
            assertSame(['denied' => true], $play($wrong, 3600), $wrong);
        assertSame(['none' => true], megastructureAnswer('a', ['snake' => ['R3'], 'ticket' => $ticket], $noon), 'only text counts');
        assertTrue(snakeReplay(snakeSeed($ticket), 'R14')['over'], 'into the wall');
        assertSame(['denied' => true], $play($way, 3600, '', megastructureAnswer('a', ['ticket' => 'idle'], $noon)['ticket']), 'a ticket of another kind');
        assertSame(['denied' => true], $play($way, 3600, '', megaProof('b', 'ticket:snake', $time)), 'of another secret');
        assertSame(['denied' => true], $play($way, SNAKE_LIFE + 1), 'too old');
        assertSame(['denied' => true], $play($way, -5), 'from the future');
        assertSame(null, snakeSeed($time . '.' . str_repeat('0', 32)), 'a ticket without a nonce places nothing');
    });

    test('Killy walks in one slot in MEGA_KILLY_CHANCE, the same for everyone', function () {
        $start = megaAt(12, 0)->getTimestamp();
        $walks = 0;
        $slots = 500;
        $first = null;
        for ($i = 0; $i < $slots; $i++) {
            $at = (new DateTimeImmutable('@' . ($start + $i * MEGA_KILLY_SLOT)));
            $answer = megastructureAnswer('a', ['ticket' => 'killy'], $at);
            assertSame(megaKillyWalks('a', $at->getTimestamp()), isset($answer['ticket']));
            if (isset($answer['ticket'])) {
                $walks++;
                $first = $first ?? [$answer['ticket'], $at];
            }
        }
        assertTrue($walks > $slots / MEGA_KILLY_CHANCE / 2 && $walks < $slots / MEGA_KILLY_CHANCE * 2, 'about one in ' . MEGA_KILLY_CHANCE . ': ' . $walks);
        assertTrue(isset(megastructureAnswer('a', ['witness' => 'killy', 'ticket' => $first[0]], $first[1]->modify('+10 seconds'))['proof']));
        assertSame(['denied' => true], megastructureAnswer('a', ['witness' => 'killy', 'ticket' => $first[0]], $first[1]->modify('+1 hour')), 'a ticket of long ago');
        assertTrue(isset(megastructureAnswer('a', ['ticket' => 'killy'], megaAt(12, 0), true)['ticket']), 'the preview sends him always');
    });

    test('what is seen in the maze is only what is near, the terminal only once close', function () {
        $maze = megaMaze('a');
        $sight = megaSight('a', $maze['start']);
        assertTrue(count($sight['rows']) <= 2 * MEGA_SIGHT + 1 && strlen($sight['rows'][0]) <= 2 * MEGA_SIGHT + 1);
        assertSame(0, $sight['x'], 'cut at the left edge');
        foreach ($sight['rows'] as $dy => $row)
            assertSame(substr($maze['rows'][$sight['y'] + $dy], 0, strlen($row)), $row);
        assertTrue(!isset($sight['end']), 'the terminal is far from the way in');
        assertSame($maze['end'], megaSight('a', $maze['end'])['end']);
        assertSame(null, megaWalkTo('a', 'L'), 'out of the way in');
        assertSame($maze['start'], megaWalkTo('a', ''));
    });

    test('js/safeguard.js witnesses through megastructure.php the finds it makes itself', function () {
        $js = '';
        foreach (['safeguard', 'home', '404', 'netsphere'] as $name)
            $js .= file_get_contents(dirname(__DIR__) . '/js/' . $name . '.js');
        assertSame(0, preg_match_all("/found\('[a-z0-9]+'\)/", $js), 'no find noted without a proof');
    });
