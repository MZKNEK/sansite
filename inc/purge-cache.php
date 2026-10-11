<?php
    // Has Cloudflare forget the addresses given on standard input, one per line
    // (inc/cloudflare.php, cloudflarePurge()). Run by deploy.sh after a deploy
    // with the files it changed, as the web server's user:
    //   printf '%s\n' https://sanakan.pl/favicon.ico | runuser -u www-data -- php inc/purge-cache.php
    // Without CLOUDFLARE_ZONE_ID in inc/config.php it says so and exits 0, so a
    // deploy goes on; a failed purge exits 1. Only from the command line; inc/ is
    // also blocked from the web in nginx.
    if (PHP_SAPI !== 'cli') {
        http_response_code(404);
        exit;
    }

    require __DIR__ . '/cloudflare.php';

    $urls = array_values(array_filter(array_map('trim', file('php://stdin') ?: []), function ($line) {
        return preg_match('#^https://[^\s/]+/\S*$#', $line) === 1;
    }));
    if (!$urls) {
        echo "Nic do wyczyszczenia.\n";
        exit(0);
    }
    if (!cloudflarePurgeConfigured()) {
        echo "Cache Cloudflare nie wyczyszczony: brak CLOUDFLARE_API_TOKEN lub CLOUDFLARE_ZONE_ID w inc/config.php.\n";
        exit(0);
    }

    $error = cloudflarePurge($urls);
    if ($error !== null) {
        fwrite(STDERR, $error . "\n");
        exit(1);
    }
    echo 'Cache Cloudflare wyczyszczony: ', count($urls), ' ', count($urls) === 1 ? 'adres' : 'adresów', ".\n";
