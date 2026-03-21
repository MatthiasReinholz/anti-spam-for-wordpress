# Contributing

## Branching Model

This repository uses short-lived branches:

- `main`: protected and always intended to stay releasable
- `feature/<topic>`: normal development work
- `release/<version>`: release preparation only
- `hotfix/<version>`: urgent production fixes branched from `main`

Do not push directly to `main`. Open a pull request instead.

Recommended merge policy:

- `feature/*`: squash merge
- `release/*`: merge commit or squash, but keep the choice consistent
- `hotfix/*`: merge commit or squash, but keep the choice consistent

Delete merged feature, release, and hotfix branches after merge.

## Release Process

Releases are tag-driven. A branch push must never publish a plugin release.

1. Merge the intended feature branches into `main`.
2. Create `release/x.y.z` from `main`.
3. Update version metadata in:
   - `anti-spam-for-wordpress.php`
   - `readme.txt`
4. Update the changelog and release notes.
5. Verify the release branch passes CI and complete a manual smoke test in WordPress.
6. Merge `release/x.y.z` back into `main` with a pull request.
7. Create an annotated tag `x.y.z` on the merge commit in `main`.
8. Push the tag to trigger the release workflow.

Hotfixes follow the same process from a `hotfix/x.y.z` branch created from `main`.

## CI And Release Automation

The repository includes:

- `ci.yml`: runs on pull requests to `main` and on pushes to `feature/*`, `release/*`, and `hotfix/*`
- `release.yml`: runs only on semver-like tag pushes

CI validates:

- PHP syntax across shipped PHP files
- JavaScript syntax across shipped JavaScript files
- version consistency across plugin metadata
- package creation and plugin root structure

The release workflow:

- verifies the tag matches plugin metadata
- builds the plugin zip
- creates a GitHub release with the zip attached
- deploys to WordPress.org only when `WP_DEPLOY_ENABLED` is not set to `false`

## Required GitHub Settings

These controls must be configured in GitHub and are not enforceable from the repo alone:

- protect `main`
- require pull requests before merge
- require at least one approval
- require status checks to pass
- require branches to be up to date before merge
- restrict direct pushes to `main`

If you want the ability to pause WordPress.org releases without changing code, set the Actions variable `WP_DEPLOY_ENABLED=false`. Prefer setting it on the `production` environment used by the release workflow.
