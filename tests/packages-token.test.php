<?php

declare(strict_types=1);

/**
 * SHOPWARE_PACKAGES_TOKEN is optional — set only if the shop uses packages.shopware.com.
 */

$root = dirname(__DIR__);
$failures = 0;

function fyrst_token_assert(bool $ok, string $message): void
{
    global $failures;
    if ($ok) {
        fwrite(STDOUT, "ok - {$message}\n");
        return;
    }
    fwrite(STDERR, "FAIL - {$message}\n");
    $failures++;
}

$readme = (string) file_get_contents($root . '/README.md');
$create = (string) file_get_contents($root . '/CREATE.md');

fyrst_token_assert(
    str_contains($readme, 'SHOPWARE_PACKAGES_TOKEN'),
    'README names SHOPWARE_PACKAGES_TOKEN'
);
fyrst_token_assert(
    (bool) preg_match('/SHOPWARE_PACKAGES_TOKEN`\s*\|\s*Optional/i', $readme),
    'README marks SHOPWARE_PACKAGES_TOKEN as optional'
);
fyrst_token_assert(
    str_contains($readme, 'set only if the shop uses'),
    'README says set packages token only if the shop uses packages.shopware.com'
);
fyrst_token_assert(
    !preg_match('/required[^\n]{0,80}SHOPWARE_PACKAGES_TOKEN|SHOPWARE_PACKAGES_TOKEN[^\n]{0,80}required/i', $readme),
    'README does not call SHOPWARE_PACKAGES_TOKEN required'
);
fyrst_token_assert(
    str_contains($create, 'SHOPWARE_PACKAGES_TOKEN'),
    'CREATE.md names SHOPWARE_PACKAGES_TOKEN'
);
fyrst_token_assert(
    str_contains($create, 'set only if the shop uses packages.shopware.com'),
    'CREATE.md says set SHOPWARE_PACKAGES_TOKEN only if the shop uses packages.shopware.com'
);
fyrst_token_assert(
    !preg_match('/^\s*- \[ \] CI: `SHOPWARE_PACKAGES_TOKEN`\s*$/m', $create),
    'CREATE.md does not list SHOPWARE_PACKAGES_TOKEN as a bare required CI checkbox'
);

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "\nAll packages-token tests passed.\n");
exit(0);
