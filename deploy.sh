#!/usr/bin/env bash
# Puts the committed site on the server over SSH. Only files tracked by git go
# out, so inc/config.php, inc/data/ and the pictures in i/ are never sent or
# replaced. Files deleted from the repository since the last deploy are deleted
# on the server too. The nginx rules (server/nginx/ and server/wiki/nginx-og.conf),
# which git archive leaves out, are sent on their own when they changed since the
# last deploy, then nginx -t runs and nginx reloads; a configuration that does
# not pass is rolled back and the deploy stops. Run it from Git Bash or any shell
# with ssh and tar:
#   ./deploy.sh sanakan                  site in /var/www/html
#   ./deploy.sh sanakan /var/www/other   another folder
#   ./deploy.sh --dry-run sanakan        only show what would be sent, changed
#                                        and reloaded, writing nothing
# sanakan.pl goes through Cloudflare, which lets no SSH through, so the target
# is the server's own address, best as a host alias in ~/.ssh/config (see the
# README). With an SSH key there is no password prompt; without one ssh asks a
# few times, and a few wrong passwords in a row can get the address banned.
set -euo pipefail

dry_run=0
if [ "${1:-}" = "--dry-run" ]; then
    dry_run=1
    shift
fi

target=${1:?"Użycie: ./deploy.sh [--dry-run] użytkownik@serwer [folder strony, domyślnie /var/www/html]"}
root=${2:-/var/www/html}
cd "$(dirname "$0")"

