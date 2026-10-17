# Development

Running the site locally and its tests. The other documents are listed in the [README](../README.md).

## Running locally

The whole site, without the wiki, runs locally with made-up data, to look at and test (Git Bash or any shell with PHP 8.1 or newer):

```bash
tools/preview.sh              # http://127.0.0.1:8765/, or tools/preview.sh 8080
tools/preview-stop.sh         # stops it; with --clean also removes its data
tools/preview.sh --reset      # the made-up data from scratch
```

Direct event and skin previews, no login needed: `http://127.0.0.1:8765/?event=<key>` sets the simulated date (`safeguard-date`) to the next day of that event before the site scripts run, a day without other events where one exists. Keys: `xmas`, `newyear`, `easter`, `aprilfools`, `birthday`, `halloween`, `friday13`, `sakura`, `perseids`, `fireflies` (the keys of `SAFEGUARD_SEASONAL` in `inc/auth/finds.php`, so a new event works here at once); `?event=none` goes back to today. The date stays in the host's browser storage, and different hosts (`127.0.0.1`, `localhost`) keep their own, so two events can stay open at once. `?skin=<key>` previews any skin of `inc/skin-catalog.php` (`teto`, `miku`, `echidna`, `marisa`, `fauna`, `padoru`, `reisen`, `peko`, `ayame`, `albedo`, `tomie`, `lain`, `cibo`, `killy`, `march7`, `shogun`, `suisei`, `hoshino`, `firefly`, `yoimiya`) for that page visit, without saving an account preference; both combine, e.g. `?event=fireflies&skin=yoimiya`.

It runs `php -S` with `tools/preview/router.php`, which does what nginx does on the server (`inc/` and the dot files kept out, the gallery's links of the accounts' folders, the short links of the commands, the security headers of `server/nginx/`, the croppers' own), and a made-up bot API next to it on the following port (`tools/preview/bot-api.php`: the roles, the commands, `api/health`). `tools/preview/cron.php` runs the site's own `inc/check-bot.php` every minute, so the status keeps its history and uploaded pictures are changed to WebP. The data lives in `.preview/data` (with the logs in `.preview/`, out of git), filled the first time by `tools/preview/seed.php`: 24 hours of checks with an outage, 120 days of availability with a planned break, the versions, a week of changes in the commands, the other sites, a notice saying the data is made up, an access request and a few pictures in the gallery (`i/Podgląd`). `/__login` (where every login of the site goes in the preview, never to Discord) logs in as one of the accounts of `tools/preview/data.php`: dev with the panel and the whole gallery, admin, moderator, user, and a guest without a role. Its settings are `tools/preview/config.php` instead of `inc/config.php`, so it refuses to start while there is one. `npm run dev` of a cropper (apps/) takes the site from it.

## Tests

The logic that can be tested without a browser or the network has a small, dependency-free suite; no composer, so it runs on the server's PHP too:

```bash
php tests/lint.php   # php -l over every PHP file
php tests/run.php    # the cases, no network
php tests/http.php   # HTTP smoke test of the pages, no network
node --test apps/*/test/*.test.mjs   # the croppers' code that runs without a page
```

Every `tests/test_*.php` registers cases with `test('name', function () { ... })` and asserts with `assertSame()`, `assertTrue()`, `assertContains()`, `assertThrows()` (see `tests/bootstrap.php`). The bootstrap points `SITE_DATA_DIR` at a temporary folder before the code is loaded, so a test never touches `inc/data`; `tests/run.php` clears it between two cases. To cover a pure function, add a `tests/test_*.php` (the suite picks it up by name); to change the data folder of a real install, set `SITE_DATA_DIR` in `inc/config.php`. The session tests start a real session in the CLI; the GD and ZIP tests run only when `gd` and `zip` are loaded, and in CI (where `CI` is set) a missing one fails them instead of leaving them out.

The network is faked through `httpRaw()` (inc/auth.php): with the `SANAKAN_HTTP` callable set, it is called instead of `file_get_contents`, so a test can script Discord, the bot API and Cloudflare. `tests/http.php` starts the real site with `php -S` and `tests/smoke-router.php`, which points the bot API at `tests/bot-stub.php` on a second server (so the single-threaded site server never asks itself) and `SITE_DATA_DIR` at a temporary folder; it then asks every page for its status and that its body has no PHP error. `BOT_API_BASE` in `inc/config.php` points the bot API elsewhere (a test bot), the live address is the default.

Covered: the text helpers and Polish plurals, the safe paths, the file names, the media sniffing and metadata stripping (JPEG/PNG/WebP/AVIF), the byte ranges, the access lists and roles, the session, the logged-in account and the test rights, the Discord login and the bot API through the fake network, the bot state (commands, days, incidents, API, dependencies, heartbeat, addresses, versions), the changelog parser, the Markdown renderer and the @nick avatars, the command and log parsers, the availability analysis and the nginx log reading, the trash, its budget and the limits of a restore, the cleanup and the log of operations, the shared links, the background conversion queue, the ZIP walk and a real ZIP by the visitor's rights, the limits of pictures by their headers, the search, the storage of `inc/data` (whole writes, broken files, several processes at once with a reader), how the code is split (`tests/test_modules.php`: every function in one place, the modules only defining things, a whole document written over only where that is meant), the headers of the thumbnails and the pages through the HTTP smoke test, and the croppers' blob: addresses, decoded pictures and frames through `node --test`.

Not unit-tested: the DNS reverse lookup of the automatic blocking (`autoBlockTrustedHost()`), the external tool conversions (`cwebp`, `ffmpeg`) beyond the GD path, and the croppers' gestures beyond their JavaScript unit tests. `tests/browser.mjs` runs the site in Chromium with disposable PHP data, including the home page and skin regressions in `tests/home-browser.mjs` and `tests/skins-browser.mjs`, and seasonal date boundaries, catches, retries and unlocks in `tests/seasonal-browser.mjs`, (`BROWSER_ONLY=seasonal,theme node tests/browser.mjs` runs only the named parts, of `storage`, `core`, `home`, `skins`, `seasonal`, `custom`, `picker` and `theme`, while working on them; the whole suite before a commit), and nginx itself (the headers on the server are checked after a deploy).

GitHub Actions runs the lint, the suite and the HTTP smoke test on PHP 8.5, the server's, and on 8.1, the oldest the code keeps working on (a deprecation notice of PHP fails the lint, the suite and the smoke test, so a newer PHP's are seen before the server shows them), and `node --check` on the scripts and the croppers' tests with Node.js 22, which also builds Skalpelator and USkalpelator and checks the build matches the one committed (`.github/workflows/ci.yml`). `tests/`, `.github/`, `tools/` and `apps/` are `export-ignore`, so `deploy.sh` never sends them to the server.
