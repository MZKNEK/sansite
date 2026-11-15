<?php
    // Profilator's way to the bot (profilator/api.php): api/Profile with the site's key,
    // always for the logged-in account, never another's. The bot keeps the layout, the
    // one-time unlocks and the pictures; the site only passes them on.
    require_once __DIR__ . '/auth.php';

    const PROFILATOR_CATALOG_TTL = 600;
    const PROFILATOR_MAX_PICTURE = 10485760;
    const PROFILATOR_MAX_LAYOUT = 16384;
    const PROFILATOR_PICTURE_USES = ['header', 'style', 'overlay', 'ultra', 'frame'];

    // one request to api/Profile/<path>; [body, status], [false, 0] without an answer
    function profilatorBot($method, $path, $body = null, array $headers = [], $timeout = 20)
    {
        $lines = ['Accept: application/json, image/webp, image/*', 'x-app-key: ' . botAppKey()];
        foreach ($headers as $name => $value)
            $lines[] = $name . ': ' . $value;
        $options = ['method' => $method, 'timeout' => $timeout, 'ignore_errors' => true, 'header' => implode("\r\n", $lines)];
        if ($body !== null)
            $options['content'] = $body;

        return httpRaw(botApiBase() . '/api/Profile/' . $path, $options);
    }

    // the path of the account's profile; only ids of Discord accounts
    function profilatorPath($id, $rest = '')
    {
        $id = (string)$id;
        if (!preg_match('/^\d{17,20}$/', $id))
            return null;

        return 'discord/' . $id . ($rest !== '' ? '/' . $rest : '');
    }

    // the prices, ready layouts and frames, kept a while as they change only with the bot
    function profilatorCatalog($now)
    {
        $kept = readData('profilator-catalog');
        if (is_array($kept['data'] ?? null) && $now - (int)($kept['time'] ?? 0) < PROFILATOR_CATALOG_TTL)
            return $kept['data'];

        [$json, $status] = profilatorBot('GET', 'catalog', null, [], 8);
        $data = $status === 200 ? json_decode((string)$json, true) : null;
        if (!is_array($data))
            return is_array($kept['data'] ?? null) ? $kept['data'] : null;

        updateData('profilator-catalog', function (&$all) use ($data, $now) {
            $all = ['time' => $now, 'data' => $data];
        });

        return $data;
    }

    // the type of a picture by its first bytes, null for anything else
    function profilatorImageType($bytes)
    {
        $bytes = (string)$bytes;
        if (strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) === 0)
            return 'image/png';
        if (strncmp($bytes, "\xff\xd8\xff", 3) === 0)
            return 'image/jpeg';
        if (strncmp($bytes, 'GIF87a', 6) === 0 || strncmp($bytes, 'GIF89a', 6) === 0)
            return 'image/gif';
        if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP')
            return 'image/webp';

        return null;
    }

    // a form with one file, as the bot's upload takes it: [body, content type]
    function profilatorMultipart(array $fields, $name, $fileName, $bytes, $type)
    {
        $boundary = '----profilator' . bin2hex(random_bytes(12));
        $body = '';
        foreach ($fields as $key => $value)
            $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)$fileName) ?: 'obrazek';
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"; filename=\"$safe\"\r\nContent-Type: $type\r\n\r\n" . $bytes . "\r\n--$boundary--\r\n";

        return [$body, 'multipart/form-data; boundary=' . $boundary];
    }

    // the body of a layout sent by the page: the JSON object as text, or null
    function profilatorLayoutJson($value)
    {
        if (!is_array($value) || (array_is_list($value) && $value !== []))
            return null;
        $json = json_encode($value === [] ? new stdClass() : $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json !== false && strlen($json) <= PROFILATOR_MAX_LAYOUT ? $json : null;
    }

    // an Idempotency-Key the page made for one save
    function profilatorKey($key)
    {
        $key = (string)$key;

        return preg_match('/^[\x21-\x7e]{8,64}$/', $key) ? $key : null;
    }
