#!/usr/bin/env bash
# PGP-05 SECURITY HOLD. The former WP-CLI/PHP-bootstrap remote probe is retired.
# This file intentionally contains no transport, credentials, PHP or WP-CLI.
# A future non-executing collector requires separate review and explicit approval.
set -euo pipefail
printf 'PGP05_REMOTE_IDENTITY=REFUSED\n' >&2
exit 78
