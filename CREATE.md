# New shop checklist

Use with the locked process: [Shopware Create & Continuous Deploy](https://app.clickup.com/90151931897/docs/2kyqjkzt-915). Full narrative is in [README.md](README.md).

## Create

- [ ] `npx @shopware-ag/shopware-cli project create <shop-name>` (or `shopware-cli project create <shop-name>`)
- [ ] Docker = **yes** in the wizard (required; the app always runs in a container)
- [ ] Optional: pin a Shopware version as the second argument (`latest` | `6.6.x.x` | `dev-trunk`)
- [ ] `cd <shop-name>`

## Packages

- [ ] `composer require shopware/docker shopware/deployment-helper` **inside the web container** (`docker compose exec web composer require …`)
- [ ] Confirm Flex dropped `docker/Dockerfile` (preferred) or keep this overlay’s root `Dockerfile` as fallback
- [ ] Point CI `DOCKERFILE` at the file you actually build (do not maintain two Dockerfiles)

## Overlay

- [ ] Copy/merge files from [fyrst-dev/shopware-cd-template](https://github.com/fyrst-dev/shopware-cd-template)
- [ ] Merge `.shopware-project.yml` (keep `compatibility_date`, browserslist, empty hooks; add `build.bundles` for custom PHP bundles)
- [ ] Merge Compose files; put local-only tweaks in `compose.override.yaml`
- [ ] Copy `.env.example` → `.env` (local) and to the VPS `.env` (mode `0600`)

## Secrets and hosts

Fill placeholders — never commit values.

- [ ] CI: `SHOPWARE_PACKAGES_TOKEN`
- [ ] CI: `COMPOSER_AUTH` / `auth.json` if you have private Composer repos
- [ ] CI: registry login (`REGISTRY_USERNAME` / `REGISTRY_PASSWORD`, or platform defaults)
- [ ] CI: `SSH_PRIVATE_KEY`, `VPS_HOST`, `VPS_USER`, `VPS_PATH` (Compose primary)
- [ ] CI: `SSH_KNOWN_HOSTS` (recommended)
- [ ] Runtime: `APP_URL`, `APP_SECRET`, `DATABASE_URL`
- [ ] Runtime: `INSTALL_ADMIN_USERNAME` / `INSTALL_ADMIN_PASSWORD` / `INSTALL_ADMIN_EMAIL` (first install only; change the helper default password)
- [ ] Optional: `DEPLOY_TARGET=managed` plus host-specific vars (see [deploy/managed/README.md](deploy/managed/README.md))
- [ ] Optional: `COMPOSE_PROFILES=redis,worker,scheduler` on the VPS if you use those services

## First pipeline

- [ ] Push to GitHub and/or GitLab (`main`)
- [ ] Build → push `:sha` (+ `:latest` on `main`) succeeds
- [ ] Deploy job SSHs to the VPS, pulls, Compose up, one-shot setup
- [ ] Storefront + admin + health/smoke URL verified
- [ ] Document shop-specific overrides (external DB, search, CDN, S3) in the shop repo README

## Anti-patterns (do not)

- [ ] Do not compile themes/assets on the VPS
- [ ] Do not add a second Dockerfile for GitLab vs GitHub or for managed hosts
- [ ] Do not rsync `vendor/` or skip `shopware-deployment-helper`
