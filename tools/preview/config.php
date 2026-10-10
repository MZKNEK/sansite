<?php
    // The settings of the local preview (tools/preview.sh) in place of
    // inc/config.php: made-up Discord keys (the login goes to /__login of
    // tools/preview/router.php, never to Discord), the made-up accounts of
    // data.php, the data in .preview/data, the bot API of bot-api.php. The
    // router loads it for the pages, cron.php for inc/check-bot.php.
    $previewData = require __DIR__ . '/data.php';

    define('SITE_DATA_DIR', str_replace('\\', '/', dirname(__DIR__, 2)) . '/.preview/data');
    define('DISCORD_CLIENT_ID', 'preview');
    define('DISCORD_CLIENT_SECRET', 'preview');
    define('DISCORD_REDIRECT_URI', '/__login');
    define('PANEL_ADMINS', [$previewData['admin']]);
    define('GALLERY_ADMINS', [$previewData['admin']]);
    define('GALLERY_VIEWERS', []);
    define('GALLERY_UPLOADERS', []);
    define('GALLERY_PRIVATE', []);
    define('API_VIEWERS', []);
    define('BOT_APP_KEY', 'preview');
    define('BOT_API_BASE', getenv('SANAKAN_PREVIEW_API') ?: 'http://127.0.0.1:8766');

    // The other Sanakan sites the status checks answer as up without asking
    // them (the preview checks itself as sanakan.pl, and the croppers are on it
    // now); everything else, the made-up bot API among it, is asked for real.
    $GLOBALS['SANAKAN_HTTP'] = function ($url, array $options = [], $maxLen = 0) {
        $host = parse_url($url, PHP_URL_HOST);
        if (in_array($host, ['sanakan.pl', 'wiki.sanakan.pl', 'waifu.sanakan.pl', 'alter.sanakan.pl'], true))
            return ['<!doctype html><title>Sanakan</title>', 200];

        $fp = @fopen($url, 'r', false, stream_context_create(['http' => $options]));
        if ($fp === false)
            return [false, 0];
        $body = stream_get_contents($fp, $maxLen > 0 ? $maxLen : -1);
        $headers = stream_get_meta_data($fp)['wrapper_data'] ?? [];
        fclose($fp);
        $status = 0;
        foreach (is_array($headers) ? $headers : [] as $line)
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $match))
                $status = (int)$match[1];

        return [$body, $status];
    };
