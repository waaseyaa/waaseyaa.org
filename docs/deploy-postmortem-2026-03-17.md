# Deployment Post-Mortem: waaseyaa.org — 2026-03-17

## Summary

Marketing site for the Waaseyaa PHP CMS framework deployed to waaseyaa.org. Built on the framework itself (dogfooding). Total time from first commit to live site: ~90 minutes, with most time spent debugging server infrastructure issues.

## What Worked

- **GitHub Actions CI/CD** — artifact deploy pattern (cloned from minoo) worked on first successful run
- **Deployer** — release structure, shared dirs/files, PHP-FPM reload all worked correctly
- **Caddyfile approach** — per-site Caddyfile imported from deploy directory is clean and maintainable
- **SSH key generation and deployment** — `ssh-copy-id` to deployer user was smooth
- **Framework dogfooding** — the site runs on the Waaseyaa framework itself

## What Broke

### 1. Nginx vs Caddy (wasted effort)
**Root cause:** Plan assumed Nginx based on minoo's `ops/nginx/` config files, but the server actually runs Caddy.
**Impact:** Created Nginx config, had to scrap it and create Caddyfile instead.
**Fix for next time:** Check the actual web server on the target host before writing configs. Add to CLAUDE.md: "northcloud.one runs Caddy, not Nginx."

### 2. Caddy log directory permissions
**Root cause:** Caddy runs as `caddy` user under systemd with `Group=caddy`. Supplementary groups (deployer, www-data) from `/etc/group` are NOT inherited by the systemd service — only the primary group is set. Log dirs owned by `deployer` were inaccessible to Caddy's systemd process.
**Impact:** Caddy failed to restart, taking ALL sites offline temporarily.
**Fix applied:** Pre-created log files owned by `caddy:caddy` in each project's `log/` dir.
**Fix for next time:**
- Add `SupplementaryGroups=deployer www-data` to Caddy's systemd override (attempted but didn't resolve — needs investigation)
- OR standardize all Caddyfile log paths to `/var/log/caddy/` (which works because caddy owns that dir)
- OR add a deploy task to pre-create log files owned by caddy

### 3. Pre-existing Caddyfile issues
**Root cause:** `orewire-laravel/Caddyfile` had `issuer acme {}` on one line (syntax error). `claudriel` and `oneredpaperclip` had log dirs with wrong permissions/ownership.
**Impact:** Caddy couldn't reload/restart until ALL Caddyfile imports were valid.
**Fix applied:** Fixed orewire syntax, created missing log dirs, fixed ownership.
**Fix for next time:** Run `caddy validate --config /etc/caddy/Caddyfile` as a periodic health check. Add to server maintenance checklist.

### 4. Framework can't run minimal (kernel requires full stack)
**Root cause:** `HttpKernel` hardcodes dependencies on `PdoDatabase`, `BroadcastStorage`, `SqliteEmbeddingStorage`, `GraphQlRouteProvider`, and requires at least one content type enabled. There's no "lite" boot mode.
**Impact:** Started with 4 packages, ended needing 30+ packages. Marketing site carries the full framework weight.
**Fix applied:** Added all framework packages as dependencies.
**Future improvement:** Consider a `LiteKernel` or conditional boot in the framework — a static site shouldn't need AI vector storage.

### 5. Missing WAASEYAA_DB environment variable
**Root cause:** The shared `.env` only had `APP_ENV=production`. The kernel needs `WAASEYAA_DB` to locate the SQLite database. Without it, PHP-FPM couldn't boot the app (CLI worked because it resolved the path from the project root).
**Impact:** Site returned JSON:API 500 error after successful deploy.
**Fix applied:** Added `WAASEYAA_DB=/home/deployer/waaseyaa-org/shared/storage/waaseyaa.sqlite` to shared `.env`.
**Fix for next time:** Document required env vars. Add a deploy verification step that checks the app returns HTML, not JSON error.

### 6. Accidental commit to wrong repo
**Root cause:** Terminal was in `/home/jones/dev/waaseyaa/` (framework) instead of `/home/jones/dev/waaseyaa.org/`. `git add -A` picked up untracked audit files from the framework repo.
**Impact:** Pushed 17 unrelated files to the framework repo. Had to revert.
**Fix for next time:** Always verify `git remote -v` before committing. Use absolute paths in commands.

## Improvements for Next Deployment

1. **Standardize Caddyfile log paths** — either all use `/var/log/caddy/<domain>.access.log` or all use `~/project/log/access.log` with caddy ownership
2. **Add deploy smoke test** — after `deploy:symlink`, curl the site and check for 200 + expected content
3. **Document required env vars** per project in CLAUDE.md
4. **Add `caddy validate` to CI** or as a server cron job
5. **Consider a `waaseyaa/full` meta-package** for apps that need the complete framework
6. **Pre-create Caddy log files** in the deploy task chain
