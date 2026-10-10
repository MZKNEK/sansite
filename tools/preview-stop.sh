#!/usr/bin/env bash
# Stops the local preview of tools/preview.sh: its two servers and its cron.
# With --clean it also removes what the preview made: .preview/ (the made-up
# data, the logs), the pictures it put into the gallery (.preview/created.txt)
# and the gallery folders of its made-up accounts; tools/preview.sh then fills
# the data again from scratch.
#   tools/preview-stop.sh
#   tools/preview-stop.sh --clean
set -euo pipefail
cd "$(dirname "$0")/.."
dir=.preview

stopped=0
for name in site api cron; do
    pid_file="$dir/$name.pid"
    [ -f "$pid_file" ] || continue
    pid=$(cat "$pid_file")
    if kill -0 "$pid" 2>/dev/null; then
        kill "$pid" 2>/dev/null || true
        stopped=1
    fi
    rm -f "$pid_file"
done
[ "$stopped" -eq 1 ] && echo "Podgląd zatrzymany." || echo "Podgląd nie działał."

if [ "${1:-}" = "--clean" ]; then
    if [ -f "$dir/created.txt" ]; then
        while IFS= read -r path; do
            [ -n "$path" ] && rm -rf -- "$path"
        done < "$dir/created.txt"
    fi
    # the folders of the made-up accounts (tools/preview/data.php), by their IDs
    rm -rf -- i/users/10000000000000000[1-9]-* 2>/dev/null || true
    rm -rf -- "$dir"
    echo "Dane podglądu usunięte."
fi
