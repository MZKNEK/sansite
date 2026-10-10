#!/usr/bin/env bash
# Builds Skalpelator and USkalpelator from their sources in apps/ into the
# site's skalpel/ and uskalpel/, which are committed and deployed with the rest
# of the site (the server needs no Node.js). Run it after changing an app and
# commit the sources together with their build; CI builds them again and fails
# when the committed build differs from the sources.
#   tools/build-apps.sh             both
#   tools/build-apps.sh skalpel     one of them
set -euo pipefail
cd "$(dirname "$0")/.."

apps=("$@")
[ ${#apps[@]} -gt 0 ] || apps=(skalpel uskalpel)

for app in "${apps[@]}"; do
    [ -f "apps/$app/package.json" ] || { echo "Nie ma aplikacji apps/$app." >&2; exit 1; }
    echo "== $app"
    # npm ci installs exactly what package-lock.json says, so the build is the
    # same on every computer
    (cd "apps/$app" && npm ci --no-audit --no-fund && npm run build)
done
