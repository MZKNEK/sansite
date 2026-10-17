#!/usr/bin/env bash
# Puts the committed site on the server over SSH. Only files tracked by git go
# out, so inc/config.php, inc/data/ and the pictures in i/ are never sent or
# replaced. Files deleted from the repository since the last deploy are deleted
# on the server too. The nginx rules (server/nginx/ and the wiki's nginx-og.conf
# and nginx-site.conf in server/wiki/), which git archive leaves out, are sent on
# their own when they changed since the last deploy, then nginx -t runs and nginx
# reloads; a configuration that does not pass is rolled back and the deploy stops.
# The wiki's icons (server/wiki/assets/) go the same way when they changed, with
# Wiki.js restarted. The skins' songs (audio/*.webm, audio/*.mp3), kept out of
# git, go when the server has none or another one of the same name, and so do
# the media of 0x40 and WAP (hues/.pack/, wap/.mu/), also out of git, with the
# list of 0x40's pack made again first (php hues/respack.php); one
# removed here stays on the server. Cloudflare is told to forget the pictures,
# icons and songs that changed (inc/purge-cache.php). Run it from Git Bash or
# any shell with ssh, tar and md5sum (and php, with hues/.pack/ there):
#   ./deploy.sh sanakan                  site in /var/www/html
#   ./deploy.sh sanakan /var/www/other   another folder
#   ./deploy.sh --dry-run sanakan        only show what would be sent, changed
#                                        and reloaded, writing nothing
#   ./deploy.sh --offline sanakan        the same with the site's writers stopped:
#                                        the cron jobs taken out, their runs waited
#                                        for, PHP-FPM stopped, a backup of inc/data
#                                        and i/, then after the files the
#                                        inventory, the data migration (asked
#                                        first; --yes does not ask) and a
#                                        maintenance run, and everything resumed
#                                        and the checks of --check
#   ./deploy.sh --resume sanakan         start PHP-FPM and put the cron jobs back
#                                        after an --offline deploy that stopped
#   ./deploy.sh --check sanakan          only report what the server should have
#                                        for the gallery (rights, cron, journal,
#                                        upload limits, tools, inc/ closed)
# sanakan.pl goes through Cloudflare, which lets no SSH through, so the target
# is the server's own address, best as a host alias in ~/.ssh/config (see
# docs/server.md). With an SSH key there is no password prompt; without one
# ssh asks a few times, and a few wrong passwords in a row can get the address
# banned.
set -euo pipefail

dry_run=0
offline=0
resume=0
check=0
yes=0
while [ $# -gt 0 ]; do
    case "$1" in
        --dry-run) dry_run=1 ;;
        --offline) offline=1 ;;
        --resume)  resume=1 ;;
        --check)   check=1 ;;
        --yes)     yes=1 ;;
        --*)       echo "Nieznana opcja: $1" >&2; exit 2 ;;
        *)         break ;;
    esac
    shift
done

target=${1:?"Użycie: ./deploy.sh [--dry-run] [--offline [--yes] | --resume | --check] użytkownik@serwer [folder strony, domyślnie /var/www/html]"}
root=${2:-/var/www/html}
cd "$(dirname "$0")"

# The writers an --offline deploy stops: the cron jobs of docs/server.md (taken out
# of /etc/cron.d into $paused, which cron does not read) and PHP-FPM. $paused
# also keeps the name of the PHP-FPM service and the backup's path, so --resume
# knows what to bring back.
paused=/root/sanakan-paused
cron_jobs="sanakan-status sanakan-site sanakan-maintenance"

