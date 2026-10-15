<?php
    test('gene fragments follow only the requesting account recorded finds', function () {
        $now = megaAt(12, 0);
        $query = ['fragments' => '1'];
        writeData('finds', ['111' => ['builders' => 100, 'maze' => 200], '222' => ['floor0' => 150, 'paper' => 250]]);
        $read = function ($id) use ($query, $now) {
            return (array)megastructureAnswer('a', $query, $now, false, $id)['fragments'];
        };
        assertSame([1 => geneFragment('a', 1), 3 => geneFragment('a', 3)], $read('111'));
        assertSame([2 => geneFragment('a', 2), 4 => geneFragment('a', 4)], $read('222'));
        assertSame([], $read('333'), 'a new account has no fragments');
        assertSame(['stranger' => true], megastructureAnswer('a', $query, $now));
        assertSame([], megastructureAnswer('', $query, $now, false, '111'));
        assertTrue(removeSafeguardFinds('111', ['maze']));
        assertSame([1 => geneFragment('a', 1)], $read('111'), 'revoked finds no longer reveal their fragments');
        assertSame([], readData('proofs'), 'reading fragments never awards a find');
    });

    test('fresh proofs of every account step cannot be donated before their first claim', function () {
        $start = megaAt(3, 2);
        $flights = megaFlights('a', '111');
        $queries = [
            'builders' => ['door' => '1'],
            'floor0' => ['at' => megaFloorPath($flights[MEGA_FLIGHTS - 1]['shaft'])],
            'maze' => ['ask' => megaConsoleWord('a')],
            'paper' => ['paper' => megaConsoleWord('a')],
            'gene' => ['gene' => geneSequence('a')],
            'admin' => ['record' => megaRecord('a', '111')]
        ];
        $proofs = [];
        $expected = [];
        foreach (array_keys($queries) as $i => $key) {
            $now = $start->modify('+' . (31 * $i) . ' seconds');
            $answer = megastructureAnswer('a', $queries[$key], $now, false, '111');
            $proofs[$key] = $key === 'floor0' ? $answer['zero']['proof'] : $answer['proof'];
            $expected[$key] = $now->getTimestamp();
        }
        $time = $start->getTimestamp() + 200;
        assertSame([$expected, []], megaProvenFinds('a', $expected, $proofs, $time, '111'));
        assertSame([[], array_keys($expected)], megaProvenFinds('a', $expected, $proofs, $time, '222'));
        assertSame([], readData('proofs'), 'rejection does not need an earlier claim');
        assertSame([$expected, []], megaChainFinds($expected, []), 'the valid steps keep their order');
    });

    test('binding new proofs leaves already recorded discoveries untouched', function () {
        $finds = ['builders' => 100, 'floor0' => 150, 'maze' => 200, 'paper' => 250, 'gene' => 300, 'admin' => 350];
        writeData('finds', ['111' => $finds]);
        assertSame($finds, safeguardFinds('111'));
        $proof = megaProof('a', 'lost', 400);
        assertSame([['lost' => 400], []], megaProvenFinds('a', ['lost' => 1], ['lost' => $proof], 500, '111'), 'anonymous finds can still be claimed after login');
    });

    test('recovering a lost ticket reply preserves its time and only one account claim', function () {
        $now = megaAt(12, 0);
        $ticket = megastructureAnswer('a', ['ticket' => 'idle'], $now)['ticket'];
        $query = ['witness' => 'idle', 'ticket' => $ticket];
        $first = megastructureAnswer('a', $query, $now->modify('+180 seconds'));
        $retry = megastructureAnswer('a', $query, $now->modify('+240 seconds'));
        assertSame($first, $retry);
        $at = $now->getTimestamp() + 180;
        $proofs = ['idle' => $retry['proof']];
        assertSame([['idle' => $at], []], megaProvenFinds('a', ['idle' => 1], $proofs, $at + 60));
        assertSame([['idle' => $at], []], megaClaimProofs('111', ['idle' => $at], $proofs, $at + 60));
        assertSame([[], ['idle']], megaClaimProofs('222', ['idle' => $at], $proofs, $at + 60));
    });

    test('a spent legacy ticket without a saved proof cannot mint another one', function () {
        $now = megaAt(12, 0);
        $ticket = megastructureAnswer('a', ['ticket' => 'idle'], $now)['ticket'];
        megaSpendTicket('idle', $ticket, $now->getTimestamp(), $now->getTimestamp() + 180);
        assertSame(['denied' => true], megastructureAnswer('a', ['witness' => 'idle', 'ticket' => $ticket], $now->modify('+240 seconds')));
    });

    test('ticket storage has a hard limit while existing proofs can still be recovered', function () {
        $now = 1800000000;
        $entries = ['idle:retry' => [$now, '', 'already-issued']];
        for ($i = 1; $i < MEGA_TICKETS_MAX; $i++)
            $entries['score:' . $i] = [$now, '111'];
        writeData('tickets', $entries);
        assertSame('already-issued', megaTicketProof('a', 'idle', 'retry', $now, $now));
        assertThrows(function () use ($now) { megaTicketProof('a', 'idle', 'new', $now, $now); });
        assertThrows(function () use ($now) { megaSpendTicket('score', 'new', $now, $now, '111'); });
        assertSame(MEGA_TICKETS_MAX, count(readData('tickets')));
        assertSame(true, megaSpendTicket('score', '1', $now, $now, '111'), 'existing games can still finish');
    });

    test('ticket entries expire by their own lifetime without reopening valid replays', function () {
        $now = 1800000000;
        writeData('tickets', ['gbe:old' => [$now - 121, ''], 'regen:live' => [$now - 121, ''], 'snake:live' => [$now - 1000, '', 'proof']]);
        megaSpendTicket('score', 'new', $now, $now, '111');
        assertSame(['regen:live', 'snake:live', 'score:new'], array_keys(readData('tickets')));
    });

    test('ticket issuance is limited per address and resets after its window', function () {
        $now = 1800000000;
        for ($i = 0; $i < MEGA_TICKET_RATE; $i++)
            assertTrue(megaTicketAllowed('192.0.2.1', $now));
        assertFalse(megaTicketAllowed('192.0.2.1', $now));
        assertTrue(megaTicketAllowed('192.0.2.2', $now));
        assertTrue(megaTicketAllowed('192.0.2.1', $now + MEGA_TICKET_RATE_SECONDS));
        for ($i = 0; $i < MEGA_TICKET_RATE; $i++)
            assertTrue(megaTicketAllowed('2001:db8:1:2::1', $now));
        assertFalse(megaTicketAllowed('2001:db8:1:2::2', $now), 'rotating IPv6 addresses in one /64 does not reset the limit');
    });

    test('the ticket rate store is bounded and recovers after old addresses expire', function () {
        $now = 1800000000;
        $entries = [];
        for ($i = 0; $i < MEGA_TICKET_RATE_KEYS; $i++)
            $entries['old-' . $i] = [$now, 1];
        writeData('ticket-rate', $entries);
        assertFalse(megaTicketAllowed('192.0.2.1', $now));
        assertSame(MEGA_TICKET_RATE_KEYS, count(readData('ticket-rate')));
        assertTrue(megaTicketAllowed('192.0.2.1', $now + MEGA_TICKET_RATE_SECONDS));
        assertSame(1, count(readData('ticket-rate')));
    });

    test('a failed ticket write never returns an untracked proof', function () {
        file_put_contents(dataFile('tickets'), '{broken');
        assertThrows(function () { megaTicketProof('a', 'idle', 'ticket', 100, 280); });
        assertSame('{broken', file_get_contents(dataFile('tickets')));
        unlink(dataFile('tickets'));
        $proof = megaTicketProof('a', 'idle', 'ticket', 100, 280);
        assertSame(280, megaProofTime('a', 'idle', $proof));
        assertSame($proof, megaTicketProof('a', 'idle', 'ticket', 100, 300));
    });
