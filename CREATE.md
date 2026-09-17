# New shop checklist

Use with the locked process: [Shopware Create & Continuous Deploy](https://app.clickup.com/90151931897/docs/2kyqjkzt-915). Full narrative is in [README.md](README.md).

**Default path is near-native Shopware:** `shopware-cli` + Flex endpoint + `composer require` + Symfony Flex + [fyrst-cli](https://github.com/fyrst-dev/cli) **0.1.0+**. No git submodule. No fyrst create/apply CLI in this package.

This package (`fyrst/shopware-cd`) is a thin Packagist library. It ships Symfony bundle `FyrstShopwareCdBundle`, `bin/console fyrst:sales-channel:rewrite-urls`, and shop overlay files under `overlay/` (Flex `copy-from-package` copies them into the shop). It does **not** own deploy/sync/backup pipeline logic. Operator commands are `fyrst-cli shopware …` only. Recipe `deploy/*.sh` wrappers are removed. **Dump stays `shopware-cli project dump`.** The Flex recipe ([`fyrst-dev/recipes`](https://github.com/fyrst-dev/recipes)) is metadata only — there is **no** Composer dependency on that recipes repo. Shops need `composer update fyrst/shopware-cd` for the command and for overlay bytes.

`shopware-cli project create` owns `compose.yaml`, `.gitignore`, `.shopware-project.yml` (create’s default; `.yaml` is also accepted — do not rename), and local Docker. The fyrst Flex recipe does **not** copy those. Flex `copy-from-package` copies CI, `.dockerignore`, `.env.example`, and `deploy/` (CD Compose `deploy/compose.yaml`, `deploy/compose.prod.yaml`, `deploy/compose.vps.yaml`) from this package’s `overlay/`. It does **not** copy `deploy/*.sh` wrappers. The Flex `env` configurator may **append** a `###> fyrst/shopware-cd ###` SoT block to `.env` (empty `SHOPWARE_SHOP_ID`, empty/omitted `SHOPWARE_DEPLOY_ENV`, `SHOPWARE_DATA_BASE=/var/lib/shopware/data`). It does **not** overwrite create’s whole `.env` and does not put secrets in that block. Shared committed `.env` holds shared keys only — no `COMPOSE_PROJECT_NAME`, no real `SHOPWARE_DEPLOY_ENV`. Then run `fyrst-cli shopware env init --shop-id <slug>`. That **strips** create’s `COMPOSE_PROJECT_NAME=sw-shop-…` from shared `.env` and writes `SHOPWARE_DEPLOY_ENV` to host `.env.local`. VPS stack name remains `${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` via `deploy/compose.yaml` `name:` / fyrst-cli `-p` from loaded identity. That command does not generate `APP_SECRET` (`shopware-cli project create` already writes it).

Shops **must** `composer require shopware/docker` on the same line as `fyrst/shopware-cd`. The image build file is always `docker/Dockerfile`.

## Create

Configure the Flex endpoint **before** `composer require`. Flex will **not** copy overlay files from `flex://defaults` alone.

```bash
shopware-cli project create <shop-name>
# or: npx @shopware-ag/shopware-cli project create <shop-name>
# optional: shopware-cli project create <shop-name> 6.6.x.x
cd <shop-name>
composer config extra.symfony.allow-contrib true
composer config --json extra.symfony.endpoint '["https://raw.githubusercontent.com/fyrst-dev/recipes/flex/main/index.json","https://raw.githubusercontent.com/shopware/recipes/flex/main/index.json","flex://defaults"]'
composer require shopware/docker shopware/deployment-helper fyrst/shopware-cd
# each VPS / laptop: fyrst-cli 0.1.0+
curl -fsSL https://raw.githubusercontent.com/fyrst-dev/cli/main/scripts/install.sh | bash
fyrst-cli shopware env init --shop-id <slug>
# strips create's COMPOSE_PROJECT_NAME from shared .env
# writes SHOPWARE_DEPLOY_ENV to host .env.local
# VPS extras: --env live --image ghcr.io/example/acme
```

Prefer Composer inside the web container when it is running: `docker compose exec web composer …`.

- [ ] Docker = **yes** in the wizard (required; `shopware-cli` `--docker` for non-interactive)
- [ ] `shopware-cli project create` wrote `compose.yaml`, `.gitignore`, `.shopware-project.yml` (create’s default; `.yaml` also accepted — do not rename; CLI owns these; Flex does **not** copy them)
- [ ] `extra.symfony.endpoint` is **fyrst-dev/recipes**, then shopware/recipes, then `flex://defaults` — set **before** `composer require`
- [ ] Flex dropped `docker/Dockerfile` from `shopware/docker` (required; same `composer require` as `fyrst/shopware-cd`)
- [ ] CI `DOCKERFILE=docker/Dockerfile` (or default to that). Do not add a shop-root `Dockerfile`.
- [ ] Flex `copy-from-package` copied `.github/workflows/cd.yaml`, `.gitlab-ci.yaml`, `.dockerignore`, `.env.example`, `deploy/` (`deploy/compose.yaml`, `deploy/compose.prod.yaml`, `deploy/compose.vps.yaml`) from this package’s `overlay/`. Flex does **not** copy `deploy/*.sh` wrappers.
- [ ] fyrst-cli **0.1.0+** is on `PATH` on each VPS and laptop (`scripts/install.sh`)
- [ ] VPS deploy uses `deploy/compose.yaml` + `deploy/compose.prod.yaml` + `deploy/compose.vps.yaml`, not the CLI-managed root `compose.yaml`
- [ ] Copied overlay files **committed** in the shop (`vendor/` is gitignored)
- [ ] Flex may have appended a `###> fyrst/shopware-cd ###` SoT block to `.env` (empty shop id). It does **not** overwrite create’s whole `.env`. Ran `fyrst-cli shopware env init --shop-id <slug>` (see `deploy/README.md`)
- [ ] Shop files: shared committed `.env` (shared keys only), then `.env.local` if present (this host’s `SHOPWARE_DEPLOY_ENV`, laptop SSH / extras), then `.env.prod` if present (VPS). Later file wins. Identity keys already set in an earlier file cannot be overridden — do not put a real `SHOPWARE_DEPLOY_ENV` in shared `.env`. Same loader for every `fyrst-cli shopware` verb. CI `VPS_*` / `SSH_PRIVATE_KEY` stay secrets — they are not shop `.env`.
- [ ] Identity (locked hybrid): required `SHOPWARE_SHOP_ID` in shared `.env` (stable shop slug, same on live/staging/laptop) + `SHOPWARE_DEPLOY_ENV` on the host (`.env.local` / `.env.prod`; `live` / `staging` / …). Optional `SHOPWARE_DATA_BASE`. `fyrst-cli shopware env init --shop-id <slug>` **strips** create’s `COMPOSE_PROJECT_NAME=sw-shop-…` from shared `.env` and writes `SHOPWARE_DEPLOY_ENV` to host `.env.local`. Do not put `COMPOSE_PROJECT_NAME` in shared `.env`. VPS stack name remains `${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` via `deploy/compose.yaml` `name:` / fyrst-cli `-p` from loaded identity. Do not hardcode a bare Compose project name `shopware`. `SHOPWARE_DATA_ROOT` is **optional** — sync derives `SHOPWARE_DATA_ROOT=${SHOPWARE_DATA_BASE:-/var/lib/shopware/data}/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}` when unset. Compose interpolates bind-mount paths from those SoT vars directly.
- [ ] Shared `.env` does **not** keep create’s `COMPOSE_PROJECT_NAME=sw-shop-…` (`fyrst-cli shopware env init` strips it). Flex does not strip it on `composer require` — run env init.
- [ ] Same-host tag-and-load / air-gap: `PULL_POLICY=never` and `SKIP_PULL=1` (or `fyrst-cli shopware deploy release --skip-pull`). Default `pull_policy` is `always` after CI pushed the tag.
- [ ] Runtime DB + media/files stay **out of git** and **out of the image**. VPS **bind mounts** under the derived shop/env root (default `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}/{files,media,thumbnail,theme,sitemap}`). Named volumes remain only for `mysql_data` / `redis_data` (scoped by the VPS Compose project `${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}`). VPS env copy is `fyrst-cli shopware sync {capture|apply|pull}` (SSH + dump + rsync of those host dirs; dump is `shopware-cli project dump`; paths from shop id + deploy env). **No S3.** Local `shopware-cli project dev` uses `fyrst-cli shopware sync local` (rsync path remap into `files/` and `public/{media,thumbnail,theme,sitemap}`; remote auto `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live`; fyrst-cli refuses `--data all`) — **not** VPS `sync {capture|apply|pull}`.
- [ ] VPS: `mkdir -p "$SHOPWARE_DATA_ROOT"/{files,media,thumbnail,theme,sitemap}` and `chown` to uid 82 (www-data in docker-base) — shop/env root derived from shop id + deploy env, not a global `/var/lib/shopware/data`

If Flex did not copy files, the shop is missing the [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes) endpoint. See [README.md](README.md). Refresh later with `composer recipes:update fyrst/shopware-cd`.

To change overlay files: edit `overlay/` in this package, merge to `main`, then in shops `composer update fyrst/shopware-cd` and `composer recipes:update fyrst/shopware-cd`. Do not keep a second copy in fyrst-dev/recipes `root/`. Do not add `compose.yaml`, `.gitignore`, or `.shopware-project.yml` / `.yaml` to the overlay.

## Secrets and hosts

Fill placeholders — never commit values.

- [ ] Optional CI: `SHOPWARE_PACKAGES_TOKEN` — set only if the shop uses packages.shopware.com (empty is fine)
- [ ] CI: `COMPOSER_AUTH` / `auth.json` if you have private Composer repos
- [ ] CI: registry login (`REGISTRY_USERNAME` / `REGISTRY_PASSWORD`, or platform defaults)
- [ ] CI: `SSH_PRIVATE_KEY`, `VPS_HOST`, `VPS_USER`, `VPS_PATH` (Compose primary)
- [ ] CI: `SSH_KNOWN_HOSTS` (recommended)
- [ ] Runtime (`.env` / `.env.local` / `.env.prod`): `APP_URL`, `APP_SECRET`, `DATABASE_URL`. Rewrite uses `APP_URL` only. `APP_SECRET` comes from `shopware-cli project create`; `fyrst-cli shopware env init` does not generate it.
- [ ] Runtime (shared `.env`): `SHOPWARE_SHOP_ID` (stable shop slug) — **required** (`fyrst-cli shopware env init --shop-id <slug>`)
- [ ] Runtime (host `.env.local` / `.env.prod`): `SHOPWARE_DEPLOY_ENV` (`live` / `staging` / …) — **required**. env init writes it to `.env.local`. Do not lock a real value in shared `.env`.
- [ ] Optional: `SHOPWARE_DATA_BASE` (prefix `/var/lib/shopware/data` when unset)
- [ ] Do not put `COMPOSE_PROJECT_NAME` in shared `.env`. `fyrst-cli shopware env init` **strips** create’s `COMPOSE_PROJECT_NAME=sw-shop-…`. VPS stack name remains `${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` via `name:` / fyrst-cli `-p` from loaded identity. Optional: `SHOPWARE_DATA_ROOT` — sync derives `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}` when unset. Compose interpolates bind-mount paths from the SoT vars directly.
- [ ] Laptop extras: `.env.local` with `SHOPWARE_DEPLOY_ENV` plus `SHOPWARE_SSH_HOST` / `SHOPWARE_SSH_USER` / `SHOPWARE_SSH_KEY` (host defaults to alias `live` when unset)
- [ ] VPS extras: `.env.prod` when the VPS needs values that are not in shared `.env` (or rely on host `.env.local`)
- [ ] Optional: `SHOPWARE_REMOTE_DATA_ROOT` if live data is not `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live`
- [ ] Live restore gate: `SHOPWARE_ALLOW_LIVE_RESTORE` (plus `--allow-live`) for `sync apply` / `backup recover`
- [ ] Runtime: `INSTALL_ADMIN_USERNAME` / `INSTALL_ADMIN_PASSWORD` / `INSTALL_ADMIN_EMAIL` (first install only)
- [ ] Planned only: managed host (`deploy/managed/README.md`) is **not implemented**. Do not set `DEPLOY_TARGET=managed` expecting a deploy.
- [ ] **Live:** uncomment `COMPOSE_PROFILES=redis,worker,scheduler` on the live host (`.env.local` / `.env.prod`; worker + scheduler; redis if used). Leave unset on staging unless you need async/scheduled tasks. Release warns on live when empty; it does not auto-enable.
- [ ] Optional: on staging/playground/dev, set `SHOPWARE_DEPLOY_ENV` + `APP_URL` in host `.env.local` / `.env.prod` (`SHOPWARE_SHOP_ID` stays in shared `.env`). Cron `fyrst-cli shopware sync pull` **on the consumer** (live → this env). Uses shop id + deploy env (source default: same shop id + `live`) unless `SHOPWARE_DATA_ROOT` / `SHOPWARE_REMOTE_DATA_ROOT` override. Dump is `shopware-cli project dump`. Sales-channel rewrite uses `APP_URL` / `bin/console fyrst:sales-channel:rewrite-urls` after `composer update fyrst/shopware-cd` (refused on live; `SHOPWARE_ALLOW_LIVE_RESTORE=1` does not bypass). Review payment/shipping webhooks after a live pull. Commit shared `.env` (shared keys only). Never commit `.env.local` / `.env.prod` or secrets. Never auto-push into live. `fyrst-cli shopware env init` is not a console command in this package.
- [ ] Optional: on a laptop, set `SHOPWARE_SHOP_ID` in shared `.env` (same as live) and `SHOPWARE_DEPLOY_ENV` + `SHOPWARE_SSH_*` in `.env.local`, then pull live media/files into `shopware-cli project dev` with `fyrst-cli shopware sync local --from live` (remote auto `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live` → `files/` and `public/{media,thumbnail,theme,sitemap}`; fyrst-cli refuses `--data all`). Do **not** use VPS `fyrst-cli shopware sync pull` locally.

## First pipeline

- [ ] Push to GitHub and/or GitLab (`main`)
- [ ] Build → push `:sha` (+ `:latest` on `main`) succeeds
- [ ] Deploy job SSHs to the VPS, then `fyrst-cli shopware deploy release` (existing `IMAGE` / `IMAGE_TAG` / `COMPOSE_DIR`): pull, Compose up, one-shot setup
- [ ] Storefront + admin + health/smoke URL verified
- [ ] Document shop-specific overrides (external DB, search, CDN) in the shop repo README. Default VPS path has **no S3** — media/files stay on host bind mounts under `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}` (Compose/sync derive that; `SHOPWARE_DATA_ROOT` is optional); live → staging/playground/dev uses `fyrst-cli shopware sync` (shop id + deploy env). Local `shopware-cli project dev` uses `fyrst-cli shopware sync local` (remote auto from `SHOPWARE_SHOP_ID` + `live`).

## Anti-patterns (do not)

- [ ] Do not add a fyrst create wrapper or git submodule
- [ ] Do not skip fyrst-cli 0.1.0+ on the VPS or laptop
- [ ] Do not dump with fyrst-cli (dump stays `shopware-cli project dump`)
- [ ] Do not expect this PHP package to own deploy/sync/backup pipeline logic
- [ ] Do not skip the fyrst-dev/recipes endpoint before `composer require` (Flex copies nothing)
- [ ] Do not expect overlay files to live in the Flex recipe `root/` (they live in this package’s `overlay/`)
- [ ] Do not let Flex copy or overwrite CLI-owned `compose.yaml`, `.gitignore`, or `.shopware-project.yaml` / `.shopware-project.yml`
- [ ] Do not rename create’s `.shopware-project.yml` to `.yaml` (shopware-cli accepts both)
- [ ] Do not leave create’s `COMPOSE_PROJECT_NAME=sw-shop-…` in shared `.env` (`fyrst-cli shopware env init` strips it)
- [ ] Do not put `COMPOSE_PROJECT_NAME` or a real `SHOPWARE_DEPLOY_ENV` in shared committed `.env`
- [ ] Do not put real secrets in the Flex env block (empty shop id only)
- [ ] Do not deploy the VPS from root `compose.yaml` (use `deploy/compose.yaml` + `deploy/compose.prod.yaml` + `deploy/compose.vps.yaml`)
- [ ] Do not compile themes/assets on the VPS
- [ ] Do not add a second Dockerfile for GitLab vs GitHub or for managed hosts (always `docker/Dockerfile`)
- [ ] Do not skip `shopware/docker` or build from a shop-root `Dockerfile`
- [ ] Do not rsync `vendor/` or skip `shopware-deployment-helper`
- [ ] Do not put media, uploads, or DB dumps in git or the image
- [ ] Do not use a single global `/var/lib/shopware/data` without `SHOPWARE_SHOP_ID` / `SHOPWARE_DEPLOY_ENV` segments
- [ ] Do not hardcode a bare Compose project name `shopware` (VPS stack is `${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` via `name:` / fyrst-cli `-p`)
- [ ] Do not require `SHOPWARE_DATA_ROOT` in `.env` (optional; Compose interpolates bind-mount paths from shop id + deploy env)
- [ ] Do not use S3 as the default VPS env-to-env copy (SSH + dump + rsync of those host dirs via `fyrst-cli shopware sync`)
- [ ] Do not run VPS `fyrst-cli shopware sync {capture|apply|pull}` against local `shopware-cli project dev` (local remap is `fyrst-cli shopware sync local`)
- [ ] Do not cron a push into live (the consumer pulls from live)
