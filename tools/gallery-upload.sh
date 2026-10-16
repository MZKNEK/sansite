#!/usr/bin/env bash
# Sends a folder of the local gallery (i/) to the same path in the gallery on
# the server, its subfolders too. Only the files the server does not have yet
# go (also not one it has as the WebP or WebM it was changed to), so it can be
# run again after adding more. They go over SSH into a temporary folder in
# inc/data/, and inc/gallery-import.php adds them as www-data the way an upload
# does: the same checks, the metadata stripped, missing folders made, a line in
# the history (by "skrypt"). Run it from Git Bash or any shell with ssh and tar:
#   tools/gallery-upload.sh Anime/2026              i/Anime/2026 to sanakan
#   tools/gallery-upload.sh i/Anime/2026            the same
#   tools/gallery-upload.sh --dry-run Anime         only list what would be sent
#   tools/gallery-upload.sh --webp Anime            PNG, JPG and GIF changed to
#                                                   WebP and MP4 to WebM in the
#                                                   background (always so in the
#                                                   folder of an account)
#   tools/gallery-upload.sh Anime sanakan-new /var/www/html
#                                                   another SSH target and site
# The server needs a release with inc/gallery-import.php (deploy.sh).
set -euo pipefail

dry_run=0
webp=()
while [ $# -gt 0 ]; do
    case "$1" in
        --dry-run) dry_run=1 ;;
        --webp)    webp=(--webp) ;;
        --*)       echo "Nieznana opcja: $1" >&2; exit 2 ;;
        *)         break ;;
    esac
    shift
done

usage="Użycie: tools/gallery-upload.sh [--dry-run] [--webp] <folder w i/> [serwer, domyślnie sanakan] [folder strony, domyślnie /var/www/html]"
[ $# -ge 1 ] || { echo "$usage" >&2; exit 2; }
path=$1
target=${2:-sanakan}
root=${3:-/var/www/html}
cd "$(dirname "$0")/.."

# the path as in i/: forward slashes, without i/ in front and the slashes at the ends
path=${path//\\//}
while [[ $path == ./* || $path == /* ]]; do path=${path#./}; path=${path#/}; done
[[ $path == i || $path == i/* ]] && path=${path#i}
path=${path#/}
path=${path%/}
case "/$path/" in
    */../*|*/./*) echo "Ścieżka nie może mieć . ani .. : $1" >&2; exit 2 ;;
esac
src="i${path:+/$path}"
[ -d "$src" ] || { echo "Nie ma folderu $src" >&2; exit 1; }

# a word for the server's shell, whatever is in it
q() { printf "'%s'" "$(printf '%s' "$1" | sed "s/'/'\\\\''/g")"; }

work=$(mktemp -d)
staging=
cleanup() {
    rm -rf -- "$work"
    [ -z "$staging" ] || ssh "$target" "rm -rf -- $(q "$staging")" || true
}
trap cleanup EXIT

# the files of the folder, without hidden ones and the gallery's own index.php
(cd "$src" && find . -mindepth 1 -name '.*' -prune -o -type f ! -path ./index.php -print0) > "$work/all"
total=$(tr -cd '\0' < "$work/all" | wc -c)
[ "$total" -gt 0 ] || { echo "W $src nie ma plików."; exit 0; }

ssh "$target" "cd $(q "$root") && runuser -u www-data -- php inc/gallery-import.php --missing $(q "$path")" \
    < "$work/all" > "$work/missing"
count=$(tr -cd '\0' < "$work/missing" | wc -c)
echo "$src: plików $total, na serwerze już $((total - count)), do wysłania $count."
[ "$count" -gt 0 ] || exit 0

if [ "$dry_run" -eq 1 ]; then
    tr '\0' '\n' < "$work/missing" | sed 's|^\./||; s|^|  |'
    exit 0
fi

size=$(cd "$src" && du -cb --files0-from="$work/missing" | tail -n 1 | cut -f 1)
echo "Wysyłam $(numfmt --to=iec "$size" 2>/dev/null || echo "$size B") do $target..."
staging=$(ssh "$target" "runuser -u www-data -- mktemp -d $(q "$root/inc/data/upload-XXXXXXXX")")
(cd "$src" && tar --null -T "$work/missing" -cf -) \
    | ssh "$target" "runuser -u www-data -- tar -xf - -C $(q "$staging")"
ssh "$target" "cd $(q "$root") && runuser -u www-data -- php inc/gallery-import.php ${webp[*]-} $(q "$staging") $(q "$path")"
