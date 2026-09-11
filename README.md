# shopware-cd-template

Reusable **fyrst.dev** overlay for Shopware create + continuous deploy.

This repository is **not** a Shopware installation. It does not vendor Shopware core and must not be bootstrapped with `composer create-project`. Teams copy or merge these files into a shop **after** `shopware-cli project create`.

Process (locked standard): [Shopware Create & Continuous Deploy](https://app.clickup.com/90151931897/docs/2kyqjkzt-915)

**Build once, run everywhere.** The image is identical for every target. Only the last mile forks: Docker Compose on a VPS (primary) vs a managed container host (optional).

## Locked standard (do not fork locally)

| Topic | Decision |
| --- | --- |
| Create | `npx @shopware-ag/shopware-cli project create <shop>` (or `shopware-cli`) |
| Local | Docker via `shopware-cli project dev` |
| Runtime | App **always** runs in Docker (`shopware/docker` / `ghcr.io/shopware/docker-base`) |
| Build | `shopware-cli project ci` inside a multi-stage Dockerfile |
| CI | GitHub Actions **and** GitLab CI, same stages |
| Primary deploy | Docker Compose on a VPS (pull image, up web, one-shot setup) |
| Optional deploy | Managed container host (same image; only the deploy job differs) |
| Deploy-time tasks | `vendor/bin/shopware-deployment-helper run --skip-theme-compile --skip-assets-install` |

Out of scope: bare Deployer/SSH without containers, Shopware PaaS as default, compiling assets on the production host.

## Adopt this template on a new shop

### 1. Create the shop

```bash
npx @shopware-ag/shopware-cli project create <shop-name>
# or: shopware-cli project create <shop-name>
# optional version pin: shopware-cli project create <shop-name> 6.6.x.x
cd <shop-name>
```

Wizard defaults for fyrst: current stable Shopware, **Docker = yes**. Advanced CI templates from the CLI are optional; still merge the dual-CI files from this overlay.

### 2. Require Docker + deployment helper

Run Composer **inside Docker** (host PHP is usually under-provisioned):

```bash
# after shopware-cli project dev has started, or:
docker compose exec web composer require shopware/docker shopware/deployment-helper
```

- `shopware/docker` — Symfony Flex recipe that installs/updates the official multi-stage Dockerfile (typically `docker/Dockerfile`). **Prefer that file** and keep this overlay’s root `Dockerfile` as reference/fallback.
- `shopware/deployment-helper` — install/update/extensions at deploy time.

### 3. Copy / merge these overlay files

Copy from this repository into the shop, or merge if the CLI already generated a file (`compose.yaml`, `.shopware-project.yml`):

| Overlay file | What to do |
| --- | --- |
| `Dockerfile` | Use as fallback if you are **not** using the Flex recipe. If Flex wrote `docker/Dockerfile`, point CI at that path (`DOCKERFILE=docker/Dockerfile`) and do **not** maintain two Dockerfiles. |
| `.dockerignore` | Merge; keep Shopware sources, exclude `.git` / `.env` / `vendor` / `node_modules`. |
| `.shopware-project.yml` | Merge with the generated file. Keep `compatibility_date`, browserslist, hooks. |
| `compose.yaml` / `compose.prod.yaml` | Merge local CLI compose with this production-oriented stack. Put shop-specific local tweaks in `compose.override.yaml` (gitignored if it contains secrets). |
| `.github/workflows/cd.yml` | GitHub CD. |
| `.gitlab-ci.yml` | GitLab CD (same stages). |
| `deploy/` | VPS release script + optional managed-host notes. |
| `.env.example` | Copy to `.env` / VPS `.env` and fill values. **Never commit secrets.** |
| `.gitignore` | Merge with the shop’s gitignore. |

See [CREATE.md](CREATE.md) for a short checklist.

### 4. Set secrets and hosts (CI + runtime)

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

Placeholders and TODOs live in `.github/workflows/cd.yml`, `.gitlab-ci.yml`, and `.env.example`. No real hostnames belong in this overlay.

### 5. Run CD

Push to GitHub and/or GitLab on `main` (or a `v*` tag). The pipeline:

1. **Build** — `docker buildx` with BuildKit secrets; `shopware-cli project ci` inside the `shopware-cli` image.
2. **Push** — `:git-sha` always; `:latest` on default branch; `:semver` on version tags.
3. **Deploy** — primary: SSH to the VPS, pull image, Compose up, one-shot setup. Optional: managed host when `DEPLOY_TARGET=managed`.

Local day-to-day remains:

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

- **Primary (Compose / VPS):** the shop’s runtime is Docker Compose. CI does not compile themes on the server. See [deploy/README.md](deploy/README.md).
- **Optional (managed host):** still the same Dockerfile and tags. Swap only the **deploy** job (and optionally push to the host’s registry). See [deploy/managed/README.md](deploy/managed/README.md). Gate with `DEPLOY_TARGET=managed`.

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

Production deploy is limited to the default branch and version tags. Other branches / MRs may build (and optionally push a SHA tag) but do not deploy.

The workflow files no-op on this overlay repo itself (no `composer.json`). They are meant to run in a real shop.

## Compose layout

- [`compose.yaml`](compose.yaml) — shared services: `web`, bundled `mysql` (or document an external DSN and remove the service), optional `redis` profile, `setup` one-shot profile, optional `worker` / `scheduler` profiles.
- [`compose.prod.yaml`](compose.prod.yaml) — VPS/prod overrides (restart, resources, extra env file).
- [`deploy/compose.vps.yaml`](deploy/compose.vps.yaml) — pull policy / no rebuild on the VPS.

Release command on the VPS (also invoked from CI): [`deploy/vps-release.sh`](deploy/vps-release.sh).

Setup always:

```bash
vendor/bin/shopware-deployment-helper run \
  --skip-theme-compile \
  --skip-assets-install
```

Themes and assets were already produced by `shopware-cli project ci` in the image. Rebuilding them on the VPS wastes time and drifts from the artifact.

## File tree

```
.
├── README.md
├── CREATE.md
├── Dockerfile                 # reference / fallback (prefer Flex docker/Dockerfile)
├── .dockerignore
├── .shopware-project.yml
├── compose.yaml
├── compose.prod.yaml
├── .env.example
├── .gitignore
├── .github/workflows/cd.yml
├── .gitlab-ci.yml
└── deploy/
    ├── README.md              # primary: Compose / VPS
    ├── compose.vps.yaml
    ├── vps-release.sh
    └── managed/
        └── README.md          # optional: managed container host
```

## Anti-patterns

- Compiling assets or themes on the VPS after the image is built
- Different Dockerfiles per CI system or per deploy target
- Manual FTP/rsync of `vendor/`
- Skipping the Deployment Helper and inventing per-shop install scripts
- Committing `.env`, `auth.json`, or real hostnames
