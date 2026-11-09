#!/usr/bin/env bash
set -euo pipefail

export COMPOSER_ALLOW_SUPERUSER=1

strip_ansi_to_file() {
    local source_file="$1"
    local stripped_file="$2"
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${source_file}" >"${stripped_file}"
}

reject_deprecation_lines() {
    local label="$1"
    local stripped_file="$2"
    if grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped_file}" >/dev/null; then
        echo "${label}: output contains Warning, Deprecated, or Notice lines" >&2
        grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped_file}" >&2 || true
        exit 1
    fi
}

php_version_out="$(mktemp)"
php_version_stripped="$(mktemp)"
php -r 'echo PHP_VERSION, "\n";' >"${php_version_out}" 2>&1
cat "${php_version_out}"
strip_ansi_to_file "${php_version_out}" "${php_version_stripped}"
reject_deprecation_lines "php version" "${php_version_stripped}"
if ! grep -E '^8\.1\.34$' "${php_version_stripped}" >/dev/null; then
    echo 'php: missing strict 8.1.34 version line' >&2
    exit 1
fi
rm -f "${php_version_out}" "${php_version_stripped}"

php -r '
$v = PHP_VERSION;
if (version_compare($v, "8.1.0", "<")) {
    fwrite(STDERR, "PHP {$v} is older than required 8.1.0\n");
    exit(1);
}
'

COMPOSER_URL="https://getcomposer.org/download/2.8.10/composer.phar"
COMPOSER_SHA256="28dbb6bd8bef31479c7985b774c130a8bda37dbe63c35b56f6cb6bc377427573"
curl -fsSL -o /tmp/composer.phar "${COMPOSER_URL}"
echo "${COMPOSER_SHA256} /tmp/composer.phar" | sha256sum -c -

composer_out="$(mktemp)"
composer_stripped="$(mktemp)"
set +e
php /tmp/composer.phar update --no-interaction --no-ansi --prefer-dist --no-progress >"${composer_out}" 2>&1
composer_status=$?
set -e
cat "${composer_out}"
strip_ansi_to_file "${composer_out}" "${composer_stripped}"
reject_deprecation_lines "composer update" "${composer_stripped}"
if [[ ${composer_status} -ne 0 ]]; then
    rm -f "${composer_out}" "${composer_stripped}"
    exit "${composer_status}"
fi
if ! grep -Fxq 'Generating autoload files' "${composer_stripped}"; then
    echo 'composer update: missing exact success line' >&2
    exit 1
fi
rm -f "${composer_out}" "${composer_stripped}"

pin_out="$(mktemp)"
pin_stripped="$(mktemp)"
set +e
php scripts/check-cluster-pins.php >"${pin_out}" 2>&1
pin_status=$?
set -e
cat "${pin_out}"
strip_ansi_to_file "${pin_out}" "${pin_stripped}"
reject_deprecation_lines "check-cluster-pins" "${pin_stripped}"
if [[ ${pin_status} -ne 0 ]]; then
    exit "${pin_status}"
fi
grep -Fxq 'check-cluster-pins: wrong-SHA demo failed on installed.php reference, as required.' "${pin_stripped}"
grep -Fxq 'check-cluster-pins: wrong-SHA demo failed on installed.json source.reference, as required.' "${pin_stripped}"
grep -Fxq 'check-cluster-pins: all cluster packages match expected SHAs and aliases.' "${pin_stripped}"
rm -f "${pin_out}" "${pin_stripped}"

audit_out="$(mktemp)"
audit_stripped="$(mktemp)"
set +e
php /tmp/composer.phar audit --no-interaction --no-ansi >"${audit_out}" 2>&1
audit_status=$?
set -e
cat "${audit_out}"
strip_ansi_to_file "${audit_out}" "${audit_stripped}"
reject_deprecation_lines "composer audit" "${audit_stripped}"
if [[ ${audit_status} -ne 0 ]]; then
    exit "${audit_status}"
fi
if ! grep -Fxq 'No security vulnerability advisories found.' "${audit_stripped}"; then
    echo 'composer audit: missing exact success line' >&2
    exit 1
fi
rm -f "${audit_out}" "${audit_stripped}"

phpunit_out="$(mktemp)"
phpunit_stripped="$(mktemp)"
set +e
vendor/bin/phpunit --colors=never --no-coverage >"${phpunit_out}" 2>&1
phpunit_status=$?
set -e
cat "${phpunit_out}"
strip_ansi_to_file "${phpunit_out}" "${phpunit_stripped}"
reject_deprecation_lines "phpunit" "${phpunit_stripped}"
if [[ ${phpunit_status} -ne 0 ]]; then
    exit "${phpunit_status}"
fi
if ! grep -E '^OK \(8 tests, 19 assertions\)$' "${phpunit_stripped}" >/dev/null; then
    echo 'phpunit: missing strict OK success line' >&2
    exit 1
fi
rm -f "${phpunit_out}" "${phpunit_stripped}"

phpstan_out="$(mktemp)"
phpstan_stripped="$(mktemp)"
set +e
COLUMNS=100 vendor/bin/phpstan analyse --no-progress --no-ansi --memory-limit=1G >"${phpstan_out}" 2>&1
phpstan_status=$?
set -e
cat "${phpstan_out}"
strip_ansi_to_file "${phpstan_out}" "${phpstan_stripped}"
reject_deprecation_lines "phpstan" "${phpstan_stripped}"
if [[ ${phpstan_status} -ne 0 ]]; then
    exit "${phpstan_status}"
fi
if ! grep -E '^ \[OK\] No errors {85}$' "${phpstan_stripped}" >/dev/null; then
    echo 'phpstan: missing anchored [OK] No errors success line' >&2
    exit 1
fi
rm -f "${phpstan_out}" "${phpstan_stripped}"

echo 'system-test: all steps passed.'
