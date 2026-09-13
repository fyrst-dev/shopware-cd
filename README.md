# fyrst/shopware-cd

Reusable **fyrst.dev** overlay for Shopware create + continuous deploy.

This repository is **not** a Shopware installation. It does not vendor Shopware core. The Packagist package [`fyrst/shopware-cd`](https://packagist.org/packages/fyrst/shopware-cd) is a **thin library only**.

This package does **not** contain overlay files. **Symfony Flex** loads CI (`.github/workflows/cd.yaml`, `.gitlab-ci.yaml`), `.dockerignore`, `.env.example`, and `deploy/` (CD Compose plus `deploy/init-env.sh`, `deploy/sync-runtime.sh` for VPS and `deploy/sync-runtime-local.sh` for local `shopware-cli project dev`) from [`fyrst-dev/recipes`](https://github.com/fyrst-dev/recipes) (`fyrst/shopware-cd/1.0/`). It does **not** copy `compose.yaml`, `.gitignore`, or `.shopware-project.yaml` — `shopware-cli project create` owns those (create writes `.shopware-project.yml`; `.yaml` is also accepted — do not rename). Flex `env` may **append** a `###> fyrst/shopware-cd ###` SoT block to `.env` (empty shop id; no secrets). It does **not** overwrite create’s whole `.env`. Then run `bash deploy/init-env.sh --shop-id <slug>`. The image build file is always [`shopware/docker`](https://github.com/shopware/docker)’s `docker/Dockerfile` — shops **must** `composer require shopware/docker` on the same line as this package.

Process (locked standard): [Shopware Create & Continuous Deploy](https://app.clickup.com/90151931897/docs/2kyqjkzt-915)

**Build once, run everywhere.** The image is identical for every target. The supported last mile is Docker Compose on a VPS. A managed container host is **planned / not implemented**.

There is **no** git submodule, **no** custom `fyrst-shopware-cd` CLI, **no** Composer plugin that copies files, and **no** Composer dependency on [`fyrst-dev/recipes`](https://github.com/fyrst-dev/recipes).

## Architecture

| Piece | Role |
| --- | --- |
| This repo ([`fyrst-dev/shopware-cd`](https://github.com/fyrst-dev/shopware-cd)) | Packagist package [`fyrst/shopware-cd`](https://packagist.org/packages/fyrst/shopware-cd): thin library. **No** shop file copies. |
| `shopware-cli project create` | Owns `compose.yaml`, `.gitignore`, `.shopware-project.yml` (create’s default; `.yaml` also accepted — do not rename), and local Docker (`shopware-cli project dev`). The fyrst Flex recipe does **not** copy those. |
| [`fyrst-dev/recipes`](https://github.com/fyrst-dev/recipes) | Owns and serves Flex overlay files at `fyrst/shopware-cd/1.0/`. Compiles [`flex/main/index.json`](https://raw.githubusercontent.com/fyrst-dev/recipes/flex/main/index.json). **Source of truth** for what shops get via Flex. |

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
bash deploy/init-env.sh --shop-id <slug>
# VPS: bash deploy/init-env.sh --shop-id <slug> --vps
```

Run Composer **inside Docker** when the web container is up (`docker compose exec web composer …`). Host PHP is often under-provisioned.

Flex then:

- `shopware/docker` → **required**. Flex copies official `docker/Dockerfile`. Image builds **always** use that file. CI `DOCKERFILE=docker/Dockerfile` (or default to that). The fyrst recipe does **not** ship a shop-root `Dockerfile`.
- `shopware/deployment-helper` → install/update at deploy time
- `fyrst/shopware-cd` → `.github/workflows/cd.yaml`, `.gitlab-ci.yaml`, `.dockerignore`, `.env.example`, `deploy/` including `deploy/compose.yaml`, `deploy/compose.prod.yaml`, `deploy/compose.vps.yaml`, `deploy/init-env.sh`, `deploy/sync-runtime.sh` (VPS), and `deploy/sync-runtime-local.sh` (local `shopware-cli project dev`) — from [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes), not this package. Does **not** copy `compose.yaml`, `.gitignore`, or `.shopware-project.yaml` / `.shopware-project.yml`. Flex `env` appends a `###> fyrst/shopware-cd ###` block (safe defaults). Finish values with `bash deploy/init-env.sh --shop-id <slug>`.

Wizard defaults for fyrst: current stable Shopware, **Docker = yes**.

Commit the files Flex copied. `vendor/` is gitignored; CI and the VPS checkout need those paths in git.

If a file already exists, Flex skips or prompts. Flex `env` **appends** to `.env`; it does **not** overwrite create’s whole `.env`. Shop-specific **local** Compose tweaks belong in `compose.override.yaml` next to the CLI-owned root `compose.yaml`.

Without the fyrst-dev/recipes endpoint, Flex installs the empty library and copies **no** overlay files.

## How to change the overlay

Overlay files are **not** in this repo. Edit them in [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes):

1. Change files under `fyrst/shopware-cd/1.0/` in [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes). Do **not** add `compose.yaml`, `.gitignore`, or `.shopware-project.yaml` / `.shopware-project.yml` to the recipe — those stay CLI-owned.
2. Push (or merge) to `main`.
3. Wait for the **Update Flex endpoint** workflow to rebuild `flex/main` (`index.json`).
4. In each shop:

```bash
composer recipes:update fyrst/shopware-cd
```

See the [recipes README](https://github.com/fyrst-dev/recipes) for endpoint details.

## Locked standard (do not fork locally)

| Topic | Decision |
| --- | --- |
| Create | `shopware-cli project create` / `npx @shopware-ag/shopware-cli` |
| CLI-owned files | `compose.yaml`, `.gitignore`, `.shopware-project.yml` (create’s default; `.yaml` also accepted — do not rename) and local Docker via CLI. Flex does **not** copy these. |
| Overlay | `extra.symfony.endpoint` (fyrst-dev/recipes first) then `composer require shopware/docker shopware/deployment-helper fyrst/shopware-cd` + Symfony Flex |
| Overlay files | CI (`.github/workflows/cd.yaml`, `.gitlab-ci.yaml`), `.dockerignore`, `.env.example`, `deploy/` (`deploy/compose.yaml`, `deploy/compose.prod.yaml`, `deploy/compose.vps.yaml`, `deploy/init-env.sh`, `deploy/sync-runtime.sh`, `deploy/sync-runtime-local.sh`) |
| Local | Docker via `shopware-cli project dev` (CLI-managed root `compose.yaml`). Live media/files: `deploy/sync-runtime-local.sh` (rsync path remap into the project tree; remote auto `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live` — not `deploy/sync-runtime.sh`) |
| Runtime | App **always** runs in Docker (`shopware/docker` / `ghcr.io/shopware/docker-base`) |
| Image | Always `docker/Dockerfile` from required `shopware/docker`. CI `DOCKERFILE=docker/Dockerfile` (or default to that). |
| Build | `shopware-cli project ci` inside that multi-stage `docker/Dockerfile` |
| CI | GitHub Actions **and** GitLab CI, same stages |
| Identity (hybrid) | Required SoT: `SHOPWARE_SHOP_ID` + `SHOPWARE_DEPLOY_ENV`. Optional `SHOPWARE_DATA_BASE`. `COMPOSE_PROJECT_NAME` and `SHOPWARE_DATA_ROOT` are **optional** — Docker Compose and sync derive `COMPOSE_PROJECT_NAME=${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` and `SHOPWARE_DATA_ROOT=${SHOPWARE_DATA_BASE:-/var/lib/shopware/data}/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}`. Compose uses those SoT vars directly. No hardcoded Compose project name `shopware`. |
| Primary deploy | Docker Compose on a VPS using `deploy/compose.yaml` + `deploy/compose.prod.yaml` + `deploy/compose.vps.yaml` (not root `compose.yaml`) |
| Runtime data | Out of git and out of the image. VPS **bind mounts** under the derived shop/env root (`{files,media,thumbnail,theme,sitemap}`). Named volumes remain only for `mysql_data` / `redis_data` (scoped by the derived Compose project name). VPS: `deploy/sync-runtime.sh` live → staging/playground/dev (SSH + dump + rsync of those host dirs; **no S3**; cron on the consumer; paths from shop id + deploy env). Local `shopware-cli project dev`: `deploy/sync-runtime-local.sh` (rsync path remap into `files/` and `public/{media,thumbnail,theme,sitemap}`; remote default `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live`) |
| Managed deploy | **Planned / not implemented** (same image contract; no CI job). Compose/VPS is the only supported last mile. |
| Deploy-time tasks | `vendor/bin/shopware-deployment-helper run --skip-theme-compile --skip-assets-install` |

Out of scope: bare Deployer/SSH without containers, Shopware PaaS as default, compiling assets on the production host, git submodules, custom create wrappers, S3 as the default VPS env-to-env copy.

## Identity (locked hybrid)

Several shops, and live + staging of the same shop, can share one VPS. Isolate them with shop id + deploy env — not a single global data directory and not a hardcoded Compose project name `shopware`.

**Required** source of truth in shop-root `.env`:

| Variable | Meaning | Example |
| --- | --- | --- |
| `SHOPWARE_SHOP_ID` | Stable shop slug (same on live, staging, and laptop) | `acme` |
| `SHOPWARE_DEPLOY_ENV` | This stack’s role | `live` / `staging` / `playground` / … |

**Optional** (Compose and sync derive these when unset):

| Variable | Meaning | When unset |
| --- | --- | --- |
| `SHOPWARE_DATA_BASE` | Host prefix for bind-mount trees | `/var/lib/shopware/data` |
| `COMPOSE_PROJECT_NAME` | Docker project name override | `${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` → `acme-live` |
| `SHOPWARE_DATA_ROOT` | Bind-mount root override | `${SHOPWARE_DATA_BASE:-/var/lib/shopware/data}/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}` |

`COMPOSE_PROJECT_NAME` and `SHOPWARE_DATA_ROOT` are **optional**. `deploy/compose.yaml` interpolates `SHOPWARE_SHOP_ID`, `SHOPWARE_DEPLOY_ENV`, and `SHOPWARE_DATA_BASE` **directly** (project name + bind-mount paths). You do **not** set expanded `COMPOSE_PROJECT_NAME` / `SHOPWARE_DATA_ROOT` for Compose to work.

**VPS warning:** `shopware-cli project create` writes `COMPOSE_PROJECT_NAME=sw-shop-…` into shop-root `.env` for local `project dev`. That env var **overrides** Compose `name:` (`${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}`). On the VPS, **comment out** that line with `bash deploy/init-env.sh --vps` (or by hand). Flex does **not** delete it on `composer require` (create owns the local flow).

After create + `composer require`, finish shop-specific `.env` values:

```bash
bash deploy/init-env.sh --shop-id acme
# VPS: add --vps --env live --image ghcr.io/example/acme
```

`--shop-id` is required unless already non-empty. The script merges missing keys from `.env.example` and does not invent MYSQL passwords or `APP_URL`. See `deploy/README.md` after Flex copies it.

**Formula:**

```text
COMPOSE_PROJECT_NAME=${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}
SHOPWARE_DATA_ROOT=${SHOPWARE_DATA_BASE:-/var/lib/shopware/data}/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}
# /var/lib/shopware/data/acme/live/{files,media,thumbnail,theme,sitemap}
# /var/lib/shopware/data/acme/staging/…
```

Set `SHOPWARE_SHOP_ID` and `SHOPWARE_DEPLOY_ENV` in shop-root `.env`. Set `SHOPWARE_DATA_BASE` only when the prefix is not `/var/lib/shopware/data`. `deploy/sync-runtime.sh` derives this host from `SHOPWARE_SHOP_ID` + `SHOPWARE_DEPLOY_ENV` (and `SHOPWARE_DATA_BASE`) and the SSH source from shop id + `live` when roots are unset. `deploy/sync-runtime-local.sh` auto-derives remote `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live`. Override with `SHOPWARE_DATA_ROOT` / `SYNC_REMOTE_DATA_ROOT` when needed.

Named volumes `mysql_data` / `redis_data` are scoped by the derived Compose project name (`COMPOSE_PROJECT_NAME=${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}`).

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
| `APP_URL` / `SALES_CHANNEL_URL` | Public shop URL |
| `APP_SECRET` | Persistent secret (`openssl rand -hex 32`) |
| `DATABASE_URL` | MySQL/MariaDB DSN |
| `SHOPWARE_SHOP_ID` | Stable shop slug (same on every stack of this shop). **Required.** |
| `SHOPWARE_DEPLOY_ENV` | This stack’s role (`live` / `staging` / …). **Required.** |
| `SHOPWARE_DATA_BASE` | Optional prefix for bind-mount trees (`/var/lib/shopware/data` when unset) |
| `COMPOSE_PROJECT_NAME` | Optional. Derived `${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}` — unique on the host. Compose uses the SoT vars directly. |
| `SHOPWARE_DATA_ROOT` | Optional bind-mount root override. Derived `${SHOPWARE_DATA_BASE:-/var/lib/shopware/data}/${SHOPWARE_SHOP_ID}/${SHOPWARE_DEPLOY_ENV}` |
| `INSTALL_ADMIN_*` | First-install admin user only |
| Store / app licence vars | Only if you ship licensed apps |

**Deploy transport (Compose / VPS):**

| Name | Purpose |
| --- | --- |
| `SSH_PRIVATE_KEY` | CI → VPS |
| `VPS_HOST` / `VPS_USER` / `VPS_PATH` | SSH target and checkout path |
| `SSH_KNOWN_HOSTS` | Recommended instead of blindly accepting host keys |

**Runtime sync (consumer VPS `.env`-style file, not CI):**

| Name | Purpose |
| --- | --- |
| `deploy/sync.env` | Copied from Flex `deploy/sync.env.example`. `SYNC_SSH_*` to live; `SYNC_ENV` = staging / playground / dev. Mode `0600`. Cron `deploy/sync-runtime.sh` on this host. |

Placeholders live in the Flex-copied `.github/workflows/cd.yaml`, `.gitlab-ci.yaml`, and `.env.example`. Flex may append SoT keys to `.env`; then run `bash deploy/init-env.sh --shop-id <slug>` (VPS: `--vps`). Copy `deploy/sync.env.example` → `deploy/sync.env` on the consumer if you sync from live.

GitLab still looks for `.gitlab-ci.yml` by default — set Settings → CI/CD → CI/CD configuration file to `.gitlab-ci.yaml`. GitHub Actions and shopware-cli load the `.yaml` names directly.

## CD pipeline

Push to GitHub and/or GitLab on `main` (or a `v*` tag):

1. **Build** — `docker buildx` with BuildKit secrets; `shopware-cli project ci` inside the `shopware-cli` image.
2. **Push** — `:git-sha` always; `:latest` on default branch; `:semver` on version tags.
3. **Deploy** — SSH to the VPS, pull image, Compose up, one-shot setup. Managed host (`DEPLOY_TARGET=managed`) is **planned / not implemented** and is not a supported CI switch.

Local day-to-day:

```bash
shopware-cli project dev
```

To pull live media/files into that checkout, use `deploy/sync-runtime-local.sh` (rsync path remap). Do **not** run `deploy/sync-runtime.sh` on a laptop — that script is VPS-only.

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

- **Primary (Compose / VPS):** `deploy/README.md` after Flex copies it. Live recommendation: uncomment `COMPOSE_PROFILES=redis,worker,scheduler` in `.env`. Staging leaves profiles unset unless you intentionally need worker/scheduler.
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

**Packagist is done.** [`fyrst/shopware-cd`](https://packagist.org/packages/fyrst/shopware-cd) is the published thin library. Flex copies **nothing** unless a configured endpoint lists a recipe for that package name.

There is **no** Composer dependency from this package on [`fyrst-dev/recipes`](https://github.com/fyrst-dev/recipes). Association is package name → recipe via `extra.symfony.endpoint`.

Shops must configure the endpoint **before** `composer require` (see [Primary path](#primary-path-only)).

[symfony/recipes-contrib](https://github.com/symfony/recipes-contrib) is **not** the primary path. An optional contrib PR may exist; do **not** wait on it. Keep using the fyrst-dev/recipes endpoint.

## Compose layout

**Local (CLI-owned, not Flex):**

- `compose.yaml` — from `shopware-cli project create`. Local Docker via `shopware-cli project dev`. Shop-specific tweaks belong in `compose.override.yaml`.
- `.gitignore` / `.shopware-project.yml` (create’s default; `.yaml` also accepted — do not rename) — also from `shopware-cli project create`

**CD / VPS (Flex-copied under `deploy/`):**

- `deploy/compose.yaml` — CD services: `web`, bundled `mysql`, optional `redis` / `setup` / `worker` / `scheduler` profiles; interpolates `SHOPWARE_SHOP_ID` / `SHOPWARE_DEPLOY_ENV` / `SHOPWARE_DATA_BASE` **directly**; **bind mounts** under the derived shop/env root (named volumes only `mysql_data` / `redis_data`, scoped by the derived Compose project name)
- `deploy/compose.prod.yaml` — VPS/prod overrides
- `deploy/compose.vps.yaml` — `pull_policy: ${PULL_POLICY:-always}` (CI/VPS default after a registry push). Same-host tag-and-load / air-gap: `PULL_POLICY=never` and `SKIP_PULL=1` (or `bash deploy/vps-release.sh --skip-pull`)
- `deploy/init-env.sh` — post-create `.env` helper (Flex `env` appends safe SoT keys; this script fills shop id / env, optional `IMAGE` / `APP_SECRET`, and `--vps` comments create’s `COMPOSE_PROJECT_NAME=sw-shop-…`)
- `deploy/vps-release.sh` — release command on the VPS (also invoked from CI). `compose run` uses `--pull never` (Compose v5 dropped `--no-build` from the run subcommand). `up` uses `--no-build`.
- `deploy/sync-runtime.sh` — VPS only: live → staging/playground/dev copy of DB + host dirs (SSH + dump + rsync; **no S3**; paths from `SHOPWARE_SHOP_ID` + `SHOPWARE_DEPLOY_ENV`, optional `SHOPWARE_DATA_BASE` / `SHOPWARE_DATA_ROOT`)
- `deploy/sync-runtime-local.sh` — laptop only: live media/files into `shopware-cli project dev` (rsync path remap; remote default `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live`; **not** `deploy/sync-runtime.sh`)

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
# .env: SHOPWARE_SHOP_ID=acme SHOPWARE_DEPLOY_ENV=staging
# Compose/sync derive COMPOSE_PROJECT_NAME=acme-staging and
# SHOPWARE_DATA_ROOT=/var/lib/shopware/data/acme/staging
sudo mkdir -p "$SHOPWARE_DATA_ROOT"/{files,media,thumbnail,theme,sitemap}
sudo chown -R 82:82 "$SHOPWARE_DATA_ROOT"
```

**No S3.** Copying live → staging / playground / dev is SSH + `mysqldump` (or Compose exec on bundled MySQL) + rsync of those host dirs, not object storage.

Flex copies **`deploy/sync-runtime.sh`**. It rsyncs those host dirs. Paths use **shop id + deploy env**: this host from `SHOPWARE_SHOP_ID` + `SHOPWARE_DEPLOY_ENV` (and optional `SHOPWARE_DATA_BASE`); the SSH source defaults to the same shop id + `live`. Override with `SHOPWARE_DATA_ROOT` / `SYNC_REMOTE_DATA_ROOT` when needed. Run it **on the consumer** (cron on staging/playground/dev). Pull from live; never auto-push into live. This script is **not** for local `shopware-cli project dev`.

```bash
# on staging / playground / dev
cd /opt/shopware/acme-staging
# .env: SHOPWARE_SHOP_ID=acme SHOPWARE_DEPLOY_ENV=staging
# COMPOSE_PROJECT_NAME and SHOPWARE_DATA_ROOT stay unset (derived)
cp deploy/sync.env.example deploy/sync.env   # SYNC_SSH_* to live; SYNC_ENV=<this env>
# Opt-in (default off; refused on live): SYNC_REWRITE_APP_URL=https://staging.example.com
bash deploy/sync-runtime.sh sync --from live --data all
```

```cron
15 2 * * * cd /opt/shopware/acme-staging && bash deploy/sync-runtime.sh sync --from live --data all
```

See `deploy/README.md` after Flex. Overlay copies live in [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes), not this package.

## Runtime files into local `shopware-cli project dev`

Local and VPS use **different paths**. `shopware-cli project dev` bind-mounts the **project tree**, not `SHOPWARE_DATA_ROOT`. Do **not** run `deploy/sync-runtime.sh` on a laptop.

Flex copies **`deploy/sync-runtime-local.sh`**. Laptop `.env` needs at least `SHOPWARE_SHOP_ID` (same slug as live). The remote path **auto-derives** `/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live` unless you override it (`--remote-data-root` / `SYNC_REMOTE_DATA_ROOT`). From the shop root it rsyncs live bind-mount trees into local project paths (rsync path remap):

| Live (`/var/lib/shopware/data/${SHOPWARE_SHOP_ID}/live`) | Local project |
| --- | --- |
| `.../files` | `files/` |
| `.../media` | `public/media/` |
| `.../thumbnail` | `public/thumbnail/` |
| `.../theme` | `public/theme/` |
| `.../sitemap` | `public/sitemap/` |

```bash
cd /path/to/your-shop
# .env: SHOPWARE_SHOP_ID=acme   # same as live; remote → /var/lib/shopware/data/acme/live
bash deploy/sync-runtime-local.sh --from live --data all
# optional: --delete  --dry-run
shopware-cli project console cache:clear
```

Needs SSH to live (for example `Host live` in `~/.ssh/config`) and `rsync` on the laptop. These dirs stay **gitignored** — never commit them.

Pulling media/files does **not** copy the database. For a full content match, dump live into the local CLI DB separately, then rewrite sales-channel URLs for `http://127.0.0.1:8000`. Do not point local at the live database.

## File tree (this package)

```
.
├── README.md
├── CREATE.md
├── LICENSE
├── composer.json                  # Packagist: fyrst/shopware-cd (library, not a plugin)
└── tests/package.test.php        # thin-package smoke tests
```

Shop overlay files live in [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes/tree/main/fyrst/shopware-cd/1.0) (`fyrst/shopware-cd/1.0/`), not here.

This package’s GitHub workflow is package tests only (`.github/workflows/ci.yml`). Shop CD YAML is served by Flex from fyrst-dev/recipes so it is not run here.

## Anti-patterns

- Compiling assets or themes on the VPS after the image is built
- Skipping `shopware/docker` on the `composer require` line
- Building from a shop-root `Dockerfile` (the fyrst recipe does not provide one)
- Different Dockerfiles per CI system or per deploy target (always `docker/Dockerfile`)
- Manual FTP/rsync of `vendor/`
- Git submodules for this overlay
- Custom `create` / `apply` CLIs or Composer plugins that copy files
- Requiring `fyrst-dev/recipes` as a Composer package (Flex uses `extra.symfony.endpoint`)
- Skipping the fyrst-dev/recipes endpoint before `composer require` (Flex copies nothing)
- Letting the fyrst Flex recipe copy or overwrite `compose.yaml`, `.gitignore`, or `.shopware-project.yaml` / `.shopware-project.yml`
- Renaming create’s `.shopware-project.yml` to `.yaml` (shopware-cli accepts both)
- Leaving create’s `COMPOSE_PROJECT_NAME=sw-shop-…` in the VPS `.env` (it overrides Compose `name:`; use `bash deploy/init-env.sh --vps`)
- Putting real secrets in the Flex env block (empty shop id only; operators fill via `deploy/init-env.sh` or by hand)
- Forcing a registry pull on a same-host tag-and-load (`pull_policy: always` without `PULL_POLICY=never` / `SKIP_PULL=1`)
- Deploying the VPS from the CLI-managed root `compose.yaml` (use `deploy/compose.yaml` + `deploy/compose.prod.yaml` + `deploy/compose.vps.yaml`)
- Editing overlay files in this repo (they are not here; change [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes))
- Skipping the Deployment Helper
- Committing `.env`, `auth.json`, `deploy/sync.env`, or real hostnames
- Baking media, uploads, or DB dumps into git or the image (they stay on VPS bind mounts under the derived shop/env root, or in the local project tree)
- Using a single global `/var/lib/shopware/data` without `SHOPWARE_SHOP_ID` / `SHOPWARE_DEPLOY_ENV` segments
- Hardcoding Compose project name `shopware` (Compose derives `COMPOSE_PROJECT_NAME=${SHOPWARE_SHOP_ID}-${SHOPWARE_DEPLOY_ENV}`)
- Requiring `COMPOSE_PROJECT_NAME` or `SHOPWARE_DATA_ROOT` in `.env` (they are optional; Compose uses those SoT vars directly)
- Using S3/MinIO as the default way to copy live data to staging (use `deploy/sync-runtime.sh`: SSH + dump + rsync of those host dirs)
- Running `deploy/sync-runtime.sh` against local `shopware-cli project dev` (that script is VPS `SHOPWARE_DATA_ROOT`; use `deploy/sync-runtime-local.sh`)
- Cron that pushes into live (the consumer pulls from live)
- Treating managed-host deploy as a supported CI path (`DEPLOY_TARGET=managed` is planned / not implemented)

## Roadmap (ops)

Overlay work lives in [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes). Package docs stay aligned with those epics:

- P0 production hardening — [recipes#10](https://github.com/fyrst-dev/recipes/issues/10)
- P1 staging & day-2 ops — [recipes#11](https://github.com/fyrst-dev/recipes/issues/11) (this package: [#14](https://github.com/fyrst-dev/shopware-cd/issues/14))
- P2 follow-ups — [recipes#12](https://github.com/fyrst-dev/recipes/issues/12)
