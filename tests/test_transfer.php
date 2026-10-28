<?php
    // inc/transfer.php: the transfers of t/, opened by an account, joined once
    // by a key from the QR code, the messages between their sides and the
    // pieces through the server.
    require_once __DIR__ . '/../inc/transfer.php';

    if (!defined('PANEL_ADMINS')) define('PANEL_ADMINS', ['999']);
    if (!defined('TRANSFER_RELAY_DIR')) define('TRANSFER_RELAY_DIR', SITE_DATA_DIR . '/relay');

    function openTransfer($id = '1', $now = 1000)
    {
        $opened = createTransfer(['id' => $id, 'name' => 'Owner'], $now);
        assertTrue(is_array($opened), 'the transfer opens');

        return $opened;
    }

    test('a transfer is joined once, by its id, before it runs out', function () {
        $host = openTransfer();
        assertTrue(validTransferToken($host['id']) && validTransferToken($host['key']));
        assertSame(TRANSFER_JOIN_TIME, $host['ttl']);
        assertSame(false, $host['relay'], 'only an owner of the site may go through the server');

        assertSame(['name' => 'Owner'], peekTransfer($host['id'], 1001));
        assertSame('gone', peekTransfer('not-a-token', 1001));
        assertSame('gone', joinTransfer(transferToken(), 'Chrome 130', 1001));

        $guest = joinTransfer($host['id'], 'Chrome 130 · Android', 1002);
        assertSame('Owner', $guest['name']);
        assertTrue($guest['key'] !== $host['key']);
        assertSame('taken', peekTransfer($host['id'], 1003));
        assertSame('taken', joinTransfer($host['id'], 'Firefox', 1003), 'a second device is refused');

        $late = openTransfer('1', 2000);
        assertSame('gone', joinTransfer($late['id'], 'Chrome', 2000 + TRANSFER_JOIN_TIME + 1), 'one not joined in time is gone');
    });

    test('the sides leave each other messages, read ones are dropped', function () {
        $host = openTransfer();
        assertSame('alone', sendTransferMessage($host['id'], $host['key'], '{"t":"offer"}', 1001));
        $guest = joinTransfer($host['id'], 'Safari 18 · iPhone', 1002);

        $seen = pollTransfer($host['id'], $host['key'], 0, 1003);
        assertSame(['joined' => 'Safari 18 · iPhone', 'messages' => []], $seen);

        assertSame(true, sendTransferMessage($host['id'], $host['key'], 'offer', 1004));
        assertSame(true, sendTransferMessage($host['id'], $guest['key'], 'answer', 1005));
        assertSame('denied', sendTransferMessage($host['id'], transferToken(), 'x', 1005));
        assertSame('denied', pollTransfer($host['id'], 'wrong', 0, 1005));
        assertSame('large', sendTransferMessage($host['id'], $host['key'], str_repeat('x', TRANSFER_MESSAGE_SIZE + 1), 1005));

        $forGuest = pollTransfer($host['id'], $guest['key'], 0, 1006)['messages'];
        assertSame([[1, 'offer']], $forGuest);
        assertSame([[2, 'answer']], pollTransfer($host['id'], $host['key'], 0, 1006)['messages']);
        assertSame([], pollTransfer($host['id'], $guest['key'], 1, 1007)['messages']);
        assertSame([], readData('transfers')[$host['id']]['box']['guest'], 'what was read is gone from the file');

        for ($i = 0; $i < TRANSFER_MESSAGES; $i++)
            sendTransferMessage($host['id'], $host['key'], 'm' . $i, 1008);
        assertSame('full', sendTransferMessage($host['id'], $host['key'], 'more', 1008));
    });

    test('a joined transfer lasts while it is used, and closing ends it for both', function () {
        $host = openTransfer();
        $guest = joinTransfer($host['id'], 'Chrome', 1001);
        // polled now and then, it stays past the time to join
        pollTransfer($host['id'], $guest['key'], 0, 1001 + TRANSFER_IDLE_TIME - 10);
        assertTrue(is_array(pollTransfer($host['id'], $host['key'], 0, 1001 + TRANSFER_IDLE_TIME + 20)));
        assertSame('gone', pollTransfer($host['id'], $host['key'], 0, 1001 + 2 * TRANSFER_IDLE_TIME + 30), 'left alone, it runs out');

        $host = openTransfer('1', 5000);
        $guest = joinTransfer($host['id'], 'Chrome', 5001);
        assertSame('denied', closeTransfer($host['id'], 'wrong', 5002));
        assertSame(true, closeTransfer($host['id'], $guest['key'], 5002));
        assertSame('gone', pollTransfer($host['id'], $host['key'], 0, 5003));
    });

    test('an account opens a few transfers at a time', function () {
        for ($i = 0; $i < TRANSFER_PER_ACCOUNT; $i++)
            openTransfer('7', 1000);
        assertSame('full', createTransfer(['id' => '7', 'name' => 'Seven'], 1000));
        assertTrue(is_array(createTransfer(['id' => '8', 'name' => 'Eight'], 1000)), 'another account is not held back');
        assertTrue(is_array(createTransfer(['id' => '7', 'name' => 'Seven'], 1001 + TRANSFER_JOIN_TIME)), 'the old ones ran out');
    });

    test('pieces go through the server only for an owner, in order, a few waiting at most', function () {
        $plain = openTransfer('1');
        $plainGuest = joinTransfer($plain['id'], 'Chrome', 1001);
        assertSame('denied', putTransferPiece($plain['id'], $plain['key'], 0, 'x', 1002));
        assertSame('denied', takeTransferPiece($plain['id'], $plainGuest['key'], 0, 1002));

        $host = openTransfer('999');
        assertSame(true, $host['relay']);
        assertSame('alone', putTransferPiece($host['id'], $host['key'], 0, 'x', 1001));
        $guest = joinTransfer($host['id'], 'Chrome', 1001);
        assertSame(true, $guest['relay']);

        assertSame(null, takeTransferPiece($host['id'], $guest['key'], 0, 1002), 'nothing came yet');
        assertSame(true, putTransferPiece($host['id'], $host['key'], 0, "\1abc", 1002));
        assertSame(true, putTransferPiece($host['id'], $host['key'], 1, "\0{}", 1002));
        assertSame(null, takeTransferPiece($host['id'], $host['key'], 0, 1002), 'a side does not get its own pieces');
        assertSame(['data' => "\1abc"], takeTransferPiece($host['id'], $guest['key'], 0, 1003));
        assertSame(['data' => "\1abc"], takeTransferPiece($host['id'], $guest['key'], 0, 1003), 'asked again after a lost answer');
        assertSame(['data' => "\0{}"], takeTransferPiece($host['id'], $guest['key'], 1, 1003));
        assertSame(false, is_file(transferRelayDir($host['id']) . '/guest-0'), 'the piece before the one asked for is gone');

        assertSame('large', putTransferPiece($host['id'], $guest['key'], 0, str_repeat('x', TRANSFER_RELAY_CHUNK + 1), 1004));
        for ($i = 0; $i < TRANSFER_RELAY_WAITING; $i++)
            assertSame(true, putTransferPiece($host['id'], $guest['key'], $i, 'p' . $i, 1004));
        assertSame('busy', putTransferPiece($host['id'], $guest['key'], TRANSFER_RELAY_WAITING, 'more', 1004));
        assertSame(true, putTransferPiece($host['id'], $guest['key'], 0, 'p0', 1004), 'a piece sent again replaces itself');
        assertSame('denied', putTransferPiece($host['id'], 'wrong', 0, 'x', 1004));

        assertSame(true, closeTransfer($host['id'], $host['key'], 1005));
        assertSame(false, is_dir(transferRelayDir($host['id'])), 'closing takes the pieces away');
        assertSame('gone', takeTransferPiece($host['id'], $guest['key'], 0, 1005));
    });

    test('the pieces of a transfer that ran out go with it', function () {
        $host = openTransfer('999');
        joinTransfer($host['id'], 'Chrome', 1001);
        putTransferPiece($host['id'], $host['key'], 0, 'x', 1002);
        assertTrue(is_dir(transferRelayDir($host['id'])));

        openTransfer('2', 1002 + TRANSFER_IDLE_TIME + 1);
        assertSame(false, isset(readData('transfers')[$host['id']]));
        assertSame(false, is_dir(transferRelayDir($host['id'])));
    });
