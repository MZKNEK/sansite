# sanakan.pl

Website of [Sanakan](https://sanakan.pl), a Discord bot written in C#. Every page shares one dark purple HUD style, modelled on the Safeguard scanner from BLAME!. The site itself is in Polish.

| Address | What it is |
|---|---|
| `/` | Home page: the logo with the bot status, links to the commands, wiki, Waifu, Skalpelator and the API documentation (red with a padlock, as it needs a login) |
| `/cmd/` | The bot's commands, read from its API: search, modules, copyable examples, a link to every command (`/cmd/#daily`, short: `/cmd/daily`, also by an alias), marks on commands new or changed in the last 14 days; the bot's admins and devs and the panel admins also see the moderator and debug commands, marked red with a padlock |
| `/cmd/zmiany/` | History of the changes in the commands, noticed by comparing every command list with the one before |
| `/api/` | API documentation (Swagger UI) with an endpoint search, behind a Discord login; the bot's devs, admins, semi-admins and testers get in by their role, an account without access can ask for it |
| `/state/` | Public bot status: the bot's own report (Discord, database, Shinden, commands, version — the version links to `/state/wersje/`), availability over the last 24 hours (quarters of an hour: yellow when less than half the checks went unanswered, red from half on, blue for a planned maintenance break) and 90 days, planned breaks not counted, the Discord ping and Shinden's answer time, whether Shinden and the database answered the bot over 24 hours (from its reports), the bot API as the site sees it (its last check, availability over 24 hours and 90 days, its answer time; a failed last check makes the bot "działa, ale API bota nie odpowiada"), the outages of the last 90 days (outages less than 30 minutes apart shown as one, with how many there were and the time down without the gaps) and the notice from the panel; below, this site (checked every 10 seconds) and the other Sanakan sites (wiki, Waifu, Alter, Skalpelator, USkalpelator) with their state and 90 days. Its link preview is a picture of the current state (`/state/og.php`) |
| `/state/wersje/` | The bot's version history: every version it reported, from when until when it ran and for how long (`?v=<version>` opens one), and below them the older versions written in `inc/verdiff-builtin.md` (1.0.0.0-alpha on) with their date. The versions are listed in series (`1.4.10.x`) that open and close, the newest open, each version one row with its first change; a search box above shows only the versions whose number or changes have the words typed (`js/versions.js`). A version's page links the previous and the next version that have changes, also on the left and right arrow keys. The changes come from `verdiff.md` in the bot's repository (read over HTTP, `BOT_REPO_VERDIFF_URL`), matched to a version by its string, and are rendered on the page with the site's own small Markdown renderer (`inc/markdown.php`); a version the file knows opens from its row and shows its commit if the file has one, and an `@nick` of an account that logged in is drawn as its avatar and nickname. It is reached from the version on `/state/`. Its link preview is the history (`/og.php?p=wersje`), or a version's own with `&v=` |
| `/i/` | Gallery of pictures (PNG, JPG, GIF, WebP, AVIF, and HEIC written as WebP) and films (WebM, and MP4 written as WebM when smaller), behind a Discord login, with a search across all folders. Files are added with a button, by dragging them onto the page or by pasting a picture (Ctrl+V); photo metadata such as the place they were taken is removed on upload. The line of upload tips in the toolbar can be folded away with a small button, and the choice is remembered in the browser (handy on a phone). Videos get a frame as thumbnail. Folders and picked items download as ZIP. A picture open in the viewer can be renamed (F2) or deleted (Delete) on its own; after a change the page comes back with the same search and sorting, with the renamed picture open again, or with the viewer closed after a delete. A picture or film can be zoomed with the wheel (towards the pointer), dragged once zoomed, and a double click goes back to fit; a click on a film's own controls still works and a drag does not pause it. Admins can turn pictures by 90° and change PNG, JPG, GIF and still WebP files to WebP: the "Na WebP" button asks for a quality, the server makes the WebP aside and shows it next to the original, and only what the admin accepts replaces the original (which goes to the trash); a bigger result is dropped. An uploaded picture (PNG, JPG, GIF, AVIF) or film (MP4) is saved at once and changed to WebP or WebM in the background (kept when the result is smaller; otherwise the original stays; the original goes to the trash and its old link opens the new file). While a file is still waiting or converting, the gallery shows its status to the account that uploaded it, and the file's tile carries a badge; a finished one stays in that panel for five minutes. A HEIC or HEIF from a phone is always written as WebP, an AVIF is treated like a PNG. Every account with a role on the bot's server, user and up, the gallery admins too (so it stays theirs should they lose the rights), has a folder of its own, `i/users/<id>-<nick>`, made the first time it opens the gallery (in links `i/u/<random token>`, so the ID is never in one; the gallery shows it by the nick alone, with the end of the ID when two accounts share a nick, and the folder follows a new nick), and without other access sees only that one (and the gallery admins it): it creates and deletes folders there, adds pictures (saved as WebP when that comes out smaller), renames, turns and deletes them, up to 50 pictures (set per account in its panel profile), 10 MB each and 100 MB in all. The accounts on the `GALLERY_UPLOADERS` list have it blocked, and one that loses its role has no way in either; their folder and files stay. What it deletes lands in its own trash for 30 days, where it can bring it back or take the entry out of its list; that hides it only for the account, the file stays and the panel can still restore it. A gallery admin can share a folder by a link that opens it without a login, for 1, 7 or 30 days or until it is turned off in the panel. An account without access can ask for it |
| `/admin/` | Admin panel, behind a Discord login: bot status line with the server's clock (its time zones, NTP, and how far it is from the clock of the computer looking), the notice for `/state/` and planned maintenance breaks (the status shows "do not disturb" meanwhile; the dates are picked in the Polish way, dd.mm.rrrr and 24 hours), requests for access, trying other rights for a while to see the live site as an account with them would (with or without the panel, any place in the gallery, with or without the private folder, on or off the API list, any role on the bot's server, 15 minutes by default; for the admin's own session only, with a bar on every page that turns it off), gallery and API access (also the accounts with a folder of their own), the shared gallery links, recent logins with their roles on the bot's server and a column per right (gallery, own folder, panel, API), each row opening to where its rights come from and the buttons to give more and logging everyone out, trash (with a preview of what is in it), change history, gallery statistics and disk space, the availability of the site (checked every 10 seconds through Cloudflare and on the server itself, the wiki for comparison; every failure with what the server went through meanwhile and the addresses that sent most requests, from the nginx log ("Wyczyść awarie" makes the card count from that moment, e.g. after a move to a new server); the scanners of the last 3 days, blocking an address in Cloudflare with one button or the scanners automatically, and the time PHP took per page), the server's resources (processor use, load, waiting for the disk and time taken by the host, memory and swap, charts of processor and memory use over 24 hours, the programs using the most memory, OPcache), server checks, the deployed version and a backup of the data as ZIP. Every account has a profile, `/admin/?konto=ID`: its roles, what it may open (given and taken there), the requests from its addresses in the last 24 hours, the devices it is logged in on (each can be logged out, or all at once), the addresses it came from in the last 30 days and the other accounts at the same ones, what it did in the gallery, its own gallery folder and its photo limit, its requests and history. The search under the title finds accounts by name, @name or ID and addresses whole or by their start, with whose they are. An address of an account of the panel, or the one the admin uses, cannot be blocked |
| `/account/` | The profile of the logged-in account, for itself only: its roles on the bot's server, what it may open on the site, the devices it is logged in on (each can be logged out, or all the others at once), what it did in the gallery and its own history; no addresses, those only the panel shows. It can also pick the look of the HUD: the site's purple by default, a colour on the accents only, or on the whole HUD; an account with a role on the bot's server (user and up) picks the colour too (turquoise, red, orange, gold, green, purple, grey, or that of its own role, the default) |
| `/status.php` | Bot status as JSON, used by the home page |
| `/alive/` | Where the bot POSTs its own report every minute (with `BOT_HEARTBEAT_SECRET`); while it comes the site does not ask the bot API for its state, only, after answering the bot, whether the API answers from outside (`api/alive`, at most every 50 s) |
| `/og.php?p=` | Link preview picture of a page (`cmd`, `zmiany`, `wersje` with an optional `&v=<version>`, `i`, `api`, `privacy`, `admin`, `account`; none for the home page) |

The top right corner of the home page logs in with Discord (`account.php`); the API button unlocks for the accounts that may read it. The home page, the API documentation, the gallery, the panel and the profile show the logged-in account there the same way (the commands, their change history, the status, the privacy notice and the 404 page do not): its avatar ringed in the colour of its role on the bot's Discord server, the name and its Safeguard level in that colour, e.g. `LV.9` for a dev or `LV.3` for a moderator. A click opens a menu in HUD corners with the profile, the gallery and the panel (these two only for the accounts that may open them), logging out (set apart by a line, back to the same page) and a line with the full role, e.g. `LV.9 DEV`, and the account ID. Dev, admin, semi-admin and tester open the API documentation, admin and dev also the private commands; any role, user and up, gives a folder of its own in the gallery and the choice of the HUD colour. Seeing and managing the whole gallery has its own lists and never follows these roles. A short click on the status dot opens `/state/`; held for 3 seconds it fires the beam of the Gravitational Beam Emitter from BLAME!. Typing `netsphere` on any page (or tapping the `SAFEGUARD · LV.9` line five times) asks the Netsphere for access: the Safeguard scans for the Net Terminal Gene, does not find it and marks the visitor an illegal resident. Then its emergency terminal stays open: `pomoc` lists the commands, `status` and `ping` ask the site, `whoami` shows the account's level, `idź` goes to a page, `man` to a command, and a few words from BLAME! (`killy`, `cibo`, `sanakan`) answer with its lore (`js/netsphere.js`).

## Repository layout

| Path | Contents |
|---|---|
| `index.html`, `404.html` | Home page and the 404 page |
| `og.php`, `inc/og.php` | Link preview pictures (`og:image`) of the pages, drawn with GD: the home page the logo with the bot's live Discord status dot (kept a minute); the commands their number and modules, their change history the latest changes with the new text of what changed, new commands of the last 30 days always among them (10 minutes); the version history the current version, how many there were and the last ones as commits on a branch, one version its number, how many changes it brought and the first of them, or without any its place on that branch (10 minutes); the panel a radar with the bot in its centre and the Sanakan sites as blips in the colours of their last check (5 minutes); the gallery, the API, the privacy notice and the profile (an identity card of the Safeguard, everything on it unknown) a drawing without any data (a day). `state/og.php` draws the status one (availability, outages, version, and Shinden, Alter and the bot API as dots) with the same code |
| `sanakan-og.png` | The home page's picture without the status dot, sent when GD cannot draw |
| `wiki-og.png` | The wiki's link preview picture, a fixed file (a fan of Pocket Waifu cards), linked from `server/wiki/head.html` |
| `cmd/`, `api/`, `state/`, `i/`, `admin/`, `account/` | Subpages |
| `status.php` | Bot status for the home page |
| `alive/` | The bot's report sent by the bot itself, kept for `inc/bot.php` |
| `inc/bot.php` | Bot API access: the bot's `api/health` (Discord connection and ping, database, Shinden, commands; the command list where a bot has no `api/health` yet), one-minute cache, 24 h check history with the ping, per day counts, outages, notice and maintenance breaks, last known command list and its changes, the versions it reported and when they changed, the moderator and debug commands fetched with the site's key |
| `inc/check-bot.php` | One bot check, run by cron every minute; every 5 minutes also the other sites, once a day it removes the thumbnails nobody looked at for 30 days, and it changes the pictures and films uploaded since the last run to WebP or WebM in the background |
| `inc/media-worker.php` | Changes the pictures and films waiting as WebP or WebM by hand, e.g. after cron was off; normally `inc/check-bot.php` does it |
| `inc/system.php` | The server's resources for the panel, read from Linux's `/proc` when the panel opens (the processor use comes from the background check, `inc/diag.php`, so opening the panel does not wait) |
| `inc/diag.php`, `inc/check-site.php` | Availability of the site, checked by cron every 10 seconds through Cloudflare and straight on the server, with the kernel's TCP counters, the state of nginx and PHP-FPM, the use of the processor and memory and the requests from the site's nginx log; the scanners and the PHP time per page from the same log; kept 3 days in `inc/data/diag/` (left out of the backup) |
| `inc/cloudflare.php` | Blocking addresses in Cloudflare from the panel, through an IP list of the account |
| `inc/autoblock.php` | Blocking scanners in Cloudflare automatically, run by `inc/check-site.php` once the panel turned it on |
| `inc/panel-account.php`, `inc/panel-search.php` | The profile of an account and the search of the panel |
| `inc/services.php` | The Sanakan sites, this one included: whether they answer, since when, per day counts |
| `inc/auth.php` | Discord login (OAuth2), a session of a week kept in `inc/data/sessions/`, access lists, the account's roles on the bot's server (asked with the site's key, kept 10 minutes), change history |
| `inc/gallery.php` | Gallery: thumbnails (also of videos), uploads without metadata, pictures changed to WebP and films to WebM in the background, the manual change to WebP with a quality and a preview, search, duplicate check, trash, renaming, rotating, ZIP downloads |
| `inc/text.php` | Escaping, lower case, Polish plurals, byte sizes and thousands, shared by every page (`e()`, `plural()`, `formatSize()`) |
| `inc/panel-stats.php` | The gallery's numbers and the room `inc/data` takes, counted by `inc/check-bot.php` in the background and only read by the panel |
| `inc/status-card.php`, `inc/meta.php` | Bot status card and link preview tags (Open Graph) |
| `inc/verdiff.php`, `inc/verdiff-builtin.md`, `inc/markdown.php` | The bot's changelog (`verdiff.md` in its repository, read over HTTP and cached; the versions from 1.0.0.0-alpha to 1.4.10.13, which the file never described, written in `inc/verdiff-builtin.md` from the bot's commits and listed on the page even when the bot never reported them), the @nick of a known account as its avatar, and the small dependency-free Markdown renderer the version history `state/wersje/` uses |
| `fonts/`, `css/fonts.css` | The site's fonts (Lato, Sanakan Mono, JetBrains Mono, SIL Open Font License; Sanakan Mono is Share Tech Mono with the Polish letters it lacks added), served from the site instead of Google Fonts, so no visitor's address goes to Google |
| `inc/fonts/` | Lato and Sanakan Mono (SIL Open Font License) for the preview picture of `/state/` |
| `privacy/` | Privacy notice, linked from the footer of every page |
| `inc/config.example.php` | Configuration template |
| `css/`, `js/` | Styles and scripts |
| `robots.txt` | Keeps search engines out of the gallery, the panel, the profile, `inc/` and the API documentation |
| `server/nginx/` | nginx: the site's server block (https from Cloudflare with its Origin Certificate), blocked `inc/`, 404 page, short links to commands, security headers, browser cache, visitors' addresses behind Cloudflare, the site's log and the state of nginx and PHP-FPM for the server itself |
| `server/ufw-cloudflare.sh` | The server's firewall: SSH for everyone, port 443 only for Cloudflare (see Firewall below) |
| `server/wiki/` | The site's look for the wiki (Wiki.js 2, dark mode): `theme.css` and `head.html` pasted by hand into its Administration → Theme → Code Injection (CSS Override, Head HTML), `nginx-site.conf`, its nginx server block, with `nginx-og.conf` (its link preview picture and purple), and in `assets/` the site's icon in the sizes Wiki.js uses, with its manifest, copied over the wiki's own `assets/` (see Wiki below) |
| `tools/` | Development helpers, sent on the server never: `asset-versions.php` rewrites the `?v=` of every CSS and JS in the pages to the first ten characters of the file's hash, and `install-hooks.sh` makes git run it before every commit, so the version follows the content without anyone raising a number by hand |
| `tests/`, `.github/` | The dependency-free test suite (`php tests/run.php`, `php tests/lint.php`) and the CI that runs it, also sent on the server never (see Tests below) |
| `deploy.sh` | Deployment to the server over SSH |
| `migrate.sh` | Moving the site and the wiki to a new server (see Moving to a new server below) |

Kept out of git:
- `inc/config.php`, which holds the Discord application secret,
- `inc/data/`, the data the site writes: access lists and requests, the roles the bot reported, status history and outages, change history, version changes and the changelog read from the bot's repository, trash, the old links of pictures changed to WebP and of films changed to WebM, the pictures and films waiting as WebP or WebM (`media-jobs.json`), the WebP results of a manual change waiting to be accepted (`webp-previews.json`, their files in `webp-preview/`), the WebP conversion qualities and the automatic scanner blocking (`settings.json`), file hashes, the time sessions are valid from, the thumbnail cache (`thumbs/`, left out of the backup), and the gallery and `inc/data` numbers the panel shows (`panel-stats.json`, counted by cron),
- the pictures in `i/` (only `i/index.php` is tracked).

The panel downloads `inc/data/`, optionally with the pictures, as one ZIP (server card, "Kopia danych"). To restore it, unpack `data/` into `inc/data/` and `i/` into `i/`, then give them back to the web server: `chown -R www-data:www-data inc/data i`.

## Server

The site runs on nginx with PHP-FPM, currently Ubuntu with PHP 8.1. Required packages:

```bash
apt-get install -y php8.1-fpm php8.1-cli php8.1-gd php8.1-zip php8.1-curl webp ffmpeg imagemagick
```

- `php8.1-gd` makes thumbnails and reads PNG and JPG for the change to WebP.
- `webp` (`cwebp`, `gif2webp`, `webpmux`) writes PNG and JPG as WebP: a JPEG or PNG goes through `cwebp` itself, which reads it as it is and keeps its ICC colour profile, so a photo in Display P3 or Adobe RGB does not come out duller or shifted (a JPEG that has to be turned takes GD, and its profile is passed along by hand); without `cwebp` GD writes them. The quality is set per kind (JPG, PNG, GIF, AVIF/HEIC) in the panel, and an AVIF keeps its Exif and XMP out (its metadata items are emptied). It also converts GIFs to animated WebP and makes their thumbnails. An uploaded picture is saved first and changed in the background (by `inc/check-bot.php` from cron), so adding many at once does not wait, and the gallery shows the status meanwhile.
- `ffmpeg` takes a frame of every film for its thumbnail and writes an uploaded MP4 as WebM (VP9, VP8 where the build has no VP9) when that comes out smaller; the film is saved first and changed in the background (by `inc/check-bot.php` from cron), so a big upload does not wait, and the gallery shows the status meanwhile; without it the tile loads the video itself and MP4 files stay MP4.
- `imagemagick` (or `ffmpeg`) reads a HEIC or HEIF from a phone and writes it as WebP; without either, such an upload is refused with a message. `apt-get install imagemagick` pulls in libheif on Ubuntu. An AVIF is read by GD when the build has `imagecreatefromavif`, by ImageMagick or ffmpeg otherwise.
- `php8.1-zip` packs folders for download; without it the ZIP buttons are not shown.
- `php8.1-cli` runs the bot check from cron.
- `php8.1-curl` runs the availability checks of the panel, several at once.

The site does not need `php8.1-mbstring` and must keep working without it: code that cuts or counts UTF-8 text checks `function_exists('mb_...')` or uses `preg` instead, as `cutText()` in `inc/auth.php` and the link preview pictures do.

### nginx

The rules are in `server/nginx/`, which git archive leaves out of the site's files:

- `site.conf` is the site's server block (`/etc/nginx/sites-available/default`). Cloudflare asks it over https on port 443, with the Cloudflare Origin Certificate of `sanakan.pl` and `*.sanakan.pl` in `/etc/ssl/cloudflare/sanakan.pem` and `sanakan.key` (Cloudflare → SSL/TLS → Origin Server → Create Certificate). Cloudflare checks it, with SSL set to Full (strict) for this server's names only, by a configuration rule (Rules → Configuration Rules, hostname `sanakan.pl` or `wiki.sanakan.pl`): the zone's own SSL/TLS mode stays as it is, for the other subdomains on other servers. Port 80 stays for the server itself: the availability checks ask `http://127.0.0.1`. It passes PHP to `/run/php/php-fpm.sock`, the link PHP-FPM's service on Debian and Ubuntu keeps to the socket of the PHP version installed, so a newer PHP needs no change here. It hides nginx's version and refuses every path with a part starting with a dot (`.env`, `.git`), except `.well-known`.
- `sanakan.conf`, included by `site.conf`, blocks `inc/`, which holds the configuration and data, sets up the 404 page, sends short links such as `/cmd/daily` to `/cmd/#daily` (the 404 page does the same where the rule is missing), and lets browsers keep CSS and JS for a year (every page links them with a `?v=` version, the first ten characters of the file's hash, kept up to date by `tools/asset-versions.php`, which `tools/install-hooks.sh` has git run before every commit) and pictures and videos for a day. It also keeps `i/users/`, the folders of the accounts with their IDs in the names, from being served straight from the disk, and sends their links, `i/u/<token>/<file>` (also in a subfolder, `i/u/<token>/<folder>/<file>`), to `i/index.php`; without these two rules those links answer 404 and the files are reachable by the ID. A PNG, JPG, GIF, WebM or MP4 of the gallery that is not on the disk goes to `i/index.php` too, which sends the old link of a picture changed to WebP, or of a film changed to WebM, on to the new one. It writes the site's own log `/var/log/nginx/sanakan-access.log` and serves `/nginx-status` and `/fpm-status` only to the server itself, for the availability checks.
- `sanakan-headers.conf` adds the security headers: Content-Security-Policy, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy and HSTS. A new outside resource (a script, font or picture from another domain) has to be allowed in the policy first. Scripts run only from the site's own files, never inline in a page (`script-src 'self'`), so a page needs its script in a `.js` file.
- `sanakan-realip.conf` takes the visitor's own address from `CF-Connecting-IP` for requests that come from Cloudflare (otherwise every log line shows Cloudflare), for every site of the server, the wiki too.
- `sanakan-log.conf` is the format of the site's log (one JSON line per request).

Where they go (`deploy.sh` and `migrate.sh` know the same list): `site.conf` to `/etc/nginx/sites-available/default`, `sanakan.conf` and `sanakan-headers.conf` to `/etc/nginx/snippets/`, `sanakan-realip.conf` and `sanakan-log.conf`, which work only in the http block, to `/etc/nginx/conf.d/`. `migrate.sh prepare` puts them on a new server, the wiki's too (see Wiki). Later changes are sent by `deploy.sh` when it sees them changed since the previous deploy, and it runs `nginx -t` and reloads for you (rolling the files back and stopping when the test fails). By hand:

```bash
scp server/nginx/site.conf sanakan:/etc/nginx/sites-available/default
scp server/nginx/sanakan.conf server/nginx/sanakan-headers.conf sanakan:/etc/nginx/snippets/
scp server/nginx/sanakan-realip.conf server/nginx/sanakan-log.conf sanakan:/etc/nginx/conf.d/
ssh sanakan 'nginx -t && systemctl reload nginx'
```

### Firewall

Only Cloudflare reaches the web ports, so nothing gets to the site or the wiki past Cloudflare and the addresses blocked there: `server/ufw-cloudflare.sh` lets SSH in for everyone, port 443 only from Cloudflare's ranges (read from https://www.cloudflare.com/ips/ when it runs) and port 80 from nobody outside the server. `migrate.sh prepare` runs it; when Cloudflare changes its ranges it is run again, and `sanakan-realip.conf` and `CLOUDFLARE_RANGES` in `inc/diag.php` get the same list:

```bash
ssh sanakan 'bash -s' < server/ufw-cloudflare.sh
```

### Wiki

The wiki (Wiki.js 2, `/var/www/wiki` on the same server, without Docker) gets the site's look by hand, from `server/wiki/`:

- `nginx-site.conf` is the wiki's server block (`/etc/nginx/sites-available/wiki`, enabled by the link `sites-enabled/wiki`): https from Cloudflare with the same certificate as the site, passed to Wiki.js on `127.0.0.1:3000` (`bindIP: 127.0.0.1` in its `config.yml`, so it listens on the server itself only).
- `theme.css` goes into its Administration → Theme → Code Injection → CSS Override, `head.html` into Head HTML Injection. Wiki.js 2 writes its `og:image` empty and its blue `theme-color` before anything injected, and Discord takes those first ones, so nginx replaces them with the link preview picture `https://sanakan.pl/wiki-og.png` and the site's purple: `nginx-og.conf` goes to `/etc/nginx/snippets/wiki-og.conf`, which `nginx-site.conf` includes. Both are sent by `deploy.sh` like the site's rules:

```bash
scp server/wiki/nginx-site.conf sanakan:/etc/nginx/sites-available/wiki
scp server/wiki/nginx-og.conf sanakan:/etc/nginx/snippets/wiki-og.conf
ssh sanakan 'ln -sfn /etc/nginx/sites-available/wiki /etc/nginx/sites-enabled/wiki && nginx -t && systemctl reload nginx'
```

Discord keeps a preview it has made for a while; a link with something added, e.g. `https://wiki.sanakan.pl/?1`, shows the new one at once.
- `assets/` holds the site's icon in the sizes Wiki.js uses and its manifest; they go over the wiki's own files, with the originals kept in `/root/wiki-assets-original/`. A Wiki.js update brings its own icons back, so this is repeated after one:

```bash
scp -r server/wiki/assets sanakan:/tmp/wiki-assets
ssh sanakan 'set -e; cd /var/www/wiki/assets; [ -d /root/wiki-assets-original ] || { mkdir /root/wiki-assets-original; cp -r favicons manifest.json favicon.ico /root/wiki-assets-original/; }; cp -r /tmp/wiki-assets/. .; chown -R --reference=. .; rm -rf /tmp/wiki-assets; systemctl restart wiki'
```

Wiki.js keeps `/favicon.ico` in memory, hence the restart. Cloudflare keeps the old icons until they are purged (Caching → Custom Purge, the addresses under `https://wiki.sanakan.pl/_assets/favicons/`, `/_assets/manifest.json` and `/favicon.ico`).

### PHP-FPM

For the availability checks the pool shows its state and logs where a slow request is stuck. In `/etc/php/8.1/fpm/pool.d/www.conf`:

```ini
pm.status_path = /fpm-status
request_slowlog_timeout = 5s
slowlog = /var/log/php8.1-fpm.slow.log
```

The slow log is written by PHP-FPM's master process, as root. When the panel says it cannot read it, let the web server read it:

```bash
systemctl restart php8.1-fpm
touch /var/log/php8.1-fpm.slow.log && chmod 644 /var/log/php8.1-fpm.slow.log
```

Upload limit for the gallery (nginx accepts only 1 MB by default):

```nginx
# /etc/nginx/conf.d/upload-size.conf
client_max_body_size 64M;
```

The same limit goes into `/etc/php/8.1/fpm/php.ini`:

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
| `DISCORD_REDIRECT_URI` | Where Discord sends the visitor back after the login, e.g. `https://sanakan.pl/i/`. It must also be listed under Redirects in the application |
| `PANEL_ADMINS` | Accounts that may open the admin panel. This list is changed only in the file |
| `GALLERY_ADMINS` | Accounts that may view the gallery and manage its files. The panel can add more |
| `GALLERY_VIEWERS` | Accounts that may only view the gallery. The panel can add more; `true` lets in any Discord account |
| `GALLERY_UPLOADERS` | Optional: accounts whose folder of their own in the gallery is blocked. Every other account with a role on the bot's server (user and up, from `BOT_APP_KEY`), the gallery admins too, has one (50 pictures by default, set per account in its panel profile), made the first time it opens the gallery. The panel can block more; `true` blocks it for everyone |
| `GALLERY_PRIVATE` | Optional: accounts that may see the private folder `i/private`. The panel can add more; `PANEL_ADMINS` always can |
| `API_VIEWERS` | Accounts that may read the API documentation in `/api/`. The panel can add more, and `PANEL_ADMINS` always can; `true` lets in any Discord account |
| `BOT_APP_KEY` | Key of the site's application in the bot API, sent as `x-app-key`; it needs the Info right (Site covers it too). With it the site reads the roles of the logged-in account (`/api/User/discord/{id}/permissions`) and the moderator and debug commands (`/api/Info/commands/private`). Without it neither happens, and the panel's server card says so |
| `BOT_HEARTBEAT_SECRET` | Optional: the secret the bot sends its report to `/alive/` with (`Heartbeat` in the bot's `Config.json`: `Url` `https://sanakan.pl/alive/` with the slash at the end, the same `Secret`). The bot sends it every minute (up to 3 tries when the site does not answer). While a report is less than 90 s old the bot check uses it instead of `api/health`; once one is missing it asks `api/health` every minute until the reports come again, so the site sees the bot also when its API cannot be reached, and asks the API only when the reports stop. Without it `/alive/` answers 404 |
| `BOT_REPO_VERDIFF_URL` | Optional: the raw address of the bot's changelog `verdiff.md` in its repository (e.g. `https://raw.githubusercontent.com/OWNER/REPO/main/verdiff.md`), read by the version history `/state/wersje/`. A heading per version (`# 1.4.10.14`), a `Data:` line, an optional `Commit:` line and the changes in Markdown under it. The site asks for the file every 6 hours (from cron and when the page opens), every 10 minutes while the newest version the bot reported has no section in it yet (for the first 24 hours after it came), and keeps the answer, so a version the bot reported shows its changes without leaving the site. A section stays known when the file drops it (the bot's repository may keep only the current version), and for a `raw.githubusercontent.com` address cron reads the sections the site missed from the file's history on GitHub (every 6 hours while a reported version from 1.4.10.14 on has none). The older versions have their changes written in `inc/verdiff-builtin.md` and are never looked for in the file. Without it the page still lists the versions but shows no changes |

| `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_LIST` | Optional: blocking addresses in Cloudflare from the panel (below) |

Without `inc/config.php` the gallery, the API documentation and the panel stay closed. Direct links to the pictures in `i/` always work, except in `i/private/`.

### Blocking in Cloudflare

The panel can block an address in Cloudflare, so its requests never reach the server: the "Zablokuj" button next to every address of the "Dostępność strony" card, "Odblokuj" on the list of the blocked ones. It puts the address (an IPv6 one with its whole /64) on an IP list of the Cloudflare account, which one custom rule of the zone blocks. Set up once:

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

The scanners can also be blocked automatically ("Blokuj skanery w Cloudflare automatycznie" above the list of scanners; off until turned on). The cron of the availability checks then blocks, every minute, an address that asked in the last day for at least 3 different paths nobody asks for on this site (`/.env`, `/.git/…`, `/wp-login.php`, PHP files the site does not have…); a scanning tool's user agent alone is not enough. It never blocks local and Cloudflare addresses, the addresses logged-in accounts came from, the bot's address (where its reports to `/alive/` come from; the panel cannot block it either), or crawlers and research scanners whose reverse DNS name is under a known domain (Google, Bing, Apple, Yandex, Baidu, Censys, Shodan, Shadowserver… in `AUTO_BLOCK_TRUSTED`) and points back to the address, so one only calling itself Googlebot is blocked. An address unblocked in the panel is not blocked again automatically; automatic blocks come off after 30 days.

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

`deploy.sh` sends the files of the last commit over SSH. It never touches `inc/config.php`, `inc/data/` or the pictures in `i/`. It deletes on the server the files that were deleted from the repository since the previous deploy. It refuses to run with uncommitted changes. It leaves the commit with its date and subject in `inc/data/deployed-info`, which the panel shows. The nginx rules (`server/nginx/`, `server/wiki/nginx-site.conf` and `server/wiki/nginx-og.conf`) are not part of the site's files, so it sends them on their own when they changed since the previous deploy: each to its place under a temporary name, then `nginx -t`; a configuration that does not pass is rolled back and the deploy stops, so a broken file never stays behind. The wiki's theme, head and assets are still pasted by hand (see Wiki). `./deploy.sh --dry-run sanakan` reads only the deployed marker and shows what it would send, delete and reload, writing nothing. The site goes to `/var/www/html` unless another folder is given as the second argument.

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
| `tune` | Also the end of `prepare`: a 2 GB swap file when there is none (`vm.swappiness` 10), the PHP-FPM pool set to 8 processes at most (`pm.max_children`, 2 to 4 waiting idle, a process renewed every 500 requests) and SSH by key only, when root has a key |

After `final`, by hand in Cloudflare and one right after the other: the DNS records of `sanakan.pl` and `wiki.sanakan.pl` (and `www` when it named the old server) to the new address (still proxied), and the configuration rule setting SSL to Full (strict) for those names turned on (made beforehand and saved as a draft), as the new server takes only https from Cloudflare and the old one only http. The zone's SSL/TLS mode is not changed, the other subdomains on other servers use it. The `sanakan` alias in `~/.ssh/config` then has to name the new server, so `deploy.sh` goes there. Until the switch the old site answers, and what is uploaded or changed on it meanwhile does not reach the new server. Nothing is ever deleted on the old server. When the new server has another PHP version, `final` points `DIAG_SLOW_LOG` in `inc/config.php` at its slow log.

## Running locally

```bash
php -d extension=gd -S 127.0.0.1:8765 -t .
```

The site is then at http://127.0.0.1:8765/. The Discord login works only with a local `DISCORD_REDIRECT_URI` in `inc/config.php` that is also listed under Redirects in the Discord application.

## Tests

The logic that can be tested without a browser or the network has a small, dependency-free suite; no composer, so it runs on the server's PHP too:

```bash
php tests/lint.php   # php -l over every PHP file
php tests/run.php    # the cases, no network
php tests/http.php   # HTTP smoke test of the pages, no network
```

Every `tests/test_*.php` registers cases with `test('name', function () { ... })` and asserts with `assertSame()`, `assertTrue()`, `assertContains()`, `assertThrows()` (see `tests/bootstrap.php`). The bootstrap points `SITE_DATA_DIR` at a temporary folder before the code is loaded, so a test never touches `inc/data`; `tests/run.php` clears it between two cases. To cover a pure function, add a `tests/test_*.php` (the suite picks it up by name); to change the data folder of a real install, set `SITE_DATA_DIR` in `inc/config.php`. The session tests start a real session in the CLI; the GD tests run only when `gd` is loaded (CI enables it).

The network is faked through `httpRaw()` (inc/auth.php): with the `SANAKAN_HTTP` callable set, it is called instead of `file_get_contents`, so a test can script Discord, the bot API and Cloudflare. `tests/http.php` starts the real site with `php -S` and `tests/smoke-router.php`, which points the bot API at `tests/bot-stub.php` on a second server (so the single-threaded site server never asks itself) and `SITE_DATA_DIR` at a temporary folder; it then asks every page for its status and that its body has no PHP error. `BOT_API_BASE` in `inc/config.php` points the bot API elsewhere (a test bot), the live address is the default.

Covered: the text helpers and Polish plurals, the safe paths, the file names, the media sniffing and metadata stripping (JPEG/PNG/WebP/AVIF), the byte ranges, the access lists and roles, the session, the logged-in account and the test rights, the Discord login and the bot API through the fake network, the bot state (commands, days, incidents, API, dependencies, heartbeat, addresses, versions), the changelog parser, the Markdown renderer and the @nick avatars, the command and log parsers, the availability analysis and the nginx log reading, the trash, the shared links, the background conversion queue, the ZIP walk and the search, and the pages through the HTTP smoke test.

Not unit-tested: the DNS reverse lookup of the automatic blocking (`autoBlockTrustedHost()`), the external tool conversions (`cwebp`, `ffmpeg`) beyond the GD path, and a browser's JavaScript beyond `node --check`.

GitHub Actions runs the lint, the suite and the HTTP smoke test on PHP 8.1 and 8.2, and `node --check` on the scripts (`.github/workflows/ci.yml`). `tests/`, `.github/` and `tools/` are `export-ignore`, so `deploy.sh` never sends them to the server.
