<!-- wp-plugin-base:agents-start -->
# Agent Operating Contract

This project consumes `wp-plugin-base` as vendored foundation source under `.wp-plugin-base/`.

## Working Rules

- Treat `.wp-plugin-base/` as generated/vendor foundation code. Prefer fixing reusable behavior upstream in `wp-plugin-base`, then resync this project.
- Managed files are listed by `bash .wp-plugin-base/scripts/ci/list_managed_files.sh --mode validate`. Do not permanently patch those files in place unless you are intentionally diverging from the foundation.
- Project-specific test bootstrap code belongs in `tests/wp-plugin-base/bootstrap-child.php`, not in managed `tests/bootstrap.php`.
- Public endpoint suppressions belong in `.wp-plugin-base-security-suppressions.json` and must keep the exact scanner-reported `kind`, `identifier`, and repo-relative `path`.

## Validation

After foundation or template updates, run:

```bash
bash .wp-plugin-base/scripts/update/sync_child_repo.sh
bash .wp-plugin-base/scripts/ci/validate_project.sh
```

For release changes, also run the release preparation workflow or local release checks documented in `CONTRIBUTING.md` before merging.
<!-- wp-plugin-base:agents-end -->


## Public repository content

Use generic examples and synthetic fixture identities. Keep customer or
application names, private repository links, deployment topology, internal
hostnames, account records and credentials out of source, docs, tests, commit
messages, PR descriptions and release notes. Review the package contents as well
as the Git diff before publishing. Use a public GitHub noreply commit identity.
