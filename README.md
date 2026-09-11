# shopware-cd-template (`fyrst/shopware-cd`)

Reusable **fyrst.dev** overlay for Shopware create + continuous deploy, applied as a **Composer package**. This repository is **not** a Shopware installation and does not vendor Shopware core.

Process (locked standard): [Shopware Create & Continuous Deploy](https://app.clickup.com/90151931897/docs/2kyqjkzt-915)

**Build once, run everywhere.** The image is identical for every target. Only the last mile forks: Docker Compose on a VPS (primary) vs a managed container host (optional).

There is **no** git submodule, **no** manual copy as the primary path, and **no** public `raw.githubusercontent.com` curl installer (this GitHub repo is private).

## Recommended commands

### New shop (one command)

From a clone of this repo, or after `composer global require` of the package:

```bash
fyrst-shopware-cd create <shop-name>
# optional version pin:
fyrst-shopware-cd create <shop-name> 6.6.x.x
# equivalent:
./bin/create <shop-name>
./scripts/create.sh <shop-name>
```

That runs `shopware-cli` / `npx @shopware-ag/shopware-cli project create --docker`, then:

```bash
composer require shopware/docker shopware/deployment-helper
composer config repositories.fyrst vcs https://github.com/fyrst-dev/shopware-cd-template.git
composer require fyrst/shopware-cd:dev-main
```

Overlay files land at shop-root paths (`.github/workflows/cd.yml`, `compose.yaml`, `deploy/`, …). No submodule is added.

### Existing shop (after `shopware-cli project create`)

The shop already uses Composer, and private GitHub auth is the same mechanism teams use for private packages:

```bash
cd <shop-name>
composer config repositories.fyrst vcs https://github.com/fyrst-dev/shopware-cd-template.git
composer config allow-plugins.fyrst/shopware-cd true
composer require shopware/docker shopware/deployment-helper
composer require fyrst/shopware-cd:dev-main
```

`composer require fyrst/shopware-cd` installs the package **and** applies `overlay/` into the project (Composer plugin + post-install/update). **Commit the applied files** in the shop (`cd.yml`, Compose, `deploy/`, …). `vendor/` is gitignored; CI and the VPS checkout need those paths in git. To re-apply later:

```bash
vendor/bin/fyrst-shopware-cd apply          # skip files that already exist
vendor/bin/fyrst-shopware-cd apply --force    # overwrite template-managed paths
```

Never overwrites `.env` or shop app code (`custom/`, `src/`, …).

### Private GitHub (VCS repo)

This package is not on Packagist. Point Composer at the private git repo (HTTPS + token, or SSH):

```bash
# HTTPS (Composer uses github-oauth / GH_TOKEN / auth.json)
composer config repositories.fyrst vcs https://github.com/fyrst-dev/shopware-cd-template.git

# SSH (uses your git credentials / deploy key)
composer config repositories.fyrst vcs git@github.com:fyrst-dev/shopware-cd-template.git

composer require fyrst/shopware-cd:dev-main
```

GitHub token for HTTPS (once per machine, not committed):

```bash
composer config --global github-oauth.github.com <token>
```

Path checkout for local work on the overlay:

```bash
composer config repositories.fyrst path /path/to/shopware-cd-template
composer require fyrst/shopware-cd:@dev
```

Allow the plugin once per shop (Composer 2.2+):

```bash
composer config allow-plugins.fyrst/shopware-cd true
```

If plugins are disabled, the package still installs; run `vendor/bin/fyrst-shopware-cd apply` yourself.

Companion packages (not required by this overlay, so this repo never pulls Shopware core):

- `shopware/docker` — Flex recipe, official multi-stage Dockerfile (typically `docker/Dockerfile`). **Prefer that file**; keep the overlay root `Dockerfile` as fallback. Point CI at it with `DOCKERFILE=docker/Dockerfile`.
- `shopware/deployment-helper` — install/update at deploy time. Require it in the shop (`bin/create` does).

Run Composer **inside Docker** when the web container is up (`docker compose exec web composer …`). Host PHP is often under-provisioned (`memory_limit`). `bin/create` uses `docker compose exec web composer` when `web` is running.

## Locked standard (do not fork locally)

| Topic | Decision |
| --- | --- |
| Create | `npx @shopware-ag/shopware-cli project create <shop>` (or `shopware-cli`); fyrst wrapper: `fyrst-shopware-cd create` |
| Local | Docker via `shopware-cli project dev` |
| Runtime | App **always** runs in Docker (`shopware/docker` / `ghcr.io/shopware/docker-base`) |
| Build | `shopware-cli project ci` inside a multi-stage Dockerfile |
| CI | GitHub Actions **and** GitLab CI, same stages |
| Primary deploy | Docker Compose on a VPS (pull image, up web, one-shot setup) |
| Optional deploy | Managed container host (same image; only the deploy job differs) |
| Deploy-time tasks | `vendor/bin/shopware-deployment-helper run --skip-theme-compile --skip-assets-install` |

Out of scope: bare Deployer/SSH without containers, Shopware PaaS as default, compiling assets on the production host, git submodules.

## Conflict policy

| Situation | Default | `--force` |
| --- | --- | --- |
| File missing in the shop | write | write |
| File exists, different | **skip** | overwrite if it is overlay-managed |
| File exists, identical | identical (no write) | identical |
| `.env`, `.env.local`, `.env.prod`, `auth.json` | **never write** | **never write** |
| `custom/`, `src/`, `public/`, `vendor/` | **never write** | **never write** |

Shop-specific Compose tweaks belong in `compose.override.yaml` (gitignored). Skip-by-default means `composer update fyrst/shopware-cd` will only add **new** overlay files unless you pass `--force` / `FYRST_SHOPWARE_CD_FORCE=1`.

To skip auto-apply: `FYRST_SHOPWARE_CD_SKIP_APPLY=1` or `extra.fyrst-shopware-cd.skip-apply: true` in the shop `composer.json`.

## Secrets and hosts (CI + runtime)

**Build-time (CI variables / GitHub secrets — never in git):**

| Name | Purpose |
| --- | --- |
| `SHOPWARE_PACKAGES_TOKEN` | [packages.shopware.com](https://packages.shopware.com) |
| `COMPOSER_AUTH` | Optional JSON for private Composer repos (`auth.json`) |
| `REGISTRY_*` | Push the image (`REGISTRY_USERNAME` / `REGISTRY_PASSWORD`, or GitHub `GITHUB_TOKEN` / GitLab `CI_REGISTRY_*`) |
| `REGISTRY_IMAGE` | Optional override of the image name |

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

Placeholders live in the applied `overlay/.github/workflows/cd.yml`, `.gitlab-ci.yml`, and `.env.example`. Copy `.env.example` → `.env` yourself; apply will not create `.env`.

## CD pipeline

Push to GitHub and/or GitLab on `main` (or a `v*` tag). The shop pipeline:

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

- **Primary (Compose / VPS):** [overlay/deploy/README.md](overlay/deploy/README.md) (copied to `deploy/README.md` in the shop).
- **Optional (managed host):** [overlay/deploy/managed/README.md](overlay/deploy/managed/README.md). Gate with `DEPLOY_TARGET=managed`.

Do **not** maintain a second Dockerfile per target or per CI system.

## Image contract

| Trigger | Tags |
| --- | --- |
| Every successful build (non-PR) | `:<git-sha>` (full SHA) |
| Default branch (`main`) | also `:latest` |
| Git tag `v*` | also `:semver` (`1.2.3`, `1.2`) |

PHP is pinned to **8.3** via `PHP_VERSION` in the Dockerfile / Compose build args.

## CI stages (identical on GitHub and GitLab)

1. Checkout
2. **Build** image (`docker buildx`, secrets `packages_token` / `composer_auth`)
3. **Push** extra tags (`:latest`, semver)
4. **Deploy** (Compose SSH **or** managed, via `DEPLOY_TARGET`)

`shopware-cli` is used **inside** the Dockerfile build stage, not as a separate runner install.

Production deploy is limited to the default branch and version tags.

Shop CD files live under `overlay/` in this package. After apply they are at the **shop** root. This package’s own GitHub workflow is package tests only (`.github/workflows/ci.yml`), not shop CD.

## Compose layout (after apply)

- `compose.yaml` — shared services: `web`, bundled `mysql`, optional `redis` / `setup` / `worker` / `scheduler` profiles
- `compose.prod.yaml` — VPS/prod overrides
- `deploy/compose.vps.yaml` — pull policy / no rebuild on the VPS
- `deploy/vps-release.sh` — release command on the VPS (also invoked from CI)

Setup always:

```bash
vendor/bin/shopware-deployment-helper run \
  --skip-theme-compile \
  --skip-assets-install
```

## File tree

```
.
├── README.md                      # this package
├── CREATE.md
├── composer.json                  # fyrst/shopware-cd (Composer plugin)
├── bin/fyrst-shopware-cd          # apply | create
├── bin/apply                      # thin wrapper
├── bin/create
├── scripts/create.sh
├── src/                           # PHP CLI + Composer plugin
└── overlay/                       # files copied into the shop root
    ├── Dockerfile                 # fallback; prefer Flex docker/Dockerfile
    ├── .dockerignore
    ├── .shopware-project.yml
    ├── compose.yaml
    ├── compose.prod.yaml
    ├── .env.example
    ├── .gitignore
    ├── .github/workflows/cd.yml    # → shop .github/workflows/cd.yml
    ├── .gitlab-ci.yml             # → shop .gitlab-ci.yml
    └── deploy/
        ├── README.md
        ├── compose.vps.yaml
        ├── vps-release.sh
        └── managed/README.md
```

## Anti-patterns

- Compiling assets or themes on the VPS after the image is built
- Different Dockerfiles per CI system or per deploy target
- Manual FTP/rsync of `vendor/`
- Git submodules for this overlay
- Skipping the Deployment Helper and inventing per-shop install scripts
- Committing `.env`, `auth.json`, or real hostnames
- Copy-pasting overlay files by hand as the default (use `composer require` / `fyrst-shopware-cd create`)
