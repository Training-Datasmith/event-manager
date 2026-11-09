<?php

declare(strict_types=1);

/**
 * Verify org-forked cluster packages match the Training-Datasmith composer index.
 */

$expected = [
    'phpstan/phpstan' => [
        'alias' => '2.2.99',
        'reference' => '63f848a4b1dbe2f2cb87acb3ff32596661e45417',
    ],
];

$installedPhpPath = __DIR__ . '/../vendor/composer/installed.php';
$installedJsonPath = __DIR__ . '/../vendor/composer/installed.json';

if (!is_readable($installedPhpPath)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n");
    exit(1);
}

if (!is_readable($installedJsonPath)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.json not found; run composer update first.\n");
    exit(1);
}

$installedRaw = file_get_contents($installedJsonPath);
if ($installedRaw === false) {
    fwrite(STDERR, "check-cluster-pins: could not read installed.json.\n");
    exit(1);
}

/** @var array{packages?: list<array<string, mixed>>, packages-dev?: list<array<string, mixed>>, 'dev-package-names'?: list<string>} $installedJson */
$installedJson = json_decode($installedRaw, true, 512, JSON_THROW_ON_ERROR);

/** @var array{versions: array<string, array<string, mixed>>} $installedPhp */
$installedPhp = require $installedPhpPath;

/**
 * @return list<array<string, mixed>>
 */
function merge_installed_json_packages(array $installedJson): array
{
    $packages = $installedJson['packages'] ?? [];

    if (isset($installedJson['packages-dev']) && is_array($installedJson['packages-dev'])) {
        $packages = array_merge($packages, $installedJson['packages-dev']);
    }

    return $packages;
}

/**
 * @param array{versions: array<string, array<string, mixed>>} $installedPhp
 * @param list<array<string, mixed>> $jsonPackages
 *
 * @return list<string>
 */
function collect_pin_failures(array $expected, array $installedPhp, array $jsonPackages): array
{
    $failures = [];
    $versions = $installedPhp['versions'] ?? [];

    /** @var array<string, string> $jsonReferences */
    $jsonReferences = [];
    foreach ($jsonPackages as $package) {
        if (!isset($package['name'], $package['source']['reference']) || !is_string($package['name'])) {
            continue;
        }
        $jsonReferences[$package['name']] = strtolower((string) $package['source']['reference']);
    }

    foreach ($expected as $name => $want) {
        if (!isset($versions[$name])) {
            $failures[] = sprintf('check-cluster-pins: %s is not installed.', $name);
            continue;
        }

        $pkg = $versions[$name];
        $aliases = $pkg['aliases'] ?? [];
        if (!in_array($want['alias'], $aliases, true)) {
            $failures[] = sprintf(
                'check-cluster-pins: %s alias mismatch (expected %s in aliases %s).',
                $name,
                $want['alias'],
                json_encode($aliases)
            );
        }

        $reference = $pkg['reference'] ?? null;
        if (!is_string($reference) || $reference === '') {
            $failures[] = sprintf('check-cluster-pins: %s has no reference in installed.php.', $name);
        } elseif (!hash_equals(strtolower($want['reference']), strtolower($reference))) {
            $failures[] = sprintf(
                'check-cluster-pins: %s installed.php reference mismatch (expected %s, got %s).',
                $name,
                $want['reference'],
                $reference
            );
        }

        if (!isset($jsonReferences[$name])) {
            $failures[] = sprintf('check-cluster-pins: %s is missing from installed.json packages.', $name);
            continue;
        }

        if (!hash_equals(strtolower($want['reference']), $jsonReferences[$name])) {
            $failures[] = sprintf(
                'check-cluster-pins: %s installed.json source.reference mismatch (expected %s, got %s).',
                $name,
                $want['reference'],
                $jsonReferences[$name]
            );
        }
    }

    return $failures;
}

/**
 * @param list<string> $failures
 */
function emit_pin_failures(array $failures): void
{
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . "\n");
    }
}

/**
 * @param list<string> $failures
 */
