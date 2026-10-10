#!/usr/bin/env bash
# Moves the site and the wiki from the old server to a new one over SSH, with
# everything going through this computer (ssh old ... | ssh new ...), so the
# servers need no keys to each other. Run it from Git Bash or any shell with
# ssh, tar and base64, in steps:
#   ./migrate.sh check   old new   only reads both servers and shows what moves
#   ./migrate.sh prepare old new   packages and settings on the new server: nginx
#                                  with this repository's rules (server/nginx/,
#                                  the wiki's in server/wiki/) and the Cloudflare
#                                  Origin Certificate (ORIGIN_CERT=… ORIGIN_KEY=…
#                                  or already in /etc/ssl/cloudflare/), the
#                                  firewall (server/ufw-cloudflare.sh), PHP-FPM (the
#                                  old pool and php.ini changes, in the new PHP
#                                  version), the PHP modules, Node.js of the same
#                                  version as the wiki's (and NODE_VERSION=22, a
#                                  major or an exact release, the one the wiki
#                                  then runs on), its database server and
#                                  user, its service (not started), fail2ban, the
#                                  time zone
#   ./migrate.sh copy    old new   the site (/var/www/html with inc/config.php,
#                                  inc/data/ and the gallery), the wiki's folder;
#                                  run again, it sends only what changed since
#   ./migrate.sh wiki-test old new runs the wiki on the new server on the copy,
#                                  shows whether it answers and its log, stops it
#                                  and puts its folder back as copied
#   ./migrate.sh final   old new   stops the crons and the wiki on the old server,
#                                  sends what changed, moves the wiki's database,
#                                  starts the crons and the wiki on the new one
#   ./migrate.sh undo    old new   before the DNS switch: back to the old server
#   ./migrate.sh tune    old new   (also at the end of prepare) a swap file, the
#                                  PHP-FPM pool of 8 processes at most,
#                                  SSH by key only
# old and new are SSH targets, best host aliases in ~/.ssh/config, logging in as
# root with a key. Nothing is ever deleted on the old server: final only moves
# its sanakan cron files aside (to /root/sanakan-migration/) and stops the wiki,
# which undo brings back. The DNS records in Cloudflare, and a configuration
# rule setting SSL to Full (strict) for this server's names only (the other
# subdomains live elsewhere), are switched by hand after final. The thumbnail
# cache (inc/data/thumbs) is not copied, the new server makes it again.
set -euo pipefail

SITE=/var/www/html
WIKI=/var/www/wiki
WORK=/root/sanakan-migration
THUMBS=var/www/html/inc/data/thumbs

usage() {
    cat >&2 <<'EOF'
Użycie: ./migrate.sh krok stary nowy
  check    tylko czyta oba serwery i pokazuje, co zostanie przeniesione
  prepare  instaluje i ustawia nowy serwer (pakiety, nginx, PHP, Node, baza wiki)
  copy     kopiuje stronę, galerię i wiki; kolejne uruchomienie dosyła tylko zmiany
  wiki-test  uruchamia wiki na nowym serwerze na kopii, pokazuje wynik i dziennik, zatrzymuje
  final    zatrzymuje crony i wiki na starym, dosyła zmiany i bazę wiki, uruchamia nowy
  undo     przed zmianą DNS: crony i wiki znów na starym, na nowym zatrzymane
  tune     (też na końcu prepare) swap, pula PHP-FPM do 8 procesów, SSH tylko kluczem
stary i nowy to cele SSH (najlepiej aliasy z ~/.ssh/config), logowanie jako root kluczem.
NODE_VERSION=22 ./migrate.sh prepare … instaluje dla wiki Node 22 (najnowsze 22.x) obok jej dotychczasowej wersji.
EOF
    exit 1
}

say() { printf '\n== %s\n' "$*" >&2; }
warn() { printf 'UWAGA: %s\n' "$*" >&2; }
die() { printf 'BŁĄD: %s\n' "$*" >&2; exit 1; }

# one fact for the computer running this script, base64 so any text fits a line
emit() { printf '%s=%s\n' "$1" "$(printf '%s' "$2" | base64 -w0)"; }

php_version() { php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;'; }

# ---------------------------------------------------------------- old server

# the enabled nginx sites of the site and of the wiki ("other": the rest), as
# "name<TAB>file"
old_sites() {
    local f match
    for f in /etc/nginx/sites-enabled/*; do
        [ -e "$f" ] || continue
        match=0
        if grep -qE 'snippets/sanakan\.conf|wiki\.sanakan\.pl' "$f"; then match=1; fi
        if [ "${1:-}" = other ]; then [ $match = 0 ] || continue; else [ $match = 1 ] || continue; fi
        printf '%s\t%s\n' "${f##*/}" "$(readlink -f "$f")"
    done
}

