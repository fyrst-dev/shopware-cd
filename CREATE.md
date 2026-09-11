# New shop checklist

Use with the locked process: [Shopware Create & Continuous Deploy](https://app.clickup.com/90151931897/docs/2kyqjkzt-915). Full narrative is in [README.md](README.md).

**Default path is near-native Shopware:** `shopware-cli` + Flex endpoint + `composer require` + Symfony Flex. No git submodule. No fyrst create/apply CLI.

This package (`fyrst/shopware-cd`) is a thin Packagist library. It does **not** contain overlay files; Flex loads them from [`fyrst-dev/recipes`](https://github.com/fyrst-dev/recipes). There is **no** Composer dependency on that recipes repo.

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
- [ ] `extra.symfony.endpoint` is **fyrst-dev/recipes**, then shopware/recipes, then `flex://defaults` — set **before** `composer require`
- [ ] Flex dropped `docker/Dockerfile` from `shopware/docker` (preferred) **or** the fyrst recipe’s root `Dockerfile` as fallback (from fyrst-dev/recipes)
- [ ] CI `DOCKERFILE` points at the file you actually build (do not maintain two Dockerfiles)
- [ ] Flex copied `.github/workflows/cd.yaml`, `.gitlab-ci.yaml`, `compose.yaml`, `compose.prod.yaml`, `deploy/`
- [ ] Copied overlay files **committed** in the shop (`vendor/` is gitignored)
- [ ] `.env` was **not** written by Flex; copy `.env.example` → `.env` yourself

If Flex did not copy files, the shop is missing the [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes) endpoint. See [README.md](README.md). Refresh later with `composer recipes:update fyrst/shopware-cd`.

To change overlay files: edit [fyrst-dev/recipes](https://github.com/fyrst-dev/recipes), push `main`, wait for the flex-update workflow, then `composer recipes:update fyrst/shopware-cd` in shops. Do not look for overlay copies in this package.

## Secrets and hosts

Fill placeholders — never commit values.

- [ ] CI: `SHOPWARE_PACKAGES_TOKEN`
- [ ] CI: `COMPOSER_AUTH` / `auth.json` if you have private Composer repos
- [ ] CI: registry login (`REGISTRY_USERNAME` / `REGISTRY_PASSWORD`, or platform defaults)
- [ ] CI: `SSH_PRIVATE_KEY`, `VPS_HOST`, `VPS_USER`, `VPS_PATH` (Compose primary)
- [ ] CI: `SSH_KNOWN_HOSTS` (recommended)
- [ ] Runtime: `APP_URL`, `APP_SECRET`, `DATABASE_URL`
- [ ] Runtime: `INSTALL_ADMIN_USERNAME` / `INSTALL_ADMIN_PASSWORD` / `INSTALL_ADMIN_EMAIL` (first install only)
- [ ] Optional: `DEPLOY_TARGET=managed` plus host-specific vars (see `deploy/managed/README.md` after Flex)
- [ ] Optional: `COMPOSE_PROFILES=redis,worker,scheduler` on the VPS if you use those services

## First pipeline

- [ ] Push to GitHub and/or GitLab (`main`)
- [ ] Build → push `:sha` (+ `:latest` on `main`) succeeds
- [ ] Deploy job SSHs to the VPS, pulls, Compose up, one-shot setup
- [ ] Storefront + admin + health/smoke URL verified
- [ ] Document shop-specific overrides (external DB, search, CDN, S3) in the shop repo README

## Anti-patterns (do not)

- [ ] Do not add a fyrst create wrapper or git submodule
- [ ] Do not skip the fyrst-dev/recipes endpoint before `composer require` (Flex copies nothing)
- [ ] Do not expect overlay files to live in this Packagist package
- [ ] Do not compile themes/assets on the VPS
- [ ] Do not add a second Dockerfile for GitLab vs GitHub or for managed hosts
- [ ] Do not rsync `vendor/` or skip `shopware-deployment-helper`
