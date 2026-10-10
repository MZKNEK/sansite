# Gallery update: offline migration and server acceptance

This checklist is for the new release. Local tests do not constitute production acceptance. No production deployment is performed by the implementation task.

1. Prepare the new code in a separate release directory with access to the same stopped data and gallery. Stop write requests, stop new cron runs and wait for active PHP writers/encoders to finish. Do not mix old and new writers.
2. Back up a consistent set of gallery files and `inc/data`, including `trash`, `operations`, preview metadata and conversion artifacts. Test restoring it to a disposable environment.
3. Run as the web-server user with the **new code**:

   ```bash
   sudo -u www-data php /path/to/new-release/inc/run-maintenance.php --inventory
   sudo -u www-data php /path/to/new-release/inc/migrate-gallery.php --dry-run
   sudo -u www-data php /path/to/new-release/inc/migrate-gallery.php
   ```

   Resolve every corrupt critical JSON document or conflicting old/new limit before migrating. The migration atomically moves `access.uploadLimits` to explicit overrides, preserves other keys, initializes absent required documents and writes `inc/data/initialized` last. Rerunning is safe. Defaults now give 200 files; explicitly stored old limits remain unchanged. Keep the complete pre-migration backup for rollback.
4. Verify that `inc/data` and `i/` are on the same filesystem. Check owner/permissions for `locks`, `operations`, `conversion-staging`, `webp-preview` and `trash`. Verify HTTP denial for all `inc/` paths and raw `i/users/` paths. Activate the release, resume traffic and cron on that code only.
5. Keep the existing single maintenance schedule every ten minutes, with errors in the journal:

   ```cron
   */10 * * * * www-data /usr/bin/systemd-cat -t sanakan-maintenance php /var/www/html/inc/run-maintenance.php
   ```

   Confirm `/usr/bin/systemd-cat` is available and persistent journal storage/rotation are configured. Inspect `journalctl -t sanakan-maintenance`. Run maintenance manually and check its actual exit code, not only a cron pipeline's status.
6. Measure nginx/proxy limits and PHP-FPM `memory_limit`, `upload_max_filesize`, `post_max_size`, execution time and worker count. The supplied transport values are 64M/64M/64M; account policy may be higher, but requests still need multipart headroom. Do not increase decoder limits merely because the file MiB policy increased.
7. Exercise global defaults, per-account file/trash/active/count overrides, resetting fields, two stale administrator forms, an account without a folder and read-only own profile. Check that a gallery admin has no upload bypass while a panel admin does; neither bypasses decoder or transport protections.
8. Exercise upload, cropper, HEIC output larger than input, rotate increasing bytes, restore file/folder, own-folder moves and administrator moves between accounts. Check foreign-source/target rejection and name collisions. A within-account move remains allowed after quota reduction. Hidden trash still counts; retention remains thirty days.
9. Exercise manual and background conversion with a source changed/renamed/removed during encoding, a preexisting independent WebP target and an interrupted commit. Recovery must preserve ambiguous states and journals after failed rollback. Verify real tool behavior and limits on this server. Confirm maintenance does not report success after a corrupt index or failed report write.
10. Check actual ZIP download with the appropriate viewer/admin/share rights and after a concurrent file mutation. Best effort failure and retry are accepted; leaked private files or incomplete archives are not. Verify private/no-store thumbnail headers and clear any old CDN gallery-thumbnail rule/cache.

The advisory 25 GB gallery budget is not enforced as a global ceiling. Account quotas do not reserve capacity; monitor real disk space and grow storage as necessary. Temporary-work admission is separately bounded to 1 GiB by default and encoders are serialized.

Rollback: stop all writers and restore the full consistent backup, or use a separately reviewed reverse migration. Never restore only the old trash index while keeping newly moved files. Never run old and new quota-writing code together.

Local validation: `php -d extension=gd -d extension=zip tests/run.php`, `php tests/lint.php`, `php tests/http.php`, Node cropper tests and `node tests/browser.mjs`. For the browser suite install `playwright@1.64.0` under `.preview/browser`; use existing Edge on Windows or install Chromium with its CLI. The browser test runs real PHP and browser requests against a temporary copy, without production data or Discord.
