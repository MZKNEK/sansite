# Server and deployment

How the site runs on its server, how it is deployed and how it moves to a new one. The rest of the documentation is listed in the [README](../README.md).

## Server

The site runs on nginx with PHP-FPM, currently Ubuntu with PHP 8.5, and the wiki on Node.js 22 (see Wiki below). The code still runs on PHP 8.1 and up, which CI checks too. Required packages (for another PHP version, its number in their names):

```bash
apt-get install -y php8.5-fpm php8.5-cli php8.5-gd php8.5-zip php8.5-curl webp ffmpeg imagemagick
```

- `php8.5-gd` makes thumbnails and reads PNG and JPG for the change to WebP.
- `webp` (`cwebp`, `gif2webp`, `webpmux`) writes PNG and JPG as WebP: a JPEG or PNG goes through `cwebp` itself, which reads it as it is and keeps its ICC colour profile, so a photo in Display P3 or Adobe RGB does not come out duller or shifted (a JPEG that has to be turned takes GD, and its profile is passed along by hand); without `cwebp` GD writes them. The quality is set per kind (JPG, PNG, GIF, AVIF/HEIC) in the panel, and an AVIF keeps its Exif and XMP out (its metadata items are emptied). It also converts GIFs to animated WebP and makes their thumbnails. An uploaded picture is saved first and changed in the background (by `inc/media-worker.php`, which the upload starts at once, or by `inc/check-bot.php` from cron), so adding many at once does not wait, and the gallery shows the status meanwhile.
- `ffmpeg` takes a frame of every film for its thumbnail and writes an uploaded MP4 as WebM (VP9, VP8 where the build has no VP9) when that comes out smaller; the film is saved first and changed in the background (by `inc/media-worker.php`, started at once, or `inc/check-bot.php` from cron), so a big upload does not wait, and the gallery shows the status meanwhile; without it the tile loads the video itself and MP4 files stay MP4.
- `imagemagick` (or `ffmpeg`) reads a HEIC or HEIF from a phone and writes it as WebP; without either, such an upload is refused with a message. `apt-get install imagemagick` pulls in libheif on Ubuntu. An AVIF is read by GD when the build has `imagecreatefromavif`, by ImageMagick or ffmpeg otherwise.
- `php8.5-zip` packs folders for download; without it the ZIP buttons are not shown.
- `php8.5-cli` runs the bot check from cron.
- `php8.5-curl` runs the availability checks of the panel, several at once.

The site does not need `php8.5-mbstring` and must keep working without it: code that cuts or counts UTF-8 text checks `function_exists('mb_...')` or uses `preg` instead, as `cutText()` in `inc/auth.php` and the link preview pictures do.

### nginx

The rules are in `server/nginx/`, which git archive leaves out of the site's files:

