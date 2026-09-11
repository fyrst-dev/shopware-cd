# fyrst/shopware-cd

Reusable **fyrst.dev** overlay for Shopware create + continuous deploy.

This repository is **not** a Shopware installation. It does not vendor Shopware core. The Packagist package [`fyrst/shopware-cd`](https://packagist.org/packages/fyrst/shopware-cd) is a **thin library only**.

This package does **not** contain overlay files. **Symfony Flex** loads CI (`.github/workflows/cd.yaml`, `.gitlab-ci.yaml`), `.dockerignore`, `.env.example`, and `deploy/` (CD Compose plus `deploy/sync-runtime.sh`) from [`fyrst-dev/recipes`](https://github.com/fyrst-dev/recipes) (`fyrst/shopware-cd/1.0/`). It does **not** copy `compose.yaml`, `.gitignore`, or `.shopware-project.yaml` — `shopware-cli project create` owns those (and local Docker via the CLI). The image build file is always [`shopware/docker`](https://github.com/shopware/docker)’s `docker/Dockerfile` — shops **must** `composer require shopware/docker` on the same line as this package.

Process (locked standard): [Shopware Create & Continuous Deploy](https://app.clickup.com/90151931897/docs/2kyqjkzt-915)

**Build once, run everywhere.** The image is identical for every target. Only the last mile forks: Docker Compose on a VPS (primary) vs a managed container host (optional).

There is **no** git submodule, **no** custom `fyrst-shopware-cd` CLI, **no** Composer plugin that copies files, and **no** Composer dependency on [`fyrst-dev/recipes`](https://github.com/fyrst-dev/recipes).

## Architecture

| Piece | Role |
| --- | --- |
| This repo ([`fyrst-dev/shopware-cd`](https://github.com/fyrst-dev/shopware-cd)) | Packagist package [`fyrst/shopware-cd`](https://packagist.org/packages/fyrst/shopware-cd): thin library. **No** shop file copies. |
| `shopware-cli project create` | Owns `compose.yaml`, `.gitignore`, `.shopware-project.yaml`, and local Docker (`shopware-cli project dev`). The fyrst Flex recipe does **not** copy those. |
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
```

Run Composer **inside Docker** when the web container is up (`docker compose exec web composer …`). Host PHP is often under-provisioned.

Flex then:

- `shopware/docker` → **required**. Flex copies official `docker/Dockerfile`. Image builds **always** use that file. CI `DOCKERFILE=docker/Dockerfile` (or default to that). The fyrst recipe does **not** ship a shop-root `Dockerfile`.
- `shopware/deployment-helper` → install/update at deploy time
- `fyrst/shopware-cd` → `.github/workflows/cd.yaml`, `.gitlab-ci.yaml`, `.dockerignore`, `.env.example`, `deploy/` including `deploy/compose.yaml`, `deploy/compose.prod.yaml`, `deploy/compose.vps.yaml`, `deploy/sync-runtime.sh` (from [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes), not this package). Does **not** copy `compose.yaml`, `.gitignore`, or `.shopware-project.yaml`.

Wizard defaults for fyrst: current stable Shopware, **Docker = yes**.

Commit the files Flex copied. `vendor/` is gitignored; CI and the VPS checkout need those paths in git.

If a file already exists, Flex skips or prompts (it does not overwrite `.env`). Shop-specific **local** Compose tweaks belong in `compose.override.yaml` next to the CLI-owned root `compose.yaml`.

Without the fyrst-dev/recipes endpoint, Flex installs the empty library and copies **no** overlay files.

## How to change the overlay

Overlay files are **not** in this repo. Edit them in [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes):

1. Change files under `fyrst/shopware-cd/1.0/` in [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes). Do **not** add `compose.yaml`, `.gitignore`, or `.shopware-project.yaml` to the recipe — those stay CLI-owned.
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
| CLI-owned files | `compose.yaml`, `.gitignore`, `.shopware-project.yaml` (and local Docker via CLI). Flex does **not** copy these. |
| Overlay | `extra.symfony.endpoint` (fyrst-dev/recipes first) then `composer require shopware/docker shopware/deployment-helper fyrst/shopware-cd` + Symfony Flex |
| Overlay files | CI (`.github/workflows/cd.yaml`, `.gitlab-ci.yaml`), `.dockerignore`, `.env.example`, `deploy/` (`deploy/compose.yaml`, `deploy/compose.prod.yaml`, `deploy/compose.vps.yaml`, `deploy/sync-runtime.sh`) |
| Local | Docker via `shopware-cli project dev` (CLI-managed root `compose.yaml`) |
| Runtime | App **always** runs in Docker (`shopware/docker` / `ghcr.io/shopware/docker-base`) |
| Image | Always `docker/Dockerfile` from required `shopware/docker`. CI `DOCKERFILE=docker/Dockerfile` (or default to that). |
| Build | `shopware-cli project ci` inside that multi-stage `docker/Dockerfile` |
| CI | GitHub Actions **and** GitLab CI, same stages |
| Primary deploy | Docker Compose on a VPS using `deploy/compose.yaml` + `deploy/compose.prod.yaml` + `deploy/compose.vps.yaml` (not root `compose.yaml`) |
| Runtime data | Out of git and out of the image. VPS Docker volumes. `deploy/sync-runtime.sh` live → staging/playground/dev (SSH + dump + volume archives; **no S3**; cron on the consumer) |
| Optional deploy | Managed container host (same image; only the deploy job differs) |
| Deploy-time tasks | `vendor/bin/shopware-deployment-helper run --skip-theme-compile --skip-assets-install` |

Out of scope: bare Deployer/SSH without containers, Shopware PaaS as default, compiling assets on the production host, git submodules, custom create wrappers, S3 as the default VPS env-to-env copy.

## Secrets and hosts (CI + runtime)

**Build-time (CI variables / GitHub secrets — never in git):**

| Name | Purpose |
| --- | --- |
| `SHOPWARE_PACKAGES_TOKEN` | [packages.shopware.com](https://packages.shopware.com) |
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

Placeholders live in the Flex-copied `.github/workflows/cd.yaml`, `.gitlab-ci.yaml`, and `.env.example`. Copy `.env.example` → `.env` yourself. Copy `deploy/sync.env.example` → `deploy/sync.env` on the consumer if you sync from live.

GitLab still looks for `.gitlab-ci.yml` by default — set Settings → CI/CD → CI/CD configuration file to `.gitlab-ci.yaml`. GitHub Actions and shopware-cli load the `.yaml` names directly.

## CD pipeline

Push to GitHub and/or GitLab on `main` (or a `v*` tag):

1. **Build** — `docker buildx` with BuildKit secrets; `shopware-cli project ci` inside the `shopware-cli` image.
2. **Push** — `:git-sha` always; `:latest` on default branch; `:semver` on version tags.
3. **Deploy** — primary: SSH to the VPS, pull image, Compose up, one-shot setup. Optional: managed host when `DEPLOY_TARGET=managed`.

Local day-to-day:

```bash
shopware-cli project dev
```

## Primary vs optional deploy

```
                    ┌─ shopware-cli project ci ─┐
  git push  ──────►  │     multi-stage image    │ ──► registry (:sha / :latest / :semver)
                    └──────────────────────────┘
                                   │
            ┌──────────────────────┴──────────────────────┐
            ▼                                             ▼
   Primary: Compose / VPS                      Optional: managed host
   SSH → pull → compose up web                 Same image, different job
   + setup one-shot                             (platform API / their registry)
```

- **Primary (Compose / VPS):** `deploy/README.md` after Flex copies it.
- **Optional (managed host):** `deploy/managed/README.md`. Gate with `DEPLOY_TARGET=managed`.

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
- `.gitignore` / `.shopware-project.yaml` — also from `shopware-cli project create`

**CD / VPS (Flex-copied under `deploy/`):**

- `deploy/compose.yaml` — CD services: `web`, bundled `mysql`, optional `redis` / `setup` / `worker` / `scheduler` profiles; named volumes for runtime media/files
- `deploy/compose.prod.yaml` — VPS/prod overrides
- `deploy/compose.vps.yaml` — pull policy / no rebuild on the VPS
- `deploy/vps-release.sh` — release command on the VPS (also invoked from CI)
- `deploy/sync-runtime.sh` — live → staging/playground/dev copy of DB + runtime volumes (SSH + dump + archives; **no S3**)

The VPS uses those deploy Compose files. It does **not** use the CLI-managed root `compose.yaml`.

Setup always:

```bash
vendor/bin/shopware-deployment-helper run \
  --skip-theme-compile \
  --skip-assets-install
```

## Runtime data sync (VPS, no S3)

DB, media, documents, thumbnails, and related uploads stay **out of git** and **out of the image**. `.dockerignore` excludes those paths. On the VPS they live in Docker named volumes (`files`, `media`, `thumbnail`, `theme`, `sitemap`) plus the database (`mysql_data` or DBaaS). A new image pull keeps the same volumes.

**No S3.** Copying live → staging / playground / dev is SSH + `mysqldump` (or Compose exec on bundled MySQL) + volume archives, not object storage.

Flex copies **`deploy/sync-runtime.sh`**. Run it **on the consumer** (cron on staging/playground/dev). Pull from live; never auto-push into live.

```bash
# on staging / playground / dev
cd /opt/shopware/<shop>
cp deploy/sync.env.example deploy/sync.env   # SYNC_SSH_* to live; SYNC_ENV=<this env>
bash deploy/sync-runtime.sh sync --from live --data all
```

```cron
15 2 * * * cd /opt/shopware/staging && bash deploy/sync-runtime.sh sync --from live --data all
```

See `deploy/README.md` after Flex. Overlay copies live in [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes), not this package.

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
- Letting the fyrst Flex recipe copy or overwrite `compose.yaml`, `.gitignore`, or `.shopware-project.yaml`
- Deploying the VPS from the CLI-managed root `compose.yaml` (use `deploy/compose.yaml` + `deploy/compose.prod.yaml` + `deploy/compose.vps.yaml`)
- Editing overlay files in this repo (they are not here; change [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes))
- Skipping the Deployment Helper
- Committing `.env`, `auth.json`, `deploy/sync.env`, or real hostnames
- Baking media, uploads, or DB dumps into git or the image (they stay on VPS volumes)
- Using S3/MinIO as the default way to copy live data to staging (use `deploy/sync-runtime.sh`: SSH + dump + volume archives)
- Cron that pushes into live (the consumer pulls from live)
