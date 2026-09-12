# New shop checklist

Use with the locked process: [Shopware Create & Continuous Deploy](https://app.clickup.com/90151931897/docs/2kyqjkzt-915). Full narrative is in [README.md](README.md).

**Default path is near-native Shopware:** `shopware-cli` + Flex endpoint + `composer require` + Symfony Flex. No git submodule. No fyrst create/apply CLI.

This package (`fyrst/shopware-cd`) is a thin Packagist library. It does **not** contain overlay files; Flex loads them from [`fyrst-dev/recipes`](https://github.com/fyrst-dev/recipes). There is **no** Composer dependency on that recipes repo.

`shopware-cli project create` owns `compose.yaml`, `.gitignore`, `.shopware-project.yml` (create’s default; `.yaml` is also accepted — do not rename), and local Docker. The fyrst Flex recipe does **not** copy those. Flex copies CI, `.dockerignore`, `.env.example`, and `deploy/` (CD Compose plus `deploy/sync-runtime.sh` for VPS and `deploy/sync-runtime-local.sh` for local `shopware-cli project dev`).

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
```

Prefer Composer inside the web container when it is running: `docker compose exec web composer …`.

- [ ] Docker = **yes** in the wizard (required; `shopware-cli` `--docker` for non-interactive)
- [ ] `shopware-cli project create` wrote `compose.yaml`, `.gitignore`, `.shopware-project.yml` (create’s default; `.yaml` also accepted — do not rename; CLI owns these; Flex does **not** copy them)
- [ ] `extra.symfony.endpoint` is **fyrst-dev/recipes**, then shopware/recipes, then `flex://defaults` — set **before** `composer require`
- [ ] Flex dropped `docker/Dockerfile` from `shopware/docker` (required; same `composer require` as `fyrst/shopware-cd`)
- [ ] CI `DOCKERFILE=docker/Dockerfile` (or default to that). Do not add a shop-root `Dockerfile`.
- [ ] Flex copied `.github/workflows/cd.yaml`, `.gitlab-ci.yaml`, `.dockerignore`, `.env.example`, `deploy/` (`deploy/compose.yaml`, `deploy/compose.prod.yaml`, `deploy/compose.vps.yaml`, `deploy/sync-runtime.sh`, `deploy/sync-runtime-local.sh`)
- [ ] VPS deploy uses `deploy/compose.yaml` + `deploy/compose.prod.yaml` + `deploy/compose.vps.yaml`, not the CLI-managed root `compose.yaml`
- [ ] Copied overlay files **committed** in the shop (`vendor/` is gitignored)
- [ ] `.env` was **not** written by Flex; copy `.env.example` → `.env` yourself
- [ ] Identity (locked hybrid): required `SHOPWARE_SHOP_ID` (stable shop slug, same on live/staging/laptop) + `SHOPWARE_DEPLOY_ENV` (`live` / `staging` / …). Optional `SHOPWARE_DATA_BASE`. `COMPOSE_PROJECT_NAME` and `SHOPWARE_DATA_ROOT` are **optional** — Docker Compose and sync derive `COMPOSE_PROJECT_NAME=${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` and `SHOPWARE_DATA_ROOT=${SHOPWARE_DATA_BASE:-/var/lib/shopware/data}/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}`. Compose uses those SoT vars directly. Do not hardcode Compose project name `shopware`.
- [ ] VPS `.env` does **not** keep create’s `COMPOSE_PROJECT_NAME=sw-shop-…` line (that **overrides** Compose `name:`). Remove or comment it out on the VPS. The recipe does not delete it (create owns local `project dev`).
- [ ] Same-host tag-and-load / air-gap: `PULL_POLICY=never` and `SKIP_PULL=1` (or `bash deploy/vps-release.sh --skip-pull`). Default `pull_policy` is `always` after CI pushed the tag.
- [ ] Runtime DB + media/files stay **out of git** and **out of the image**. VPS **bind mounts** under the derived shop/env root (default `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}/{files,media,thumbnail,theme,sitemap}`). Named volumes remain only for `mysql_data` / `redis_data` (scoped by the derived Compose project name). VPS env copy is `deploy/sync-runtime.sh` (SSH + dump + rsync of those host dirs; paths from shop id + deploy env). **No S3.** Local `shopware-cli project dev` uses `deploy/sync-runtime-local.sh` (rsync path remap into `files/` and `public/{media,thumbnail,theme,sitemap}`; remote auto `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live`) — **not** `deploy/sync-runtime.sh`.
- [ ] VPS: `mkdir -p "$SHOPWARE_DATA_ROOT"/{files,media,thumbnail,theme,sitemap}` and `chown` to uid 82 (www-data in docker-base) — shop/env root derived from shop id + deploy env, not a global `/var/lib/shopware/data`

If Flex did not copy files, the shop is missing the [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes) endpoint. See [README.md](README.md). Refresh later with `composer recipes:update fyrst/shopware-cd`.

To change overlay files: edit [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes), push `main`, wait for the flex-update workflow, then `composer recipes:update fyrst/shopware-cd` in shops. Do not look for overlay copies in this package. Do not add `compose.yaml`, `.gitignore`, or `.shopware-project.yml` / `.yaml` to the recipe.

## Secrets and hosts

Fill placeholders — never commit values.

- [ ] Optional CI: `SHOPWARE_PACKAGES_TOKEN` — set only if the shop uses packages.shopware.com (empty is fine)
- [ ] CI: `COMPOSER_AUTH` / `auth.json` if you have private Composer repos
- [ ] CI: registry login (`REGISTRY_USERNAME` / `REGISTRY_PASSWORD`, or platform defaults)
- [ ] CI: `SSH_PRIVATE_KEY`, `VPS_HOST`, `VPS_USER`, `VPS_PATH` (Compose primary)
- [ ] CI: `SSH_KNOWN_HOSTS` (recommended)
- [ ] Runtime: `APP_URL`, `APP_SECRET`, `DATABASE_URL`
- [ ] Runtime: `SHOPWARE_SHOP_ID` (stable shop slug) — **required**
- [ ] Runtime: `SHOPWARE_DEPLOY_ENV` (`live` / `staging` / …) — **required**
- [ ] Optional: `SHOPWARE_DATA_BASE` (prefix `/var/lib/shopware/data` when unset)
- [ ] Optional: `COMPOSE_PROJECT_NAME` / `SHOPWARE_DATA_ROOT` — Compose and sync derive `COMPOSE_PROJECT_NAME=${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` and `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}` when unset. Compose uses those SoT vars directly. Do **not** leave create’s `COMPOSE_PROJECT_NAME=sw-shop-…` on the VPS.
- [ ] Runtime: `INSTALL_ADMIN_USERNAME` / `INSTALL_ADMIN_PASSWORD` / `INSTALL_ADMIN_EMAIL` (first install only)
- [ ] Planned only: managed host (`deploy/managed/README.md`) is **not implemented**. Do not set `DEPLOY_TARGET=managed` expecting a deploy.
- [ ] **Live:** uncomment `COMPOSE_PROFILES=redis,worker,scheduler` in `.env` (worker + scheduler; redis if used). Leave unset on staging unless you need async/scheduled tasks. Release warns on live when empty; it does not auto-enable.
- [ ] Optional: on staging/playground/dev, copy `deploy/sync.env.example` → `deploy/sync.env` (`SYNC_SSH_*` to live, `SYNC_ENV` = this env). Cron `deploy/sync-runtime.sh` **on the consumer** (live → this env). Script uses shop id + deploy env (source default: same shop id + `live`) unless `SHOPWARE_DATA_ROOT` / `SYNC_REMOTE_DATA_ROOT` override. Opt-in sales-channel rewrite: `SYNC_REWRITE_APP_URL` (default off; refused on live). Review payment/shipping webhooks after a live pull. Never commit filled `deploy/sync.env`. Never auto-push into live.
- [ ] Optional: on a laptop, set `SHOPWARE_SHOP_ID` (same as live) and pull live media/files into `shopware-cli project dev` with `bash deploy/sync-runtime-local.sh --from live --data all` (remote auto `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live` → `files/` and `public/{media,thumbnail,theme,sitemap}`). Do **not** use `deploy/sync-runtime.sh` locally.

## First pipeline

- [ ] Push to GitHub and/or GitLab (`main`)
- [ ] Build → push `:sha` (+ `:latest` on `main`) succeeds
- [ ] Deploy job SSHs to the VPS, pulls, Compose up, one-shot setup
- [ ] Storefront + admin + health/smoke URL verified
- [ ] Document shop-specific overrides (external DB, search, CDN) in the shop repo README. Default VPS path has **no S3** — media/files stay on host bind mounts under `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}` (Compose/sync derive that; `SHOPWARE_DATA_ROOT` is optional); live → staging/playground/dev uses `deploy/sync-runtime.sh` (shop id + deploy env). Local `shopware-cli project dev` uses `deploy/sync-runtime-local.sh` (remote auto from `SHOPWARE_SHOP_ID` + `live`).

## Anti-patterns (do not)

- [ ] Do not add a fyrst create wrapper or git submodule
- [ ] Do not skip the fyrst-dev/recipes endpoint before `composer require` (Flex copies nothing)
- [ ] Do not expect overlay files to live in this Packagist package
- [ ] Do not let Flex copy or overwrite CLI-owned `compose.yaml`, `.gitignore`, or `.shopware-project.yaml` / `.shopware-project.yml`
- [ ] Do not rename create’s `.shopware-project.yml` to `.yaml` (shopware-cli accepts both)
- [ ] Do not leave create’s `COMPOSE_PROJECT_NAME=sw-shop-…` in the VPS `.env` (it overrides Compose `name:`)
- [ ] Do not deploy the VPS from root `compose.yaml` (use `deploy/compose.yaml` + `deploy/compose.prod.yaml` + `deploy/compose.vps.yaml`)
- [ ] Do not compile themes/assets on the VPS
- [ ] Do not add a second Dockerfile for GitLab vs GitHub or for managed hosts (always `docker/Dockerfile`)
- [ ] Do not skip `shopware/docker` or build from a shop-root `Dockerfile`
- [ ] Do not rsync `vendor/` or skip `shopware-deployment-helper`
- [ ] Do not put media, uploads, or DB dumps in git or the image
- [ ] Do not use a single global `/var/lib/shopware/data` without `SHOPWARE_SHOP_ID` / `SHOPWARE_DEPLOY_ENV` segments
- [ ] Do not hardcode Compose project name `shopware` (Compose derives `COMPOSE_PROJECT_NAME=${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}`)
- [ ] Do not require `COMPOSE_PROJECT_NAME` or `SHOPWARE_DATA_ROOT` in `.env` (they are optional; Compose uses those SoT vars directly)
- [ ] Do not use S3 as the default VPS env-to-env copy (SSH + dump + rsync of those host dirs via `deploy/sync-runtime.sh`)
- [ ] Do not run `deploy/sync-runtime.sh` against local `shopware-cli project dev` (VPS only; local remap is `deploy/sync-runtime-local.sh`)
- [ ] Do not cron a push into live (the consumer pulls from live)
