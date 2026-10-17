# sansite

Website of Sanakan, a Discord bot written in C#. Every page shares a skinnable HUD, modelled on the Safeguard scanner from BLAME!, with dark purple as the default and light palettes supported too. The site itself is in Polish.

Plain PHP on nginx, with no framework, no Composer and no database: the data lives in JSON files in `inc/data/`. The two croppers, Skalpelator and USkalpelator, are Svelte apps whose builds are committed, so the server needs no Node.js.

## Quick start

```bash
tools/preview.sh                        # the whole site with made-up data, http://127.0.0.1:8765/
php tests/lint.php && php tests/run.php # the lint and the tests
./deploy.sh sanakan                     # the committed site on the server
```

## Documentation

| Document | What it covers |
|---|---|
| [docs/pages.md](docs/pages.md) | Every address of the site, the login and the account menu, the search and the offline page |
| [docs/skins.md](docs/skins.md) | The shared palette, the skins, the custom skin, the backgrounds and the skin picker |
| [docs/safeguard.md](docs/safeguard.md) | The Safeguard's register of finds, their proofs and the ranking |
| [docs/layout.md](docs/layout.md) | What each file and folder holds, what stays out of git, restoring a backup |
| [docs/croppers.md](docs/croppers.md) | Skalpelator and USkalpelator: their sources, build and work with the site |
| [docs/development.md](docs/development.md) | Running the site locally and the tests |
| [docs/server.md](docs/server.md) | The server, deploying with `deploy.sh` and moving to a new server |
