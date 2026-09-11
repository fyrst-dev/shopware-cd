<?php

declare(strict_types=1);

namespace Fyrst\ShopwareCd;

final class ApplyResult
{
    /** @var list<string> */
    public array $written = [];

    /** @var list<string> */
    public array $forced = [];

    /** @var list<string> */
    public array $skipped = [];

    /** @var list<string> */
    public array $identical = [];

    /** @var list<string> */
    public array $protected = [];

    public string $projectDir = '';

    public string $overlayDir = '';

    public bool $force = false;

    public bool $dryRun = false;

    public function render(bool $quiet = false): string
    {
        $mode = [];
        if ($this->dryRun) {
            $mode[] = 'dry-run';
        }
        if ($this->force) {
            $mode[] = 'force';
        }
        $modeLabel = $mode === [] ? '' : ' (' . implode(', ', $mode) . ')';

        if ($quiet) {
            return sprintf(
                "fyrst/shopware-cd apply%s: written=%d skipped=%d identical=%d protected=%d\n",
                $modeLabel,
                count($this->written),
                count($this->skipped),
                count($this->identical),
                count($this->protected)
            );
        }

        $out = "fyrst/shopware-cd apply{$modeLabel}\n";
        $out .= '  project : ' . $this->projectDir . "\n";
        $out .= '  overlay : ' . $this->overlayDir . "\n";
        $out .= $this->section('written', $this->written);
        $out .= $this->section('skipped (already exists; use --force to overwrite template-managed paths)', $this->skipped);
        $out .= $this->section('identical', $this->identical);
        $out .= $this->section('protected (never written: .env, auth.json, shop app code)', $this->protected);
        if ($this->forced !== []) {
            $out .= $this->section('overwritten (--force)', $this->forced);
        }
        $out .= sprintf(
            "  summary : %d written, %d skipped, %d identical, %d protected\n",
            count($this->written),
            count($this->skipped),
            count($this->identical),
            count($this->protected)
        );

        return $out;
    }

    /**
     * @param list<string> $files
     */
    private function section(string $label, array $files): string
    {
        $out = sprintf("  %-8s %d\n", $label . ':', count($files));
        foreach ($files as $file) {
            $out .= "           {$file}\n";
        }

        return $out;
    }
}
