# Filter Time Tracker — Runbook

## Environments

| Environment | URL | Server |
|---|---|---|
| Production | https://time.filter.agency | DigitalOcean droplet (2 vCPU / 4GB) |
| Staging | https://staging-time.filter.agency | DigitalOcean droplet ($6) |

Managed via Laravel Forge. All deploys go through Forge — do not SSH and run artisan manually in production unless recovering from an incident.

---

## Deploy

Deploys are triggered automatically on merge to `main` via Forge's GitHub integration.

To trigger a manual deploy:
```
# Via Forge UI: Sites → Filter Time Tracker → Deploy Now
# Or via Forge CLI if configured:
forge deploy <site-id>
```

Post-deploy steps run automatically (configured in Forge deploy script):
```bash
cd /home/forge/time.filter.agency
composer install --no-dev --no-interaction --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
npm ci && npm run build
php artisan queue:restart
```

---

## Rollback

If a deploy breaks production:

1. In Forge, go to **Deployments** tab → find the last good deploy → **Rollback**.
2. If Forge rollback is not available, SSH in and revert manually:

```bash
ssh forge@<droplet-ip>
cd /home/forge/time.filter.agency
git log --oneline -10          # find the last good commit hash
git checkout <commit-hash>
composer install --no-dev --no-interaction --optimize-autoloader
php artisan migrate --force    # only if rollback includes a migration reversal
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo supervisorctl restart all
```

3. If a migration must be reversed: `php artisan migrate:rollback` — confirm with Paul before running in production.

---

## Restore from Backup

Backups are taken nightly via Spatie Laravel Backup → DigitalOcean Spaces (`filter-time-backups` bucket), retained for 30 days.

### Restore database

```bash
# 1. Download the latest backup from DO Spaces (via s3cmd or the DO console)
s3cmd get s3://filter-time-backups/latest/db-YYYY-MM-DD.sql.gz /tmp/restore.sql.gz

# 2. Decompress
gunzip /tmp/restore.sql.gz

# 3. Restore (this overwrites the current database — confirm first)
mysql -u forge -p time_tracking < /tmp/restore.sql

# 4. Clear caches
php artisan cache:clear
```

### Restore application files

Application files are in git — re-deploy from the correct commit rather than restoring from backup.

---

## Staging restore test (automated)

A cron runs every Saturday that:
1. Drops the staging database.
2. Restores from the latest production backup.
3. Runs `php artisan migrate --force` to apply any pending migrations.
4. Sends a pass/fail notification.

This is configured as a Laravel scheduled command in `app/Console/Kernel.php` (Phase 8).

---

## Common Artisan commands

```bash
# Run migrations
php artisan migrate

# Import Harvest CSV
php artisan harvest:import /path/to/detailed-time.csv

# Reconcile import against Harvest CSV
php artisan harvest:reconcile /path/to/detailed-time.csv

# Seed the database (local/staging only)
php artisan db:seed

# Clear all caches
php artisan optimize:clear

# Check queue health
php artisan queue:monitor database:100
```

---

## Monitoring

- **Error reporting:** Sentry (production only) — alerts go to #dev-alerts Slack channel.
- **Uptime:** Authenticated cron check from another Filter server — pings `/timesheet` with a service account session. Alerts via Slack.
- **Laravel Pulse:** `/pulse` (admin-only) — shows slow queries, job failures, exception counts.

---

## Contacts

| Role | Person | Contact |
|---|---|---|
| Product owner | Paul Halfpenny | paul@filter.agency |
| JDW report owner | Olly | olly@filter.agency |
| Server access | Forge admin | see 1Password vault "Filter Infra" |
