#!/usr/bin/env bash
# OrgaVela-only GitHub Actions launcher, to be run in an authorized gh CLI session.
set -euo pipefail
[[ $# -ge 2 ]] || { echo "Usage: $0 sftp|ssh probe|install|upload [orgavela.com SHA256]" >&2; exit 64; }
transport="$1"
action="$2"
shift 2
case "$transport" in
  sftp) workflow="deploy-orgavela-sftp.yml" ;;
  ssh) workflow="deploy-orgavela-ssh.yml" ;;
  *) echo "Unknown transport" >&2; exit 64 ;;
esac
if [[ "$action" == probe && $# -eq 0 ]]; then
  target=""
  checksum=""
elif [[ "$transport" == sftp && "$action" == install && $# -eq 2 ]] || [[ "$transport" == ssh && "$action" == upload && $# -eq 2 ]]; then
  target="$1"
  checksum="$2"
  [[ "$target" == orgavela.com && "$checksum" =~ ^[0-9a-fA-F]{64}$ ]] || { echo "Refusing unsafe target or hash" >&2; exit 65; }
else
  echo "Refusing unsupported action or arguments" >&2
  exit 64
fi
command -v gh >/dev/null || { echo "GitHub CLI is missing" >&2; exit 69; }
gh auth status >/dev/null 2>&1 || { echo "Authenticate GitHub CLI privately, never paste a token into chat" >&2; exit 77; }
case "$transport" in
  sftp)
    gh workflow run "$workflow" --repo janjrre/Projekt-Wordpress-Plugin --ref main \
      -f "operation=$action" -f "target_domain=$target" -f "expected_sha256=$checksum"
    ;;
  ssh)
    gh workflow run "$workflow" --repo janjrre/Projekt-Wordpress-Plugin --ref main \
      -f "operation=$action" -f "confirm_target=$target" -f "expected_sha256=$checksum"
    ;;
esac
echo "Dispatch submitted, run not yet verified. Check https://github.com/janjrre/Projekt-Wordpress-Plugin/actions"
