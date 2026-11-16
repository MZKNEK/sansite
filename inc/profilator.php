<?php
    // Profilator's way to the bot (profilator/api.php): api/Profile with the site's key,
    // for the logged-in account, another's only for a site admin (profilatorOwner()). The
    // bot keeps the layout, the one-time unlocks and the pictures; the site only passes them
    // on, and keeps each account's three layouts of its own (profilatorSlots()).
    require_once __DIR__ . '/auth.php';

    const PROFILATOR_CATALOG_TTL = 600;
    const PROFILATOR_MAX_PICTURE = 10485760;
    const PROFILATOR_MAX_LAYOUT = 16384;
    const PROFILATOR_PICTURE_USES = ['header', 'style', 'overlay', 'ultra', 'frame'];
    // the player's own layouts kept by the site (inc/data/profilator-slots.json), not the bot
    const PROFILATOR_SLOTS = 3;
    const PROFILATOR_SLOT_NAME = 40;
    // what a site admin may ask for another player's profile (api.php ?as=)
    const PROFILATOR_AS_ACTIONS = ['me', 'picture', 'current', 'quote', 'preview', 'save'];

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

    // whose profile a request is for: [the account's own id, null] without ?as=, the asked
    // id for a site admin and an action of PROFILATOR_AS_ACTIONS, else [null, 'admin' or 'as']
    function profilatorOwner($id, $as, $action)
    {
        if ($as === null)
            return [(string)$id, null];
        if (!isPanelAdminId($id))
            return [null, 'admin'];
        if (!is_string($as) || profilatorPath($as) === null || !in_array($action, PROFILATOR_AS_ACTIONS, true))
            return [null, 'as'];

        return [$as, null];
    }

    // the bot's quote of a layout charges nothing: nothing missing, nothing to pay
    function profilatorFree($quote)
    {
        return is_array($quote) && empty($quote['missing']) && (int)($quote['sc'] ?? 0) === 0 && (int)($quote['tc'] ?? 0) === 0;
    }

    // the account's saved layouts, PROFILATOR_SLOTS places, each null or
    // ['name', 'time', 'layout' => the layout's JSON as the page sent it]
    function profilatorSlots($id)
    {
        $kept = readData('profilator-slots')[(string)$id] ?? [];
        $slots = [];
        for ($i = 0; $i < PROFILATOR_SLOTS; $i++) {
            $slot = $kept[$i] ?? null;
            $slots[] = is_array($slot) && is_string($slot['layout'] ?? null) ? $slot : null;
        }

        return $slots;
    }

    // the slots as the page gets them, each layout an object again ({} kept as {})
    function profilatorSlotsOut(array $slots)
    {
        return array_map(function ($slot) {
            return $slot === null ? null : ['name' => (string)$slot['name'], 'time' => (int)$slot['time'], 'layout' => json_decode($slot['layout'])];
        }, $slots);
    }

    // a slot's name: one line, no control characters, PROFILATOR_SLOT_NAME characters at most
    function profilatorSlotName($name, $index)
    {
        $name = trim(cutText(trim(preg_replace('/[\p{C}\s]+/u', ' ', validText(is_string($name) ? $name : '')) ?? ''), PROFILATOR_SLOT_NAME));

        return $name !== '' ? $name : 'Układ ' . ($index + 1);
    }

    // puts a layout's JSON (from profilatorLayoutJson) in a slot, or empties it with null;
    // the slots after the change, or null when the file could not be written
    function profilatorSetSlot($id, $index, $layout, $name, $now)
    {
        $id = (string)$id;
        $ok = updateData('profilator-slots', function (&$all) use ($id, $index, $layout, $name, $now) {
            $mine = $all[$id] ?? [];
            for ($i = 0; $i < PROFILATOR_SLOTS; $i++)
                $mine[$i] = is_array($mine[$i] ?? null) ? $mine[$i] : null;
            $mine[$index] = $layout === null ? null : ['name' => profilatorSlotName($name, $index), 'time' => $now, 'layout' => $layout];
            $mine = array_slice($mine, 0, PROFILATOR_SLOTS);
            if (array_filter($mine) === [])
                unset($all[$id]);
            else
                $all[$id] = $mine;
        }, $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $ok ? profilatorSlots($id) : null;
    }

    // an Idempotency-Key the page made for one save
    function profilatorKey($key)
    {
        $key = (string)$key;

        return preg_match('/^[\x21-\x7e]{8,64}$/', $key) ? $key : null;
    }
