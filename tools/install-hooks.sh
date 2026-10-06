#!/usr/bin/env bash
# Makes git run tools/asset-versions.php before every commit, so the "?v=" of a
# stylesheet or script follows its content without anyone raising the number by
# hand. Run once per clone:
#   sh tools/install-hooks.sh
# The hook stages only the files the tool rewrote, so nothing else is added.
set -euo pipefail
cd "$(dirname "$0")/.."

mkdir -p .git/hooks
cat > .git/hooks/pre-commit <<'HOOK'
#!/usr/bin/env sh
# raise the "?v=" asset versions and stage just those files, then let the commit go on
if ! command -v php >/dev/null 2>&1; then
    echo "asset-versions: nie ma php w PATH, wersje ?v= zostają bez zmian." >&2
    exit 0
fi
changed=$(php tools/asset-versions.php) || exit 1
if [ -n "$changed" ]; then
    echo "$changed" | while IFS= read -r file; do
        git add -- "$file"
    done
fi
HOOK
chmod +x .git/hooks/pre-commit
echo "Zainstalowano .git/hooks/pre-commit."
