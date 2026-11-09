#!/usr/bin/env bash
set -euo pipefail

export COMPOSER_ALLOW_SUPERUSER=1

fail_deprecation_output() {
    local label="$1"
    local file="$2"
    local stripped
    stripped="$(mktemp)"
    sed -E 's/\x1b\[[0-9;]*[[:alpha:]]//g' "${file}" >"${stripped}"
    if grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >/dev/null; then
        echo "${label}: output contains Warning, Deprecated, or Notice lines" >&2
        grep -E '(^|[[:space:]])(PHP[[:space:]]+)?(Warning|Deprecated|Notice):' "${stripped}" >&2 || true
        rm -f "${stripped}"
        exit 1
    fi
    rm -f "${stripped}"
}

php_version_out="$(mktemp)"
php -r 'echo PHP_VERSION, "\n";' >"${php_version_out}" 2>&1
cat "${php_version_out}"
fail_deprecation_output "php version" "${php_version_out}"
if ! grep -E '^8\.1\.34$' "${php_version_out}" >/dev/null; then
    echo 'php: missing strict 8.1.34 version line' >&2
    exit 1
fi
rm -f "${php_version_out}"

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
set +e
php /tmp/composer.phar update --no-interaction --no-ansi --prefer-dist --no-progress >"${composer_out}" 2>&1
composer_status=$?
set -e
cat "${composer_out}"
fail_deprecation_output "composer update" "${composer_out}"
if [[ ${composer_status} -ne 0 ]]; then
    rm -f "${composer_out}"
    exit "${composer_status}"
fi
if ! grep -Fxq 'Generating autoload files' "${composer_out}"; then
    echo 'composer update: missing exact success line' >&2
    exit 1
fi
rm -f "${composer_out}"

pin_out="$(mktemp)"
set +e
php scripts/check-cluster-pins.php >"${pin_out}" 2>&1
pin_status=$?
set -e
cat "${pin_out}"
fail_deprecation_output "check-cluster-pins" "${pin_out}"
if [[ ${pin_status} -ne 0 ]]; then
    exit "${pin_status}"
fi
grep -Fxq 'check-cluster-pins: wrong-SHA demo failed on installed.php reference, as required.' "${pin_out}"
grep -Fxq 'check-cluster-pins: wrong-SHA demo failed on installed.json source.reference, as required.' "${pin_out}"
grep -Fxq 'check-cluster-pins: all cluster packages match expected SHAs and aliases.' "${pin_out}"
rm -f "${pin_out}"

audit_out="$(mktemp)"
set +e
php /tmp/composer.phar audit --no-interaction --no-ansi >"${audit_out}" 2>&1
audit_status=$?
set -e
cat "${audit_out}"
fail_deprecation_output "composer audit" "${audit_out}"
if [[ ${audit_status} -ne 0 ]]; then
    exit "${audit_status}"
fi
if ! grep -Fxq 'No security vulnerability advisories found.' "${audit_out}"; then
    echo 'composer audit: missing exact success line' >&2
    exit 1
fi
rm -f "${audit_out}"

phpunit_out="$(mktemp)"
set +e
vendor/bin/phpunit --colors=never --no-coverage >"${phpunit_out}" 2>&1
phpunit_status=$?
set -e
cat "${phpunit_out}"
fail_deprecation_output "phpunit" "${phpunit_out}"
if [[ ${phpunit_status} -ne 0 ]]; then
    exit "${phpunit_status}"
fi
if ! grep -E '^OK \([0-9]+ tests, [0-9]+ assertions\)$' "${phpunit_out}" >/dev/null; then
    echo 'phpunit: missing strict OK success line' >&2
    exit 1
fi
rm -f "${phpunit_out}"

phpstan_out="$(mktemp)"
set +e
COLUMNS=100 vendor/bin/phpstan analyse --no-progress --no-ansi --memory-limit=1G >"${phpstan_out}" 2>&1
phpstan_status=$?
set -e
cat "${phpstan_out}"
fail_deprecation_output "phpstan" "${phpstan_out}"
if [[ ${phpstan_status} -ne 0 ]]; then
    exit "${phpstan_status}"
fi
if ! grep -E '^ \[OK\] No errors {85}$' "${phpstan_out}" >/dev/null; then
    echo 'phpstan: missing anchored [OK] No errors success line' >&2
    exit 1
fi
rm -f "${phpstan_out}"

echo 'system-test: all steps passed.'
