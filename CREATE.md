# New shop checklist

Use with the locked process: [Shopware Create & Continuous Deploy](https://app.clickup.com/90151931897/docs/2kyqjkzt-915). Full narrative is in [README.md](README.md).

**Default path is automated.** There is no git submodule. Do not treat manual copy as the primary install.

## Create (preferred)

From a clone of [fyrst-dev/shopware-cd-template](https://github.com/fyrst-dev/shopware-cd-template) (private GitHub; use SSH or a Composer GitHub token):

```bash
./bin/create <shop-name>
# or: ./scripts/create.sh <shop-name>
# optional: ./bin/create <shop-name> 6.6.x.x
```

Equivalent once `fyrst-shopware-cd` is on `PATH`:

```bash
fyrst-shopware-cd create <shop-name> [version]
```

This runs `shopware-cli project create --docker --no-interaction`, requires `shopware/docker` + `shopware/deployment-helper`, registers the private VCS repo, `composer require fyrst/shopware-cd:dev-main`, and applies the overlay.

- [ ] Shop directory created with Docker
- [ ] Flex `docker/Dockerfile` present **or** overlay `Dockerfile` at shop root as fallback
- [ ] CI `DOCKERFILE` points at the file you actually build (do not maintain two Dockerfiles)
- [ ] Shop has `.github/workflows/cd.yml`, `.gitlab-ci.yml`, `compose.yaml`, `compose.prod.yaml`, `deploy/`

## Existing shop (Composer overlay)

If the shop already exists (`shopware-cli project create` already ran):

```bash
cd <shop-name>
composer config repositories.fyrst vcs https://github.com/fyrst-dev/shopware-cd-template.git
composer config allow-plugins.fyrst/shopware-cd true
composer require shopware/docker shopware/deployment-helper
composer require fyrst/shopware-cd:dev-main
```

SSH instead of HTTPS:

```bash
composer config repositories.fyrst vcs git@github.com:fyrst-dev/shopware-cd-template.git
```

- [ ] Prefer Composer **inside** the web container when it is running: `docker compose exec web composer …`
- [ ] Overlay applied (plugin post-install). If plugins are blocked: `vendor/bin/fyrst-shopware-cd apply`
- [ ] Existing files skipped; `apply --force` only for template-managed paths
- [ ] `.env` was **not** overwritten; copy `.env.example` → `.env` yourself
- [ ] Applied overlay files **committed** in the shop (not a submodule; not only under `vendor/`)

## Secrets and hosts

Fill placeholders — never commit values.

- [ ] CI: `SHOPWARE_PACKAGES_TOKEN`
- [ ] CI: `COMPOSER_AUTH` / `auth.json` if you have private Composer repos (including this overlay on HTTPS)
- [ ] CI: registry login (`REGISTRY_USERNAME` / `REGISTRY_PASSWORD`, or platform defaults)
- [ ] CI: `SSH_PRIVATE_KEY`, `VPS_HOST`, `VPS_USER`, `VPS_PATH` (Compose primary)
- [ ] CI: `SSH_KNOWN_HOSTS` (recommended)
- [ ] Runtime: `APP_URL`, `APP_SECRET`, `DATABASE_URL`
- [ ] Runtime: `INSTALL_ADMIN_USERNAME` / `INSTALL_ADMIN_PASSWORD` / `INSTALL_ADMIN_EMAIL` (first install only)
- [ ] Optional: `DEPLOY_TARGET=managed` plus host-specific vars (see [overlay/deploy/managed/README.md](overlay/deploy/managed/README.md))
- [ ] Optional: `COMPOSE_PROFILES=redis,worker,scheduler` on the VPS if you use those services

## First pipeline

- [ ] Push to GitHub and/or GitLab (`main`)
- [ ] Build → push `:sha` (+ `:latest` on `main`) succeeds
- [ ] Deploy job SSHs to the VPS, pulls, Compose up, one-shot setup
- [ ] Storefront + admin + health/smoke URL verified
- [ ] Document shop-specific overrides (external DB, search, CDN, S3) in the shop repo README

## Anti-patterns (do not)

- [ ] Do not copy overlay files by hand unless Composer/VCS is unavailable
- [ ] Do not add this overlay as a git submodule
- [ ] Do not compile themes/assets on the VPS
- [ ] Do not add a second Dockerfile for GitLab vs GitHub or for managed hosts
- [ ] Do not rsync `vendor/` or skip `shopware-deployment-helper`