# where a file of server/ goes on the server; the wiki's theme and assets are
# pasted by hand and have no place here, so they are not among the rules sent
nginx_dest() {
    case "$1" in
        server/nginx/sanakan-log.conf) echo "/etc/nginx/conf.d/$(basename "$1")" ;;
        server/nginx/*)                echo "/etc/nginx/snippets/$(basename "$1")" ;;
        server/wiki/nginx-og.conf)     echo "/etc/nginx/snippets/$(basename "$1")" ;;
        *)                             return 1 ;;
    esac
}

# only HEAD is sent, so work that is not committed would silently stay behind
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "Są niezacommitowane zmiany. Najpierw je zacommituj, wysyłany jest tylko ostatni commit." >&2
    exit 1
fi

commit=$(git rev-parse HEAD)
marker="$root/inc/data/deployed-commit"
if [ "$dry_run" -eq 1 ]; then
    echo "[dry-run] Odczyt znacznika z serwera (tylko czytanie), żeby pokazać zmiany."
fi
previous=$(ssh "$target" "cat '$marker' 2>/dev/null || true")

if [ "$dry_run" -eq 1 ]; then
    echo "[dry-run] Wysłałbym $(git log -1 --format='%h %s') do $target:$root"
else
    echo "Wysyłanie $(git log -1 --format='%h %s') do $target:$root"
    # autocrlf off: the server gets the LF line endings that are in the repository;
    # --no-overwrite-dir keeps the owners and rights of folders already there (i/ must
    # stay writable by the web server)
    git -c core.autocrlf=false archive --format=tar HEAD \
        | ssh "$target" "tar -x -C '$root' --no-same-owner --no-overwrite-dir"
fi

# files deleted from the repository since the last deploy
deleted=""
if [ -n "$previous" ] && git cat-file -e "$previous^{commit}" 2>/dev/null; then
    deleted=$(git diff --name-only --no-renames --diff-filter=D "$previous" HEAD)
    if [ -n "$deleted" ]; then
        echo "Usuwanie z serwera plików usuniętych z repozytorium:"
        echo "$deleted" | sed 's/^/  /'
        if [ "$dry_run" -eq 0 ]; then
            printf '%s\n' "$deleted" | ssh "$target" "cd '$root' && xargs -d '\n' rm -f --"
        fi
    fi
elif [ -n "$previous" ]; then
    echo "Poprzednio wdrożonego commita $previous nie ma w tym repozytorium, usuniętych plików nie sprawdzam."
fi

# The nginx rules are not part of the site's files (server/ is export-ignored),
# so they go on their own when they changed since the last deploy: to their
# place, then nginx -t; a configuration that does not pass is rolled back and
# the deploy stops, so a broken file never stays behind.
config_files=()
config_dests=()
if [ -n "$previous" ] && git cat-file -e "$previous^{commit}" 2>/dev/null; then
    changed_config=$(git diff --name-only --diff-filter=ACMR "$previous" HEAD -- server/nginx/ server/wiki/nginx-og.conf)
else
    # no marker yet (the first deploy): send them, it is harmless
    changed_config=$(git ls-files -- server/nginx/ server/wiki/nginx-og.conf)
fi

if [ -n "$changed_config" ]; then
    echo "Zmienione reguły nginx, wysyłam:"
    config_files=()
    config_dests=()
    while IFS= read -r file; do
        [ -n "$file" ] || continue
        if dest=$(nginx_dest "$file"); then
            config_files+=("$file")
            config_dests+=("$dest")
            echo "  $file -> $dest"
        else
            echo "  $file (pomijam, nie ma dla niego miejsca)"
        fi
    done <<< "$changed_config"

    if [ ${#config_files[@]} -gt 0 ] && [ "$dry_run" -eq 0 ]; then
        # each file goes next to its place under a temporary name, so the whole
        # configuration can be tested before anything is replaced
        i=0
        while [ $i -lt ${#config_files[@]} ]; do
            scp -q "${config_files[$i]}" "$target:${config_dests[$i]}.sanakan-new"
            i=$((i + 1))
        done

        ssh "$target" "bash -s -- $(printf '%q ' "${config_dests[@]}")" <<'REMOTE'
set -euo pipefail

# puts the old files back (or removes the new ones) after a configuration that
# does not pass
restore() {
    for dest in "$@"; do
        if [ -f "$dest.sanakan-bak" ]; then
            mv -f "$dest.sanakan-bak" "$dest"
        else
            rm -f "$dest"
        fi
        rm -f "$dest.sanakan-new"
    done
}

for dest in "$@"; do
    if [ -e "$dest" ]; then
        cp -a "$dest" "$dest.sanakan-bak"
    fi
    mv -f "$dest.sanakan-new" "$dest"
done

if ! nginx -t; then
    echo "nginx -t nie przeszło, przywracam poprzednią konfigurację." >&2
    restore "$@"
    exit 1
fi

if ! systemctl reload nginx; then
    echo "Nie udało się przeładować nginx." >&2
    exit 1
fi
for dest in "$@"; do
    rm -f "$dest.sanakan-bak"
done
echo "Reguły nginx wgrane i przeładowane."
REMOTE
    elif [ ${#config_files[@]} -gt 0 ]; then
        echo "[dry-run] Wysłałbym je, uruchomił nginx -t i przeładował nginx (przy błędzie wycofanie i stop)."
    fi
fi

# the marker, and for the panel the commit's date and subject
if [ "$dry_run" -eq 1 ]; then
    # how much would go: the files of the commit that git archive keeps (the
    # export-ignored ones are already out of it), the deletions and the rules
    site_count=$(git archive --format=tar HEAD | tar -tf - | wc -l | tr -d ' ')
    deleted_count=$(printf '%s\n' "$deleted" | sed '/^$/d' | wc -l | tr -d ' ')
    nginx_note=""
    [ ${#config_files[@]} -gt 0 ] && nginx_note=" (nginx -t i reload)"
    echo "[dry-run] Podsumowanie: plików strony $site_count, do usunięcia $deleted_count, reguł nginx ${#config_files[@]}$nginx_note. Znacznik $(git log -1 --format='%h') nie został zapisany, nic nie wysłano."
else
    git log -1 --format='%H%n%cI%n%s' \
        | ssh "$target" "mkdir -p '$root/inc/data' && echo '$commit' > '$marker' && cat > '$root/inc/data/deployed-info' && chown www-data:www-data '$root/inc/data' '$marker' '$root/inc/data/deployed-info'"
    echo "Gotowe: $(git log -1 --format='%h')"
fi
