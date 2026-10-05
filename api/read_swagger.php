<?php
    // OpenAPI spec of the bot API for Swagger UI, for the accounts that may read
    // the documentation (see api/index.php). The spec is asked for at most every
    // 10 minutes, and the last good copy is served while the API is down.
    require __DIR__ . '/../inc/bot.php';
    require_once __DIR__ . '/../inc/auth.php';

    if (!apiCanView()) {
        http_response_code(!authConfigured() ? 503 : (siteUser() ? 403 : 401));
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Dokumentacja API wymaga logowania przez Discorda.']);
        exit;
    }

    const SPEC_URL = 'https://api.sanakan.pl/swagger/v2/swagger.json';
    const SPEC_TTL = 600;

    $file = botFile('swagger.json');

    if (!is_file($file) || time() - filemtime($file) >= SPEC_TTL) {
        $opts = [
            "http" => [
                "method" => "GET",
                "timeout" => 5
            ]
        ];
        $context = stream_context_create($opts);
        $json = @file_get_contents(SPEC_URL, false, $context);
        $data = $json === false ? null : @json_decode($json, true);

        if (is_array($data) && isset($data['paths'])) {
            $data['host'] = 'http://api.sanakan.pl/';
            botWriteFile($file, json_encode($data, JSON_PRETTY_PRINT));
        }
        else if (is_file($file)) {
            // the API is down: keep the old copy and try again in 10 minutes
            @touch($file);
        }

        // PHP caches file times, and the file was just written or touched
        clearstatcache();
    }

    header('Content-Type: application/json');

    $spec = is_file($file) ? @file_get_contents($file) : false;
    if ($spec === false) {
        http_response_code(503);
        echo json_encode(['error' => 'API bota teraz nie odpowiada']);
        exit;
    }

    // only for this visitor: other ones may not be let in
    header('Cache-Control: private, max-age=' . max(0, SPEC_TTL - (time() - filemtime($file))));
    echo $spec;
