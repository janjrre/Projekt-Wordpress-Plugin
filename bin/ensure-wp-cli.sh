#!/usr/bin/env bash
# WP-CLI setup is best-effort in setup-php: verify it, then use the official
# signed-release checksum feed if setup-php omitted the executable.
set -euo pipefail

if command -v wp >/dev/null 2>&1 && wp --info >/dev/null 2>&1; then
  echo "WP-CLI already available: $(command -v wp)"
  exit 0
fi

: "${RUNNER_TEMP:?RUNNER_TEMP is required}"
: "${GITHUB_PATH:?GITHUB_PATH is required}"
directory="$(mktemp -d "${RUNNER_TEMP}/uop-wp-cli.XXXXXXXX")"
base="https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar"
echo "WP-CLI absent; retrieving the official stable Phar with checksum validation"
curl -fsSL --retry 5 --retry-all-errors --connect-timeout 15 --max-time 120 "${base}/wp-cli.phar" -o "${directory}/wp-cli.phar"
curl -fsSL --retry 5 --retry-all-errors --connect-timeout 15 --max-time 30 "${base}/wp-cli.phar.sha512" -o "${directory}/wp-cli.phar.sha512"
expected="$(tr -d '\r\n[:space:]' < "${directory}/wp-cli.phar.sha512")"
if [[ ! "${expected}" =~ ^[[:xdigit:]]{128}$ ]]; then
  echo "::error::Official WP-CLI checksum did not contain a SHA-512 digest"
  exit 1
fi
printf '%s  %s\n' "${expected}" "${directory}/wp-cli.phar" | sha512sum --check --status
printf '#!/usr/bin/env bash\nexec php %q "$@"\n' "${directory}/wp-cli.phar" > "${directory}/wp"
chmod 755 "${directory}/wp"
echo "${directory}" >> "${GITHUB_PATH}"
export PATH="${directory}:${PATH}"
wp --info
