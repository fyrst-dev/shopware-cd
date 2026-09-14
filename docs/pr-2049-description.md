| Q             | A
| ------------- | ---
| License       | MIT
| Packagist     | https://packagist.org/packages/fyrst/shopware-cd

## Package
- Packagist: https://packagist.org/packages/fyrst/shopware-cd (`1.1.0`+)
- GitHub: https://github.com/fyrst-dev/shopware-cd
- License: MIT

## What the recipe does
Flex metadata only: `copy-from-package` of `overlay/` → shop root, plus the bundle and env keys. CI, Compose, edge, and `.env.example` live in the Composer package (`fyrst/shopware-cd` `overlay/`), not in this recipe.

Same idea as `shopware/docker`, except the overlay is copied from the package instead of from the recipe.

Shopware projects typically already have `extra.symfony.allow-contrib: true` and `flex://defaults`.

## Discoverability
While this PR is in review, fyrst also keeps a private Flex endpoint that uses the same thin `copy-from-package` manifest:

https://raw.githubusercontent.com/fyrst-dev/recipes/flex/main/index.json

This contrib recipe is the preferred path once merged and indexed. The private endpoint is optional and independent of this PR.
