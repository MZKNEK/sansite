#!/usr/bin/env bash
# Puts the committed site on the server over SSH. Only files tracked by git go
# out, so inc/config.php, inc/data/ and the pictures in i/ are never sent or
# replaced. Files deleted from the repository since the last deploy are deleted
# on the server too. Run it from Git Bash or any shell with ssh and tar:
#   ./deploy.sh sanakan                  site in /var/www/html
#   ./deploy.sh sanakan /var/www/other   another folder
# sanakan.pl goes through Cloudflare, which lets no SSH through, so the target
# is the server's own address, best as a host alias in ~/.ssh/config (see the
# README). With an SSH key there is no password prompt; without one ssh asks a
# few times, and a few wrong passwords in a row can get the address banned.
set -euo pipefail

target=${1:?"Użycie: ./deploy.sh użytkownik@serwer [folder strony, domyślnie /var/www/html]"}
root=${2:-/var/www/html}
cd "$(dirname "$0")"

# only HEAD is sent, so work that is not committed would silently stay behind
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "Są niezacommitowane zmiany. Najpierw je zacommituj, wysyłany jest tylko ostatni commit." >&2
    exit 1
fi

commit=$(git rev-parse HEAD)
marker="$root/inc/data/deployed-commit"
previous=$(ssh "$target" "cat '$marker' 2>/dev/null || true")

echo "Wysyłanie $(git log -1 --format='%h %s') do $target:$root"
# autocrlf off: the server gets the LF line endings that are in the repository;
# --no-overwrite-dir keeps the owners and rights of folders already there (i/ must
# stay writable by the web server)
git -c core.autocrlf=false archive --format=tar HEAD \
    | ssh "$target" "tar -x -C '$root' --no-same-owner --no-overwrite-dir"

# files deleted from the repository since the last deploy
if [ -n "$previous" ] && git cat-file -e "$previous^{commit}" 2>/dev/null; then
    deleted=$(git diff --name-only --no-renames --diff-filter=D "$previous" HEAD)
    if [ -n "$deleted" ]; then
        echo "Usuwanie z serwera plików usuniętych z repozytorium:"
        echo "$deleted" | sed 's/^/  /'
        printf '%s\n' "$deleted" | ssh "$target" "cd '$root' && xargs -d '\n' rm -f --"
    fi
elif [ -n "$previous" ]; then
    echo "Poprzednio wdrożonego commita $previous nie ma w tym repozytorium, usuniętych plików nie sprawdzam."
fi

ssh "$target" "mkdir -p '$root/inc/data' && echo '$commit' > '$marker' && chown www-data:www-data '$root/inc/data' '$marker'"
echo "Gotowe: $(git log -1 --format='%h')"