# the files the nginx configuration points at that are not copied with its
# snippets/ and conf.d/: certificates, password files, includes elsewhere
# (Let's Encrypt as its whole folder, so its links and renewals keep working)
old_nginx_refs() {
    local p
    { grep -hE '^[[:space:]]*(ssl_certificate|ssl_certificate_key|ssl_trusted_certificate|ssl_client_certificate|ssl_dhparam|ssl_password_file|auth_basic_user_file|include)[[:space:]]' \
        /etc/nginx/nginx.conf /etc/nginx/snippets/* /etc/nginx/conf.d/* $(old_sites | cut -f2) 2>/dev/null || true; } \
        | sed -E 's/^[[:space:]]*[a-z_]+[[:space:]]+//; s/;.*$//; s/[[:space:]]+$//' | tr -d "\"'" \
        | while read -r p; do
            case $p in ''|*'*'*|*'$'*) continue ;; esac
            case $p in /*) ;; *) p=/etc/nginx/$p ;; esac
            [ -e "$p" ] || continue
            case $p in
                /etc/nginx/snippets/*|/etc/nginx/conf.d/*|/etc/nginx/sites-*|/etc/nginx/modules-*) continue ;;
                /etc/nginx/*) if dpkg -S "$p" >/dev/null 2>&1; then continue; fi ;;
                /etc/letsencrypt/*) echo /etc/letsencrypt; continue ;;
            esac
            echo "$p"
            if [ -L "$p" ]; then readlink -f "$p"; fi
        done | sort -u
}

old_wiki_unit() {
    grep -lsE "^WorkingDirectory=$WIKI/?\$" /etc/systemd/system/*.service /lib/systemd/system/*.service | head -1 || true
}

old_facts() {
    local unit node exec t toks=() names=() n users
    . /etc/os-release
    emit os "$PRETTY_NAME"
    emit arch "$(uname -m)"
    emit uid "$(id -u)"
    emit php "$(php_version 2>/dev/null || true)"
    emit php_pkgs "$(dpkg-query -W -f='${db:Status-Abbrev} ${Package}\n' 'php*' 2>/dev/null | awk '$1 == "ii" { print $2 }' | tr '\n' ' ')"
    emit tz "$(timedatectl show -p Timezone --value 2>/dev/null || cat /etc/timezone 2>/dev/null || true)"
    emit sites "$(old_sites)"
    emit other_sites "$(old_sites other)"
    emit refs "$(old_nginx_refs)"
    emit listen_ips "$(old_sites | cut -f2 | xargs -r grep -hE '^[[:space:]]*listen[[:space:]]+([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+|\[[0-9a-fA-F]+:)' 2>/dev/null || true)"
    # one du each: given together, du leaves out the folders it already counted
    emit sizes "$(for p in "$SITE" "$SITE/i" "$SITE/inc/data" "$WIKI"; do if [ -d "$p" ]; then du -sh "$p" 2>/dev/null || true; fi; done)"

    if [ -d "$WIKI" ]; then
        emit wiki 1
        unit=$(old_wiki_unit)
        node=""
        emit wiki_unit "$unit"
        if [ -n "$unit" ]; then
            emit envfiles "$(sed -n 's/^EnvironmentFile=-\{0,1\}//p' "$unit")"
            names+=("$(sed -n 's/^User=//p' "$unit" | head -1)")
            exec=$(sed -n 's/^ExecStart=[-@+!:]*//p' "$unit" | head -1)
            read -ra toks <<< "$exec"
            for t in "${toks[@]}"; do
                case ${t##*/} in node|nodejs) node=$t; break ;; esac
            done
        fi
        if [ -n "$node" ] && [[ $node != /* ]]; then node=$(command -v "$node" || true); fi
        [ -n "$node" ] || node=$(command -v node || true)
        if [ -n "$node" ]; then
            emit node_version "$("$node" --version 2>/dev/null || true)"
            # the database from the wiki's config.yml, read with its own js-yaml
            "$node" -e '
                const w = process.argv[1];
                const yaml = require(w + "/node_modules/js-yaml");
                const c = yaml.load(require("fs").readFileSync(w + "/config.yml", "utf8")) || {};
                const d = c.db || {};
                for (const k of ["type", "host", "port", "user", "pass", "db", "storage"]) {
                    const v = d[k] == null ? "" : String(d[k]);
                    console.log("db_" + k + "=" + Buffer.from(v).toString("base64"));
                }' "$WIKI" 2>/dev/null || emit db_error "nie udało się odczytać $WIKI/config.yml"
        fi
        emit pg_version "$(psql --version 2>/dev/null || true)"
        emit wiki_owner "$(stat -c '%U:%G' "$WIKI")"
        emit wiki_assets_backup "$([ -d /root/wiki-assets-original ] && echo 1 || true)"
        names+=("$(stat -c %U "$WIKI")")
        users=$(for n in "${names[@]}"; do
            case $n in ''|root|www-data) continue ;; esac
            getent passwd "$n" >/dev/null || continue
            getent passwd "$n" | awk -F: -v g="$(id -gn "$n")" '{ print $1 ":" g ":" $6 ":" $7 }'
        done | sort -u)
        emit users "$users"
    fi

    emit fail2ban "$(systemctl is-active fail2ban 2>/dev/null | grep -x active || true)"
    emit ufw "$(ufw status 2>/dev/null | head -1 || true)"
    emit ufw_rules "$(ufw status 2>/dev/null | tail -n +2 | sed '/^[[:space:]]*$/d' || true)"
    emit swap "$(free -h | awk '/^Swap:/ { print $2 }')"
    emit www_dirs "$(ls -A /var/www 2>/dev/null || true)"
    emit crontabs "$(for n in root www-data; do crontab -l -u "$n" 2>/dev/null | grep -vE '^[[:space:]]*(#|$)' | sed "s/^/$n: /"; done || true)"
    emit other_cron "$(for f in /etc/cron.d/*; do
        case ${f##*/} in sanakan-*|.placeholder) continue ;; esac
        dpkg -S "$f" >/dev/null 2>&1 || echo "$f"
    done)"
    emit own_units "$(for f in /etc/systemd/system/*.service; do
        # snap writes its own units there, they are not the server's own
        case ${f##*/} in snap.*|snap-*) continue ;; esac
        if [ -f "$f" ] && [ ! -L "$f" ] && [ "$f" != "$unit" ]; then echo "${f##*/}"; fi
    done)"
}

# what of the old configuration goes to the new server's staging folder
# whether nginx.conf is no longer the one its package brought
old_nginx_conf_changed() {
    local sum
    sum=$(dpkg-query -W -f='${Conffiles}\n' nginx-common 2>/dev/null | awk '$1 == "/etc/nginx/nginx.conf" { print $2 }')
    [ -z "$sum" ] || [ "$(md5sum < /etc/nginx/nginx.conf | cut -d' ' -f1)" != "$sum" ]
}

old_config_paths() {
    local v unit p s
    v=$(php_version)
    unit=$(old_wiki_unit)
    {
        # nginx's own files (nginx.conf as it came, mime.types, fastcgi-php.conf)
        # stay behind, the new server's newer ones are better
        if old_nginx_conf_changed; then echo /etc/nginx/nginx.conf; fi
        for p in /etc/nginx/snippets/* /etc/nginx/conf.d/*; do
            if [ -f "$p" ] && ! dpkg -S "$p" >/dev/null 2>&1; then echo "$p"; fi
        done
        old_sites | cut -f2
        old_nginx_refs
        for s in fpm cli; do
            echo "/etc/php/$v/$s/php.ini"
            echo "/etc/php/$v/$s/conf.d"
        done
        echo "/etc/php/$v/fpm/pool.d"
        echo "/usr/lib/php/$v/php.ini-production"
        echo "/usr/lib/php/$v/php.ini-production.cli"
        if [ -n "$unit" ]; then
            echo "$unit"
            sed -n 's/^EnvironmentFile=-\{0,1\}//p' "$unit"
        fi
        ls -d /etc/cron.d/sanakan-* 2>/dev/null || true
        echo /etc/fail2ban/jail.local
        echo /etc/fail2ban/jail.d
    } | while read -r p; do
        if [ -e "$p" ]; then echo "${p#/}"; fi
    done | sort -u
}

old_tar_config() {
    old_config_paths | tar -C / -cf - -T -
}

# the crons and the wiki stop, so nothing writes while the last changes go
old_stop() {
    local unit=$1 f i
    mkdir -p "$WORK/cron.d-off"
    for f in /etc/cron.d/sanakan-*; do
        [ -e "$f" ] || continue
        mv "$f" "$WORK/cron.d-off/"
        echo "  cron ${f##*/} wyłączony (leży w $WORK/cron.d-off/)"
    done
    for i in $(seq 1 24); do
        pgrep -f "$SITE/inc/(check-bot|check-site|media-worker)\.php" >/dev/null || break
        [ "$i" -gt 1 ] || echo "  czekam, aż skończą się trwające sprawdzenia z crona..."
        sleep 5
    done
    if pgrep -f "$SITE/inc/(check-bot|check-site|media-worker)\.php" >/dev/null; then
        warn "Sprawdzenia z crona wciąż trwają po 2 minutach, kopiuję mimo to."
    fi
    if [ -n "$unit" ]; then
        systemctl stop "$unit"
        echo "  wiki ($unit) zatrzymane"
    fi
}

old_undo() {
    local unit=$1 f
    for f in "$WORK/cron.d-off/"sanakan-*; do
        [ -e "$f" ] || continue
        mv "$f" /etc/cron.d/
        echo "  cron ${f##*/} znów włączony"
    done
    if [ -n "$unit" ]; then
        systemctl start "$unit"
        echo "  wiki ($unit) uruchomione"
    fi
}

# ---------------------------------------------------------------- new server

new_facts() {
    . /etc/os-release
    emit os "$PRETTY_NAME"
    emit arch "$(uname -m)"
    emit uid "$(id -u)"
    emit php "$(php_version 2>/dev/null || true)"
    emit free "$(df -h --output=avail /var | tail -1 | tr -d ' ')"
    emit prepared "$(cat "$WORK/prepared" 2>/dev/null || true)"
    emit since "$(cat "$WORK/copy-since" 2>/dev/null || true)"
    emit final "$(cat "$WORK/final" 2>/dev/null || true)"
    emit has_site "$([ -e "$SITE/inc" ] && echo 1 || true)"
}

# installs the packages this Ubuntu has, and says which it does not
apt_install() {
    local p ok=()
    for p in "$@"; do
        if apt-cache show "$p" >/dev/null 2>&1; then ok+=("$p"); else warn "Nie ma pakietu $p, pomijam."; fi
    done
    [ ${#ok[@]} -eq 0 ] || apt-get install -y -q "${ok[@]}"
}

# the old PHP version in paths and names becomes the new one
php_subst() {
    sed -E "s#php$old_re#php$v#g; s#/php/$old_re/#/php/$v/#g"
}

# the settings of php.ini ($2) that differ from the stock one ($1)
ini_changes() {
    awk '
        { line = $0; sub(/^[ \t]+/, "", line) }
        line ~ /^[;#[]/ || line !~ /=/ { next }
        {
            key = line; sub(/[ \t]*=.*/, "", key)
            val = line; sub(/^[^=]*=[ \t]*/, "", val); sub(/[ \t]+$/, "", val)
        }
        NR == FNR { stock[key SUBSEP val] = 1; next }
        !((key SUBSEP val) in stock) { print key " = " val }
    ' "$1" "$2"
}

sql_str() { local s=${1//\\/\\\\}; printf '%s' "${s//\'/\\\'}"; }
sql_id() { printf '%s' "${1//\`/\`\`}"; }
pg() { (cd / && runuser -u postgres -- "$@"); }

new_prepare() {
    . "$WORK/old-facts.sh"
    local stage=$WORK/old-root v old_re p name f sapi src stock out link target u g home shell slow
    local extra=()
    export DEBIAN_FRONTEND=noninteractive NEEDRESTART_SUSPEND=1

    say "Pakiety"
    apt-get update -q
    apt_install nginx php-fpm php-cli php-gd php-zip php-curl webp ffmpeg imagemagick curl ca-certificates xz-utils cron
    v=$(php_version)
    old_re=${OLD_php//./\\.}
    # the PHP modules the old server had, in the new version
    for p in $OLD_php_pkgs; do
        case $p in "php$OLD_php"-*) extra+=("php$v-${p#"php$OLD_php"-}") ;; esac
    done
    [ -z "$OLD_fail2ban" ] || extra+=(fail2ban)
    [ ! -d "$stage/etc/letsencrypt" ] || extra+=(certbot python3-certbot-nginx)
    if [ "$OLD_db_local" = 1 ]; then
        case $OLD_db_type in
            postgres) extra+=(postgresql) ;;
            mysql) extra+=(mysql-server) ;;
            mariadb) extra+=(mariadb-server) ;;
        esac
    fi
    [ ${#extra[@]} -eq 0 ] || apt_install "${extra[@]}"

    if [ -n "$OLD_tz" ]; then
        timedatectl set-timezone "$OLD_tz" || warn "Nie udało się ustawić strefy czasu $OLD_tz."
    fi

    # the wiki's user, so the copied files keep their owner
    while IFS=: read -r u g home shell; do
        [ -n "$u" ] || continue
        getent group "$g" >/dev/null || groupadd --system "$g"
        if ! id "$u" >/dev/null 2>&1; then
            useradd --system -g "$g" -d "$home" -M -s "$shell" "$u"
            echo "  utworzony użytkownik $u"
        fi
    done <<< "$OLD_users"

    say "nginx"
    [ -d "$WORK/nginx-fresh" ] || cp -a /etc/nginx "$WORK/nginx-fresh"
    rm -f /etc/nginx/sites-enabled/*
    cp -a "$stage/etc/nginx/." /etc/nginx/
    while IFS=$'\t' read -r link target; do
        [ -n "$link" ] || continue
        if [ "$target" != "/etc/nginx/sites-enabled/$link" ]; then
            ln -sfn "$target" "/etc/nginx/sites-enabled/$link"
        fi
        echo "  $link -> $target"
    done <<< "$OLD_sites"
    while read -r p; do
        case $p in ''|/etc/nginx/*) continue ;; esac
        mkdir -p "$(dirname "$p")"
        cp -a "$stage$p" "$(dirname "$p")/"
        echo "  $p"
    done <<< "$OLD_refs"
    if [ "$v" != "$OLD_php" ]; then
        { grep -rlE "php$old_re-fpm\.sock|/etc/php/$old_re/" /etc/nginx || true; } | while read -r f; do
            sed -i -E "s#php$old_re-fpm\.sock#php$v-fpm.sock#g; s#/etc/php/$old_re/#/etc/php/$v/#g" "$f"
            echo "  $f: PHP $OLD_php -> $v"
        done
    fi
    rm -f "$SITE/index.nginx-debian.html"
    if ! nginx -t; then
        warn "nginx -t nie przeszło z nginx.conf ze starego serwera, próbuję z nowym."
        cp -a "$WORK/nginx-fresh/nginx.conf" /etc/nginx/nginx.conf
        nginx -t || die "Konfiguracja nginx nie przechodzi nginx -t. Popraw ją na nowym serwerze i uruchom prepare jeszcze raz."
    fi
    systemctl enable nginx
    systemctl restart nginx

    say "PHP $v (na starym $OLD_php)"
    for f in "$stage/etc/php/$OLD_php/fpm/pool.d/"*.conf; do
        [ -f "$f" ] || continue
        name=/etc/php/$v/fpm/pool.d/${f##*/}
        if [ -f "$name" ] && [ ! -f "$WORK/pool-fresh-${f##*/}" ]; then cp -a "$name" "$WORK/pool-fresh-${f##*/}"; fi
        php_subst < "$f" > "$name"
        echo "  pula ${f##*/} ze starego serwera"
    done
    for sapi in fpm cli; do
        src=$stage/etc/php/$OLD_php/$sapi/php.ini
        stock=$stage/usr/lib/php/$OLD_php/php.ini-production
        if [ "$sapi" = cli ] && [ -f "$stock.cli" ]; then stock=$stock.cli; fi
        if [ -f "$src" ] && [ -f "$stock" ]; then
            out=/etc/php/$v/$sapi/conf.d/99-sanakan-migrated.ini
            ini_changes "$stock" "$src" | php_subst > "$out"
            if [ -s "$out" ]; then
                echo "  $sapi: zmiany ze starego php.ini w $out"
                sed 's/^/    /' "$out"
            else
                rm -f "$out"
            fi
        fi
        # own ini files, not the links of the modules
        for f in "$stage/etc/php/$OLD_php/$sapi/conf.d/"*; do
            if [ -f "$f" ] && [ ! -L "$f" ]; then
                php_subst < "$f" > "/etc/php/$v/$sapi/conf.d/${f##*/}"
                echo "  $sapi: ${f##*/}"
            fi
        done
    done
    "php-fpm$v" -t || die "Konfiguracja PHP-FPM nie przechodzi testu (wyżej)."
    systemctl enable "php$v-fpm"
    systemctl restart "php$v-fpm"
    # the nginx rules pass PHP to /run/php/php-fpm.sock, the link PHP-FPM's
    # service keeps to its socket; without it they get the socket itself
    sleep 1
    if [ ! -S /run/php/php-fpm.sock ]; then
        warn "Nie ma /run/php/php-fpm.sock, nginx dostaje /run/php/php$v-fpm.sock (w repozytorium trzeba będzie zrobić to samo)."
        { grep -rl 'php-fpm\.sock' /etc/nginx || true; } | while read -r f; do
            sed -i "s#/run/php/php-fpm\.sock#/run/php/php$v-fpm.sock#g" "$f"
        done
        nginx -t
        systemctl reload nginx
    fi
    slow=$(sed -nE 's/^[[:space:]]*slowlog[[:space:]]*=[[:space:]]*//p' "/etc/php/$v/fpm/pool.d/www.conf" | tail -1)
    if [ -n "$slow" ]; then
        touch "$slow"
        chmod 644 "$slow"
    fi

    if [ -n "$OLD_node_version" ]; then
        # the wiki's own version always, as the way back; NODE_VERSION (a major
        # such as 22 for its newest release, or an exact one) is the one used
        local want=${OLD_node_want#v} ver
        say "Node.js dla wiki"
        node_install "${OLD_node_version#v}"
        ver=${OLD_node_version#v}
        if [ -n "$want" ]; then
            if [[ $want == *.*.* ]]; then
                ver=$want
            else
                ver=$(curl -fsSL "https://nodejs.org/dist/latest-v${want%%.*}.x/SHASUMS256.txt" \
                    | sed -nE "s/.*node-v([0-9.]+)-linux-$(node_arch)\.tar\.xz\$/\1/p" | head -1)
                [ -n "$ver" ] || die "Nie znalazłem na nodejs.org wydania Node.js $want."
            fi
            node_install "$ver"
        fi
        node_use "$ver"
    fi

    if [ -n "$OLD_wiki_unit" ]; then
        say "Usługa wiki ${OLD_wiki_unit##*/} (uruchomi się w final)"
        name=/etc/systemd/system/${OLD_wiki_unit##*/}
        sed -E "s#^(ExecStart=[-@+!:]*)(/usr/bin/env[[:space:]]+)?[^[:space:]]*node(js)?([[:space:]]|\$)#\1/usr/local/bin/node\4#" \
            "$stage$OLD_wiki_unit" > "$name"
        if ! grep -q '^ExecStart=.*/usr/local/bin/node' "$name"; then
            warn "ExecStart w $name nie zaczyna się od node, sprawdź go."
        fi
        while read -r p; do
            if [ -n "$p" ] && [ -e "$stage$p" ]; then
                mkdir -p "$(dirname "$p")"
                cp -a "$stage$p" "$p"
            fi
        done <<< "$OLD_envfiles"
        systemctl daemon-reload
    fi

    if [ "$OLD_db_local" = 1 ]; then
        say "Baza wiki: $OLD_db_type, użytkownik $OLD_db_user"
        case $OLD_db_type in
            postgres)
                systemctl enable --now postgresql
                pg psql -q -v ON_ERROR_STOP=1 -v user="$OLD_db_user" -v pass="$OLD_db_pass" <<'SQL'
SELECT format('CREATE ROLE %I LOGIN', :'user') WHERE NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = :'user') \gexec
ALTER ROLE :"user" WITH LOGIN PASSWORD :'pass';
SQL
                ;;
            mysql|mariadb)
                systemctl enable --now "$OLD_db_type"
                mysql <<SQL
CREATE USER IF NOT EXISTS '$(sql_str "$OLD_db_user")'@'localhost' IDENTIFIED BY '$(sql_str "$OLD_db_pass")';
ALTER USER '$(sql_str "$OLD_db_user")'@'localhost' IDENTIFIED BY '$(sql_str "$OLD_db_pass")';
SQL
                ;;
        esac
    fi

    if [ -n "$OLD_fail2ban" ]; then
        say "fail2ban"
        [ ! -f "$stage/etc/fail2ban/jail.local" ] || cp -a "$stage/etc/fail2ban/jail.local" /etc/fail2ban/
        [ ! -d "$stage/etc/fail2ban/jail.d" ] || cp -a "$stage/etc/fail2ban/jail.d/." /etc/fail2ban/jail.d/
        systemctl enable fail2ban
        systemctl restart fail2ban || warn "fail2ban nie wstał, sprawdź: journalctl -u fail2ban"
    fi
    if [ -d /etc/letsencrypt ]; then
        systemctl enable --now certbot.timer 2>/dev/null || true
    fi

    new_tune
    date -Is > "$WORK/prepared"
}

# a setting of an ini-like file: the active line changed, or added at the end
set_ini() {
    local file=$1 key=$2 value=$3 re
    re=$(printf '%s' "$key" | sed 's/\./\\./g')
    if grep -qE "^[[:space:]]*$re[[:space:]]*=" "$file"; then
        sed -i -E "s#^[[:space:]]*$re[[:space:]]*=.*#$key = $value#" "$file"
    else
        printf '%s = %s\n' "$key" "$value" >> "$file"
    fi
}

# What the old server's settings do not fit on a bigger new one: a swap file
# (ffmpeg and ImageMagick on a big upload can take a lot of memory at once),
# a pool of 8 PHP-FPM processes at most, and SSH by key only.
new_tune() {
    local v pool
    v=$(php_version)
    pool=/etc/php/$v/fpm/pool.d/www.conf

    say "Swap"
    if [ -n "$(swapon --show --noheadings)" ]; then
        swapon --show
    else
        fallocate -l 2G /swapfile 2>/dev/null || dd if=/dev/zero of=/swapfile bs=1M count=2048 status=none
        chmod 600 /swapfile
        mkswap /swapfile >/dev/null
        swapon /swapfile
        grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
        echo "  /swapfile 2 GB"
    fi
    # the swap only when memory runs short
    echo 'vm.swappiness = 10' > /etc/sysctl.d/99-sanakan.conf
    sysctl -q -p /etc/sysctl.d/99-sanakan.conf

    # 8 PHP processes at once are plenty for the site's traffic (the media
    # conversions run from cron, outside the pool); 2 wait idle, a process is
    # renewed every 500 requests
    say "PHP-FPM"
    set_ini "$pool" pm dynamic
    set_ini "$pool" pm.max_children 8
    set_ini "$pool" pm.start_servers 2
    set_ini "$pool" pm.min_spare_servers 2
    set_ini "$pool" pm.max_spare_servers 4
    set_ini "$pool" pm.max_requests 500
    "php-fpm$v" -t 2>/dev/null || die "Pula PHP-FPM nie przechodzi testu po zmianie, sprawdź $pool."
    systemctl restart "php$v-fpm"
    echo "  pm.max_children = 8 (2 czekają gotowe, do 4 wolnych), proces odnawiany co 500 zapytań"

    # the key this session came with is there, so passwords can go
    say "SSH"
    if [ -s /root/.ssh/authorized_keys ] && grep -qE '^(ssh-|ecdsa-|sk-)' /root/.ssh/authorized_keys; then
        printf '%s\n' '# written by migrate.sh: keys only, root too (read before 50-cloud-init.conf)' \
            'PasswordAuthentication no' 'KbdInteractiveAuthentication no' 'PermitRootLogin prohibit-password' \
            > /etc/ssh/sshd_config.d/10-sanakan.conf
        sshd -t || { rm -f /etc/ssh/sshd_config.d/10-sanakan.conf; die "Konfiguracja SSH nie przeszła testu, zostaje jak była."; }
        systemctl reload ssh
        echo "  logowanie tylko kluczem: $(sshd -T | grep -iE '^(passwordauthentication|permitrootlogin)' | tr '\n' ' ')"
    else
        warn "root nie ma klucza w /root/.ssh/authorized_keys, logowanie hasłem zostaje."
    fi
}

new_restore_db() {
    . "$WORK/old-facts.sh"
    if [ "$OLD_db_type" = postgres ]; then
        pg psql -q -v ON_ERROR_STOP=1 -v db="$OLD_db_db" -v user="$OLD_db_user" <<'SQL'
DROP DATABASE IF EXISTS :"db";
CREATE DATABASE :"db" OWNER :"user";
SQL
        if ! pg pg_restore --no-owner --role="$OLD_db_user" -d "$OLD_db_db" < "$WORK/wiki.dump"; then
            warn "pg_restore zgłosił błędy (wyżej). Zwykle to tylko komentarze rozszerzeń, ale sprawdź wiki."
        fi
    else
        mysql <<SQL
DROP DATABASE IF EXISTS \`$(sql_id "$OLD_db_db")\`;
CREATE DATABASE \`$(sql_id "$OLD_db_db")\`;
GRANT ALL PRIVILEGES ON \`$(sql_id "$OLD_db_db")\`.* TO '$(sql_str "$OLD_db_user")'@'localhost';
FLUSH PRIVILEGES;
SQL
        mysql "$OLD_db_db" < "$WORK/wiki.sql"
    fi
    echo "  baza $OLD_db_db odtworzona (zrzut zostaje w $WORK)"
}

# Wiki.js listens only on the server itself, nginx passes to 127.0.0.1:3000
wiki_bind_local() {
    local c=$WIKI/config.yml
    [ -f "$c" ] || return 0
    if grep -qE '^bindIP:' "$c"; then
        sed -i -E 's/^bindIP:.*/bindIP: 127.0.0.1/' "$c"
    else
        printf '\nbindIP: 127.0.0.1\n' >> "$c"
    fi
}

new_start() {
    . "$WORK/old-facts.sh"
    local v f slow config=$SITE/inc/config.php
    v=$(php_version)

    say "Crony"
    for f in "$WORK/old-root/etc/cron.d/"sanakan-*; do
        [ -f "$f" ] || continue
        install -m 644 -o root -g root "$f" /etc/cron.d/
        echo "  /etc/cron.d/${f##*/}"
    done

    # the panel reads PHP-FPM's slow log at the path of its PHP version
    # (diagSlowLog() in inc/diag.php) unless told otherwise
    slow=$(sed -nE 's/^[[:space:]]*slowlog[[:space:]]*=[[:space:]]*//p' "/etc/php/$v/fpm/pool.d/www.conf" | tail -1)
    if [ -f "$config" ] && [ -n "$slow" ] && [ "$slow" != "/var/log/php$v-fpm.slow.log" ]; then
        if grep -qE '^[[:space:]]*const[[:space:]]+DIAG_SLOW_LOG' "$config"; then
            sed -i -E "s#^([[:space:]]*const[[:space:]]+DIAG_SLOW_LOG[[:space:]]*=[[:space:]]*)'[^']*'#\1'$slow'#" "$config"
        else
            {
                if tail -c 64 "$config" | tr -d '[:space:]' | grep -q '?>$'; then echo '<?php'; fi
                printf "\n    // PHP-FPM's slow log of PHP %s on this server (written by migrate.sh)\n    const DIAG_SLOW_LOG = '%s';\n" "$v" "$slow"
            } >> "$config"
        fi
        echo "  inc/config.php: DIAG_SLOW_LOG = '$slow'"
    fi

    say "Usługi"
    systemctl restart "php$v-fpm"
    if [ -n "$slow" ]; then
        touch "$slow"
        chmod 644 "$slow"
    fi
    nginx -t || die "nginx -t nie przeszło na nowym serwerze."
    systemctl reload nginx
    if [ -n "$OLD_wiki_unit" ]; then
        wiki_bind_local
        systemctl daemon-reload
        systemctl enable --now "${OLD_wiki_unit##*/}"
    fi
    date -Is > "$WORK/final"
}

# the status code of a page asked on this server itself, past Cloudflare
probe() {
    local h=${1#*://} code
    h=${h%%/*}
    code=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 15 --resolve "$h:443:127.0.0.1" "https://${1#*://}" 2>/dev/null || true)
    if [ -z "$code" ] || [ "$code" = 000 ]; then
        code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 --resolve "$h:80:127.0.0.1" "http://${1#*://}" 2>/dev/null || true)
    fi
    echo "${code:-000}"
}

node_arch() {
    case $(uname -m) in
        x86_64) echo x64 ;;
        aarch64) echo arm64 ;;
        *) die "Nie wiem, który Node.js pobrać dla $(uname -m)." ;;
    esac
}

# an official Node.js release in /opt, kept beside the others
node_install() {
    local dir
    dir=/opt/node-v$1-linux-$(node_arch)
    if [ ! -x "$dir/bin/node" ]; then
        curl -fsSL "https://nodejs.org/dist/v$1/node-v$1-linux-$(node_arch).tar.xz" | tar -xJ -C /opt
    fi
    echo "  $dir"
}

# the one /usr/local/bin/node points at, which the wiki's service runs
node_use() {
    local dir
    dir=/opt/node-v$1-linux-$(node_arch)
    ln -sfn "$dir/bin/node" /usr/local/bin/node
    ln -sfn "$dir/bin/npm" /usr/local/bin/npm
    echo "  wiki używa: $(/usr/local/bin/node --version)"
}

# Runs the wiki on the new server on the copy, before final: started, asked
# through nginx, its log shown, stopped. Its folder is put back as copied, so
# the test leaves nothing in its database for final to miss.
new_wiki_test() {
    . "$WORK/old-facts.sh"
    local unit=${OLD_wiki_unit##*/} started code i ok=0
    say "Próba wiki na nowym serwerze, Node $(/usr/local/bin/node --version)"
    wiki_bind_local
    rm -rf "$WIKI.pretest"
    cp -a "$WIKI" "$WIKI.pretest"
    started=$(date '+%Y-%m-%d %H:%M:%S')
    systemctl start "$unit"
    for i in $(seq 1 24); do
        sleep 5
        code=$(probe https://wiki.sanakan.pl/)
        case $code in 2*|3*) ok=1; break ;; esac
        systemctl is-active --quiet "$unit" || break
    done
    printf '  https://wiki.sanakan.pl/ odpowiada: %s\n  dziennik usługi:\n' "$code"
    journalctl -u "$unit" --since "$started" --no-pager -n 40 | sed 's/^/    /'
    systemctl stop "$unit"
    rm -rf "$WIKI"
    mv "$WIKI.pretest" "$WIKI"
    if [ $ok = 1 ]; then
        say "Wiki działa na tym Node. Folder wiki przywrócony do stanu z kopii."
    else
        warn "Wiki nie odpowiedziała. Powrót do Node ${OLD_node_version}:"
        echo "  ssh <nowy> 'ln -sfn /opt/node-${OLD_node_version}-linux-$(node_arch)/bin/node /usr/local/bin/node && ln -sfn /opt/node-${OLD_node_version}-linux-$(node_arch)/bin/npm /usr/local/bin/npm'"
        echo "  i jeszcze raz wiki-test."
    fi
}

new_verify() {
    . "$WORK/old-facts.sh"
    local u s code i
    say "Sprawdzenie nowego serwera (na nim samym, bez Cloudflare)"
    for s in nginx "php$(php_version)-fpm" cron ${OLD_wiki_unit:+${OLD_wiki_unit##*/}}; do
        printf '  %-28s %s\n' "$s" "$(systemctl is-active "$s" 2>/dev/null || true)"
    done
    for u in https://sanakan.pl/ https://sanakan.pl/state/ https://sanakan.pl/cmd/ https://sanakan.pl/i/; do
        printf '  %-28s %s\n' "$u" "$(probe "$u")"
    done
    if [ -n "$OLD_wiki_unit" ]; then
        # Wiki.js takes a while to start
        for i in $(seq 1 12); do
            code=$(probe https://wiki.sanakan.pl/)
            case $code in 2*|3*) break ;; esac
            sleep 5
        done
        printf '  %-28s %s\n' https://wiki.sanakan.pl/ "$code"
    fi
}

new_undo() {
    local unit=$1 f
    mkdir -p "$WORK/cron.d-off"
    for f in /etc/cron.d/sanakan-*; do
        [ -e "$f" ] || continue
        mv "$f" "$WORK/cron.d-off/"
        echo "  cron ${f##*/} wyłączony na nowym"
    done
    if [ -n "$unit" ]; then
        systemctl disable --now "$unit" || true
        echo "  wiki ($unit) zatrzymane na nowym"
    fi
    rm -f "$WORK/final"
}

# ---------------------------------------------------------------- this computer

# Runs a function of this script on a server. The functions go there as a
# script file, and it runs with its standard input closed, so apt and the
# others can never read the script itself.
on() {
    local host=$1 fn=$2
    shift 2
    {
        declare -p SITE WIKI WORK THUMBS
        declare -f
        echo 'set -euo pipefail'
        printf '%s' "$fn"
        [ $# -eq 0 ] || printf ' %q' "$@"
        echo
    } | ssh "$host" 'f=$(mktemp) && cat > "$f" && bash "$f" < /dev/null; r=$?; rm -f "$f"; exit $r'
}

OLD_KEYS="os arch uid php php_pkgs tz sites other_sites refs listen_ips sizes wiki wiki_unit envfiles
    node_version db_type db_host db_port db_user db_pass db_db db_storage db_error pg_version wiki_owner
    wiki_assets_backup users fail2ban ufw ufw_rules swap node_want www_dirs crontabs other_cron own_units"
NEW_KEYS="os arch uid php free prepared since final has_site"

# the facts a server sends, into the associative array named first
read_facts() {
    local -n into=$1
    local raw k v line stray=""
    raw=$(on "$2" "$3")
    # anything else on the output (a login script's greeting, a \r from a
    # Windows ssh) is set aside and shown, so it can not break the facts
    while IFS= read -r line; do
        line=${line%$'\r'}
        [ -n "$line" ] || continue
        k=${line%%=*}
        v=${line#*=}
        if [[ $line == *=* && $k =~ ^[a-z0-9_]+$ && $v =~ ^[A-Za-z0-9+/]*=*$ ]] && v=$(printf '%s' "$v" | base64 -d 2>/dev/null); then
            into[$k]=$v
        else
            stray+="$line"$'\n'
        fi
    done <<< "$raw"
    if [ -n "$stray" ]; then
        warn "$2 wypisał przy sprawdzaniu coś poza danymi (pomijam to; jeśli to tekst z .bashrc roota, lepiej go stamtąd usunąć):"
        printf '%s' "$stray" | head -10 | sed 's/^/    /' >&2
    fi
    for k in $4; do into[$k]=${into[$k]:-}; done
}

# key login, which the many connections need
can_ssh() {
    ssh -o BatchMode=yes -o ConnectTimeout=15 "$1" true 2>/dev/null && return 0
    die "Nie mogę zalogować się na $1 kluczem SSH. Połącz się raz ręcznie (ssh $1), żeby przyjąć klucz serwera, i dodaj swój klucz:
  cat ~/.ssh/id_ed25519.pub | ssh $1 'mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys'"
}

load_old() {
    can_ssh "$old"
    read_facts O "$old" old_facts "$OLD_KEYS"
    [ "${O[uid]}" = 0 ] || die "Na $old trzeba być rootem (User root w ~/.ssh/config)."
    [ -n "${O[php]}" ] || die "Na $old nie ma PHP. Na pewno to stary serwer strony?"
    O[db_local]=0
    case ${O[db_type]} in
        postgres|mysql|mariadb)
            case ${O[db_host]} in ''|localhost|127.0.0.1|::1|/*) O[db_local]=1 ;; esac ;;
    esac
    O[sqlite_file]=""
    if [ "${O[db_type]}" = sqlite ]; then
        local s=${O[db_storage]}
        [[ $s == /* ]] || s=$WIKI/$s
        case $s in "$WIKI"/*) ;; *) O[sqlite_file]=$s ;; esac
    fi
    TREES=(var/www/html)
    [ "${O[wiki]}" != 1 ] || TREES+=(var/www/wiki)
    [ "${O[wiki_assets_backup]}" != 1 ] || TREES+=(root/wiki-assets-original)
    [ -z "${O[sqlite_file]}" ] || TREES+=("${O[sqlite_file]#/}")
}

load_new() {
    can_ssh "$new"
    read_facts N "$new" new_facts "$NEW_KEYS"
    [ "${N[uid]}" = 0 ] || die "Na $new trzeba być rootem (User root w ~/.ssh/config)."
}

# the facts of the old server for the scripts on the new one, readable by root only
send_facts() {
    local k
    for k in "${!O[@]}"; do printf 'OLD_%s=%q\n' "$k" "${O[$k]}"; done \
        | ssh "$new" "mkdir -p $WORK && umask 077 && cat > $WORK/old-facts.sh"
}

block() {
    [ -n "$2" ] || return 0
    echo "  $1"
    sed 's/^/    /' <<< "$2"
}

confirm() {
    local answer
    printf '\n%s\nWpisz "tak", żeby kontynuować: ' "$1"
    read -r answer
    [ "$answer" = tak ] || die "Przerwane."
}

report() {
    say "Stary serwer ($old)"
    echo "  ${O[os]}, ${O[arch]}, PHP ${O[php]}, strefa czasu ${O[tz]:-?}"
    block "Rozmiary:" "${O[sizes]}"
    block "Strony nginx do przeniesienia (włączona nazwa, plik):" "${O[sites]}"
    block "Pliki spoza nginx, na które wskazuje konfiguracja:" "$(grep -v '^/etc/nginx/' <<< "${O[refs]}" || true)"
    block "Adresy IP wpisane w listen (na nowym trzeba je zmienić ręcznie):" "${O[listen_ips]}"
    if [ "${O[wiki]}" = 1 ]; then
        echo "  Wiki: $WIKI (${O[wiki_owner]}), usługa ${O[wiki_unit]:-NIE ZNALEZIONA}, Node ${O[node_version]:-?}"
        if [ -n "${O[db_error]}" ]; then
            warn "${O[db_error]}"
        elif [ "${O[db_type]}" = sqlite ]; then
            echo "  Baza wiki: SQLite ${O[db_storage]}"
        else
            echo "  Baza wiki: ${O[db_type]} ${O[db_db]}, użytkownik ${O[db_user]}, host ${O[db_host]:-?}$([ "${O[db_local]}" = 1 ] && echo ' (na tym serwerze, zostanie przeniesiona)' || echo ' (poza tym serwerem, zostaje)')"
            [ -z "${O[pg_version]}" ] || echo "  ${O[pg_version]}"
            case ${O[db_pass]} in '$('*) warn "Hasło bazy wiki to zmienna środowiskowa ${O[db_pass]}; użytkownika bazy na nowym ustaw ręcznie." ;; esac
        fi
    fi
    echo "  fail2ban: ${O[fail2ban]:-nie}, ufw: ${O[ufw]:-brak}, swap: ${O[swap]:-?}"

    say "Tego skrypt NIE przenosi (jeśli coś z tego jest potrzebne, przenieś ręcznie)"
    block "Inne strony nginx:" "${O[other_sites]}"
    block "Własne usługi systemd:" "${O[own_units]}"
    block "Inne pliki w /etc/cron.d:" "${O[other_cron]}"
    block "Crontaby:" "${O[crontabs]}"
    block "Inne foldery w /var/www:" "$(grep -vxE 'html|wiki' <<< "${O[www_dirs]}" || true)"
    case ${O[ufw]} in *' active'*) block "Reguły ufw starego serwera (na nowym prepare ustawia własne: SSH dla wszystkich, 443 tylko dla Cloudflare):" "${O[ufw_rules]}" ;; esac

    say "Nowy serwer ($new)"
    echo "  ${N[os]}, ${N[arch]}, wolne na /var: ${N[free]}${N[php]:+, PHP ${N[php]}}"
    [ "${O[arch]}" = "${N[arch]}" ] || warn "Inna architektura niż na starym (${O[arch]} i ${N[arch]}): moduły Node.js wiki mogą nie zadziałać."
    [ -z "${N[prepared]}" ] || echo "  prepare zrobione: ${N[prepared]}"
    [ -z "${N[since]}" ] || echo "  ostatnie copy: $(date -d "@$((N[since] + 120))" '+%Y-%m-%d %H:%M')"
    [ -z "${N[final]}" ] || echo "  final zrobione: ${N[final]}"
    if [ -n "${N[has_site]}" ] && [ -z "${N[prepared]}" ]; then
        warn "Na nowym serwerze jest już $SITE/inc, a prepare nie było robione tym skryptem."
    fi
}

full_copy() {
    say "Kopia całości: ${TREES[*]} (kropka co ~100 MB)"
    ssh "$old" "cd / && { tar -cf - --exclude=$THUMBS --checkpoint=10000 --checkpoint-action=dot --totals $(printf '%q ' "${TREES[@]}"); r=\$?; echo >&2; [ \$r -le 1 ]; }" \
        | ssh "$new" "tar -C / -xpf -"
}

# what disappeared on the old server goes away on the new one too, and what was
# changed or added there since the previous copy (by the inode change time, so
# renamed and moved files count) is sent
sync_changes() {
    local since=$1 list="cd / && find $(printf '%q ' "${TREES[@]}") -path $THUMBS -prune -o -print"
    ssh "$old" "$list" | LC_ALL=C sort > "$TMP/old"
    ssh "$new" "$list 2>/dev/null || true" | LC_ALL=C sort > "$TMP/new"
    LC_ALL=C comm -13 "$TMP/old" "$TMP/new" > "$TMP/gone"
    if [ -s "$TMP/gone" ]; then
        say "Usuwanie z nowego serwera $(wc -l < "$TMP/gone") ścieżek, których na starym już nie ma"
        head -20 "$TMP/gone" | sed 's/^/  \//'
        [ "$(wc -l < "$TMP/gone")" -le 20 ] || echo "  ..."
        ssh "$new" "cd / && xargs -d '\n' rm -rf --" < "$TMP/gone"
    fi
    say "Dosyłanie zmian od $(date -d "@$since" '+%Y-%m-%d %H:%M')"
    ssh "$old" "cd / && { find $(printf '%q ' "${TREES[@]}") -path $THUMBS -prune -o -newerct @$since -print0 | tar -cf - --null --no-recursion -T - --totals; r=\$?; [ \$r -le 1 ]; }" \
        | ssh "$new" "tar -C / -xpf -"
}

# full the first time, the changes after; the next start counts 2 minutes back
copy_site() {
    local start
    start=$(ssh "$old" 'date +%s')
    if [ -z "${N[since]}" ]; then full_copy; else sync_changes "${N[since]}"; fi
    echo $((start - 120)) | ssh "$new" "cat > $WORK/copy-since"
}

move_wiki_db() {
    [ "${O[wiki]}" = 1 ] || return 0
    case ${O[db_type]} in
        postgres|mysql|mariadb)
            if [ "${O[db_local]}" != 1 ]; then
                warn "Baza wiki jest na ${O[db_host]}, nie na starym serwerze. Zostaje, gdzie jest; nowy serwer musi mieć do niej dostęp."
                return 0
            fi ;;
        sqlite) echo "Baza wiki to SQLite, przeszła razem z plikami."; return 0 ;;
        *) warn "Nieznany typ bazy wiki '${O[db_type]}', przenieś ją ręcznie."; return 0 ;;
    esac
    say "Baza wiki (${O[db_type]} ${O[db_db]})"
    if [ "${O[db_type]}" = postgres ]; then
        ssh "$old" "cd / && runuser -u postgres -- pg_dump -Fc -d $(printf %q "${O[db_db]}")" \
            | ssh "$new" "umask 077 && cat > $WORK/wiki.dump"
    else
        ssh "$old" "mysqldump --single-transaction --routines --triggers $(printf %q "${O[db_db]}")" \
            | ssh "$new" "umask 077 && cat > $WORK/wiki.sql"
    fi
    on "$new" new_restore_db
}

step_check() {
    load_old
    load_new
    report
    if [ -z "${N[prepared]}" ]; then
        echo; echo "Dalej: ./migrate.sh prepare $old $new"
    elif [ -z "${N[final]}" ]; then
        echo; echo "Dalej: ./migrate.sh copy $old $new, a gdy wszystko gotowe: ./migrate.sh final $old $new"
    fi
}

# where a file of server/ goes on the server, the same list as in deploy.sh
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

# runs a script of the repository on a server, its standard input closed
run_file() {
    ssh "$1" 'f=$(mktemp) && cat > "$f" && bash "$f" < /dev/null; r=$?; rm -f "$f"; exit $r' < "$2"
}

CERT=/etc/ssl/cloudflare/sanakan.pem
CERT_KEY=/etc/ssl/cloudflare/sanakan.key

step_prepare() {
    local file dest
    load_old
    load_new
    report
    O[node_want]=${NODE_VERSION:-}
    if [ -n "${O[node_want]}" ]; then echo "  Wiki dostanie Node ${O[node_want]} (zostaje też ${O[node_version]} do powrotu)."; fi
    # the Cloudflare Origin Certificate the nginx sites need: given here, or
    # already on the new server
    if [ -n "${ORIGIN_CERT:-}${ORIGIN_KEY:-}" ]; then
        grep -q 'BEGIN CERTIFICATE' "${ORIGIN_CERT:-/dev/null}" 2>/dev/null || die "ORIGIN_CERT musi wskazywać plik certyfikatu Origin (-----BEGIN CERTIFICATE-----)."
        grep -q 'PRIVATE KEY' "${ORIGIN_KEY:-/dev/null}" 2>/dev/null || die "ORIGIN_KEY musi wskazywać plik klucza prywatnego (-----BEGIN PRIVATE KEY-----)."
    elif ! ssh "$new" "test -s $CERT && test -s $CERT_KEY"; then
        die "Na $new nie ma certyfikatu Origin z Cloudflare ($CERT i $CERT_KEY). Zrób go w Cloudflare: SSL/TLS → Origin Server → Create Certificate (sanakan.pl i *.sanakan.pl), zapisz certyfikat i klucz do dwóch plików poza repozytorium i podaj je:
  ORIGIN_CERT=/f/work/origin.pem ORIGIN_KEY=/f/work/origin.key ./migrate.sh prepare $old $new"
    fi
    if [ -n "$(git status --porcelain -- server/)" ]; then
        warn "W server/ są niezacommitowane zmiany; na nowy serwer pójdą pliki takie, jak są teraz na dysku."
    fi
    confirm "Na $new zostaną zainstalowane pakiety, konfiguracja nginx z repozytorium, PHP-FPM ze starego serwera i zapora, która na port 443 wpuszcza tylko Cloudflare. Stary serwer nie zmieni się."

    # the sites are the repository's, enabled by links
    O[sites]=$'default\t/etc/nginx/sites-available/default\nwiki\t/etc/nginx/sites-available/wiki'
    send_facts
    say "Konfiguracja ze starego serwera i nginx z repozytorium"
    on "$old" old_tar_config | ssh "$new" "rm -rf $WORK/old-root && mkdir -p $WORK/old-root && tar -C $WORK/old-root -xpf - && rm -rf $WORK/old-root/etc/nginx/sites-enabled"
    while read -r file; do
        dest=$(nginx_dest "$file") || continue
        ssh "$new" "mkdir -p $WORK/old-root$(dirname "$dest") && cat > $WORK/old-root$dest" < "$file"
        echo "  $file -> $dest"
    done < <(git ls-files --cached --others --exclude-standard -- server/nginx/ server/wiki/nginx-og.conf server/wiki/nginx-site.conf | sort -u)
    if [ -n "${ORIGIN_CERT:-}" ]; then
        tr -d '\r' < "$ORIGIN_CERT" | ssh "$new" "install -d -m 755 $(dirname "$CERT") && cat > $CERT && chmod 644 $CERT"
        tr -d '\r' < "$ORIGIN_KEY" | ssh "$new" "umask 077 && cat > $CERT_KEY && chmod 600 $CERT_KEY"
        echo "  certyfikat Origin -> $CERT, $CERT_KEY"
    fi
    on "$new" new_prepare
    say "Zapora"
    run_file "$new" server/ufw-cloudflare.sh
    say "Nowy serwer gotowy. Dalej: ./migrate.sh copy $old $new"
}

step_copy() {
    load_old
    load_new
    [ -n "${N[prepared]}" ] || die "Najpierw ./migrate.sh prepare $old $new"
    [ -z "${N[final]}" ] || die "final już było; nowy serwer ma swoje dane i kopia ze starego by je nadpisała."
    copy_site
    say "Skopiowane. Strona na nowym serwerze działa już pod jego adresem, ale crony i wiki ruszą dopiero w final."
    echo "Kolejne copy dośle tylko zmiany; final zrobi to samo po zatrzymaniu starego: ./migrate.sh final $old $new"
    [ "${O[wiki]}" != 1 ] || echo "Wiki na nowym serwerze sprawdzisz przed final: ./migrate.sh wiki-test $old $new"
}

step_wiki_test() {
    load_old
    load_new
    [ -n "${O[wiki_unit]}" ] || die "Na starym serwerze nie ma usługi wiki."
    [ -n "${N[prepared]}" ] && [ -n "${N[since]}" ] || die "Najpierw prepare i copy."
    [ -z "${N[final]}" ] || die "final już było, wiki działa na nowym serwerze naprawdę."
    on "$new" new_wiki_test
}

step_final() {
    load_old
    load_new
    [ -n "${N[prepared]}" ] || die "Najpierw ./migrate.sh prepare $old $new"
    [ -z "${N[final]}" ] || die "final już było (${N[final]}). Żeby powtórzyć, najpierw ./migrate.sh undo $old $new"
    confirm "Na $old zostaną wyłączone crony strony i zatrzymane wiki (strona dalej odpowiada), na $new dosłane zmiany i baza wiki, potem crony i wiki ruszą tam."
    say "Zatrzymanie na starym serwerze"
    on "$old" old_stop "${O[wiki_unit]##*/}"
    copy_site
    move_wiki_db
    send_facts
    on "$new" new_start
    on "$new" new_verify

    local ip
    ip=$(ssh -G "$new" 2>/dev/null | awk '$1 == "hostname" { print $2; exit }')
    say "Teraz w Cloudflare, jedno po drugim:"
    echo "  1. DNS: rekordy sanakan.pl i wiki.sanakan.pl (i www, jeśli wskazywał stary serwer) na $ip, dalej proxied (pomarańczowa chmurka)"
    echo "  2. Rules → Configuration Rules: włącz regułę SSL Full (strict) dla tych nazw (nowy serwer przyjmuje od Cloudflare tylko https)."
    echo "     Trybu całej strefy w SSL/TLS → Overview nie zmieniaj, alter i api na innych serwerach z niego korzystają."
    echo "Do zmiany DNS odpowiada stara strona: to, co ktoś w tym czasie wgra do galerii albo zmieni w panelu, na nowy serwer nie przejdzie."
    echo "Alias sanakan w ~/.ssh/config wskazuje już nowy serwer, deploy.sh pójdzie tam."
    echo "Gdyby coś było nie tak, a DNS jeszcze nie przełączony: ./migrate.sh undo $old $new"
}

step_tune() {
    load_new
    [ -n "${N[prepared]}" ] || die "Najpierw ./migrate.sh prepare $old $new"
    confirm "Na $new: plik swap (jeśli go nie ma), pula PHP-FPM do 8 procesów i SSH tylko kluczem."
    on "$new" new_tune
}

step_undo() {
    load_old
    can_ssh "$new"
    confirm "Crony i wiki wrócą na $old, a na $new zostaną zatrzymane. Rób to tylko, gdy DNS wciąż wskazuje stary serwer."
    say "Nowy serwer"
    on "$new" new_undo "${O[wiki_unit]##*/}"
    say "Stary serwer"
    on "$old" old_undo "${O[wiki_unit]##*/}"
    say "Stary serwer znów działa jak przed final. Kolejny final dośle zmiany od ostatniej kopii."
}

step=${1:-}
old=${2:-}
new=${3:-}
[ -n "$step" ] && [ -n "$old" ] && [ -n "$new" ] || usage
[ "$old" != "$new" ] || die "Stary i nowy serwer to ten sam cel SSH."
declare -A O=() N=()
TREES=()
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

case $step in
    check) step_check ;;
    prepare) step_prepare ;;
    copy) step_copy ;;
    wiki-test) step_wiki_test ;;
    tune) step_tune ;;
    final) step_final ;;
    undo) step_undo ;;
    *) usage ;;
esac
