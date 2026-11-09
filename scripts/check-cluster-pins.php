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
 */
function assert_cluster_pins(array $expected, array $installedPhp, array $jsonPackages): int
{
    $failed = false;
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
            fwrite(STDERR, sprintf("check-cluster-pins: %s is not installed.\n", $name));
            $failed = true;
            continue;
        }

        $pkg = $versions[$name];
        $aliases = $pkg['aliases'] ?? [];
        if (!in_array($want['alias'], $aliases, true)) {
            fwrite(STDERR, sprintf(
                "check-cluster-pins: %s alias mismatch (expected %s in aliases %s).\n",
                $name,
                $want['alias'],
                json_encode($aliases)
            ));
            $failed = true;
        }

        $reference = $pkg['reference'] ?? null;
        if (!is_string($reference) || $reference === '') {
            fwrite(STDERR, sprintf("check-cluster-pins: %s has no reference in installed.php.\n", $name));
            $failed = true;
        } elseif (!hash_equals(strtolower($want['reference']), strtolower($reference))) {
            fwrite(STDERR, sprintf(
                "check-cluster-pins: %s installed.php reference mismatch (expected %s, got %s).\n",
                $name,
                $want['reference'],
                $reference
            ));
            $failed = true;
        }

        if (!isset($jsonReferences[$name])) {
            fwrite(STDERR, sprintf("check-cluster-pins: %s is missing from installed.json packages.\n", $name));
            $failed = true;
            continue;
        }

        if (!hash_equals(strtolower($want['reference']), $jsonReferences[$name])) {
            fwrite(STDERR, sprintf(
                "check-cluster-pins: %s installed.json source.reference mismatch (expected %s, got %s).\n",
                $name,
                $want['reference'],
                $jsonReferences[$name]
            ));
            $failed = true;
        }
    }

    return $failed ? 1 : 0;
}

/**
 * @param array{versions: array<string, array<string, mixed>>} $installedPhp
 */
function assert_installed_php_reference_fail(array $installedPhp, string $package, string $wrongReference): int
{
    $installedPhp = $installedPhp;
    $installedPhp['versions'][$package]['reference'] = $wrongReference;

    return assert_cluster_pins(
        [$package => ['alias' => '2.2.99', 'reference' => '63f848a4b1dbe2f2cb87acb3ff32596661e45417']],
        $installedPhp,
        []
    );
}

/**
 * @param list<array<string, mixed>> $jsonPackages
 */
function assert_installed_json_reference_fail(array $jsonPackages, string $package, string $wrongReference): int
{
    foreach ($jsonPackages as &$pkg) {
        if (($pkg['name'] ?? '') !== $package) {
            continue;
        }
        if (!isset($pkg['source']) || !is_array($pkg['source'])) {
            $pkg['source'] = [];
        }
        $pkg['source']['reference'] = $wrongReference;
    }
    unset($pkg);

    $versions = require __DIR__ . '/../vendor/composer/installed.php';

    return assert_cluster_pins(
        [$package => ['alias' => '2.2.99', 'reference' => '63f848a4b1dbe2f2cb87acb3ff32596661e45417']],
        $versions,
        $jsonPackages
    );
}

$jsonPackages = merge_installed_json_packages($installedJson);

$phpDemoExit = assert_installed_php_reference_fail($installedPhp, 'phpstan/phpstan', str_repeat('0', 40));
if ($phpDemoExit === 0) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo should have failed on installed.php reference.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo failed on installed.php reference, as required.\n");

$jsonDemoExit = assert_installed_json_reference_fail($jsonPackages, 'phpstan/phpstan', str_repeat('1', 40));
if ($jsonDemoExit === 0) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo should have failed on installed.json source.reference.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo failed on installed.json source.reference, as required.\n");

$liveExit = assert_cluster_pins($expected, $installedPhp, $jsonPackages);
if ($liveExit !== 0) {
    exit($liveExit);
}

fwrite(STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n");
exit(0);
