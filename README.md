# sanakan.pl

Website of [Sanakan](https://sanakan.pl), a Discord bot written in C#. Every page shares one dark purple HUD style, modelled on the Safeguard scanner from BLAME!. The site itself is in Polish.

| Address | What it is |
|---|---|
| `/` | Home page: the logo with the bot status, links to the commands, wiki, Waifu, Skalpelator and the API documentation (red with a padlock, as it needs a login) |
| `/cmd/` | The bot's commands, read from its API: search, modules, copyable examples, a link to every command (`/cmd/#daily`, short: `/cmd/daily`, also by an alias), marks on commands new or changed in the last 14 days; the bot's admins and devs and the panel admins also see the moderator and debug commands, marked red with a padlock |
| `/cmd/zmiany/` | History of the changes in the commands, noticed by comparing every command list with the one before |
| `/api/` | API documentation (Swagger UI) with an endpoint search, behind a Discord login; the bot's devs, admins, semi-admins and testers get in by their role, an account without access can ask for it |
| `/state/` | Public bot status: the bot's own report (Discord, database, Shinden, commands, version), availability over the last 24 hours (quarters of an hour: yellow when less than half the checks went unanswered, red from half on, blue for a planned maintenance break) and 90 days, planned breaks not counted, the Discord ping and Shinden's answer time, the outages of the last 90 days and the notice from the panel; below, this site (checked every 10 seconds) and the other Sanakan sites (wiki, Waifu, Alter, Skalpelator, USkalpelator) with their state and 90 days. Its link preview is a picture of the current state (`/state/og.php`) |
| `/i/` | Gallery of pictures and WebM videos, behind a Discord login, with a search across all folders. Files are added with a button, by dragging them onto the page or by pasting a picture (Ctrl+V); photo metadata such as the place they were taken is removed on upload. Videos get a frame as thumbnail. Folders and picked items download as ZIP, admins can turn pictures by 90°. An account without access can ask for it |
| `/admin/` | Admin panel, behind a Discord login: bot status line with the server's clock (its time zones, NTP, and how far it is from the clock of the computer looking), the notice for `/state/` and planned maintenance breaks (the status shows "do not disturb" meanwhile; the dates are picked in the Polish way, dd.mm.rrrr and 24 hours), requests for access, gallery and API access, recent logins with their roles on the bot's server and logging everyone out, trash, change history, gallery statistics and disk space, the availability of the site (checked every 10 seconds through Cloudflare and on the server itself, the wiki for comparison; every failure with what the server went through meanwhile and the addresses that sent most requests, from the nginx log; the scanners of the last 3 days, blocking an address in Cloudflare with one button, and the time PHP took per page), the server's resources (processor use, load, waiting for the disk and time taken by the host, memory and swap, the programs using the most memory, OPcache), server checks, the deployed version and a backup of the data as ZIP. Every account has a profile, `/admin/?konto=ID`: its roles, what it may open (given and taken there), the requests from its addresses in the last 24 hours, the devices it is logged in on (each can be logged out, or all at once), the addresses it came from in the last 30 days and the other accounts at the same ones, what it did in the gallery, its requests and history. The search under the title finds accounts by name, @name or ID and addresses whole or by their start, with whose they are. An address of an account of the panel, or the one the admin uses, cannot be blocked |
| `/account/` | The profile of the logged-in account, for itself only: its roles on the bot's server, what it may open on the site, the devices it is logged in on (each can be logged out, or all the others at once), what it did in the gallery and its own history; no addresses, those only the panel shows |
| `/status.php` | Bot status as JSON, used by the home page |

The top right corner of the home page logs in with Discord (`account.php`); the API button unlocks for the accounts that may read it. The home page, the API documentation, the gallery, the panel and the profile show the logged-in account there the same way (the commands, their change history, the status, the privacy notice and the 404 page do not): its avatar ringed in the colour of its role on the bot's Discord server, the name and its Safeguard level in that colour, e.g. `LV.9` for a dev or `LV.3` for a moderator. A click opens a menu in HUD corners with the profile, the gallery and the panel (these two only for the accounts that may open them), logging out (set apart by a line, back to the same page) and a line with the full role, e.g. `LV.9 DEV`, and the account ID. Only dev, admin, semi-admin and tester give anything on the site (the API documentation, and for admin and dev the private commands); the gallery has its own lists and never follows these roles. A short click on the status dot opens `/state/`; held for 3 seconds it fires the beam of the Gravitational Beam Emitter from BLAME!. Typing `netsphere` on any page (or tapping the `SAFEGUARD · LV.9` line five times) asks the Netsphere for access: the Safeguard scans for the Net Terminal Gene, does not find it and marks the visitor an illegal resident (`js/netsphere.js`).

## Repository layout

| Path | Contents |
|---|---|
| `index.html`, `404.html` | Home page and the 404 page |
| `sanakan-og.png` | Link preview picture of the home page |
| `cmd/`, `api/`, `state/`, `i/`, `admin/`, `account/` | Subpages |
| `status.php` | Bot status for the home page |
| `inc/bot.php` | Bot API access: the bot's `api/health` (Discord connection and ping, database, Shinden, commands; the command list where a bot has no `api/health` yet), one-minute cache, 24 h check history with the ping, per day counts, outages, notice and maintenance breaks, last known command list and its changes, the moderator and debug commands fetched with the site's key |
| `inc/check-bot.php` | One bot check, run by cron every minute; every 5 minutes also the other sites, once a day it removes the thumbnails nobody looked at for 30 days |
| `inc/system.php` | The server's resources for the panel, read from Linux's `/proc` when the panel opens |
| `inc/diag.php`, `inc/check-site.php` | Availability of the site, checked by cron every 10 seconds through Cloudflare and straight on the server, with the kernel's TCP counters, the state of nginx and PHP-FPM and the requests from the site's nginx log; the scanners and the PHP time per page from the same log; kept 3 days in `inc/data/diag/` (left out of the backup) |
| `inc/cloudflare.php` | Blocking addresses in Cloudflare from the panel, through an IP list of the account |
| `inc/panel-account.php`, `inc/panel-search.php` | The profile of an account and the search of the panel |
| `inc/services.php` | The Sanakan sites, this one included: whether they answer, since when, per day counts |
| `inc/auth.php` | Discord login (OAuth2), a session of a week kept in `inc/data/sessions/`, access lists, the account's roles on the bot's server (asked with the site's key, kept 10 minutes), change history |
| `inc/gallery.php` | Gallery: thumbnails (also of videos), uploads without metadata, WebP conversion, search, duplicate check, trash, renaming, rotating, ZIP downloads |
| `inc/status-card.php`, `inc/meta.php` | Bot status card and link preview tags (Open Graph) |
| `fonts/`, `css/fonts.css` | The site's fonts (Lato, Share Tech Mono, JetBrains Mono, SIL Open Font License), served from the site instead of Google Fonts, so no visitor's address goes to Google |
| `inc/fonts/` | Lato and Share Tech Mono (SIL Open Font License) for the preview picture of `/state/` |
| `privacy/` | Privacy notice, linked from the footer of every page |
| `inc/config.example.php` | Configuration template |
| `css/`, `js/` | Styles and scripts |
| `robots.txt` | Keeps search engines out of the gallery, the panel, the profile, `inc/` and the API documentation |
| `server/nginx/` | nginx rules: blocked `inc/`, 404 page, short links to commands, security headers, browser cache, visitors' addresses behind Cloudflare, the site's log and the state of nginx and PHP-FPM for the server itself |
| `deploy.sh` | Deployment to the server over SSH |

Kept out of git:
- `inc/config.php`, which holds the Discord application secret,
- `inc/data/`, the data the site writes: access lists and requests, the roles the bot reported, status history and outages, change history, trash, file hashes, the time sessions are valid from, the thumbnail cache (`thumbs/`, left out of the backup),
- the pictures in `i/` (only `i/index.php` is tracked).

The panel downloads `inc/data/`, optionally with the pictures, as one ZIP (server card, "Kopia danych"). To restore it, unpack `data/` into `inc/data/` and `i/` into `i/`, then give them back to the web server: `chown -R www-data:www-data inc/data i`.

## Server

The site runs on nginx with PHP-FPM, currently Ubuntu with PHP 8.1. Required packages:

```bash
apt-get install -y php8.1-fpm php8.1-cli php8.1-gd php8.1-zip php8.1-curl webp ffmpeg
```

- `php8.1-gd` makes thumbnails and converts PNG and JPG to WebP.
- `webp` (`gif2webp`, `webpmux`) converts GIFs to animated WebP and makes their thumbnails.
- `ffmpeg` takes a frame of every WebM video for its thumbnail; without it the tile loads the video itself.
- `php8.1-zip` packs folders for download; without it the ZIP buttons are not shown.
- `php8.1-cli` runs the bot check from cron.
- `php8.1-curl` runs the availability checks of the panel, several at once.

### nginx

The rules are in `server/nginx/`, which `deploy.sh` does not send:

- `sanakan.conf` blocks `inc/`, which holds the configuration and data, sets up the 404 page, sends short links such as `/cmd/daily` to `/cmd/#daily` (the 404 page does the same where the rule is missing), and lets browsers keep CSS and JS for a year (every page links them with a `?v=` version, raised on each change) and pictures and videos for a day.
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
| `API_VIEWERS` | Accounts that may read the API documentation in `/api/`. The panel can add more, and `PANEL_ADMINS` always can; `true` lets in any Discord account |
| `BOT_APP_KEY` | Key of the site's application in the bot API, sent as `x-app-key`; it needs the Info right (Site covers it too). With it the site reads the roles of the logged-in account (`/api/User/discord/{id}/permissions`) and the moderator and debug commands (`/api/Info/commands/private`). Without it neither happens, and the panel's server card says so |

| `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ACCOUNT_ID`, `CLOUDFLARE_LIST` | Optional: blocking addresses in Cloudflare from the panel (below) |

Without `inc/config.php` the gallery, the API documentation and the panel stay closed. Direct links to the pictures in `i/` always work.

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