function failures_include_installed_php_reference_mismatch(array $failures, string $package): bool
{
    $needle = sprintf('check-cluster-pins: %s installed.php reference mismatch', $package);

    foreach ($failures as $failure) {
        if (str_starts_with($failure, $needle)) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $failures
 */
function failures_include_installed_json_reference_mismatch(array $failures, string $package): bool
{
    $needle = sprintf('check-cluster-pins: %s installed.json source.reference mismatch', $package);

    foreach ($failures as $failure) {
        if (str_starts_with($failure, $needle)) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $failures
 */
function failures_include_installed_json_issue(array $failures): bool
{
    foreach ($failures as $failure) {
        if (str_contains($failure, 'installed.json')) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<string> $failures
 */
function failures_include_installed_php_reference_issue(array $failures, string $package): bool
{
    foreach ($failures as $failure) {
        if (!str_contains($failure, $package)) {
            continue;
        }
        if (str_contains($failure, 'installed.php reference')) {
            return true;
        }
    }

    return false;
}

/**
 * @param array{versions: array<string, array<string, mixed>>} $installedPhp
 *
 * @return array{versions: array<string, array<string, mixed>>}
 */
function with_wrong_installed_php_reference(array $installedPhp, string $package, string $wrongReference): array
{
    $copy = $installedPhp;
    $copy['versions'][$package]['reference'] = $wrongReference;

    return $copy;
}

/**
 * @param list<array<string, mixed>> $jsonPackages
 *
 * @return list<array<string, mixed>>
 */
function with_wrong_installed_json_reference(array $jsonPackages, string $package, string $wrongReference): array
{
    $copy = $jsonPackages;
    foreach ($copy as $index => $pkg) {
        if (($pkg['name'] ?? '') !== $package) {
            continue;
        }
        if (!isset($pkg['source']) || !is_array($pkg['source'])) {
            $pkg['source'] = [];
        }
        $pkg['source']['reference'] = $wrongReference;
        $copy[$index] = $pkg;
        break;
    }

    return $copy;
}

$jsonPackages = merge_installed_json_packages($installedJson);
$packageName = 'phpstan/phpstan';

$phpDemoInstalled = with_wrong_installed_php_reference($installedPhp, $packageName, str_repeat('0', 40));
$phpDemoFailures = collect_pin_failures($expected, $phpDemoInstalled, $jsonPackages);
if ($phpDemoFailures === []) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo should have failed on installed.php reference.\n");
    exit(1);
}
if (!failures_include_installed_php_reference_mismatch($phpDemoFailures, $packageName)) {
    fwrite(STDERR, "check-cluster-pins: installed.php demo did not report an installed.php reference mismatch.\n");
    emit_pin_failures($phpDemoFailures);
    exit(1);
}
if (failures_include_installed_json_issue($phpDemoFailures)) {
    fwrite(STDERR, "check-cluster-pins: installed.php demo must not report any installed.json failure.\n");
    emit_pin_failures($phpDemoFailures);
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo failed on installed.php reference, as required.\n");

$jsonDemoPackages = with_wrong_installed_json_reference($jsonPackages, $packageName, str_repeat('1', 40));
$jsonDemoFailures = collect_pin_failures($expected, $installedPhp, $jsonDemoPackages);
if ($jsonDemoFailures === []) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo should have failed on installed.json source.reference.\n");
    exit(1);
}
if (!failures_include_installed_json_reference_mismatch($jsonDemoFailures, $packageName)) {
    fwrite(STDERR, "check-cluster-pins: installed.json demo did not report a source.reference mismatch.\n");
    emit_pin_failures($jsonDemoFailures);
    exit(1);
}
if (failures_include_installed_php_reference_issue($jsonDemoFailures, $packageName)) {
    fwrite(STDERR, "check-cluster-pins: installed.json demo must not report an installed.php reference failure.\n");
    emit_pin_failures($jsonDemoFailures);
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo failed on installed.json source.reference, as required.\n");

$liveFailures = collect_pin_failures($expected, $installedPhp, $jsonPackages);
if ($liveFailures !== []) {
    emit_pin_failures($liveFailures);
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n");
exit(0);
