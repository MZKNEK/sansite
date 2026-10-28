<?php
    // The calls of the transfer page (t/t.js, inc/transfer.php), all POST, so
    // no id or key lands in a log: ?a=new (a logged-in account, with the CSRF
    // token), peek and join (the scanned id), send, poll and close (the id and
    // the side's key), and through the server put and get, a piece's bytes in
    // the body or the answer, with the id, the key and the piece's number in
    // the X-Transfer-Id, -Key and -Seq headers. The others answer JSON:
    // {ok: true, ...} or {ok: false, error, message}.
    require __DIR__ . '/../inc/transfer.php';
    installDataErrorHandler();
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');

    const TRANSFER_ERRORS = [
        'login' => [401, 'Zaloguj się, żeby zacząć przesyłanie.'],
        'csrf' => [403, 'Sesja wygasła, odśwież stronę.'],
        'full' => [429, 'Masz już otwarte ' . TRANSFER_PER_ACCOUNT . ' przesyłań. Zamknij któreś albo poczekaj, aż wygasną.'],
        'gone' => [410, 'To przesyłanie wygasło albo zostało zakończone.'],
        'taken' => [409, 'Do tego przesyłania dołączyło już inne urządzenie.'],
        'denied' => [403, 'Brak dostępu do tego przesyłania.'],
        'alone' => [409, 'Nikt jeszcze nie dołączył.'],
        'large' => [413, 'Za duża wiadomość.'],
        'busy' => [429, 'Druga strona jeszcze nie odebrała poprzednich kawałków.'],
        'failed' => [503, 'Nie udało się zapisać, spróbuj jeszcze raz.'],
        'bad' => [400, 'Nieznane polecenie.']
    ];

    function transferReply($result)
    {
        if (is_string($result)) {
            [$status, $message] = TRANSFER_ERRORS[$result] ?? TRANSFER_ERRORS['failed'];
            http_response_code($status);
            $result = ['ok' => false, 'error' => $result, 'message' => $message];
        } else {
            $result = ['ok' => true] + (is_array($result) ? $result : []);
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        transferReply('bad');

    $action = (string)($_GET['a'] ?? '');
    $id = (string)($_POST['id'] ?? $_SERVER['HTTP_X_TRANSFER_ID'] ?? '');
    $key = (string)($_POST['key'] ?? $_SERVER['HTTP_X_TRANSFER_KEY'] ?? '');
    $seq = filter_var($_SERVER['HTTP_X_TRANSFER_SEQ'] ?? '', FILTER_VALIDATE_INT);

    switch ($action) {
        case 'new':
            $user = siteUser();
            if (!$user)
                transferReply('login');
            if (!checkCsrf())
                transferReply('csrf');
            session_write_close();
            transferReply(createTransfer($user));
        case 'peek':
            transferReply(peekTransfer($id));
        case 'join':
            transferReply(joinTransfer($id, deviceName((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))));
        case 'send':
            transferReply(sendTransferMessage($id, $key, (string)($_POST['message'] ?? '')));
        case 'poll':
            transferReply(pollTransfer($id, $key, (int)($_POST['after'] ?? 0)));
        case 'close':
            transferReply(closeTransfer($id, $key));
        case 'put':
            transferReply(putTransferPiece($id, $key, $seq, (string)file_get_contents('php://input', false, null, 0, TRANSFER_RELAY_CHUNK + 1)));
        case 'get':
            $piece = takeTransferPiece($id, $key, $seq);
            if (is_string($piece))
                transferReply($piece);
            if ($piece === null) {
                http_response_code(204);
                exit;
            }
            header('Content-Type: application/octet-stream');
            header('Content-Length: ' . strlen($piece['data']));
            echo $piece['data'];
            exit;
    }
    transferReply('bad');
