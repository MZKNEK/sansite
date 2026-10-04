# sanakan.pl

Website of [Sanakan](https://sanakan.pl), a Discord bot written in C#. Every page shares one dark purple HUD style, modelled on the Safeguard scanner from BLAME!. The site itself is in Polish.

| Address | What it is |
|---|---|
| `/` | Home page: the logo with the bot status, links to the commands, wiki, Waifu and Skalpelator |
| `/cmd/` | The bot's commands, read from its API: search, modules, copyable examples |
| `/api/` | API documentation (Swagger UI) with an endpoint search |
| `/state/` | Public bot status and its availability over the last 24 hours |
| `/i/` | Gallery of pictures and WebM videos, behind a Discord login |
| `/admin/` | Admin panel, behind a Discord login |
| `/status.php` | Bot status as JSON, used by the home page |

Hidden way into the panel: hold the status dot on the home page for 10 seconds. A short click on the dot opens `/state/`.

## Repository layout

| Path | Contents |
|---|---|
| `index.html`, `404.html` | Home page and the 404 page |
| `cmd/`, `api/`, `state/`, `i/`, `admin/` | Subpages |
| `status.php` | Bot status for the home page |
| `inc/bot.php` | Bot API access: one-minute cache, 24 h check history, last known command list |
| `inc/check-bot.php` | One bot check, run by cron every minute |
| `inc/auth.php` | Discord login (OAuth2), session, access lists, change history |
| `inc/gallery.php` | Gallery: thumbnails, uploads, WebP conversion, trash, renaming |
| `inc/status-card.php`, `inc/meta.php` | Bot status card and link preview tags (Open Graph) |
| `inc/config.example.php` | Configuration template |
| `css/`, `js/` | Styles and scripts |
| `deploy.sh` | Deployment to the server over SSH |

Kept out of git:
- `inc/config.php`, which holds the Discord application secret,
- `inc/data/`, the data the site writes: access lists, status and change history, trash,
- the pictures in `i/` (only `i/index.php` is tracked).

## Server

The site runs on nginx with PHP-FPM, currently Ubuntu with PHP 8.1. Required packages:

```bash
apt-get install -y php8.1-fpm php8.1-cli php8.1-gd webp
```

- `php8.1-gd` makes thumbnails and converts PNG and JPG to WebP.
- `webp` (`gif2webp`, `webpmux`) converts GIFs to animated WebP and makes their thumbnails.
- `php8.1-cli` runs the bot check from cron.

### nginx

Block `inc/`, which holds the configuration and data, and set up the 404 page:

```nginx
# /etc/nginx/snippets/sanakan.conf, included in the server block: include snippets/sanakan.conf;
location ^~ /inc/ {
    deny all;
}

error_page 404 /404.html;
location = /404.html {
    internal;
}
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

Without `inc/config.php` the gallery and the panel stay closed. Direct links to the pictures in `i/` always work.

### Cron

A bot check every minute, so the availability history on `/state/` and in the panel has no gaps:

```bash
echo '* * * * * www-data php /var/www/html/inc/check-bot.php > /dev/null 2>&1' > /etc/cron.d/sanakan-status
```

## Deployment

```bash
./deploy.sh root@sanakan.pl
```

`deploy.sh` sends the files of the last commit over SSH. It never touches `inc/config.php`, `inc/data/` or the pictures in `i/`. It deletes on the server the files that were deleted from the repository since the previous deploy. It refuses to run with uncommitted changes. The site goes to `/var/www/html` unless another folder is given as the second argument.

## Running locally

```bash
php -d extension=gd -S 127.0.0.1:8765 -t .
```

The site is then at http://127.0.0.1:8765/. The Discord login works only with a local `DISCORD_REDIRECT_URI` in `inc/config.php` that is also listed under Redirects in the Discord application.
