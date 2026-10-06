# sanakan.pl

Website of [Sanakan](https://sanakan.pl), a Discord bot written in C#. Every page shares one dark purple HUD style, modelled on the Safeguard scanner from BLAME!. The site itself is in Polish.

| Address | What it is |
|---|---|
| `/` | Home page: the logo with the bot status, links to the commands, wiki, Waifu, Skalpelator and the API documentation (red with a padlock, as it needs a login) |
| `/cmd/` | The bot's commands, read from its API: search, modules, copyable examples, a link to every command (`/cmd/#daily`, short: `/cmd/daily`, also by an alias), marks on commands new or changed in the last 14 days; the bot's admins and devs and the panel admins also see the moderator and debug commands, marked red with a padlock |
| `/cmd/zmiany/` | History of the changes in the commands, noticed by comparing every command list with the one before |
| `/api/` | API documentation (Swagger UI) with an endpoint search, behind a Discord login; the bot's devs, admins, semi-admins and testers get in by their role, an account without access can ask for it |
| `/state/` | Public bot status: the bot's own report (Discord, database, Shinden, commands, version), availability over the last 24 hours (quarters of an hour: yellow when less than half the checks went unanswered, red from half on, blue for a planned maintenance break) and 90 days, planned breaks not counted, the Discord ping and Shinden's answer time, whether Shinden and the database answered the bot over 24 hours (from its reports), the bot API as the site sees it (its last check, availability over 24 hours and 90 days, its answer time; a failed last check makes the bot "działa, ale API bota nie odpowiada"), the outages of the last 90 days (outages less than 30 minutes apart shown as one, with how many there were and the time down without the gaps) and the notice from the panel; below, this site (checked every 10 seconds) and the other Sanakan sites (wiki, Waifu, Alter, Skalpelator, USkalpelator) with their state and 90 days. Its link preview is a picture of the current state (`/state/og.php`) |
| `/i/` | Gallery of pictures and WebM videos, behind a Discord login, with a search across all folders. Files are added with a button, by dragging them onto the page or by pasting a picture (Ctrl+V); photo metadata such as the place they were taken is removed on upload. Videos get a frame as thumbnail. Folders and picked items download as ZIP. A picture open in the viewer can be renamed (F2) or deleted (Delete) on its own, and after a change the page comes back with the same search, sorting and picture. Admins can turn pictures by 90° and change PNG, JPG and GIF files to WebP (kept only when more than 2% smaller; the original goes to the trash and its old link opens the WebP). An account can get a folder of its own, `i/users/<id>-<nick>` (in links `i/u/<random token>`, so the ID is never in one; the gallery shows it by the nick alone, with the end of the ID when two accounts share a nick, and the folder follows a new nick), and then sees only that one (and the gallery admins it): it adds pictures there, saved as WebP when that is more than 2% smaller, renames, turns and deletes them, up to 10 pictures (set per account in its panel profile), 10 MB each and 100 MB in all. A gallery admin can share a folder by a link that opens it without a login, for 1, 7 or 30 days or until it is turned off in the panel. An account without access can ask for it |
| `/admin/` | Admin panel, behind a Discord login: bot status line with the server's clock (its time zones, NTP, and how far it is from the clock of the computer looking), the notice for `/state/` and planned maintenance breaks (the status shows "do not disturb" meanwhile; the dates are picked in the Polish way, dd.mm.rrrr and 24 hours), requests for access, trying other rights for a while to see the live site as an account with them would (with or without the panel, any place in the gallery, on or off the API list, any role on the bot's server; for the admin's own session only, with a bar on every page that turns it off), gallery and API access (also the accounts with a folder of their own), the shared gallery links, recent logins with their roles on the bot's server and a column per right (gallery, own folder, panel, API), each row opening to where its rights come from and the buttons to give more and logging everyone out, trash (with a preview of what is in it), change history, gallery statistics and disk space, the availability of the site (checked every 10 seconds through Cloudflare and on the server itself, the wiki for comparison; every failure with what the server went through meanwhile and the addresses that sent most requests, from the nginx log; the scanners of the last 3 days, blocking an address in Cloudflare with one button or the scanners automatically, and the time PHP took per page), the server's resources (processor use, load, waiting for the disk and time taken by the host, memory and swap, charts of processor and memory use over 24 hours, the programs using the most memory, OPcache), server checks, the deployed version and a backup of the data as ZIP. Every account has a profile, `/admin/?konto=ID`: its roles, what it may open (given and taken there), the requests from its addresses in the last 24 hours, the devices it is logged in on (each can be logged out, or all at once), the addresses it came from in the last 30 days and the other accounts at the same ones, what it did in the gallery, its own gallery folder and its photo limit, its requests and history. The search under the title finds accounts by name, @name or ID and addresses whole or by their start, with whose they are. An address of an account of the panel, or the one the admin uses, cannot be blocked |
| `/account/` | The profile of the logged-in account, for itself only: its roles on the bot's server, what it may open on the site, the devices it is logged in on (each can be logged out, or all the others at once), what it did in the gallery and its own history; no addresses, those only the panel shows |
| `/status.php` | Bot status as JSON, used by the home page |
| `/alive/` | Where the bot POSTs its own report every minute (with `BOT_HEARTBEAT_SECRET`); while it comes the site does not ask the bot API for its state, only, after answering the bot, whether the API answers from outside (`api/alive`, at most every 50 s) |
| `/og.php?p=` | Link preview picture of a page (`cmd`, `zmiany`, `i`, `api`, `privacy`, `admin`, `account`; none for the home page) |

The top right corner of the home page logs in with Discord (`account.php`); the API button unlocks for the accounts that may read it. The home page, the API documentation, the gallery, the panel and the profile show the logged-in account there the same way (the commands, their change history, the status, the privacy notice and the 404 page do not): its avatar ringed in the colour of its role on the bot's Discord server, the name and its Safeguard level in that colour, e.g. `LV.9` for a dev or `LV.3` for a moderator. A click opens a menu in HUD corners with the profile, the gallery and the panel (these two only for the accounts that may open them), logging out (set apart by a line, back to the same page) and a line with the full role, e.g. `LV.9 DEV`, and the account ID. Only dev, admin, semi-admin and tester give anything on the site (the API documentation, and for admin and dev the private commands); the gallery has its own lists and never follows these roles. A short click on the status dot opens `/state/`; held for 3 seconds it fires the beam of the Gravitational Beam Emitter from BLAME!. Typing `netsphere` on any page (or tapping the `SAFEGUARD · LV.9` line five times) asks the Netsphere for access: the Safeguard scans for the Net Terminal Gene, does not find it and marks the visitor an illegal resident. Then its emergency terminal stays open: `pomoc` lists the commands, `status` and `ping` ask the site, `whoami` shows the account's level, `idź` goes to a page, `man` to a command, and a few words from BLAME! (`killy`, `cibo`, `sanakan`) answer with its lore (`js/netsphere.js`).

## Repository layout

| Path | Contents |
|---|---|
| `index.html`, `404.html` | Home page and the 404 page |
| `og.php`, `inc/og.php` | Link preview pictures (`og:image`) of the pages, drawn with GD: the home page the logo with the bot's live Discord status dot (kept a minute); the commands their number and modules, their change history the latest changes with the new text of what changed, new commands of the last 30 days always among them (10 minutes); the panel a radar with the bot in its centre and the Sanakan sites as blips in the colours of their last check (5 minutes); the gallery, the API, the privacy notice and the profile (an identity card of the Safeguard, everything on it unknown) a drawing without any data (a day). `state/og.php` draws the status one (availability, outages, version, the other sites) with the same code |
| `sanakan-og.png` | The home page's picture without the status dot, sent when GD cannot draw |
| `wiki-og.png` | The wiki's link preview picture, a fixed file (a fan of Pocket Waifu cards), linked from `server/wiki/head.html` |
| `cmd/`, `api/`, `state/`, `i/`, `admin/`, `account/` | Subpages |
| `status.php` | Bot status for the home page |
| `alive/` | The bot's report sent by the bot itself, kept for `inc/bot.php` |
| `inc/bot.php` | Bot API access: the bot's `api/health` (Discord connection and ping, database, Shinden, commands; the command list where a bot has no `api/health` yet), one-minute cache, 24 h check history with the ping, per day counts, outages, notice and maintenance breaks, last known command list and its changes, the moderator and debug commands fetched with the site's key |
| `inc/check-bot.php` | One bot check, run by cron every minute; every 5 minutes also the other sites, once a day it removes the thumbnails nobody looked at for 30 days |
| `inc/system.php` | The server's resources for the panel, read from Linux's `/proc` when the panel opens |
| `inc/diag.php`, `inc/check-site.php` | Availability of the site, checked by cron every 10 seconds through Cloudflare and straight on the server, with the kernel's TCP counters, the state of nginx and PHP-FPM, the use of the processor and memory and the requests from the site's nginx log; the scanners and the PHP time per page from the same log; kept 3 days in `inc/data/diag/` (left out of the backup) |
| `inc/cloudflare.php` | Blocking addresses in Cloudflare from the panel, through an IP list of the account |
| `inc/autoblock.php` | Blocking scanners in Cloudflare automatically, run by `inc/check-site.php` once the panel turned it on |
| `inc/panel-account.php`, `inc/panel-search.php` | The profile of an account and the search of the panel |
| `inc/services.php` | The Sanakan sites, this one included: whether they answer, since when, per day counts |
| `inc/auth.php` | Discord login (OAuth2), a session of a week kept in `inc/data/sessions/`, access lists, the account's roles on the bot's server (asked with the site's key, kept 10 minutes), change history |
| `inc/gallery.php` | Gallery: thumbnails (also of videos), uploads without metadata, WebP conversion, search, duplicate check, trash, renaming, rotating, ZIP downloads |
| `inc/status-card.php`, `inc/meta.php` | Bot status card and link preview tags (Open Graph) |
| `fonts/`, `css/fonts.css` | The site's fonts (Lato, Sanakan Mono, JetBrains Mono, SIL Open Font License; Sanakan Mono is Share Tech Mono with the Polish letters it lacks added), served from the site instead of Google Fonts, so no visitor's address goes to Google |
| `inc/fonts/` | Lato and Sanakan Mono (SIL Open Font License) for the preview picture of `/state/` |
| `privacy/` | Privacy notice, linked from the footer of every page |
| `inc/config.example.php` | Configuration template |
| `css/`, `js/` | Styles and scripts |
| `robots.txt` | Keeps search engines out of the gallery, the panel, the profile, `inc/` and the API documentation |
| `server/nginx/` | nginx rules: blocked `inc/`, 404 page, short links to commands, security headers, browser cache, visitors' addresses behind Cloudflare, the site's log and the state of nginx and PHP-FPM for the server itself |
| `server/wiki/` | The site's look for the wiki (Wiki.js 2, dark mode): `theme.css` and `head.html` pasted by hand into its Administration → Theme → Code Injection (CSS Override, Head HTML), `nginx-og.conf` for its nginx site (its link preview picture and purple), and in `assets/` the site's icon in the sizes Wiki.js uses, with its manifest, copied over the wiki's own `assets/` (see Wiki below) |
| `deploy.sh` | Deployment to the server over SSH |

Kept out of git:
- `inc/config.php`, which holds the Discord application secret,
- `inc/data/`, the data the site writes: access lists and requests, the roles the bot reported, status history and outages, change history, trash, the old links of pictures changed to WebP, file hashes, the time sessions are valid from, the thumbnail cache (`thumbs/`, left out of the backup),
- the pictures in `i/` (only `i/index.php` is tracked).

The panel downloads `inc/data/`, optionally with the pictures, as one ZIP (server card, "Kopia danych"). To restore it, unpack `data/` into `inc/data/` and `i/` into `i/`, then give them back to the web server: `chown -R www-data:www-data inc/data i`.

## Server

The site runs on nginx with PHP-FPM, currently Ubuntu with PHP 8.1. Required packages:

```bash
apt-get install -y php8.1-fpm php8.1-cli php8.1-gd php8.1-zip php8.1-curl webp ffmpeg
```

- `php8.1-gd` makes thumbnails and reads PNG and JPG for the change to WebP.
- `webp` (`cwebp`, `gif2webp`, `webpmux`) writes PNG and JPG as WebP (`cwebp` with `-sharp_yuv`; without it GD writes them, with colour noise along lines), converts GIFs to animated WebP and makes their thumbnails.
- `ffmpeg` takes a frame of every WebM video for its thumbnail; without it the tile loads the video itself.
- `php8.1-zip` packs folders for download; without it the ZIP buttons are not shown.
- `php8.1-cli` runs the bot check from cron.
- `php8.1-curl` runs the availability checks of the panel, several at once.

The site does not need `php8.1-mbstring` and must keep working without it: code that cuts or counts UTF-8 text checks `function_exists('mb_...')` or uses `preg` instead, as `cutText()` in `inc/auth.php` and the link preview pictures do.

### nginx

The rules are in `server/nginx/`, which `deploy.sh` does not send:

- `sanakan.conf` blocks `inc/`, which holds the configuration and data, sets up the 404 page, sends short links such as `/cmd/daily` to `/cmd/#daily` (the 404 page does the same where the rule is missing), and lets browsers keep CSS and JS for a year (every page links them with a `?v=` version, raised on each change) and pictures and videos for a day. It also keeps `i/users/`, the folders of the accounts with their IDs in the names, from being served straight from the disk, and sends their links, `i/u/<token>/<file>` (also in a subfolder, `i/u/<token>/<folder>/<file>`), to `i/index.php`; without these two rules those links answer 404 and the files are reachable by the ID. A PNG, JPG or GIF of the gallery that is not on the disk goes to `i/index.php` too, which sends the old link of a picture changed to WebP on to it.
- `sanakan-headers.conf` adds the security headers: Content-Security-Policy, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy and HSTS. A new outside resource (a script, font or picture from another domain) has to be allowed in the policy first. Scripts run only from the site's own files, never inline in a page (`script-src 'self'`), so a page needs its script in a `.js` file.

- `sanakan.conf` also takes the visitor's own address from `CF-Connecting-IP` for requests that come from Cloudflare (otherwise every log line shows Cloudflare), writes the site's own log `/var/log/nginx/sanakan-access.log`, and serves `/nginx-status` and `/fpm-status` only to the server itself, for the availability checks.
- `sanakan-log.conf` is the format of that log (one JSON line per request). `log_format` works only in the http block, so this file goes to `/etc/nginx/conf.d/` instead.

The first two go to `/etc/nginx/snippets/`, and the server block of the site includes the first one:

```nginx
include snippets/sanakan.conf;
```

```bash
scp server/nginx/sanakan.conf server/nginx/sanakan-headers.conf sanakan:/etc/nginx/snippets/
scp server/nginx/sanakan-log.conf sanakan:/etc/nginx/conf.d/
ssh sanakan 'nginx -t && systemctl reload nginx'
```

`/fpm-status` in `sanakan.conf` goes to `unix:/run/php/php8.1-fpm.sock`, the socket of Ubuntu's PHP 8.1; where the site's PHP location uses another one, change it there too.

### Wiki

The wiki (Wiki.js 2, `/var/www/wiki` on the same server, without Docker) gets the site's look by hand, from `server/wiki/`:

- `theme.css` goes into its Administration → Theme → Code Injection → CSS Override, `head.html` into Head HTML Injection. Wiki.js 2 writes its `og:image` empty and its blue `theme-color` before anything injected, and Discord takes those first ones, so nginx replaces them with the link preview picture `https://sanakan.pl/wiki-og.png` and the site's purple: `nginx-og.conf` goes into the wiki's nginx site, in the location with `proxy_pass` to Wiki.js, next to its other `proxy_set_header` lines:

```bash
scp server/wiki/nginx-og.conf sanakan:/etc/nginx/snippets/wiki-og.conf
ssh sanakan 'grep -ln "wiki.sanakan.pl" /etc/nginx/sites-enabled/*'   # the wiki's site
# there, in the location with proxy_pass:  include snippets/wiki-og.conf;
ssh sanakan 'nginx -t && systemctl reload nginx'
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
| `GALLERY_UPLOADERS` | Optional: accounts with a folder of their own in the gallery. The panel can add more; `true` lets in nobody |
| `GALLERY_PRIVATE` | Optional: accounts that may see the private folder `i/private`. The panel can add more; `PANEL_ADMINS` always can |
| `API_VIEWERS` | Accounts that may read the API documentation in `/api/`. The panel can add more, and `PANEL_ADMINS` always can; `true` lets in any Discord account |
| `BOT_APP_KEY` | Key of the site's application in the bot API, sent as `x-app-key`; it needs the Info right (Site covers it too). With it the site reads the roles of the logged-in account (`/api/User/discord/{id}/permissions`) and the moderator and debug commands (`/api/Info/commands/private`). Without it neither happens, and the panel's server card says so |
| `BOT_HEARTBEAT_SECRET` | Optional: the secret the bot sends its report to `/alive/` with (`Heartbeat` in the bot's `Config.json`: `Url` `https://sanakan.pl/alive/` with the slash at the end, the same `Secret`). The bot sends it every minute (up to 3 tries when the site does not answer). While a report is less than 90 s old the bot check uses it instead of `api/health`; once one is missing it asks `api/health` every minute until the reports come again, so the site sees the bot also when its API cannot be reached, and asks the API only when the reports stop. Without it `/alive/` answers 404 |

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

`deploy.sh` sends the files of the last commit over SSH. It never touches `inc/config.php`, `inc/data/` or the pictures in `i/`. It deletes on the server the files that were deleted from the repository since the previous deploy. It refuses to run with uncommitted changes. It leaves the commit with its date and subject in `inc/data/deployed-info`, which the panel shows. The site goes to `/var/www/html` unless another folder is given as the second argument.

## Running locally

```bash
php -d extension=gd -S 127.0.0.1:8765 -t .
```

The site is then at http://127.0.0.1:8765/. The Discord login works only with a local `DISCORD_REDIRECT_URI` in `inc/config.php` that is also listed under Redirects in the Discord application.
