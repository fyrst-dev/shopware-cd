@diimpp Thanks — that was the right call.

The overlay now lives in the Composer package, not in this recipe:

- Package: https://packagist.org/packages/fyrst/shopware-cd (`1.1.0`+)
- Files: `overlay/` in https://github.com/fyrst-dev/shopware-cd (CI, Compose, edge, `.env.example`)
- This recipe is Flex metadata only: `copy-from-package` of `overlay/` → shop root, plus the existing bundle and env keys
- The old `root/` tree (including the deploy shell scripts) is gone from the PR

`manifest.json` is now:

{
    "copy-from-package": {
        "overlay/": ""
    }
}

Ready for another look.
