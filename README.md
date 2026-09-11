# fyrst/shopware-cd

Reusable **fyrst.dev** overlay for Shopware create + continuous deploy.

This repository is **not** a Shopware installation. It does not vendor Shopware core. The Packagist package is a thin library; **Symfony Flex** copies the shop files (same pattern as [`shopware/docker`](https://github.com/shopware/docker)).

Process (locked standard): [Shopware Create & Continuous Deploy](https://app.clickup.com/90151931897/docs/2kyqjkzt-915)

**Build once, run everywhere.** The image is identical for every target. Only the last mile forks: Docker Compose on a VPS (primary) vs a managed container host (optional).

There is **no** git submodule, **no** custom `fyrst-shopware-cd` CLI, and **no** Composer plugin that copies files.

## Primary path (only)

```bash
shopware-cli project create <shop-name>
# or: npx @shopware-ag/shopware-cli project create <shop-name>
# optional version pin: shopware-cli project create <shop-name> 6.6.x.x
cd <shop-name>
composer require shopware/docker shopware/deployment-helper fyrst/shopware-cd
```

Run Composer **inside Docker** when the web container is up (`docker compose exec web composer …`). Host PHP is often under-provisioned.

Flex then:

- `shopware/docker` → official `docker/Dockerfile` (prefer this; keep this recipe’s root `Dockerfile` as fallback; set CI `DOCKERFILE=docker/Dockerfile`)
- `shopware/deployment-helper` → install/update at deploy time
- `fyrst/shopware-cd` → dual CI, Compose, `deploy/`, `.shopware-project.yaml`, `.env.example`, `.dockerignore`

Wizard defaults for fyrst: current stable Shopware, **Docker = yes**.

Commit the files Flex copied. `vendor/` is gitignored; CI and the VPS checkout need those paths in git.

Refresh after a recipe update:

```bash
composer recipes:update fyrst/shopware-cd
```

If a file already exists, Flex skips or prompts (it does not overwrite `.env`). Shop-specific Compose tweaks belong in `compose.override.yaml`.

## Locked standard (do not fork locally)

| Topic | Decision |
| --- | --- |
| Create | `shopware-cli project create` / `npx @shopware-ag/shopware-cli` |
| Overlay | `composer require … fyrst/shopware-cd` + Symfony Flex recipe |
| Local | Docker via `shopware-cli project dev` |
| Runtime | App **always** runs in Docker (`shopware/docker` / `ghcr.io/shopware/docker-base`) |
| Build | `shopware-cli project ci` inside a multi-stage Dockerfile |
| CI | GitHub Actions **and** GitLab CI, same stages |
| Primary deploy | Docker Compose on a VPS (pull image, up web, one-shot setup) |
| Optional deploy | Managed container host (same image; only the deploy job differs) |
| Deploy-time tasks | `vendor/bin/shopware-deployment-helper run --skip-theme-compile --skip-assets-install` |

Out of scope: bare Deployer/SSH without containers, Shopware PaaS as default, compiling assets on the production host, git submodules, custom create wrappers.

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

Placeholders live in the copied `.github/workflows/cd.yaml`, `.gitlab-ci.yaml`, and `.env.example`. Copy `.env.example` → `.env` yourself.

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

Do **not** maintain a second Dockerfile per target or per CI system.

## Image contract

| Trigger | Tags |
| --- | --- |
| Every successful build (non-PR) | `:<git-sha>` (full SHA) |
| Default branch (`main`) | also `:latest` |
| Git tag `v*` | also `:semver` (`1.2.3`, `1.2`) |

PHP is pinned to **8.3** via `PHP_VERSION` in the Dockerfile / Compose build args.

## How the Flex recipe is registered

Shopware projects already enable contrib recipes (`extra.symfony.allow-contrib: true`) and typically include `flex://defaults` (official recipes **and** [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib)).

Until the recipe is on contrib **and** this package is on Packagist, `composer require fyrst/shopware-cd` will install an empty library and Flex will not copy files. Remaining publisher steps:

### 1. Packagist

1. Tag a release (`1.0.0`) on [fyrst-dev/shopware-cd-template](https://github.com/fyrst-dev/shopware-cd-template).
2. Submit the public GitHub repo at [packagist.org/packages/submit](https://packagist.org/packages/submit) as `fyrst/shopware-cd`.
3. Enable GitHub Service Hook / Packagist auto-update.

### 2. recipes-contrib PR (preferred, near-native)

The recipe in this repo is already in contrib layout:

```
flex-recipe/fyrst/shopware-cd/1.0/
  manifest.json
  post-install.txt
  root/          # copy-from-recipe → shop project root
```

Checklist:

1. Fork [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib).
2. Copy this tree into the fork at **`fyrst/shopware-cd/`** (same files as under `flex-recipe/fyrst/shopware-cd/`).
3. Open a PR whose title does **not** contain `WIP`.
4. Wait for Symfony Bot validation (no errors).
5. Get a review approval from someone who is not the PR author.
6. A Symfony Core Merger (or contrib auto-merge rules) merges it.
7. Wait for the `flex/main` index sync on recipes-contrib.
8. Shops: `composer require fyrst/shopware-cd` — Flex copies files.

See [recipes-contrib contributing](https://github.com/symfony/recipes-contrib) and [Creating recipes](https://github.com/symfony/recipes#creating-recipes).

### 3. Optional: `fyrst-dev/recipes` while waiting for contrib

Same layout as Shopware’s [`shopware/recipes`](https://github.com/shopware/recipes) (vendor folders at repo root, not nested under `flex-recipe/`). Copy `flex-recipe/fyrst/` to `fyrst/` in that repo and add Symfony’s flex compiler workflow:

```yaml
# .github/workflows/flex-update.yml
name: Update Flex endpoint
on:
  push:
    branches: [main]
jobs:
  call-flex-update:
    uses: symfony/recipes/.github/workflows/callable-flex-update.yml@main
    permissions:
      contents: write
    with:
      branch: main
      contrib: true
    secrets:
      token: ${{ secrets.GITHUB_TOKEN }}
```

Then prepend the compiled index **before** Shopware / defaults (Shopware shops already set `extra.symfony.endpoint`):

```bash
composer config extra.symfony.allow-contrib true
# keep shopware/recipes + flex://defaults; prepend fyrst
```

Example `extra.symfony.endpoint` list:

```json
[
  "https://raw.githubusercontent.com/fyrst-dev/recipes/flex/main/index.json",
  "https://raw.githubusercontent.com/shopware/recipes/flex/main/index.json",
  "flex://defaults"
]
```

Remove the fyrst endpoint after recipes-contrib has synced.

## Compose layout (after Flex)

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
├── README.md
├── CREATE.md
├── LICENSE
├── composer.json                  # Packagist: fyrst/shopware-cd (library, not a plugin)
└── flex-recipe/fyrst/shopware-cd/1.0/
    ├── manifest.json
    ├── post-install.txt
    └── root/                      # files Flex copies into the shop
        ├── Dockerfile             # fallback; prefer Flex docker/Dockerfile
        ├── .dockerignore
        ├── .shopware-project.yaml
        ├── compose.yaml
        ├── compose.prod.yaml
        ├── .env.example
        ├── .gitignore
        ├── .github/workflows/cd.yaml
        ├── .gitlab-ci.yaml
        └── deploy/
```

This package’s GitHub workflow is package tests only (`.github/workflows/ci.yml`). Shop CD YAML lives in the recipe `root/` so it is not run here.

## Anti-patterns

- Compiling assets or themes on the VPS after the image is built
- Different Dockerfiles per CI system or per deploy target
- Manual FTP/rsync of `vendor/`
- Git submodules for this overlay
- Custom `create` / `apply` CLIs or Composer plugins that copy files
- Skipping the Deployment Helper
- Committing `.env`, `auth.json`, or real hostnames
