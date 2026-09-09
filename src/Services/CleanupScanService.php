<?php

namespace Homemove\AbTesting\Services;

use Homemove\AbTesting\Models\Experiment;
use Symfony\Component\Finder\Finder;

/**
 * Finds leftover experiment code in the host app after a variant is accepted.
 *
 * Two-pass strategy: pass 1 collects files containing the experiment-name
 * literal (every matching line is a reference). Pass 2 searches for variant
 * names, but — because names like 'control' appear everywhere — only inside
 * pass-1 files and convention locations (ab-testing config, enum dirs, files
 * whose path carries a token of the experiment name).
 */
class CleanupScanService
{
    public function scan(Experiment $experiment): array
    {
        $startedAt = microtime(true);
        $config = config('ab-testing.accept.scan', []);
        $deadline = $startedAt + (int) ($config['timeout_seconds'] ?? 60);
        $maxReferences = (int) ($config['max_references'] ?? 500);
        $maxFileSize = (int) ($config['max_file_size'] ?? 1048576);

        $variantNames = array_keys($experiment->variants ?? []);
        $losing = array_values(array_filter($variantNames, fn ($v) => $v !== $experiment->accepted_variant));
        // Only the leading (product) token: generic tokens like 'landing'
        // would pull every other experiment's mirror files into the report.
        $leadToken = explode('_', $experiment->name)[0] ?? '';
        $nameTokens = strlen($leadToken) > 3 ? [$leadToken] : [];

        $references = [];
        $scannedFiles = 0;
        $truncated = false;
        $passOneFiles = [];

        foreach ($this->files($config, $maxFileSize) as $file) {
            if (microtime(true) > $deadline || count($references) >= $maxReferences) {
                $truncated = true;
                break;
            }

            $scannedFiles++;
            $relative = ltrim(str_replace(base_path(), '', $file->getRealPath()), '/');
            $contents = $file->getContents();

            // Convention files that reference this experiment's variants
            // without naming it — pass-1 (name literal) covers the rest.
            $isConvention = $this->pathCarriesNameToken($relative, $nameTokens);

            $hasExperimentName = str_contains($contents, $experiment->name);
            if (!$hasExperimentName && !$isConvention) {
                continue;
            }

            if ($hasExperimentName) {
                $passOneFiles[$relative] = true;
            }

            foreach (explode("\n", $contents) as $i => $line) {
                if (count($references) >= $maxReferences) {
                    $truncated = true;
                    break;
                }

                $matched = null;
                if ($hasExperimentName && str_contains($line, $experiment->name)) {
                    $matched = 'experiment:' . $experiment->name;
                } else {
                    foreach ($variantNames as $variant) {
                        if (str_contains($line, "'{$variant}'") || str_contains($line, "\"{$variant}\"")) {
                            $matched = 'variant:' . $variant;
                            break;
                        }
                    }
                }

                if ($matched === null) {
                    continue;
                }

                $references[] = [
                    'file' => $relative,
                    'line' => $i + 1,
                    'snippet' => trim(mb_substr($line, 0, 300)),
                    'match' => $matched,
                    'suggested_action' => $this->suggestAction($relative, $line, $experiment, $losing),
                ];
            }
        }

        return [
            'references' => $references,
            'scanned_files' => $scannedFiles,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'truncated' => $truncated,
        ];
    }

    /** @return iterable<\Symfony\Component\Finder\SplFileInfo> */
    protected function files(array $config, int $maxFileSize): iterable
    {
        $paths = array_values(array_filter(
            array_map(
                fn ($p) => str_starts_with((string) $p, DIRECTORY_SEPARATOR) ? $p : base_path($p),
                (array) ($config['paths'] ?? [])
            ),
            'is_dir'
        ));

        if ($paths === []) {
            return [];
        }

        $extensions = (array) ($config['extensions'] ?? ['php']);
        $namePatterns = array_map(fn ($ext) => '*.' . $ext, $extensions);

        return (new Finder)
            ->files()
            ->in($paths)
            ->exclude(['vendor', 'node_modules', 'storage'])
            ->notPath(['public/build', 'public/vendor'])
            ->name($namePatterns)
            ->size('<= ' . $maxFileSize)
            ->getIterator();
    }

    protected function pathCarriesNameToken(string $relative, array $nameTokens): bool
    {
        $haystack = strtolower(basename($relative));
        if (!str_contains($haystack, 'experiment')) {
            return false;
        }

        foreach ($nameTokens as $token) {
            if (str_contains($haystack, strtolower($token))) {
                return true;
            }
        }

        return false;
    }

    protected function suggestAction(string $relative, string $line, Experiment $experiment, array $losing): string
    {
        if (str_contains($relative, 'config/ab-testing.php')) {
            return 'remove_config_entry';
        }

        if (str_contains($relative, 'Enums/')) {
            return 'review_enum';
        }

        foreach ($losing as $variant) {
            if (str_contains($line, "'{$variant}'") || str_contains($line, "\"{$variant}\"")) {
                $conditional = str_contains($line, '@variant')
                    || str_contains($line, 'isVariant')
                    || str_contains($line, '===') || str_contains($line, '==')
                    || str_contains($line, '?') || str_contains($line, 'in_array');

                return $conditional ? 'delete_losing_branch' : 'review';
            }
        }

        if ($experiment->accepted_variant
            && (str_contains($line, "'{$experiment->accepted_variant}'") || str_contains($line, "\"{$experiment->accepted_variant}\""))) {
            return 'inline_winner';
        }

        return 'review';
    }
}
