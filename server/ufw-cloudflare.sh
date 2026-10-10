#!/usr/bin/env bash
# The server's firewall: SSH for everyone, port 443 only for Cloudflare, port 80
# for nobody from outside (the server itself still asks it over 127.0.0.1), so
# nothing reaches the site or the wiki past Cloudflare and its blocking. Runs
# on the server as root; migrate.sh prepare runs it, and it is run again by
# hand when Cloudflare's ranges change (https://www.cloudflare.com/ips/):
#   ssh sanakan 'bash -s' < server/ufw-cloudflare.sh
# It reads the ranges from Cloudflare itself, replaces the rules it made before
# (marked with the comment "cloudflare") and removes rules that open the web
# ports to everyone. The same ranges are also in server/nginx/sanakan-realip.conf
# and in CLOUDFLARE_RANGES of inc/diag.php.
set -euo pipefail

# commands here never read the standard input, which is this script with bash -s
ranges=$(curl -fsS --max-time 20 https://www.cloudflare.com/ips-v4 < /dev/null; echo; curl -fsS --max-time 20 https://www.cloudflare.com/ips-v6 < /dev/null)
ranges=$(printf '%s\n' "$ranges" | tr -d '\r' | sed '/^[[:space:]]*$/d')
if [ -z "$ranges" ] || printf '%s\n' "$ranges" | grep -qvE '^[0-9a-fA-F.:]+/[0-9]+$'; then
    echo "Nie udało się pobrać zakresów Cloudflare, zapora zostaje bez zmian." >&2
    exit 1
fi

if ! command -v ufw >/dev/null; then
    DEBIAN_FRONTEND=noninteractive apt-get install -y -q ufw < /dev/null
fi

ufw allow 22/tcp comment 'SSH' < /dev/null

# the rules of an earlier run, from the last so the numbers do not move
ufw status numbered < /dev/null | sed -nE 's/^\[ *([0-9]+)\].*# cloudflare$/\1/p' | sort -rn | while read -r n; do
    ufw --force delete "$n" < /dev/null > /dev/null
done
# the web ports open to everyone, as the old server had them
for rule in 80/tcp 80 443/tcp 443; do
    ufw --force delete allow "$rule" < /dev/null > /dev/null 2>&1 || true
done

while read -r range; do
    ufw allow proto tcp from "$range" to any port 443 comment 'cloudflare' < /dev/null > /dev/null
done <<< "$ranges"

ufw default deny incoming < /dev/null
ufw default allow outgoing < /dev/null
ufw --force enable < /dev/null
echo "Zapora: SSH dla wszystkich, 443 dla $(printf '%s\n' "$ranges" | wc -l) zakresów Cloudflare, 80 tylko z serwera."
ufw status < /dev/null