# starts PHP-FPM and puts the cron jobs back, whatever of them was stopped
resume_remote() {
    ssh "$target" "bash -s -- '$paused'" <<'REMOTE'
set -euo pipefail
paused=$1
if [ ! -d "$paused" ]; then
    echo "Nic nie było wstrzymane."
    exit 0
fi
if [ -f "$paused/fpm" ]; then
    systemctl start "$(cat "$paused/fpm")"
    echo "Uruchomiono $(cat "$paused/fpm")."
    rm -f "$paused/fpm"
fi
for job in "$paused"/sanakan-*; do
    [ -e "$job" ] || continue
    mv "$job" /etc/cron.d/
    echo "Cron $(basename "$job") włączony."
done
if [ -f "$paused/backup" ]; then
    echo "Kopia sprzed wdrożenia: $(cat "$paused/backup")"
    rm -f "$paused/backup"
fi
rmdir "$paused"
REMOTE
}

# What the gallery needs from the server, only reported,
# never changed: the data and the gallery on one filesystem, the folders the
# site writes owned and writable by www-data, the cleanup's cron line,
# systemd-cat and a persistent journal, the upload limits of nginx and
# PHP-FPM, PHP-FPM's gd and zip, the conversion tools, the backup readable,
# then from here over HTTPS that inc/ and the accounts' folders stay closed.
# The checks in the browser (limits, uploads, moves, conversions, ZIP) and
# the CDN's old thumbnail rule remain by hand.
site_url=${SITE_URL:-https://sanakan.pl}
checks() {
    echo "Kontrole serwera:"
    ssh "$target" "bash -s -- '$root' '$paused'" <<'REMOTE'
set -uo pipefail
root=$1
paused=$2
ok() { echo "  OK     $*"; }
warn() { echo "  UWAGA  $*"; }

if [ "$(stat -c %d "$root/inc/data")" = "$(stat -c %d "$root/i")" ]; then
    ok "inc/data i i/ na jednym systemie plików"
else
    warn "inc/data i i/ są na różnych systemach plików (przenoszenie do kosza nie będzie atomowe)"
fi

for dir in inc/data inc/data/locks inc/data/operations inc/data/conversion-staging inc/data/webp-preview inc/data/trash i; do
    path="$root/$dir"
    if [ ! -e "$path" ]; then
        ok "$dir jeszcze nie istnieje (www-data go utworzy)"
    elif [ "$(stat -c %U "$path")" != www-data ] || ! runuser -u www-data -- test -w "$path"; then
        warn "$dir: właściciel $(stat -c %U:%G "$path"), prawa $(stat -c %a "$path"); potrzebny zapis dla www-data"
    else
        ok "$dir należy do www-data"
    fi
done

if grep -qs 'run-maintenance.php' /etc/cron.d/sanakan-maintenance "$paused/sanakan-maintenance"; then
    ok "cron maintenance jest"
else
    warn "brak /etc/cron.d/sanakan-maintenance"
fi
if [ -x /usr/bin/systemd-cat ]; then ok "systemd-cat jest"; else warn "brak /usr/bin/systemd-cat"; fi
if [ -d /var/log/journal ] || grep -Eqs '^Storage=persistent' /etc/systemd/journald.conf /etc/systemd/journald.conf.d/*.conf; then
    ok "journal trwały (journalctl -t sanakan-maintenance)"
else
    warn "journal nie jest trwały, błędy maintenance znikną po restarcie (mkdir /var/log/journal)"
fi

body=$(nginx -T 2>/dev/null | grep -E '^\s*client_max_body_size' | awk '{print $2}' | tr -d ';' | sort -u | tr '\n' ' ')
echo "  INFO   nginx client_max_body_size: ${body:-domyślne 1m}"

fpm=$(systemctl list-units --type=service --plain --no-legend 'php*-fpm.service' | awk 'NR == 1 {print $1}')
[ -n "$fpm" ] || fpm=$(cat "$paused/fpm" 2>/dev/null || true)
version=$(echo "$fpm" | sed -nE 's/^php([0-9.]+)-fpm\.service$/\1/p')
if [ -n "$version" ] && command -v "php-fpm$version" > /dev/null; then
    ini=$("php-fpm$version" -i 2>/dev/null)
    for key in upload_max_filesize post_max_size memory_limit max_execution_time; do
        echo "  INFO   PHP-FPM $key: $(echo "$ini" | awk -F' => ' -v k="$key" '$1 == k {print $2; exit}')"
    done
    pool=$(grep -hE '^\s*(pm\.max_children|php_(admin_)?value\[(upload_max_filesize|post_max_size|memory_limit|max_execution_time)\])' /etc/php/"$version"/fpm/pool.d/*.conf 2>/dev/null | tr -s ' ' | tr '\n' ';')
    echo "  INFO   pula PHP-FPM: ${pool:-bez nadpisań}"
    modules=$("php-fpm$version" -m 2>/dev/null)
    for module in gd zip; do
        if echo "$modules" | grep -qx "$module"; then ok "PHP-FPM ma $module"; else warn "PHP-FPM bez $module"; fi
    done
else
    warn "nie znaleziono PHP-FPM, limitów nie odczytano"
fi

for tool in cwebp gif2webp webpmux ffmpeg; do
    if command -v "$tool" > /dev/null; then ok "$tool jest"; else warn "brak $tool"; fi
done
if command -v magick > /dev/null || command -v convert > /dev/null; then ok "ImageMagick jest"; else warn "brak ImageMagick (HEIC tylko przez ffmpeg)"; fi

backup=$(ls -t /root/sansite-backup-*.tar 2>/dev/null | head -n 1)
if [ -z "$backup" ]; then
    echo "  INFO   brak kopii /root/sansite-backup-*.tar"
elif tar -tf "$backup" > /dev/null 2>&1; then
    ok "kopia $backup czyta się w całości ($(du -h "$backup" | cut -f1))"
else
    warn "kopia $backup jest uszkodzona"
fi
REMOTE

    echo "Kontrole HTTP ($site_url):"
    local path code
    for path in /inc/config.php /inc/data/access.json /inc/data/trash.json /inc/data/operations/ \
        /inc/data/conversion-staging/ /inc/migrate-gallery.php /inc/run-maintenance.php /i/users/; do
        code=$(curl -s -o /dev/null -w '%{http_code}' "$site_url$path" || echo 000)
        case "$code" in
            200|30?) echo "  UWAGA  $path odpowiada $code, powinien być zamknięty" ;;
            000)     echo "  UWAGA  $path bez odpowiedzi" ;;
            *)       echo "  OK     $path zamknięty ($code)" ;;
        esac
    done
    for path in / /i/; do
        code=$(curl -s -o /dev/null -w '%{http_code}' "$site_url$path" || echo 000)
        if [ "$code" = 200 ]; then echo "  OK     $path działa"; else echo "  UWAGA  $path odpowiada $code"; fi
    done
    echo "Ręcznie zostaje: sprawdzenie w przeglądarce (limity, upload, przenoszenie, konwersje, ZIP), próba odtworzenia kopii i journalctl -t sanakan-maintenance po 10 minutach."
}

if [ "$resume" -eq 1 ]; then
    resume_remote
    exit 0
fi
if [ "$check" -eq 1 ]; then
    checks
    exit 0
fi

# where a file of server/ goes on the server (migrate.sh has the same list);
# the wiki's theme is pasted by hand and its assets go on their own (below), so
# they are not among the rules sent
nginx_dest() {
    case "$1" in
        server/nginx/sanakan-log.conf|server/nginx/sanakan-realip.conf)
                                       echo "/etc/nginx/conf.d/$(basename "$1")" ;;
        server/nginx/site.conf)        echo /etc/nginx/sites-available/default ;;
        server/nginx/*.conf)           echo "/etc/nginx/snippets/$(basename "$1")" ;;
        server/wiki/nginx-og.conf)     echo /etc/nginx/snippets/wiki-og.conf ;;
        server/wiki/nginx-site.conf)   echo /etc/nginx/sites-available/wiki ;;
        *)                             return 1 ;;
    esac
}

# only HEAD is sent, so work that is not committed would silently stay behind
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    echo "Są niezacommitowane zmiany. Najpierw je zacommituj, wysyłany jest tylko ostatni commit." >&2
    exit 1
fi

# The list of 0x40's pack (respack.json) and its pictures in one
# (pictures.bin) are made again by hues/respack.php before anything goes, so
# a picture or song added, changed or removed never goes without them; it
# writes only what changed, and a failure (a PNG cwebp could not make WebP)
# stops the deploy. --dry-run only says whether it would run.
if [ -d hues/.pack ]; then
    if [ "$dry_run" -eq 0 ]; then
        php hues/respack.php
    elif php hues/respack.php --check; then
        echo "[dry-run] Lista 0x40 (respack.json, pictures.bin) jest aktualna."
    else
        echo "[dry-run] Uruchomiłbym php hues/respack.php: obrazy lub utwory 0x40 się zmieniły."
    fi
fi

commit=$(git rev-parse HEAD)
marker="$root/inc/data/deployed-commit"
if [ "$dry_run" -eq 1 ]; then
    echo "[dry-run] Odczyt znacznika z serwera (tylko czytanie), żeby pokazać zmiany."
fi
previous=$(ssh "$target" "cat '$marker' 2>/dev/null || true")

# --offline: the writers stop before any file goes. A failure before the files
# are sent resumes them at once, as the server still has the old code; a later
# one leaves the site stopped, for a look at what happened (--resume, or the
# backup back).
sent=0
stopped=0
offline_exit() {
    local status=$?
    if [ "$stopped" -eq 1 ] && [ "$status" -ne 0 ]; then
        if [ "$sent" -eq 0 ]; then
            echo "Przerwano przed wysłaniem plików, wznawiam stronę i crony." >&2
            resume_remote || true
        else
            echo "Wdrożenie przerwane, strona i crony są wstrzymane. Po sprawdzeniu: ./deploy.sh --resume $target" >&2
            echo "Kopia danych sprzed wdrożenia: ssh $target cat $paused/backup" >&2
        fi
    fi
    exit "$status"
}

if [ "$offline" -eq 1 ] && [ "$dry_run" -eq 1 ]; then
    echo "[dry-run] --offline: wyjąłbym crony ($cron_jobs), poczekał na ich procesy, zatrzymał PHP-FPM i zrobił kopię inc/data oraz i/ w /root."
elif [ "$offline" -eq 1 ]; then
    trap offline_exit EXIT
    stopped=1
    echo "Wstrzymywanie zapisów na serwerze:"
    ssh "$target" "bash -s -- '$root' '$paused' $cron_jobs" <<'REMOTE'
set -euo pipefail
root=$1
paused=$2
shift 2
mkdir -p "$paused"
chmod 700 "$paused"

for job in "$@"; do
    if [ -f "/etc/cron.d/$job" ]; then
        mv "/etc/cron.d/$job" "$paused/$job"
        echo "  cron $job wyłączony"
    fi
done
# the cleanup's own cron job, as docs/server.md has it, when the server has none
# yet: it goes in with the others when they are resumed
if [ ! -f "$paused/sanakan-maintenance" ]; then
    echo "*/10 * * * * www-data /usr/bin/systemd-cat -t sanakan-maintenance php $root/inc/run-maintenance.php" > "$paused/sanakan-maintenance"
    chmod 644 "$paused/sanakan-maintenance"
    echo "  cron sanakan-maintenance zostanie założony przy wznowieniu"
fi

# a run already started finishes on its own; a background conversion can take
# up to 15 minutes, so the wait is 20
pattern="$root/inc/(check-bot|check-site|run-maintenance|media-worker|pw-sync)\.php"
deadline=$((SECONDS + 1200))
while pgrep -f "$pattern" > /dev/null; do
    if [ "$SECONDS" -ge "$deadline" ]; then
        echo "Procesy cronów nie skończyły się w 20 minut:" >&2
        pgrep -af "$pattern" >&2
        exit 1
    fi
    echo "  czekam na: $(pgrep -af "$pattern" | sed -E 's/.*inc\/([a-z-]+\.php).*/\1/' | sort -u | tr '\n' ' ')"
    sleep 10
done
echo "  procesy cronów zakończone"

# QUIT lets PHP-FPM finish the requests it has (TERM would cut them), then stop
# after a minute at the latest
fpm=$(systemctl list-units --type=service --state=active --plain --no-legend 'php*-fpm.service' | awk 'NR == 1 {print $1}')
if [ -n "$fpm" ]; then
    echo "$fpm" > "$paused/fpm"
    systemctl kill --kill-who=main --signal=QUIT "$fpm"
    for _ in $(seq 60); do
        systemctl is-active --quiet "$fpm" || break
        sleep 1
    done
    systemctl stop "$fpm"
    echo "  $fpm zatrzymany"
elif [ -f "$paused/fpm" ]; then
    echo "  $(cat "$paused/fpm") był już zatrzymany"
else
    echo "Nie znaleziono działającej usługi php*-fpm." >&2
    exit 1
fi

# the backup leaves out what the panel's backup leaves out (thumbnails, diag,
# the mirrored card pictures, lock files): they are made again
need=$(du -sb --exclude=thumbs --exclude=diag --exclude=pw --exclude=locks "$root/inc/data" "$root/i" | awk '{s += $1} END {print s}')
avail=$(df --output=avail -B1 /root | tail -n 1)
if [ "$need" -gt $((avail - 1073741824)) ]; then
    echo "Za mało miejsca na kopię: potrzeba $need B i 1 GiB zapasu, wolne $avail B." >&2
    exit 1
fi
backup="/root/sansite-backup-$(date +%Y%m%d-%H%M%S).tar"
tar -cf "$backup" -C "$root" --exclude=inc/data/thumbs --exclude=inc/data/diag \
    --exclude=inc/data/pw --exclude=inc/data/locks inc/data i
chmod 600 "$backup"
echo "$backup" > "$paused/backup"
echo "  kopia: $backup ($(du -h "$backup" | cut -f1))"
REMOTE
fi

if [ "$dry_run" -eq 1 ]; then
    echo "[dry-run] Wysłałbym $(git log -1 --format='%h %s') do $target:$root"
else
    sent=1
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
    changed_config=$(git diff --name-only --diff-filter=ACMR "$previous" HEAD -- server/nginx/ server/wiki/nginx-og.conf server/wiki/nginx-site.conf)
else
    # no marker yet (the first deploy): send them, it is harmless
    changed_config=$(git ls-files -- server/nginx/ server/wiki/nginx-og.conf server/wiki/nginx-site.conf)
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

# The wiki's icons and manifest (server/wiki/assets/) when they changed since
# the last deploy: over Wiki.js' own files, whose originals are kept once in
# /root/wiki-assets-original/, then Wiki.js restarts, as it keeps /favicon.ico
# in memory. A Wiki.js update brings its own icons back; WIKI_FORCE=1 sends
# them again without a change.
wiki_assets=${WIKI_ASSETS:-/var/www/wiki/assets}
wiki_service=${WIKI_SERVICE:-wiki}
wiki_url=${WIKI_URL:-https://wiki.sanakan.pl}
if [ "${WIKI_FORCE:-0}" = 1 ] || [ -z "$previous" ] || ! git cat-file -e "$previous^{commit}" 2>/dev/null; then
    changed_wiki=$(git -c core.quotepath=false ls-files -- server/wiki/assets/)
else
    changed_wiki=$(git -c core.quotepath=false diff --name-only --diff-filter=ACMR "$previous" HEAD -- server/wiki/assets/)
fi
if [ -n "$changed_wiki" ]; then
    echo "Zmienione pliki wiki, wysyłam do $target:$wiki_assets i restartuję $wiki_service:"
    echo "$changed_wiki" | sed 's#^server/wiki/assets/#  #'
    if [ "$dry_run" -eq 0 ]; then
        wiki_tar=/tmp/sanakan-wiki-assets.tar
        git -C server/wiki/assets ls-files -z \
            | tar -c -C server/wiki/assets --null -T - \
            | ssh "$target" "cat > '$wiki_tar'"
        ssh "$target" "bash -s -- '$wiki_assets' '$wiki_service' '$wiki_tar'" <<'REMOTE'
set -euo pipefail
trap 'rm -f "$3"' EXIT
cd "$1"
if [ ! -d /root/wiki-assets-original ]; then
    mkdir /root/wiki-assets-original
    cp -r favicons manifest.json favicon.ico /root/wiki-assets-original/
fi
tar -x -f "$3" --no-same-owner --no-overwrite-dir
chown -R --reference=. .
systemctl restart "$2"
echo "Ikony wiki wgrane, $2 zrestartowane."
REMOTE
    else
        echo "[dry-run] Wysłałbym je i zrestartował $wiki_service."
    fi
fi

# The skins' songs (audio/*.webm in Opus and audio/*.mp3 for the browsers
# without it, js/seasonal-background.js) are kept out of git: one goes when the
# server has none of its name or another one (by MD5), and Cloudflare forgets
# it below. One removed here stays on the server.
changed_audio=()
if compgen -G 'audio/*.webm' > /dev/null || compgen -G 'audio/*.mp3' > /dev/null; then
    # in bash, as the server's login shell (zsh) stops at a pattern that
    # matches nothing, before there is any audio/ there
    remote_audio=$(ssh "$target" "bash -s -- '$root'" <<'REMOTE'
cd "$1" 2>/dev/null || exit 0
for file in audio/*.webm audio/*.mp3; do
    [ -f "$file" ] && echo "$(md5sum < "$file" | cut -d' ' -f1) $file"
done
exit 0
REMOTE
)
    for file in audio/*.webm audio/*.mp3; do
        [ -f "$file" ] || continue
        grep -qxF "$(md5sum < "$file" | cut -d' ' -f1) $file" <<< "$remote_audio" || changed_audio+=("$file")
    done
fi
if [ ${#changed_audio[@]} -gt 0 ]; then
    echo "Zmienione piosenki skinów, wysyłam do $target:$root/audio:"
    printf '  %s\n' "${changed_audio[@]}"
    if [ "$dry_run" -eq 0 ]; then
        tar -c "${changed_audio[@]}" \
            | ssh "$target" "mkdir -p '$root/audio' && tar -x -C '$root' --no-same-owner --no-overwrite-dir"
    else
        echo "[dry-run] Wysłałbym je."
    fi
fi

# The media of 0x40 (hues/.pack/: pictures, animations, songs, respack.json)
# and of WAP (wap/.mu/) are kept out of git as well and go the same way as the
# songs: a file goes when the server has none of its name or another one (by
# MD5), and one removed here stays on the server. They lie in dot folders,
# which nginx refuses, and go out only through hues/pack.php and wap/song.php
# with Cache-Control: private, so Cloudflare has nothing of them to forget.
media_dirs=()
for dir in hues/.pack wap/.mu; do
    [ -d "$dir" ] && media_dirs+=("$dir")
done
changed_media=()
if [ ${#media_dirs[@]} -gt 0 ]; then
    # "hash path" either side: md5sum in Git Bash marks the files binary
    # ("hash *path"), on the server it does not ("hash  path")
    sums() { sed -E 's/^([0-9a-f]{32}) [ *]/\1 /'; }
    remote_media=$(ssh "$target" "bash -s -- '$root' $(printf "'%s' " "${media_dirs[@]}")" <<'REMOTE' | sums
cd "$1" 2>/dev/null || exit 0
shift
for dir in "$@"; do
    [ -d "$dir" ] && find "$dir" -type f -print0 | xargs -0 -r md5sum
done
exit 0
REMOTE
)
    while IFS= read -r line; do
        [ -n "$line" ] || continue
        grep -qxF "$line" <<< "$remote_media" || changed_media+=("${line#* }")
    done < <(find "${media_dirs[@]}" -type f -print0 | xargs -0 -r md5sum | sums)
fi
if [ ${#changed_media[@]} -gt 0 ]; then
    echo "Zmienione pliki 0x40 i WAP spoza gita, wysyłam do $target:$root:"
    printf '  %s\n' "${changed_media[@]}"
    if [ "$dry_run" -eq 0 ]; then
        tar -c "${changed_media[@]}" \
            | ssh "$target" "tar -x -C '$root' --no-same-owner --no-overwrite-dir"
    else
        echo "[dry-run] Wysłałbym je."
    fi
fi

# Cloudflare forgets the pictures, icons and the like the deploy changed or
# deleted (the CSS and JS have a new ?v= instead), the wiki's too, so they show
# at once and not after a day; inc/purge-cache.php on the server does it with
# the token of inc/config.php. A failure only warns, the files are in place.
purge=""
if [ -n "$previous" ] && git cat-file -e "$previous^{commit}" 2>/dev/null; then
    purge=$(git -c core.quotepath=false diff --name-only --no-renames --diff-filter=ACMRD "$previous" HEAD \
        | grep -E '\.(png|jpe?g|gif|webp|avif|svg|ico|woff2|webmanifest|txt)$' \
        | grep -vE '^(server|tools|apps|tests|\.github)/' \
        | sed "s#^#$site_url/#" || true)
fi
while IFS= read -r file; do
    [ -n "$file" ] || continue
    purge+=$'\n'"$wiki_url/_assets/${file#server/wiki/assets/}"
    [ "$file" = server/wiki/assets/favicon.ico ] && purge+=$'\n'"$wiki_url/favicon.ico"
done <<< "$changed_wiki"
for file in ${changed_audio[@]+"${changed_audio[@]}"}; do
    purge+=$'\n'"$site_url/$file"
done
purge=$(printf '%s\n' "$purge" | sed '/^$/d; s/ /%20/g')
if [ -n "$purge" ]; then
    echo "Czyszczenie cache Cloudflare:"
    echo "$purge" | sed 's/^/  /'
    if [ "$dry_run" -eq 0 ]; then
        if ! printf '%s\n' "$purge" | ssh "$target" "cd '$root' && runuser -u www-data -- php inc/purge-cache.php"; then
            echo "UWAGA: cache Cloudflare nie wyczyszczony, stare pliki mogą się pokazywać do doby (Caching → Custom Purge)." >&2
        fi
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
fi

# --offline, with the new code in place and nothing else writing: what the data
# holds that the new checks would refuse, then the migration's dry run, which
# fails on a broken document or a conflict; the migration itself only after a
# yes, then one maintenance run whose exit code must be 0. The migration can
# run again harmlessly; a release without inc/migrate-gallery.php skips it.
if [ "$offline" -eq 1 ] && [ "$dry_run" -eq 1 ]; then
    echo "[dry-run] --offline: potem inwentarz, migracja danych (dry-run, pytanie, migracja), przebieg maintenance i wznowienie PHP-FPM oraz cronów."
elif [ "$offline" -eq 1 ]; then
    echo "Inwentarz i próba migracji (nowy kod, jako www-data):"
    ssh "$target" "bash -s -- '$root'" <<'REMOTE'
set -euo pipefail
cd "$1"
runuser -u www-data -- php inc/run-maintenance.php --inventory
if [ -f inc/migrate-gallery.php ]; then
    runuser -u www-data -- php inc/migrate-gallery.php --dry-run
fi
REMOTE

    if ssh "$target" "test -f '$root/inc/migrate-gallery.php'"; then
        if [ "$yes" -eq 0 ]; then
            read -r -p "Migrować dane? [t/N] " answer < /dev/tty
            case "$answer" in
                t|T|y|Y) ;;
                *) echo "Bez migracji." >&2; exit 1 ;;
            esac
        fi
        ssh "$target" "cd '$root' && runuser -u www-data -- php inc/migrate-gallery.php && test -f inc/data/initialized"
    fi

    echo "Przebieg maintenance:"
    ssh "$target" "cd '$root' && runuser -u www-data -- php inc/run-maintenance.php"

    resume_remote
    stopped=0
    checks
fi

if [ "$dry_run" -eq 0 ]; then
    echo "Gotowe: $(git log -1 --format='%h')"
fi