- `site.conf` is the site's server block (`/etc/nginx/sites-available/default`). Cloudflare asks it over https on port 443, with the Cloudflare Origin Certificate of `sanakan.pl` and `*.sanakan.pl` in `/etc/ssl/cloudflare/sanakan.pem` and `sanakan.key` (Cloudflare → SSL/TLS → Origin Server → Create Certificate). Cloudflare checks it, with SSL set to Full (strict) for this server's names only, by a configuration rule (Rules → Configuration Rules, hostname `sanakan.pl` or `wiki.sanakan.pl`): the zone's own SSL/TLS mode stays as it is, for the other subdomains on other servers. Over https it answers only `sanakan.pl` and `www.sanakan.pl` (the wiki has its own block): a first, default block refuses the handshake of any other name, so a name of another Cloudflare account pointed at this server, which the firewall cannot tell from the zone's own, does not get past the WAF and the blocking of `sanakan.pl`. For a proof that the request came from this zone and no other, Authenticated Origin Pulls with a zone-level certificate of its own (Cloudflare → SSL/TLS → Origin Server; Cloudflare's shared certificate only proves Cloudflare's network) has two lines ready, commented out in `site.conf`, to turn on once Cloudflare sends it. Port 80 stays for the server itself, with any name: the availability checks ask `http://127.0.0.1`. It passes PHP to `/run/php/php-fpm.sock`, the link PHP-FPM's service on Debian and Ubuntu keeps to the socket of the PHP version installed, so a newer PHP needs no change here. It hides nginx's version and refuses every path with a part starting with a dot (`.env`, `.git`), except `.well-known`.
- `sanakan.conf`, included by `site.conf`, blocks `inc/`, which holds the configuration and data, sets up the 404 page and the 403 and 500 ones (for an address refused, and for PHP-FPM down or too slow; a PHP page's own error answers, such as the JSON of the panel, are left as they are), sends short links such as `/cmd/daily` to `/cmd/#daily` through `cmd/index.php`, which instead shows the 401 page (or 403 to an account without the rights) where the name is one of the moderator and debug commands the visitor may not see, and which `/cmd/#<name>` asks too, once, when the name is not on the page (`cmd/search.js`; the 404 page sends a short link on itself where the rule is missing), and lets browsers keep CSS and JS for a year (every page links them with a `?v=` version, the first ten characters of the file's hash, kept up to date by `tools/asset-versions.php`, which `tools/install-hooks.sh` has git run before every commit) and pictures and videos for a day, while the static pages (`index.html`, `privacy/`, the 404, `offline.html`; the 403 and 500 pages too, the 500 never kept at all) and the service worker `sw.js` are asked for every time (`no-cache`, a 304 when nothing changed), so a browser never shows an old page that links the old CSS and JS. It also keeps `i/users/`, the folders of the accounts with their IDs in the names, from being served straight from the disk, and sends their links, `i/u/<token>/<file>` (also in a subfolder, `i/u/<token>/<folder>/<file>`), to `i/index.php`; without these two rules those links answer 404 and the files are reachable by the ID. A PNG, JPG, GIF, AVIF, WebM or MP4 of the gallery that is not on the disk goes to `i/index.php` too, which sends the old link of a picture changed to WebP, or of a film changed to WebM, on to the new one. It writes the site's own log `/var/log/nginx/sanakan-access.log` and serves `/nginx-status` and `/fpm-status` only to the server itself, for the availability checks.
- `sanakan-headers.conf` adds the security headers: Content-Security-Policy, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy and HSTS, and, for the curious, `X-Safeguard` (an easter egg; the terminal's password itself comes from PHP, `sendSafeguardHeader()`, as it is made from the site's secret). A new outside resource (a script, font or picture from another domain) has to be allowed in the policy first. Scripts run only from the site's own files, never inline in a page (`script-src 'self'`), so a page needs its script in a `.js` file.
- `sanakan-realip.conf` takes the visitor's own address from `CF-Connecting-IP` for requests that come from Cloudflare (otherwise every log line shows Cloudflare), for every site of the server, the wiki too.
- `sanakan.conf` also serves `/models/`, the models of USkalpelator's background removal (out of git, sent by `deploy.sh`; see [croppers.md](croppers.md)): their pieces, named by their content, for a year as `.bin`, which Cloudflare caches by itself, and their list `resources.json` asked for every time.
- `sanakan.conf` also serves the pictures of the bot's cards that cron mirrors into `inc/data/pw/` (`inc/pw.php`) at `/pw/`, from a location of its own with a rewrite, so the rule keeping `inc/` closed does not apply to them; with another `SITE_DATA_DIR` that rewrite has to follow it.
- `sanakan-app-headers.conf` is the same for Skalpelator and USkalpelator, with a policy of their own: a picture from any https link (the card frames are the site's own copy at `/pw/`), local files as `data:` and `blob:`, their crop worker, and for USkalpelator onnxruntime's script and WebAssembly as `blob:` (from the site's `/models/`), with `'wasm-unsafe-eval'` and, for USkalpelator alone, `'unsafe-eval'` (its background removal makes classes with `new Function`). `sanakan.conf` serves them from their folders with it, their `assets/` (hashed names) for a year with the types listed, as a module script sent as anything else does not run with nosniff.
- `sanakan-log.conf` is the format of the site's log (one JSON line per request).
- `sanakan.conf` also passes on the profile pictures of the bot's cards from Shinden's CDN at `/profilator/cards/<id>.webp` (only `cdn2.shinden.eu/profile/<id>.webp`, none of the visitor's headers sent there), as Profilator plays an animated card and needs its bytes, which the CDN lets no page read. `sanakan-cards.conf` is their cache (`/var/lib/nginx/sanakan-cards`, 256 MB at most), kept as long as the CDN says (two hours). nginx asks the DNS at `127.0.0.53` (systemd-resolved, Ubuntu's) when a card is asked for, so a server without it needs another `resolver` there.

Where they go (`deploy.sh` and `migrate.sh` know the same list): `site.conf` to `/etc/nginx/sites-available/default`, `sanakan.conf`, `sanakan-headers.conf` and `sanakan-app-headers.conf` to `/etc/nginx/snippets/`, `sanakan-realip.conf`, `sanakan-log.conf` and `sanakan-cards.conf`, which work only in the http block, to `/etc/nginx/conf.d/`. `migrate.sh prepare` puts them on a new server, the wiki's too (see Wiki). Later changes are sent by `deploy.sh` when it sees them changed since the previous deploy, and it runs `nginx -t` and reloads for you (rolling the files back and stopping when the test fails). By hand:

```bash
scp server/nginx/site.conf sanakan:/etc/nginx/sites-available/default
scp server/nginx/sanakan.conf server/nginx/sanakan-headers.conf sanakan:/etc/nginx/snippets/
scp server/nginx/sanakan-realip.conf server/nginx/sanakan-log.conf server/nginx/sanakan-cards.conf sanakan:/etc/nginx/conf.d/
ssh sanakan 'nginx -t && systemctl reload nginx'
```

### Firewall

Only Cloudflare reaches the web ports, so nothing gets to the site or the wiki past Cloudflare and the addresses blocked there: `server/ufw-cloudflare.sh` lets SSH in for everyone, port 443 only from Cloudflare's ranges (read from https://www.cloudflare.com/ips/ when it runs) and port 80 from nobody outside the server. `migrate.sh prepare` runs it; when Cloudflare changes its ranges it is run again, and `sanakan-realip.conf` and `CLOUDFLARE_RANGES` in `inc/diag.php` get the same list:

```bash
ssh sanakan 'bash -s' < server/ufw-cloudflare.sh
```

### Wiki

The wiki (Wiki.js 2 on Node.js 22, `/var/www/wiki` on the same server, without Docker) gets the site's look by hand, from `server/wiki/`:

- `nginx-site.conf` is the wiki's server block (`/etc/nginx/sites-available/wiki`, enabled by the link `sites-enabled/wiki`): https from Cloudflare with the same certificate as the site, passed to Wiki.js on `127.0.0.1:3000` (`bindIP: 127.0.0.1` in its `config.yml`, so it listens on the server itself only).
- `theme.css` goes into its Administration → Theme → Code Injection → CSS Override, `head.html` into Head HTML Injection. Wiki.js 2 writes its `og:image` empty and its blue `theme-color` before anything injected, and Discord takes those first ones, so nginx replaces them with the link preview picture `https://sanakan.pl/img/wiki-og.png` and the site's purple: `nginx-og.conf` goes to `/etc/nginx/snippets/wiki-og.conf`, which `nginx-site.conf` includes. Both are sent by `deploy.sh` like the site's rules:

```bash
scp server/wiki/nginx-site.conf sanakan:/etc/nginx/sites-available/wiki
scp server/wiki/nginx-og.conf sanakan:/etc/nginx/snippets/wiki-og.conf
ssh sanakan 'ln -sfn /etc/nginx/sites-available/wiki /etc/nginx/sites-enabled/wiki && nginx -t && systemctl reload nginx'
```

Discord keeps a preview it has made for a while; a link with something added, e.g. `https://wiki.sanakan.pl/?1`, shows the new one at once.
- `assets/` holds the site's icon in the sizes Wiki.js uses and its manifest; they go over the wiki's own files, with the originals kept in `/root/wiki-assets-original/`. `deploy.sh` sends them when they changed since the last deploy, restarts Wiki.js (it keeps `/favicon.ico` in memory) and has Cloudflare forget the old ones (below). A Wiki.js update brings its own icons back; `WIKI_FORCE=1 ./deploy.sh sanakan` sends them again without a change.

### PHP-FPM

For the availability checks the pool shows its state and logs where a slow request is stuck. In `/etc/php/8.5/fpm/pool.d/www.conf`:

```ini
pm.status_path = /fpm-status
request_slowlog_timeout = 5s
slowlog = /var/log/php8.5-fpm.slow.log
```

The slow log is written by PHP-FPM's master process, as root. When the panel says it cannot read it, let the web server read it:

```bash
systemctl restart php8.5-fpm
touch /var/log/php8.5-fpm.slow.log && chmod 644 /var/log/php8.5-fpm.slow.log
```

Upload limit for the gallery (nginx accepts only 1 MB by default):

```nginx
# /etc/nginx/conf.d/upload-size.conf
client_max_body_size 64M;
```

The same limit goes into `/etc/php/8.5/fpm/php.ini`:

```ini
upload_max_filesize = 64M
post_max_size = 64M
```

### Configuration

```bash
cp /var/www/html/inc/config.example.php /var/www/html/inc/config.php
mkdir -p /var/www/html/inc/data && chown www-data:www-data /var/www/html/inc/data
```

Fill `inc/config.php` with the Discord application details (https://discord.com/developers/applications, OAuth2 tab) and account IDs:

| Constant | Meaning |
|---|---|
| `DISCORD_CLIENT_ID`, `DISCORD_CLIENT_SECRET` | Discord application credentials |
| `DISCORD_REDIRECT_URI` | The shared Discord callback, `https://sanakan.pl/auth/discord/`. Set the same URL in `inc/config.php` and under Redirects in the Discord application, including the trailing slash. The session keeps the page or gallery folder to return to after login |
| `PANEL_ADMINS` | Accounts that may open the admin panel. This list is changed only in the file |
| `GALLERY_ADMINS` | Accounts that may view the gallery and manage its files. The panel can add more |
| `GALLERY_VIEWERS` | Accounts that may only view the gallery. The panel can add more; `true` lets in any Discord account |
| `GALLERY_UPLOADERS` | Optional: accounts whose folder of their own in the gallery is blocked. Every other account with a role on the bot's server (user and up, from `BOT_APP_KEY`), the gallery admins too, has one (200 pictures by default, set per account in its panel profile), made the first time it opens the gallery. The panel can block more; `true` blocks it for everyone |
| `GALLERY_PRIVATE` | Optional: accounts that may see the private folder `i/private`. The panel can add more; `PANEL_ADMINS` always can |
| `API_VIEWERS` | Accounts that may read the API documentation in `/api/`. The panel can add more, and `PANEL_ADMINS` always can; `true` lets in any Discord account |
| `BOT_APP_KEY` | Key of the site's application in the bot API, sent as `x-app-key`; it needs the Info right (Site covers it too). With it the site reads the roles of the logged-in account (`/api/User/discord/{id}/permissions`) and the moderator and debug commands (`/api/Info/commands/private`). Without it neither happens, and the panel's server card says so |
| `BOT_HEARTBEAT_SECRET` | Optional: the secret the bot sends its report to `/alive/` with (`Heartbeat` in the bot's `Config.json`: `Url` `https://sanakan.pl/alive/` with the slash at the end, the same `Secret`). The bot sends it every minute (up to 3 tries when the site does not answer). While a report is less than 90 s old the bot check uses it instead of `api/health`; once one is missing it asks `api/health` every minute until the reports come again, so the site sees the bot also when its API cannot be reached, and asks the API only when the reports stop. Without it `/alive/` answers 404 |
| `BOT_REPO_VERDIFF_URL` | Optional: the raw address of the bot's changelog `verdiff.md` in its repository (e.g. `https://raw.githubusercontent.com/OWNER/REPO/main/verdiff.md`), read by the version history `/state/versions/`. A heading per version (`# 1.4.10.14`), a `Data:` line, an optional `Commit:` line and the changes in Markdown under it. The site asks for the file every 6 hours (from cron and when the page opens), every 10 minutes while the newest version the bot reported has no section in it yet (for the first 24 hours after it came), and keeps the answer, so a version the bot reported shows its changes without leaving the site. A section stays known when the file drops it (the bot's repository may keep only the current version), and for a `raw.githubusercontent.com` address cron reads the sections the site missed from the file's history on GitHub (every 6 hours while a reported version from 1.4.10.14 on has none). The older versions have their changes written in `inc/verdiff-builtin.md` and are never looked for in the file. Without it the page still lists the versions but shows no changes |

| `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_LIST` | Optional: blocking addresses in Cloudflare from the panel (below) |
| `CLOUDFLARE_ZONE_ID` | Optional: the zone of `sanakan.pl`, for `deploy.sh` to purge the files it changed from Cloudflare's cache (below) |

Without `inc/config.php` the gallery, the API documentation and the panel stay closed. Direct links to the pictures in `i/` always work, except in `i/private/`.

### Blocking in Cloudflare

The panel can block an address in Cloudflare, so its requests never reach the server: the "Zablokuj" button next to every address of the "Dostępność strony" card, "Odblokuj" on the list of the blocked ones. It puts the address (an IPv6 one with its whole /64) on an IP list of the Cloudflare account, which one custom rule of the zone blocks. Cloudflare makes such a change in the background, so the panel answers at once and shows it above its pages as waiting ("Oczekuje na potwierdzenie") until Cloudflare confirms it, asking every few seconds in short requests of its own (cron asks too, every minute, with the panel shut); the history notes it only once done, and one not confirmed in 5 minutes is reported as such. Set up once:

1. Cloudflare, account home → Manage Account → Configurations → Lists → Create new list: type IP, name `sanakan_blokada`.
2. Domain sanakan.pl → Security → Security rules (or WAF → Custom rules) → Create rule, expression `(ip.src in $sanakan_blokada)`, action Block.
3. My Profile → API Tokens → Create Token → Custom token, permission Account → Account Filter Lists → Edit, for the account of the domain.
4. In `inc/config.php` the token, the account ID (domain overview, right column) and the name of the list:

```php
const CLOUDFLARE_API_TOKEN = '...';
const CLOUDFLARE_ACCOUNT_ID = '...';
const CLOUDFLARE_LIST = 'sanakan_blokada';
```

The "Ustawienie" list of the card says whether the panel reaches the list.

Cloudflare limits how fast its API may be asked and answers too many requests with "Please wait and consider throttling your request speed" (HTTP 429, error 971). So the site asks it as little as it can (`inc/cloudflare.php`): the list is read at most every 2 minutes, not at every opening of the panel or of an account's profile, and again right after a change of its own; all the addresses of one change go in one request; whether Cloudflare has made a change is asked after 0.5, 1 and 1.5 seconds and then every 2 (10 times at most); and after such an answer nothing is asked for 5 minutes (the panel says until when, still showing the list read last).

The scanners can also be blocked automatically ("Blokuj skanery w Cloudflare automatycznie" above the list of scanners; off until turned on). The cron of the availability checks then blocks, every minute, at most 5 at once in one change of the list, an address that asked in the last day for at least 3 different paths nobody asks for on this site (`/.env`, `/.git/…`, `/wp-login.php`, PHP files the site does not have…); a scanning tool's user agent alone is not enough. It never blocks local and Cloudflare addresses, the addresses logged-in accounts came from, the bot's address (where its reports to `/alive/` come from; the panel cannot block it either), or crawlers and research scanners whose reverse DNS name is under a known domain (Google, Bing, Apple, Yandex, Baidu, Censys, Shodan, Shadowserver… in `AUTO_BLOCK_TRUSTED`) and points back to the address, so one only calling itself Googlebot is blocked. An address unblocked in the panel is not blocked again automatically; automatic blocks come off after 30 days, all of a day in one change. While Cloudflare asks to slow down, the automatic blocking waits.

### Cron

A bot check every minute, so the availability history on `/state/` has no gaps:

```bash
echo '* * * * * www-data php /var/www/html/inc/check-bot.php > /dev/null 2>&1' > /etc/cron.d/sanakan-status
```

The panel shows a warning when this check has not run for 5 minutes.

The availability checks of the site, a run a minute doing a round every 10 seconds:

```bash
echo '* * * * * www-data php /var/www/html/inc/check-site.php > /dev/null 2>&1' > /etc/cron.d/sanakan-site
```

The "Dostępność strony" card of the panel lists what is still missing on the server (curl, the log, the state pages, the slow log).

The cleanup of the gallery's data, its own job every 10 minutes, so it neither waits on the bot checks nor holds them up:

```bash
echo '*/10 * * * * www-data /usr/bin/systemd-cat -t sanakan-maintenance php /var/www/html/inc/run-maintenance.php' > /etc/cron.d/sanakan-maintenance
```

It finishes or undoes a move to the trash or a restore cut short (each is noted in `inc/data/operations/` before it starts; one that is unclear, the file in both places or in neither, is only reported), then deletes the trash entries past 30 days, the oldest first, for at most 20 seconds a run; a big folder goes in parts, and is no longer restorable once begun. With the time left it deletes the sessions without an account or a shared folder unused for an hour (a login given up, a form sent anonymously), which PHP would otherwise keep a week. Folders in the trash without an entry are reported, never deleted. `inc/data/maintenance.json` keeps the last run; the panel shows it at the trash and warns when no run went well for 2 hours. By hand: `sudo -u www-data php /var/www/html/inc/run-maintenance.php --dry-run` says what it would do.

## Deployment

sanakan.pl goes through Cloudflare, which passes only web traffic, so SSH needs the server's own address. A host alias in `~/.ssh/config` keeps it in one place:

```
Host sanakan
    HostName <server IP>
    User root
```

With an SSH key (`ssh-keygen -t ed25519`, the `.pub` line added to `/root/.ssh/authorized_keys` on the server) nothing asks for a password. A few wrong passwords in a row can get the address banned for a while.

```bash
./deploy.sh sanakan
```

`deploy.sh` sends the files of the last commit over SSH. It never touches `inc/config.php`, `inc/data/` or the pictures in `i/`. It deletes on the server the files that were deleted from the repository since the previous deploy. It refuses to run with uncommitted changes. It leaves the commit with its date and subject in `inc/data/deployed-info`, which the panel shows. The nginx rules (`server/nginx/`, `server/wiki/nginx-site.conf` and `server/wiki/nginx-og.conf`) are not part of the site's files, so it sends them on their own when they changed since the previous deploy: each to its place under a temporary name, then `nginx -t`; a configuration that does not pass is rolled back and the deploy stops, so a broken file never stays behind. The wiki's icons (`server/wiki/assets/`) go the same way when they changed, with Wiki.js restarted (see Wiki); its theme and head are still pasted by hand. Then Cloudflare forgets the pictures, icons, fonts and the manifest the deploy changed or deleted, the wiki's icons too, so they show at once rather than after the day they are kept (the CSS and JS get a new `?v=` instead): the server runs `inc/purge-cache.php` as `www-data` with the token of `inc/config.php`, which needs `CLOUDFLARE_ZONE_ID` and, on the token, the permission Zone → Cache Purge → Purge for `sanakan.pl` (My Profile → API Tokens → the token → Edit). Without them it says so and the deploy goes on; a failed purge only warns. `./deploy.sh --dry-run sanakan` reads only the deployed marker and shows what it would send, delete and reload, writing nothing. The site goes to `/var/www/html` unless another folder is given as the second argument.

`./deploy.sh --offline sanakan` is for an update that changes the data, with nothing writing meanwhile. Before any file goes it takes the cron jobs (`sanakan-status`, `sanakan-site`, `sanakan-maintenance`) out of `/etc/cron.d` into `/root/sanakan-paused/`, waits up to 20 minutes for their runs to finish, stops PHP-FPM (letting its requests finish first) and backs up `inc/data` and `i/` without thumbnails, diag, the card pictures and lock files to `/root/sansite-backup-<date>.tar`, refusing when the disk would keep less than 1 GiB free. After the files it runs, as `www-data`, `run-maintenance.php --inventory` and `migrate-gallery.php --dry-run`, asks before `migrate-gallery.php` (`--yes` does not ask; a release without the migrator skips it), runs maintenance once, which has to exit 0, then starts PHP-FPM and puts the cron jobs back (with `sanakan-maintenance` from the Cron section added when the server had none) and runs the checks of `--check`. `./deploy.sh --check sanakan` only reports, changing nothing: `inc/data` and `i/` on one filesystem, the folders the site writes (`locks`, `operations`, `conversion-staging`, `webp-preview`, `trash`) owned and writable by `www-data`, the cleanup's cron job, `systemd-cat` and a persistent journal, `client_max_body_size` of nginx and the upload, memory and time limits of PHP-FPM with its pool's overrides, its `gd` and `zip`, the conversion tools, that the newest backup reads whole, and over HTTPS (`SITE_URL`, `https://sanakan.pl` by default) that `inc/` and `i/users/` stay closed while the site and the gallery answer. A failure before the files are sent resumes everything on the old code; a later one leaves the site and the cron jobs stopped for a look, and `./deploy.sh --resume sanakan` starts them again. The site is down for the whole run, and the availability checks are off with it.

### Adding a folder to the gallery

`tools/gallery-upload.sh` sends a folder of the local `i/` to the same path in the gallery on the server, with its subfolders, the folders missing there made:

```sh
tools/gallery-upload.sh Anime/2026              # i/Anime/2026 to sanakan
tools/gallery-upload.sh --dry-run Anime/2026    # only list what would be sent
tools/gallery-upload.sh --webp Anime/2026       # PNG, JPG and GIF to WebP, MP4 to WebM, in the background
tools/gallery-upload.sh Anime/2026 sanakan-new /var/www/html
```

It first asks the server which files it lacks and sends only those, so it can run again after more were added; a file the server has, also as the WebP or WebM it was changed to, is skipped, never added under another name. The files go over SSH (tar, as root) into a temporary folder in `inc/data/`, removed at the end, and `inc/gallery-import.php` adds them as `www-data` the way an upload by a gallery admin does: the same checks of the name and the content and decoder limits, the metadata stripped, a HEIC written as WebP, a line in the history for each file and folder (by `skrypt`), and without the limits of the accounts' folders. An account's own folder (`users/<id>-<nick>`) is never made, only added to, and there pictures are always changed to WebP. Hidden files and the gallery's `index.php` are left out. The server needs a release with `inc/gallery-import.php`.

### Gallery limits and the data they protect

Defaults are 200 files, 10 MiB per file, 100 MiB active and 50 MiB trash. The panel permits up to 10,000 files, 128 MiB per file, 1000 MiB active and 1000 MiB trash. Each account can inherit or override each field; reset preserves a growing revision, and stale forms return 409. Hidden trash counts. Lowering quotas never deletes existing data. Panel administrators bypass account quotas only for uploads, retaining transport and decoder limits. Other operations use the owner's fresh quotas, including cron. The account profile shows usage and effective values without edit controls.

The agreed server budget is advisory: 4 GB RAM, 2 CPU, 32 GB free disk and about 25 GB for galleries, about 100 accounts and potentially 1000. Account allowances do not reserve disk. One background/manual encoder runs at a time (synchronous HEIC uploads use the same lock). Temporary conversion admission has a 1 GiB budget and 100 MiB free-space headroom plus copies, configurable with `GALLERY_STAGING_MAX_BYTES`. Keep data and gallery on the same filesystem. Maintenance cleans inactive staging files, preserves journal-owned artifacts and builds paged usage snapshots for the panel. Decoder limits remain independent of MiB quotas.

The documented nginx and PHP upload limits remain 64M. The UI reserves 1 MiB for multipart overhead; a policy of 128 MiB cannot be uploaded through a 64M transport. Optional `GALLERY_PROXY_BODY_BYTES` records a verified proxy limit without reconfiguring it. ZIP is best effort and does not lock gallery mutations; changing a source can fail a download, with a retry message. Authorization and cleanup of incomplete archives remain enforced.

Inspect maintenance errors with `journalctl -t sanakan-maintenance`; configure persistent journald storage and normal journal rotation on the server. Its CLI reports errors on stderr and exits 1, including a failed final report write. `--dry-run` may create stable lock files, but does not change user files, indexes, journals or success state. Never delete lock files while writers run.

## Moving to a new server

`migrate.sh` moves the site and the wiki from one server to another over SSH. Everything goes through the computer running it (`ssh old … | ssh new …`), so the servers need no keys to each other, but both need key login as root (it opens many connections). It runs in steps, each with the two SSH targets, best host aliases in `~/.ssh/config` (e.g. `sanakan` and `sanakan-new`):

| Step | What it does |
|---|---|
| `./migrate.sh check sanakan sanakan-new` | Reads both servers, changing nothing: their system, PHP and sizes, the nginx sites that move (the one including `snippets/sanakan.conf` and the wiki's), the certificates they point at, the wiki's service, Node.js and database, and what it does **not** move (other nginx sites, own systemd services, other cron files and crontabs, other folders in `/var/www`, ufw rules) |
| `prepare` | Sets up the new server: the packages of the Server section, the PHP modules the old one had in the new PHP version, nginx with the rules of this repository (the two sites, the snippets, `conf.d/`; the old server's own files of `snippets/` and `conf.d/` too, its `nginx.conf` only when it was changed from Ubuntu's), the Cloudflare Origin Certificate (given as `ORIGIN_CERT=… ORIGIN_KEY=… ./migrate.sh prepare …`, from two files kept outside the repository, or already in `/etc/ssl/cloudflare/`; without it `prepare` stops before changing anything), the firewall (`server/ufw-cloudflare.sh`), the old PHP-FPM pool and the changes of the old `php.ini` against its stock one (in `conf.d/99-sanakan-migrated.ini`), all with the PHP version and socket paths changed, Node.js of the wiki's exact version from nodejs.org in `/opt` (with `NODE_VERSION=22 ./migrate.sh prepare …` also the newest 22.x, which the wiki then runs on; `/usr/local/bin/node` points at the one used, the old one stays beside it as the way back), the wiki's service (not started), its database server and user, fail2ban and the time zone. The old configuration stays in `/root/sanakan-migration/old-root/` on the new server, and the new server's own nginx in `nginx-fresh/` |
| `copy` | Copies `/var/www/html` (with `inc/config.php`, `inc/data/` and the gallery, without the thumbnail cache) and `/var/www/wiki` while the old server keeps running. Run again, it sends only what changed since (by the files' change time) and deletes what is gone from the old server |
| `wiki-test` | Runs the wiki on the new server on the copy before `final`: starts its service, asks it through nginx on the server itself, shows its log, stops it and puts its folder back as copied, so the test leaves nothing in its SQLite database. When it does not answer, pointing `/usr/local/bin/node` back at the old Node.js is the way back |
| `final` | Moves the sanakan cron files of the old server aside to `/root/sanakan-migration/cron.d-off/` and stops the wiki there (the old site keeps answering), sends the last changes, dumps the wiki's database (a PostgreSQL or MySQL one; SQLite goes with the files) and restores it on the new server, installs the cron files there, starts the wiki (listening on `127.0.0.1` only) and asks the pages on the new server itself, past Cloudflare |
| `undo` | Before the DNS switch only: the crons and the wiki run on the old server again, the new one stops them |
| `tune` | Also the end of `prepare`: a 2 GB swap file when there is none (`vm.swappiness` 10), the PHP-FPM pool set to 8 processes at most (`pm.max_children`, 2 to 4 waiting idle, a process renewed every 500 requests) and SSH by key only, when root has a key, except for the `ubuntu` account, which keeps its password as the way in should the keys be lost (`Match User ubuntu` in `/etc/ssh/sshd_config.d/10-sanakan.conf`; fail2ban bans the addresses guessing it, and `tune` warns when the account or its password is missing) |

After `final`, by hand in Cloudflare and one right after the other: the DNS records of `sanakan.pl` and `wiki.sanakan.pl` (and `www` when it named the old server) to the new address (still proxied), and the configuration rule setting SSL to Full (strict) for those names turned on (made beforehand and saved as a draft), as the new server takes only https from Cloudflare and the old one only http. The zone's SSL/TLS mode is not changed, the other subdomains on other servers use it. The `sanakan` alias in `~/.ssh/config` then has to name the new server, so `deploy.sh` goes there. Until the switch the old site answers, and what is uploaded or changed on it meanwhile does not reach the new server. Nothing is ever deleted on the old server. When the new server has another PHP version, `final` points `DIAG_SLOW_LOG` in `inc/config.php` at its slow log.
