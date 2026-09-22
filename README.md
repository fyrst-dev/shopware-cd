# fyrst/shopware-cd

Packagist library for fyrst.dev Shopware continuous deploy: overlay files and an empty Symfony bundle.

This repository is **not** a Shopware installation. It does not vendor Shopware core. The Packagist package [`fyrst/shopware-cd`](https://packagist.org/packages/fyrst/shopware-cd) is a **thin library**. It ships an empty `FyrstShopwareCdBundle` so Flex `bundles` and existing `config/bundles.php` entries still load. It does **not** own deploy, sync, backup, or env-init pipeline logic, and it does **not** shell Shopware `sales-channel:update:domain`. After a non-live DB restore, fyrst-cli sync passes the host from `APP_URL` to that command (skipped on live; scheme, port, and path stay as in the dump).

**Operator path:** [fyrst-cli](https://github.com/fyrst-dev/cli) **0.1.0+** only. Install it on each VPS and laptop. Recipe `deploy/*.sh` wrappers are removed — CI and operators call `fyrst-cli shopware …` only. **Dump stays `shopware-cli project dump` forever** — fyrst-cli never dumps.

Shop overlay files live in this package under [`overlay/`](overlay/). **Symfony Flex** `copy-from-package` copies CI (`.github/workflows/cd.yaml`, `.gitlab-ci.yaml`), `.dockerignore`, `.env.example`, and `deploy/` (CD Compose: `deploy/compose.yaml`, `deploy/compose.prod.yaml`, `deploy/compose.vps.yaml`, plus edge/managed docs) from `vendor/fyrst/shopware-cd/overlay/` into the shop. It does **not** copy `deploy/*.sh` wrappers, and it does **not** copy `compose.yaml`, `.gitignore`, or `.shopware-project.yaml` — `shopware-cli project create` owns those (create writes `.shopware-project.yml`; `.yaml` is also accepted — do not rename). Flex `env` may **append** a `###> fyrst/shopware-cd ###` SoT block to `.env` (empty shop id; empty/omitted `SHOPWARE_DEPLOY_ENV`; no secrets). It does **not** overwrite create’s whole `.env`. Shared committed `.env` holds shared keys only — no `COMPOSE_PROJECT_NAME`, no real `SHOPWARE_DEPLOY_ENV`. Then run `fyrst-cli shopware env init --shop-id <slug>`. That **strips** create’s `COMPOSE_PROJECT_NAME=sw-shop-…` from shared `.env`, writes `SHOPWARE_DEPLOY_ENV` and `COMPOSE_PROJECT_NAME=<shop-id>-<env>` (e.g. `acme-dev` / `acme-live`) to host `.env.local`, and sets gitignored `compose.override.yaml` `name:` for `shopware-cli project dev`. Local and VPS use that same Compose project name — not folder basename, not `shopware-<shop-id>`. VPS Compose naming SoT is `deploy/compose.yaml` `name: ${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` with host env files loaded. fyrst-cli passes `--env-file .env` then `.env.local` then `.env.prod` (if present). Raw Compose must use those same `--env-file` flags. That command does not generate `APP_SECRET` (`shopware-cli project create` already writes it). The image build file is always [`shopware/docker`](https://github.com/shopware/docker)’s `docker/Dockerfile` — shops **must** `composer require shopware/docker` on the same line as this package. Each VPS and laptop needs fyrst-cli 0.1.0+ on `PATH`. The Flex recipe ([`fyrst-dev/recipes`](https://github.com/fyrst-dev/recipes), later recipes-contrib) is metadata only (`copy-from-package` + `bundles` + `env` + post-install toast) — not a second overlay source.

Process (locked standard): [Shopware Create & Continuous Deploy](https://app.clickup.com/90151931897/docs/2kyqjkzt-915)

**Build once, run everywhere.** The image is identical for every target. The supported last mile is Docker Compose on a VPS. A managed container host is **planned / not implemented**.

There is **no** git submodule, **no** custom `fyrst-shopware-cd` CLI in this package, **no** Composer plugin that copies files, and **no** Composer dependency on [`fyrst-dev/recipes`](https://github.com/fyrst-dev/recipes). Operators install **fyrst-cli** from [`fyrst-dev/cli`](https://github.com/fyrst-dev/cli) (0.1.0+), not from this repo.

## Architecture

| Piece | Role |
| --- | --- |
| This repo ([`fyrst-dev/shopware-cd`](https://github.com/fyrst-dev/shopware-cd)) | Packagist package [`fyrst/shopware-cd`](https://packagist.org/packages/fyrst/shopware-cd): thin library + empty Symfony bundle `FyrstShopwareCdBundle` + `overlay/` (Flex `copy-from-package` → shop root). **No** deploy/sync/backup pipeline. Domain hosts after a non-live restore are Shopware `sales-channel:update:domain` (fyrst-cli; host from `APP_URL`). |
| [fyrst-cli](https://github.com/fyrst-dev/cli) 0.1.0+ | Operator CLI: `shopware env init`, `deploy {release\|rollback}`, `sync {capture\|apply\|pull\|local}`, `backup {create\|prune\|recover}`, `db import`. **Never dumps.** |
| `shopware-cli` | `project create` owns `compose.yaml`, `.gitignore`, `.shopware-project.yml` (create’s default; `.yaml` also accepted — do not rename), and local Docker (`shopware-cli project dev`). **`project dump` is the only dump.** The fyrst Flex recipe does **not** copy those create files. |
| [`fyrst-dev/recipes`](https://github.com/fyrst-dev/recipes) | Flex metadata only: `copy-from-package` of this package’s `overlay/` + existing `bundles` + `env` + post-install toast. Compiles [`flex/main/index.json`](https://raw.githubusercontent.com/fyrst-dev/recipes/flex/main/index.json). **Not** a second overlay source. Does **not** ship `deploy/*.sh` wrappers. |

Flex maps the Packagist package name `fyrst/shopware-cd` to a recipe **only** via the shop’s `extra.symfony.endpoint` list — not via a `require` of the recipes repo.

## Primary path (only)

Configure the Flex endpoint **before** `composer require`. Shopware already writes `extra.symfony.endpoint`; replace it so fyrst is first, then Shopware, then defaults:

```json
{
    "extra": {
        "symfony": {
            "allow-contrib": true,
            "endpoint": [
                "https://raw.githubusercontent.com/fyrst-dev/recipes/flex/main/index.json",
                "https://raw.githubusercontent.com/shopware/recipes/flex/main/index.json",
                "flex://defaults"
            ]
        }
    }
}
```

```bash
shopware-cli project create <shop-name>
# or: npx @shopware-ag/shopware-cli project create <shop-name>
# optional version pin: shopware-cli project create <shop-name> 6.6.x.x
cd <shop-name>
composer config extra.symfony.allow-contrib true
composer config --json extra.symfony.endpoint '["https://raw.githubusercontent.com/fyrst-dev/recipes/flex/main/index.json","https://raw.githubusercontent.com/shopware/recipes/flex/main/index.json","flex://defaults"]'
composer require shopware/docker shopware/deployment-helper fyrst/shopware-cd
# each VPS / laptop needs fyrst-cli 0.1.0+
curl -fsSL https://raw.githubusercontent.com/fyrst-dev/cli/main/scripts/install.sh | bash
# pin: FYRST_CLI_VERSION=0.1.0
fyrst-cli shopware env init --shop-id <slug>
# strips create's COMPOSE_PROJECT_NAME from shared .env
# writes SHOPWARE_DEPLOY_ENV + COMPOSE_PROJECT_NAME=<shop-id>-<env> to .env.local
# sets gitignored compose.override.yaml name: (shopware-cli project dev)
# laptop: --env dev    VPS: --env live --image ghcr.io/example/acme
```

Run Composer **inside Docker** when the web container is up (`docker compose exec web composer …`). Host PHP is often under-provisioned.

Flex then:

- `shopware/docker` → **required**. Flex copies official `docker/Dockerfile`. Image builds **always** use that file. CI `DOCKERFILE=docker/Dockerfile` (or default to that). The fyrst recipe does **not** ship a shop-root `Dockerfile`.
- `shopware/deployment-helper` → install/update at deploy time
- `fyrst/shopware-cd` → `.github/workflows/cd.yaml`, `.gitlab-ci.yaml`, `.dockerignore`, `.env.example`, `deploy/` including `deploy/compose.yaml`, `deploy/compose.prod.yaml`, `deploy/compose.vps.yaml` — from this package’s `overlay/` via Flex `copy-from-package`. Does **not** copy `deploy/*.sh` wrappers. Does **not** copy `compose.yaml`, `.gitignore`, or `.shopware-project.yaml` / `.shopware-project.yml`. Flex `env` appends a `###> fyrst/shopware-cd ###` block (safe defaults). Finish values with `fyrst-cli shopware env init --shop-id <slug>`.

Wizard defaults for fyrst: current stable Shopware, **Docker = yes**.

Commit the files Flex copied. `vendor/` is gitignored; CI and the VPS checkout need those paths in git.

If a file already exists, Flex skips or prompts. Flex `env` **appends** to `.env`; it does **not** overwrite create’s whole `.env`. `fyrst-cli shopware env init` sets gitignored `compose.override.yaml` `name:` (`<shop-id>-<env>`) for `shopware-cli project dev`. Other shop-specific **local** Compose tweaks belong in that same file, next to the CLI-owned root `compose.yaml`.

Without the fyrst-dev/recipes endpoint, Flex copies **no** overlay files. Flex still registers the empty `FyrstShopwareCdBundle` (see [Sales-channel domains](#sales-channel-domains)). Domain host updates are Shopware `sales-channel:update:domain`, called by fyrst-cli.

## How to change the overlay

Overlay files live in this repo under [`overlay/`](overlay/). Flex `copy-from-package` maps `overlay/` → shop root.

1. Change files under `overlay/` here. Do **not** add `compose.yaml`, `.gitignore`, `.shopware-project.yaml` / `.shopware-project.yml`, or `compose.override.yaml` — those stay CLI-owned / env-init host files. Do **not** add `deploy/*.sh`.
2. Merge to `main` and wait for Packagist (or point shops at `dev-main`).
3. In each shop:

```bash
composer update fyrst/shopware-cd
composer recipes:update fyrst/shopware-cd
```

The Flex recipe ([fyrst-dev/recipes](https://github.com/fyrst-dev/recipes), later recipes-contrib) must stay a thin `copy-from-package` of `overlay/`. Do not keep a second `root/` copy in the recipe. See the [recipes README](https://github.com/fyrst-dev/recipes) for endpoint details.

## Locked standard (do not fork locally)

| Topic | Decision |
| --- | --- |
| Create | `shopware-cli project create` / `npx @shopware-ag/shopware-cli` |
| CLI-owned files | `compose.yaml`, `.gitignore`, `.shopware-project.yml` (create’s default; `.yaml` also accepted — do not rename) and local Docker via CLI. Flex does **not** copy these. |
| Overlay | `extra.symfony.endpoint` (fyrst-dev/recipes first) then `composer require shopware/docker shopware/deployment-helper fyrst/shopware-cd` + Symfony Flex |
| Overlay files | Shipped in this package’s `overlay/`. Flex copies CI (`.github/workflows/cd.yaml`, `.gitlab-ci.yaml`), `.dockerignore`, `.env.example`, `deploy/` (`deploy/compose.yaml`, `deploy/compose.prod.yaml`, `deploy/compose.vps.yaml`). No `deploy/*.sh` wrappers. |
| Operator CLI | [fyrst-cli](https://github.com/fyrst-dev/cli) 0.1.0+ on each VPS / laptop. Call `fyrst-cli shopware …` only. Dump stays `shopware-cli project dump`. |
| Local | Docker via `shopware-cli project dev` (CLI-managed root `compose.yaml`). Live media/files: `fyrst-cli shopware sync local` (rsync path remap into the project tree; remote auto `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live` — not VPS `sync {capture\|apply\|pull}`; fyrst-cli refuses `--data all` on `sync local` — use the default volume list) |
| Runtime | App **always** runs in Docker (`shopware/docker` / `ghcr.io/shopware/docker-base`) |
| Image | Always `docker/Dockerfile` from required `shopware/docker`. CI `DOCKERFILE=docker/Dockerfile` (or default to that). |
| Build | `shopware-cli project ci` inside that multi-stage `docker/Dockerfile` |
| CI | GitHub Actions **and** GitLab CI, same stages |
| Identity (hybrid) | Required SoT: `SHOPWARE_SHOP_ID` in shared committed `.env` + `SHOPWARE_DEPLOY_ENV` on the host (`.env.local` / `.env.prod`). Optional `SHOPWARE_DATA_BASE`. `fyrst-cli shopware env init --shop-id <slug>` **strips** create’s `COMPOSE_PROJECT_NAME=sw-shop-…` from shared `.env`, writes `SHOPWARE_DEPLOY_ENV` and `COMPOSE_PROJECT_NAME=<shop-id>-<env>` (e.g. `acme-dev` / `acme-live`) to host `.env.local`, and sets gitignored `compose.override.yaml` `name:` for `shopware-cli project dev`. Local and VPS use that same Compose project name — not folder basename, not `shopware-<shop-id>`. VPS Compose naming SoT is `deploy/compose.yaml` `name: ${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` with host env files loaded. fyrst-cli passes `--env-file .env` then `.env.local` then `.env.prod` (if present). Raw Compose must use those same `--env-file` flags. Optional `SHOPWARE_DATA_ROOT` — sync derives `SHOPWARE_DATA_ROOT=${SHOPWARE_DATA_BASE:-/var/lib/shopware/data}/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}` when unset. Compose interpolates bind-mount paths from those SoT vars directly. No bare Compose project name `shopware`. |
| Primary deploy | Docker Compose on a VPS using `deploy/compose.yaml` + `deploy/compose.prod.yaml` + `deploy/compose.vps.yaml` (not root `compose.yaml`) |
| Runtime data | Out of git and out of the image. VPS **bind mounts** under the derived shop/env root (`{files,media,thumbnail,theme,sitemap}`). Named volumes remain only for `mysql_data` / `redis_data` (scoped by the VPS Compose project `${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}`). VPS: `fyrst-cli shopware sync {capture\|apply\|pull}` live → staging/playground/dev (SSH + `shopware-cli project dump` + rsync of those host dirs; **no S3**; cron on the consumer; paths from shop id + deploy env). Local `shopware-cli project dev`: `fyrst-cli shopware sync local` (rsync path remap into `files/` and `public/{media,thumbnail,theme,sitemap}`; remote default `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live`) |
| Managed deploy | **Planned / not implemented** (same image contract; no CI job). Compose/VPS is the only supported last mile. |
| Deploy-time tasks | `vendor/bin/shopware-deployment-helper run --skip-theme-compile --skip-assets-install` |

Out of scope: bare Deployer/SSH without containers, Shopware PaaS as default, compiling assets on the production host, git submodules, custom create wrappers, S3 as the default VPS env-to-env copy.

## Identity (locked hybrid)

Several shops, and live + staging of the same shop, can share one VPS. Isolate them with shop id + deploy env — not a single global data directory and not a bare Compose project name `shopware`. Local and VPS Compose project name is `${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` (e.g. `acme-dev` / `acme-live`) — not folder basename, not `shopware-<shop-id>`.

**Shop-root files only.** fyrst-cli loads process env, then shared `.env`, then `.env.local` if present (this host’s `SHOPWARE_DEPLOY_ENV` + `COMPOSE_PROJECT_NAME=<shop-id>-<env>`, laptop SSH / extras), then `.env.prod` if present (VPS). Later file wins. Identity keys already set in an earlier file cannot be overridden — do not put a real `SHOPWARE_DEPLOY_ENV` in shared `.env`. fyrst-cli passes `--env-file .env` then `.env.local` then `.env.prod` (if present). Raw Compose must use those same `--env-file` flags (omit a flag if that host file is missing). CI `VPS_*` / `SSH_PRIVATE_KEY` stay GitHub/GitLab secrets — they are not shop `.env`.

**Required** source of truth:

- `SHOPWARE_SHOP_ID` — stable shop slug in shared committed `.env` (same on live, staging, and laptop). Example: `acme`.
- `SHOPWARE_DEPLOY_ENV` — this host’s role: `live` / `staging` / `playground` / `dev` in `.env.local` / `.env.prod` (env init writes `.env.local`). Not a real value in shared `.env`.
- `COMPOSE_PROJECT_NAME` — `<shop-id>-<env>` in host `.env.local` (env init writes it). Not in shared `.env`.

**After Flex, run env init.** `fyrst-cli shopware env init --shop-id <slug>` **strips** create’s `COMPOSE_PROJECT_NAME=sw-shop-…` from shared `.env`, writes `SHOPWARE_DEPLOY_ENV` and `COMPOSE_PROJECT_NAME=<shop-id>-<env>` (e.g. `acme-dev` / `acme-live`) to host `.env.local`, and sets gitignored `compose.override.yaml` `name:` for `shopware-cli project dev`. VPS Compose naming SoT is `deploy/compose.yaml` `name: ${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` with host env files loaded. fyrst-cli passes `--env-file .env` then `.env.local` then `.env.prod` (if present). Raw Compose must use those same `--env-file` flags.

**Optional:**

- `SHOPWARE_DATA_BASE` — host prefix for bind-mount trees (`/var/lib/shopware/data` when unset)
- `SHOPWARE_DATA_ROOT` — bind-mount root override. When unset: `${SHOPWARE_DATA_BASE:-/var/lib/shopware/data}/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}`

`deploy/compose.yaml` interpolates `SHOPWARE_SHOP_ID`, `SHOPWARE_DEPLOY_ENV`, and `SHOPWARE_DATA_BASE` **directly** for bind-mount paths. VPS Compose naming SoT is its `name: ${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` (e.g. `acme-live`) with host env files loaded — the same Compose project name local `shopware-cli project dev` uses (`acme-dev` when `--env dev`). fyrst-cli passes `--env-file .env` then `.env.local` then `.env.prod` (if present). Raw Compose must use those same `--env-file` flags. `SHOPWARE_DATA_ROOT` stays optional.

`shopware-cli project create` writes `COMPOSE_PROJECT_NAME=sw-shop-…` into shop-root `.env`. Run `fyrst-cli shopware env init --shop-id <slug>` so shared `.env` does **not** keep that line (env init **strips** it). Host `.env.local` holds `COMPOSE_PROJECT_NAME=<shop-id>-<env>`. Flex does **not** strip the create line on `composer require`.

After create + `composer require`, finish shop-specific `.env` values with `fyrst-cli shopware env init`:

```bash
fyrst-cli shopware env init --shop-id acme
# strips create's COMPOSE_PROJECT_NAME from shared .env
# writes SHOPWARE_DEPLOY_ENV + COMPOSE_PROJECT_NAME=<shop-id>-<env> to .env.local
# sets gitignored compose.override.yaml name: (shopware-cli project dev)
# laptop: --env dev    VPS: --env live --image ghcr.io/example/acme
```

`--shop-id` is required unless already non-empty. fyrst-cli merges missing keys from `.env.example` and does not invent MYSQL passwords or `APP_URL`. It does not generate `APP_SECRET` (`shopware-cli project create` already writes it). See `deploy/README.md` after Flex copies it, and [fyrst-cli](https://github.com/fyrst-dev/cli). Same loader for every `fyrst-cli shopware` verb.

**Formula:**

```text
# Compose project name (local and VPS; host .env.local + compose.override.yaml name:):
# COMPOSE_PROJECT_NAME=<shop-id>-<env>
# ${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}  →  acme-dev / acme-live
# not folder basename, not shopware-<shop-id>
SHOPWARE_DATA_ROOT=${SHOPWARE_DATA_BASE:-/var/lib/shopware/data}/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}
# /var/lib/shopware/data/acme/live/{files,media,thumbnail,theme,sitemap}
# /var/lib/shopware/data/acme/staging/…
```

Set `SHOPWARE_SHOP_ID` in shared committed `.env`. Set `SHOPWARE_DEPLOY_ENV` and `COMPOSE_PROJECT_NAME=<shop-id>-<env>` in host `.env.local` / `.env.prod` (env init writes `.env.local` and gitignored `compose.override.yaml` `name:`). Set `SHOPWARE_DATA_BASE` only when the prefix is not `/var/lib/shopware/data`. `fyrst-cli shopware sync` derives this host from `SHOPWARE_SHOP_ID` + `SHOPWARE_DEPLOY_ENV` (and `SHOPWARE_DATA_BASE`) and the SSH source from shop id + `live` when roots are unset. `fyrst-cli shopware sync local` auto-derives remote `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live`. Override with `SHOPWARE_DATA_ROOT` / `SHOPWARE_REMOTE_DATA_ROOT` when needed.

Named volumes `mysql_data` / `redis_data` on the VPS are scoped by `${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` (e.g. `acme-live_mysql_data`).

## Secrets and hosts (CI + runtime)

**Build-time (CI variables / GitHub secrets — never in git):**

| Name | Purpose |
| --- | --- |
| `SHOPWARE_PACKAGES_TOKEN` | Optional. Set only if the shop uses [packages.shopware.com](https://packages.shopware.com). Empty is fine. |
| `COMPOSER_AUTH` | Optional JSON for private Composer repos (`auth.json`) |
| `REGISTRY_*` | Push the image (`REGISTRY_USERNAME` / `REGISTRY_PASSWORD`, or GitHub `GITHUB_TOKEN` / GitLab `CI_REGISTRY_*`) |
| `REGISTRY_IMAGE` | Optional override of the image name |
| `DOCKERFILE` | Image build file. Always `docker/Dockerfile` (or default to that). |

**Runtime (VPS `.env` mode `0600`, or managed-host env):**

| Name | Purpose |
| --- | --- |
| `APP_URL` / `SALES_CHANNEL_URL` | Public shop URL. Sync passes the host from `APP_URL` to `sales-channel:update:domain` (skipped on live; scheme, port, and path stay as in the dump). Default post-deploy probe is `APP_URL` (trim trailing slash) + `/api/_info/health-check` |
| `DEPLOY_HEALTH_URL` | Optional override for that probe. **Live:** `deploy release` refuses without a resolvable probe URL unless `--allow-no-deploy-health` / `ALLOW_NO_DEPLOY_HEALTH=1`. **Non-live:** probe optional if no URL |
| `ALLOW_NO_DEPLOY_HEALTH` | Live escape hatch (`1`, same as `--allow-no-deploy-health`). Do not set this in production CI unless you accept no probe |
| `APP_SECRET` | Persistent secret (`shopware-cli project create` writes it; `fyrst-cli shopware env init` does not rewrite it) |
| `DATABASE_URL` | MySQL/MariaDB DSN |
| `SHOPWARE_SHOP_ID` | Stable shop slug (same on every stack of this shop). **Required.** Shared committed `.env`. |
| `SHOPWARE_DEPLOY_ENV` | This host’s role (`live` / `staging` / …). **Required.** Host `.env.local` / `.env.prod` (env init writes `.env.local`). Do not lock a real value in shared `.env`. |
| `SHOPWARE_DATA_BASE` | Optional prefix for bind-mount trees (`/var/lib/shopware/data` when unset) |
| `COMPOSE_PROJECT_NAME` | Not in shared `.env`. Host `.env.local`: `<shop-id>-<env>` (e.g. `acme-dev` / `acme-live`). `fyrst-cli shopware env init` **strips** create’s `sw-shop-…` line, writes this to `.env.local`, and sets gitignored `compose.override.yaml` `name:` for `shopware-cli project dev`. VPS Compose naming SoT is `deploy/compose.yaml` `name: ${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` with host env files loaded. fyrst-cli passes `--env-file .env` then `.env.local` then `.env.prod` (if present). Raw Compose must use those same `--env-file` flags. |
| `SHOPWARE_DATA_ROOT` | Optional bind-mount root override. Derived `${SHOPWARE_DATA_BASE:-/var/lib/shopware/data}/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}` |
| `SHOPWARE_SSH_HOST` / `SHOPWARE_SSH_USER` / `SHOPWARE_SSH_KEY` | Laptop → live (and any host SSH). Put in `.env.local`. Host defaults to the sync alias (`live` → `~/.ssh/config` `Host live`) when unset |
| `SHOPWARE_REMOTE_DATA_ROOT` | Optional live bind-mount root. Else `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live` |
| `SHOPWARE_ALLOW_LIVE_RESTORE` | Live gate for `sync apply` and `backup recover` (plus `--allow-live`) |
| `INSTALL_ADMIN_*` | First-install admin user only |
| Store / app licence vars | Only if you ship licensed apps |

**Deploy transport (CI secrets — not shop `.env`):**

| Name | Purpose |
| --- | --- |
| `SSH_PRIVATE_KEY` | CI → VPS |
| `VPS_HOST` / `VPS_USER` / `VPS_PATH` | SSH target and checkout path |
| `SSH_KNOWN_HOSTS` | Recommended instead of blindly accepting host keys |

Placeholders live in the Flex-copied `.github/workflows/cd.yaml`, `.gitlab-ci.yaml`, and `.env.example`. Flex may append SoT keys to `.env`; then run `fyrst-cli shopware env init --shop-id <slug>` (strips create’s `COMPOSE_PROJECT_NAME` from shared `.env`; writes `SHOPWARE_DEPLOY_ENV` and `COMPOSE_PROJECT_NAME=<shop-id>-<env>` to host `.env.local`; sets gitignored `compose.override.yaml` `name:`). Put `SHOPWARE_SHOP_ID` in shared `.env`. Put `SHOPWARE_DEPLOY_ENV`, `COMPOSE_PROJECT_NAME`, and `APP_URL` in `.env.local` / `.env.prod`. Put laptop SSH in `.env.local`.

GitLab still looks for `.gitlab-ci.yml` by default — set Settings → CI/CD → CI/CD configuration file to `.gitlab-ci.yaml`. GitHub Actions and shopware-cli load the `.yaml` names directly.

## CD pipeline

Push to GitHub and/or GitLab on `main` (or a `v*` tag):

1. **Build** — `docker buildx` with BuildKit secrets; `shopware-cli project ci` inside the `shopware-cli` image.
2. **Push** — `:git-sha` always; `:latest` on default branch; `:semver` on version tags.
3. **Deploy** — SSH to the VPS, then `fyrst-cli shopware deploy release` (existing `IMAGE` / `IMAGE_TAG` / `COMPOSE_DIR`): pull image, Compose up, one-shot setup, then GET the post-deploy probe (default `APP_URL` trim trailing slash + `/api/_info/health-check`; override `DEPLOY_HEALTH_URL`). **Live:** refuses without a resolvable probe URL unless `--allow-no-deploy-health` / `ALLOW_NO_DEPLOY_HEALTH=1`. **Non-live:** probe optional if no URL. Managed host (`DEPLOY_TARGET=managed`) is **planned / not implemented** and is not a supported CI switch.

Local day-to-day:

```bash
shopware-cli project dev
```

To pull live media/files into that checkout, use `fyrst-cli shopware sync local` (rsync path remap; do not pass `--data all` — fyrst-cli refuses it; use the default volume list). Do **not** run `fyrst-cli shopware sync {capture|apply|pull}` on a laptop — that is VPS-only.

## Primary vs planned deploy

```
                    ┌─ shopware-cli project ci ─┐
  git push  ──────│     multi-stage image    │  ──│ registry (:sha / :latest / :semver)
                    └──────────────────────────────┘
                                   │
            ┌───────────────────────┴──────────────────────┐
            ▼                                             ▼
   Primary: Compose / VPS                      Planned: managed host
   SSH → pull → compose up web                 Same image, different job
   + setup one-shot                             (not implemented; no CI job)
```

- **Primary (Compose / VPS):** `deploy/README.md` after Flex copies it. Live recommendation: uncomment `COMPOSE_PROFILES=redis,worker,scheduler` on the live host (`.env.local` / `.env.prod`). Staging leaves profiles unset unless you intentionally need worker/scheduler.
- **Planned (managed host):** `deploy/managed/README.md`. **Not implemented.** `DEPLOY_TARGET=managed` is not a supported switch.

The only image build file is `docker/Dockerfile`. Do **not** maintain a second Dockerfile per target or per CI system.

## Image contract

| Trigger | Tags |
| --- | --- |
| Every successful build (non-PR) | `:<git-sha>` (full SHA) |
| Default branch (`main`) | also `:latest` |
| Git tag `v*` | also `:semver` (`1.2.3`, `1.2`) |

PHP is pinned to **8.3** via `PHP_VERSION` in `docker/Dockerfile` / deploy Compose build args.

## How Flex finds the recipe

**Packagist is done.** [`fyrst/shopware-cd`](https://packagist.org/packages/fyrst/shopware-cd) is the published thin library. Flex copies **nothing** unless a configured endpoint lists a recipe for that package name. That recipe’s job is `copy-from-package` of this package’s `overlay/` plus `bundles` / `env` — it does not carry a `root/` tree.

There is **no** Composer dependency from this package on [`fyrst-dev/recipes`](https://github.com/fyrst-dev/recipes). Association is package name → recipe via `extra.symfony.endpoint`.

Shops must configure the endpoint **before** `composer require` (see [Primary path](#primary-path-only)).

[symfony/recipes-contrib](https://github.com/symfony/recipes-contrib) is **not** the primary path. An optional contrib PR may exist; do **not** wait on it. Keep using the fyrst-dev/recipes endpoint.

## Compose layout

**Local (CLI-owned, not Flex):**

- `compose.yaml` — from `shopware-cli project create`. Local Docker via `shopware-cli project dev`. env init sets gitignored `compose.override.yaml` `name:` (`<shop-id>-<env>`). Other shop-specific tweaks belong in that file.
- `.gitignore` / `.shopware-project.yml` (create’s default; `.yaml` also accepted — do not rename) — also from `shopware-cli project create`

**CD / VPS (Flex-copied under `deploy/`):**

- `deploy/compose.yaml` — CD services: `web`, bundled `mysql`, optional `redis` / `setup` / `worker` / `scheduler` profiles; interpolates `SHOPWARE_SHOP_ID` / `SHOPWARE_DEPLOY_ENV` / `SHOPWARE_DATA_BASE` **directly** for bind mounts; **bind mounts** under the derived shop/env root (named volumes only `mysql_data` / `redis_data`, scoped by `${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}`)
- `deploy/compose.prod.yaml` — VPS/prod overrides
- `deploy/compose.vps.yaml` — `pull_policy: ${PULL_POLICY:-always}` (CI/VPS default after a registry push). Same-host tag-and-load / air-gap: `PULL_POLICY=never` and `SKIP_PULL=1` (or `fyrst-cli shopware deploy release --skip-pull`)

Operators call fyrst-cli 0.1.0+ (recipe `deploy/*.sh` wrappers are removed):

- `fyrst-cli shopware env init` — Flex `env` appends safe SoT keys; fyrst-cli fills shop id, optional `IMAGE`, **strips** create’s `COMPOSE_PROJECT_NAME=sw-shop-…` from shared `.env`, writes `SHOPWARE_DEPLOY_ENV` and `COMPOSE_PROJECT_NAME=<shop-id>-<env>` to host `.env.local`, and sets gitignored `compose.override.yaml` `name:` for `shopware-cli project dev`. Does not generate `APP_SECRET` (`shopware-cli project create` already writes it).
- `fyrst-cli shopware deploy release` — also invoked from CI (existing `IMAGE` / `IMAGE_TAG` / `COMPOSE_DIR`). `compose run` uses `--pull never` (Compose v5 dropped `--no-build` from the run subcommand). `up` uses `--no-build`. After recreate, GET `DEPLOY_HEALTH_URL` or default `APP_URL` (trim trailing slash) + `/api/_info/health-check`. **Live:** refuses without a resolvable probe URL unless `--allow-no-deploy-health` / `ALLOW_NO_DEPLOY_HEALTH=1`. **Non-live:** probe optional if no URL.
- `fyrst-cli shopware deploy rollback`
- `fyrst-cli shopware sync {capture\|apply\|pull}` — VPS only: live → staging/playground/dev copy of DB + host dirs (SSH + `shopware-cli project dump` + rsync; **no S3**; paths from `SHOPWARE_SHOP_ID` + `SHOPWARE_DEPLOY_ENV`, optional `SHOPWARE_DATA_BASE` / `SHOPWARE_DATA_ROOT`)
- `fyrst-cli shopware sync local` — laptop only: live media/files into `shopware-cli project dev` (rsync path remap; remote default `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live`; **not** VPS `sync {capture\|apply\|pull}`; fyrst-cli refuses `--data all`)
- `fyrst-cli shopware backup {create\|prune\|recover}` — off-host DR; **not** sync; dump is still `shopware-cli project dump`

The VPS uses those deploy Compose files. It does **not** use the CLI-managed root `compose.yaml`.

Setup always:

```bash
vendor/bin/shopware-deployment-helper run \
  --skip-theme-compile \
  --skip-assets-install
```

## Runtime data sync (VPS, no S3)

DB, media, documents, thumbnails, and related uploads stay **out of git** and **out of the image**. `.dockerignore` excludes those paths. On the VPS they live in **bind mounts** under the derived shop/env root (default `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}/{files,media,thumbnail,theme,sitemap}`) plus the database (`mysql_data` named volume or DBaaS). Named Docker volumes remain only for `mysql_data` / `redis_data` — not for those media/files paths. A new image pull keeps the same host directories mounted.

One-time on each VPS stack (shop id + deploy env):

```bash
# shared .env: SHOPWARE_SHOP_ID=acme
# host .env.local / .env.prod: SHOPWARE_DEPLOY_ENV=staging
# COMPOSE_PROJECT_NAME=acme-staging (same formula as local acme-dev)
# SHOPWARE_DATA_ROOT=/var/lib/shopware/data/acme/staging
sudo mkdir -p "$SHOPWARE_DATA_ROOT"/{files,media,thumbnail,theme,sitemap}
sudo chown -R 82:82 "$SHOPWARE_DATA_ROOT"
```

**No S3.** Copying live → staging / playground / dev is SSH + `shopware-cli project dump` (operator-provided; fyrst-cli never dumps) + rsync of those host dirs, not object storage. Restore is `fyrst-cli shopware db import`.

`fyrst-cli shopware sync {capture|apply|pull}` rsyncs those host dirs. Paths use **shop id + deploy env**: this host from `SHOPWARE_SHOP_ID` + `SHOPWARE_DEPLOY_ENV` (and optional `SHOPWARE_DATA_BASE`); the SSH source defaults to the same shop id + `live`. Override with `SHOPWARE_DATA_ROOT` / `SHOPWARE_REMOTE_DATA_ROOT` when needed. Run it **on the consumer** (cron on staging/playground/dev). Pull from live; never auto-push into live. VPS sync is **not** for local `shopware-cli project dev`.

```bash
# on staging / playground / dev
cd /opt/shopware/acme-staging
# shared .env: SHOPWARE_SHOP_ID=acme
# .env.local / .env.prod: SHOPWARE_DEPLOY_ENV=staging
# COMPOSE_PROJECT_NAME=acme-staging
# APP_URL=https://staging.example.com
# no COMPOSE_PROJECT_NAME in shared .env (env init strips create’s sw-shop-…)
# SHOPWARE_DATA_ROOT stays unset (derived)
# fyrst-cli sync passes the host from APP_URL to sales-channel:update:domain
# (skipped on live; scheme, port, and path stay as in the dump).
fyrst-cli shopware sync pull --from live --data all
```

```cron
15 2 * * * cd /opt/shopware/acme-staging && fyrst-cli shopware sync pull --from live --data all
```

See `deploy/README.md` after Flex. Overlay copies live in this package’s [`overlay/`](overlay/).

## Runtime files into local `shopware-cli project dev`

Local and VPS use **different paths**. `shopware-cli project dev` bind-mounts the **project tree**, not `SHOPWARE_DATA_ROOT`. Do **not** run `fyrst-cli shopware sync {capture|apply|pull}` on a laptop.

Laptop `.env` needs at least `SHOPWARE_SHOP_ID` (same slug as live). Put `SHOPWARE_DEPLOY_ENV` (typically `dev`), `COMPOSE_PROJECT_NAME=<shop-id>-<env>`, and `SHOPWARE_SSH_HOST` / `SHOPWARE_SSH_USER` / `SHOPWARE_SSH_KEY` in `.env.local` (host defaults to alias `live` when unset). `fyrst-cli shopware sync local` **auto-derives** remote `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live` unless you override it (`--remote-data-root` / `SHOPWARE_REMOTE_DATA_ROOT`). From the shop root it rsyncs live bind-mount trees into local project paths (rsync path remap). fyrst-cli refuses `--data all` on `sync local`; use the default volume list (volumes only, never DB):

| Live (`/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live`) | Local project |
| --- | --- |
| `.../files` | `files/` |
| `.../media` | `public/media/` |
| `.../thumbnail` | `public/thumbnail/` |
| `.../theme` | `public/theme/` |
| `.../sitemap` | `public/sitemap/` |

```bash
cd /path/to/your-shop
# shared .env: SHOPWARE_SHOP_ID=acme   # same as live; remote → /var/lib/shopware/data/acme/live
# .env.local: SHOPWARE_DEPLOY_ENV=dev COMPOSE_PROJECT_NAME=acme-dev + SHOPWARE_SSH_*
# (or rely on ~/.ssh/config Host live)
fyrst-cli shopware sync local --from live
# optional: --data media,files  --delete  --dry-run
shopware-cli project console cache:clear
```

Needs SSH to live (`SHOPWARE_SSH_*` in `.env.local`, or `Host live` in `~/.ssh/config`) and `rsync` on the laptop. These dirs stay **gitignored** — never commit them.

Pulling media/files does **not** copy the database. For a full content match, dump live into the local CLI DB separately, then pass the host from `APP_URL` (for example `127.0.0.1`) to `sales-channel:update:domain` (see [Sales-channel domains](#sales-channel-domains)). Scheme, port, and path stay as in the dump. Do not point local at the live database.

## Sales-channel domains

This package registers Symfony bundle `Fyrst\ShopwareCd\FyrstShopwareCdBundle` (`extra.symfony.bundle`). The bundle is empty: it registers no console command and does not shell Shopware. Flex `bundles` and an existing `config/bundles.php` line still load it. Shops need a **Composer package update** (`composer update fyrst/shopware-cd`) for that class and for overlay bytes — this is not a Flex overlay copy. Pipeline verbs (`env init`, deploy, sync, backup) stay in fyrst-cli 0.1.0; they are not console commands in this package.

**Discoverable in a Shopware app** after `composer require fyrst/shopware-cd` (or update) when Flex adds the bundle to `config/bundles.php`:

1. Flex reads `extra.symfony.bundle` / the Bundle class heuristic, **or**
2. The [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes) recipe lists the bundle in `manifest.json` (`"Fyrst\\ShopwareCd\\FyrstShopwareCdBundle": ["all"]`) so `composer recipes:update fyrst/shopware-cd` writes `config/bundles.php`. If that line is missing, add:

```php
Fyrst\ShopwareCd\FyrstShopwareCdBundle::class => ['all' => true],
```

After a non-live DB restore, fyrst-cli `sync apply` / `sync pull` passes the **host** from `APP_URL` (`.env` / `.env.local` / `.env.prod`) to Shopware’s native command. This package does not call it.

```bash
# APP_URL=https://staging.example.com → host only
bin/console sales-channel:update:domain staging.example.com
```

Skipped on live. `SHOPWARE_ALLOW_LIVE_RESTORE=1` does not turn it on. Scheme, port, and path stay as in the dump; only the host changes (`https://shop.example.com:8443/en` becomes `https://staging.example.com:8443/en`). Media CDN, plugin configs, and payment/shipping webhooks are not updated.

Local `shopware-cli project dev` after a live DB dump uses the same host rule (`APP_URL=http://127.0.0.1:8000` → `127.0.0.1`). Scheme, port, and path stay as in the dump.

## File tree (this package)

```
.
├── README.md
├── CREATE.md
├── LICENSE
├── composer.json                  # Packagist: fyrst/shopware-cd (library + Symfony bundle)
├── phpunit.xml.dist
├── overlay/                       # Flex copy-from-package → shop root (kept in the Composer dist)
│   ├── .dockerignore
│   ├── .env.example
│   ├── .github/workflows/cd.yaml
│   ├── .gitlab-ci.yaml
│   └── deploy/                    # CD Compose, edge, managed docs — no *.sh
├── src/                           # empty FyrstShopwareCdBundle
└── tests/
```

This package’s GitHub workflow is package tests only (`.github/workflows/ci.yml`). Shop CD YAML lives in `overlay/.github/workflows/cd.yaml` so it is not run here.

## Anti-patterns

- Compiling assets or themes on the VPS after the image is built
- Skipping `shopware/docker` on the `composer require` line
- Building from a shop-root `Dockerfile` (the fyrst recipe does not provide one)
- Different Dockerfiles per CI system or per deploy target (always `docker/Dockerfile`)
- Manual FTP/rsync of `vendor/`
- Git submodules for this overlay
- Custom `create` / `apply` CLIs or Composer plugins that copy files
- Putting deploy / sync / backup pipeline logic into this PHP package (operators use fyrst-cli 0.1.0 only)
- Skipping fyrst-cli 0.1.0+ on the VPS or laptop
- Dumping with fyrst-cli (**Dump stays `shopware-cli project dump`**)
- Treating recipe `deploy/*.sh` wrappers as the operator path (they are removed; call `fyrst-cli shopware …`)
- Requiring `fyrst-dev/recipes` as a Composer package (Flex uses `extra.symfony.endpoint`)
- Skipping the fyrst-dev/recipes endpoint before `composer require` (Flex copies nothing)
- Letting the fyrst Flex recipe copy or overwrite `compose.yaml`, `.gitignore`, or `.shopware-project.yaml` / `.shopware-project.yml`
- Renaming create’s `.shopware-project.yml` to `.yaml` (shopware-cli accepts both)
- Leaving create’s `COMPOSE_PROJECT_NAME=sw-shop-…` in shared `.env` (`fyrst-cli shopware env init` **strips** it)
- Putting `COMPOSE_PROJECT_NAME` or a real `SHOPWARE_DEPLOY_ENV` in shared committed `.env` (host `.env.local` holds `SHOPWARE_DEPLOY_ENV` + `COMPOSE_PROJECT_NAME=<shop-id>-<env>`)
- Putting real secrets in the Flex env block (empty shop id only; operators fill via `fyrst-cli shopware env init` or by hand)
- Forcing a registry pull on a same-host tag-and-load (`pull_policy: always` without `PULL_POLICY=never` / `SKIP_PULL=1`)
- Deploying the VPS from the CLI-managed root `compose.yaml` (use `deploy/compose.yaml` + `deploy/compose.prod.yaml` + `deploy/compose.vps.yaml`)
- Editing overlay files in fyrst-dev/recipes `root/` (this package’s `overlay/` is the source of truth; the recipe is metadata only)
- Skipping the Deployment Helper
- Committing `.env.local`, `.env.prod`, `compose.override.yaml`, `auth.json`, leftover `deploy/*.env`, secrets, or real hostnames (shared `.env` is committed with shared keys only)
- Baking media, uploads, or DB dumps into git or the image (they stay on VPS bind mounts under the derived shop/env root, or in the local project tree)
- Using a single global `/var/lib/shopware/data` without `SHOPWARE_SHOP_ID` / `SHOPWARE_DEPLOY_ENV` segments
- Hardcoding a bare Compose project name `shopware`, folder basename, or `shopware-<shop-id>` (local and VPS are `${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}`, e.g. `acme-dev` / `acme-live`)
- Running raw `docker compose` without `--env-file .env` then `.env.local` then `.env.prod` (if present). VPS Compose naming SoT is `deploy/compose.yaml` `name:` with those env files
- Requiring `SHOPWARE_DATA_ROOT` in `.env` (optional; Compose interpolates bind-mount paths from shop id + deploy env)
- Using S3/MinIO as the default way to copy live data to staging (use `fyrst-cli shopware sync`: SSH + `shopware-cli project dump` + rsync of those host dirs)
- Running VPS `fyrst-cli shopware sync {capture|apply|pull}` against local `shopware-cli project dev` (use `fyrst-cli shopware sync local`)
- Cron that pushes into live (the consumer pulls from live)
- Treating managed-host deploy as a supported CI path (`DEPLOY_TARGET=managed` is planned / not implemented)
- Going live without a resolvable post-deploy probe (`APP_URL` + `/api/_info/health-check`, or `DEPLOY_HEALTH_URL`) unless `--allow-no-deploy-health` / `ALLOW_NO_DEPLOY_HEALTH=1`

## Roadmap (ops)

Shared pipeline logic lives in [fyrst-cli](https://github.com/fyrst-dev/cli) 0.1.0. Compose/CI templates live in this package’s `overlay/`. The Flex recipe stays metadata. Sales-channel hosts after a non-live restore are Shopware `sales-channel:update:domain` (fyrst-cli passes the host from `APP_URL`; skipped on live). Package docs stay aligned with those epics:

- P0 production hardening — [recipes#10](https://github.com/fyrst-dev/recipes/issues/10)
- P1 staging & day-2 ops — [recipes#11](https://github.com/fyrst-dev/recipes/issues/11) (this package: [#14](https://github.com/fyrst-dev/shopware-cd/issues/14))
- P2 follow-ups — [recipes#12](https://github.com/fyrst-dev/recipes/issues/12)
