# M5 Verification and Failure-Recovery Matrix

This document is the review evidence for M5-01 through M5-10. It is not a legal assurance, deployment approval, or proof of delivery to recipients.

## Required controls

| Workflow | Failure or replay scenario | Expected safe outcome | Automated coverage |
|---|---|---|---|
| Consent versions | Duplicate publish; cross-tenant access; outbox unavailable | Reject the conflicting version; roll back pointer and immutable version | `tests/Integration/M5ConsentTest.php` |
| Consent decisions | Required consent rejected; replay; delegated guardian revoked; outbox failure | No false positive consent; historical actor/subject evidence retained; no orphan records | `tests/Integration/M5ConsentRecordsTest.php` |
| Consent withdrawal | Same-command retry and conflicting new withdrawal | Append one superseding record, never rewrite the original | `tests/Integration/M5ConsentRecordsTest.php` |
| Template override | Unsafe HTML, arbitrary variables, stale revision, outbox failure | Reject unsafe or outdated revisions; preserve code defaults | `tests/Integration/M5EmailTemplatesTest.php` |
| Email queue | Queue enqueued while Action Scheduler is unavailable | Frozen immutable message remains in the database; sweep can enqueue existing work | `tests/Integration/M5EmailDeliveryTest.php` |
| Duplicate email delivery | Identical worker call twice | One claimed send only; never repeat `accepted` or ambiguous `sending` | `tests/Integration/M5EmailDeliveryTest.php` |
| Email transport failure | `wp_mail()` refuses a message | Persist `failed`, allow authorized explicit retry with original frozen body, no secret in outbox | `tests/Integration/M5EmailDeliveryTest.php` |
| Private export | Revoked authorization before processing or download | Fail closed, no reusable private URL and no unauthorized file | `tests/Integration/M5ExportJobsTest.php` |
| Private export | Storage write fails; checksum tampered; expiry reached | Safe `failed` or unavailable status, no arbitrary path or leaked content | `tests/Integration/M5ExportJobsTest.php` |
| Retention preview | Disabled rule, legal hold, active registration, expired rule | No mutation, bounded counts and opaque IDs only | `tests/Integration/M5RetentionTest.php` |
| Retention execution | Interrupted transaction/outbox failure; restart with cursor | Roll back the whole batch, preserve holds, advance only examined keyset positions | `tests/Integration/M5RetentionTest.php` |
| Privacy request | Shared family email or unlinked guest | Manual subject-resolution response; no cross-person export/erasure | `tests/Integration/M5WordPressPrivacyTest.php` |
| Administration | Invalid nonce, unprivileged tenant, private content in status | Deny commands and show counts, safe error codes and public IDs only | `tests/Integration/M5OperationsTest.php`; `src/Admin/M5OperationsScreen.php` |

## Operational review protocol

1. Run `composer lint`, `composer analyse`, `composer test:unit` and `composer test:integration`.
2. Verify GitHub Actions **both** for the final branch push and PR check (PHP 8.3/8.4/8.5 × MySQL 8.0/MariaDB 10.11 plus quality and artifact smoke).
3. Verify that stale failed checks are not mistaken for the most recent commit result. Review against the exact PR head SHA.
4. Do not merge this stacked M5 PR ahead of M4 PR #4 or the earlier dependent PRs.
5. No automatic resend after an ambiguous `sending` crash. An operator must reconcile the provider result.
6. No default legal retention deadlines; enable reviewed rules only after organization-specific policy review. Historical consent and audit evidence remain until an appropriately configured, authorized retention process handles them.
7. Personal data exporter identity is anchored only in explicit account links. A shared email is a **manual identity problem**, not permission to merge or indiscriminately disclose family records.
8. Do not publish exported files in Media Library, expose paths in REST, or copy rendered email content into audit, logs or WordPress notices.

## Implementation boundaries

- M5 is the **service, infrastructure and minimal administrator recovery** milestone. M6 will integrate the full participant/admin UX and channel interfaces.
- `wp_mail()` returning true is mail acceptance, not proof of delivery to an inbox.
- M4's token-free event handoffs for guest verification and waitlist promotions still require a securely controlled delivery adapter before enabling public workflows. They must not be treated as a deployed notification feature simply because the generic M5 mail service exists.
- Retention actions deliberately support only fixed, explicitly approved class/trigger/action tuples. No arbitrary field-level SQL deletion engine or statutory retention advice is provided.
- WordPress privacy erasure is intentionally conservative and reports retained historical data for review instead of claiming that all legal obligations are discharged.

**Review gate:** M5 may be marked ready for review only after the latest CI matrix is green and all dependency PRs remain correctly stacked. Production deployment is not implied.
