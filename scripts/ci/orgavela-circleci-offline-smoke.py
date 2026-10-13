#!/usr/bin/env python3
"""Synthetic-only, offline CircleCI connection test for OrgaVela / UOP Core.

No networking, credentials, WordPress, IONOS, Google Drive or backup data.
Used only by the branch-filtered CircleCI Free proof of connection.
"""

import json
from pathlib import Path


def main() -> None:
    root = Path(__file__).resolve().parents[2]
    required = [
        "AGENTS.md",
        "docs/operations/V1_ACCEPTANCE_PLAN.md",
        "docs/operations/V1_PROGRESS.json",
        "docs/operations/W0_O03_CIRCLECI_GITHUB_BACKUP_ASSESSMENT_2026-10-13.md",
        "scripts/ci/orgavela-readonly-audit.sh",
        "scripts/ci/orgavela-synthetic-restore.sh",
    ]

    for name in required:
        assert (root / name).is_file(), f"Missing expected repository file: {name}"

    progress = json.loads(
        (root / "docs/operations/V1_PROGRESS.json").read_text(encoding="utf-8")
    )
    metrics = progress["measurements"]
    assert metrics["milestone_count"] == 8
    assert metrics["reported_implemented_packages"] <= metrics["normative_packages"]
    assert metrics["milestones_formally_v1_accepted"] <= metrics["milestone_count"]
    assert metrics["release_gates_formally_green"] <= metrics["release_gates_total"]
    assert not metrics["v1_release_accepted"]

    o03 = next(item for item in progress["ops"] if item["id"] == "O03")
    assert o03["status"] != "completed"
    assert not o03["formal_accepted"]
    assert o03["actual_isolated_restoration"] == "not performed"

    print("ORGAVELA_CIRCLECI_OFFLINE_SMOKE=success")
    print("ORGAVELA_CIRCLECI_LIVE_BACKUP_USED=no")
    print("ORGAVELA_CIRCLECI_LIVE_RESTORE=no")


if __name__ == "__main__":
    main()
