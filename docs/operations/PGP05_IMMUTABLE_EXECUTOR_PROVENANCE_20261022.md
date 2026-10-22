# PGP05 immutable executor candidate — 2026-10-22
Tracking #27 / Draft #32. No live metadata approval or credential setup.

## Supplied build log
Operator supplied build_41_step_0_container_0.txt from contextless CircleCI #41.
It shows amd64, cimg/python:3.12, cache/refresh messages and short layer IDs.
It contains **no full registry manifest digest**. The log does not independently
identify an immutable image. The live tag lookup below is a **new candidate**,
not a claim to reconstruct the exact historical job image. The short layer
prefixes do not form an identical manifest layer set/order; do not infer identity.

## Direct registry verification actually performed
HTTPS GET https://hub.docker.com/v2/repositories/cimg/python/tags/3.12:
- observed update 2026-10-03T03:42:49.007363Z;
- OCI index sha256:d0177be857b2c8492d9d655f082bd32b6d02cc4cb7497abb99f8f2d66064fc29;
- linux/amd64 manifest sha256:793ca7ea5a2fc27fa5b379fd077bf571c456d103992c7b3098cfc885f46cfa08.
Anonymous pull authorization from auth.docker.io used only in process memory,
never logged or stored. Then HTTPS registry-1.docker.io/v2/cimg/python/manifests/3.12
returned an OCI image index whose raw bytes SHA256 matched the index above.
The linux/amd64 descriptor selected from that index matched Docker Hub's API.
Fetching that descriptor and SHA256-hashing its raw bytes reproduced the exact
manifest digest. Media types were application/vnd.oci.image.index.v1+json and
application/vnd.oci.image.manifest.v1+json.

Official CircleCI source identifies cimg/python as its Docker image and states
that minor tags advance with patch releases:
https://circleci.com/developer/images/image/cimg/python
Publisher/repository + HTTPS registry consistency and content-hash checks are
the established provenance here, **not a signature, SBOM or vulnerability audit**.

## Configuration change
Both existing jobs now use exactly:
`docker.io/cimg/python@sha256:793ca7ea5a2fc27fa5b379fd077bf571c456d103992c7b3098cfc885f46cfa08`

The default off workflow remains contextless, same featurebranch only. Before
its synthetic suite, a runtime step requires x86_64, required executable paths,
reports SSH/system-Python versions and checks probe's trusted SSH/keyscan/Python
ownership/permissions. No installs, network probes or context/secret values are
requested. The existing synthetic tests also parse local OpenSSH options using
-G without connection and exercise the fixed remote shell on private fixtures.

Local validation: parsed YAML confirms both exact pins and default-off workflow
without context; **117/117 PGP05 synthetic tests OK**. Provider pull, image
availability and actual tool behavior remain **NOT VERIFIED** for this revision.

## Next required provider evidence
Independently review exact new SHA, then operator triggers this featurebranch
with pgp05_ssh_probe=off (all other modes off), no Context or credentials.
Verify actual full source SHA, pinned image pull identity, runtime-step output
and full synthetic test success. Missing binary/unsupported behavior stops;
do not install a workaround or switch to floating tag.
No hostkey or metadata approval is part of this default workflow.
Old context restrictions remain on the earlier SHA and must not be widened.

Provider startup/hooks evidence is still required before any password context.
Domain/vHost mapping remains separate; no authenticated metadata run, site
write, PHP/WordPress/SQL, backup/deploy/merge or purchase was performed.
CP5 **7/10**, #6/#8/#10 open; production_capture_allowed=false.
