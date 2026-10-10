#!/usr/bin/env bash
# Runs the whole site locally, without the wiki, with made-up data, to look at
# and test it: php -S with tools/preview/router.php (what nginx does on the
# server, the security headers too), a made-up bot API next to it
# (tools/preview/bot-api.php), the site's own bot check every minute
# (tools/preview/cron.php), and the data in .preview/, filled the first time by
# tools/preview/seed.php. /__login logs in as one of a few made-up accounts
# (dev with the panel, admin, moderator, user, a guest without a role), never
# through Discord. The croppers are there as built in skalpel/ and uskalpel/
# (tools/build-apps.sh), and `npm run dev` in apps/ takes the site from here.
# Runs in Git Bash or any shell with PHP 8.1 or newer; stopped by
# tools/preview-stop.sh.
#   tools/preview.sh            http://127.0.0.1:8765/
#   tools/preview.sh 8080       another port (the bot API takes the next one)
#   tools/preview.sh --reset    the made-up data from scratch
set -euo pipefail
cd "$(dirname "$0")/.."

reset=0
port=8765
for arg in "$@"; do
    case "$arg" in
        --reset) reset=1 ;;
        *[!0-9]*|'') echo "Użycie: tools/preview.sh [port] [--reset]" >&2; exit 1 ;;
        *) port=$arg ;;
    esac
done
api_port=$((port + 1))
php=${PHP:-php}
dir=.preview

# a running process from a pid file
alive() {
    [ -f "$1" ] && kill -0 "$(cat "$1")" 2>/dev/null
}

# --reset: the running preview stops and its data goes, then it starts afresh
if [ "$reset" -eq 1 ] && [ -d "$dir" ]; then
    bash tools/preview-stop.sh --clean >/dev/null
fi

if alive "$dir/site.pid"; then
    echo "Podgląd już działa: http://127.0.0.1:$(cat "$dir/port")/ (zatrzymuje go tools/preview-stop.sh)."
    exit 0
fi
command -v "$php" >/dev/null || { echo "Nie ma php w PATH (albo wskaż go w PHP=...)." >&2; exit 1; }
# the site loads inc/config.php by itself, which would clash with the preview's settings
if [ -f inc/config.php ]; then
    echo "Jest inc/config.php: podgląd ma własne ustawienia (tools/preview/config.php). Zmień na chwilę jego nazwę." >&2
    exit 1
fi

# GD for the thumbnails and the change to WebP, when PHP does not load it itself
args=(-d display_errors=1 -d error_reporting=-1)
"$php" -m | grep -qix gd || args+=(-d extension=gd)

mkdir -p "$dir"
if [ ! -d "$dir/data" ]; then
    "$php" "${args[@]}" tools/preview/seed.php
fi

echo "$port" > "$dir/port"
SANAKAN_PREVIEW_API="http://127.0.0.1:$api_port" nohup "$php" "${args[@]}" -S "127.0.0.1:$api_port" tools/preview/bot-api.php > "$dir/api.log" 2>&1 &
echo $! > "$dir/api.pid"
SANAKAN_PREVIEW_API="http://127.0.0.1:$api_port" nohup "$php" "${args[@]}" -S "127.0.0.1:$port" tools/preview/router.php > "$dir/site.log" 2>&1 &
echo $! > "$dir/site.pid"
SANAKAN_PREVIEW_API="http://127.0.0.1:$api_port" nohup "$php" tools/preview/cron.php > "$dir/cron.log" 2>&1 &
echo $! > "$dir/cron.pid"

# up when the home page answers (a few seconds at most)
for _ in $(seq 1 30); do
    if "$php" -r 'exit(@file_get_contents($argv[1]) === false ? 1 : 0);' "http://127.0.0.1:$port/robots.txt" 2>/dev/null; then
        echo "Podgląd działa: http://127.0.0.1:$port/"
        echo "  logowanie:   http://127.0.0.1:$port/__login"
        echo "  croppery:    http://127.0.0.1:$port/skalpel/  http://127.0.0.1:$port/uskalpel/"
        echo "  dane i logi: $dir/ · zatrzymanie: tools/preview-stop.sh (z --clean usuwa też dane)"
        exit 0
    fi
    sleep 0.2
done
echo "Podgląd nie odpowiada, zobacz $dir/site.log." >&2
bash tools/preview-stop.sh >/dev/null
exit 1
