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

Normal release flow:

1. Merge the intended feature branches into `main`.
2. Run the `prepare-release` workflow and choose `patch`, `minor`, `major`, or `custom`.
3. Review the generated `release/x.y.z` pull request.
4. Review the auto-generated changelog entry, adjust it if needed, and complete the release checklist in the PR body.
5. Merge the `release/x.y.z` pull request into `main`.
6. The merged release PR automatically creates and pushes the `x.y.z` tag.
7. The pushed tag automatically triggers the publish workflow.

Hotfixes use the same idea, but from a `hotfix/x.y.z` branch. If a hotfix branch has matching metadata and is merged into `main`, the tag is created automatically there as well.

## CI And Release Automation

The repository includes:

- `ci.yml`: runs on pull requests to `main` and on pushes to `feature/*`, `release/*`, and `hotfix/*`
- `prepare-release.yml`: creates a `release/x.y.z` pull request, deriving the version from a selected release type or a custom override
- auto-generates the initial changelog entry from commits since the latest release tag
- `finalize-release.yml`: automatically tags merged `release/*` and `hotfix/*` pull requests after metadata validation
- `release.yml`: runs on semver tag pushes, builds the plugin zip, creates the GitHub release, and optionally deploys to WordPress.org

CI validates:

- PHP syntax across shipped PHP files
- JavaScript syntax across shipped JavaScript files
- version consistency across plugin metadata
- release branch name and version alignment for `release/*` and `hotfix/*`
- presence of at least one changelog bullet item for `release/*` and `hotfix/*`
- package creation and plugin root structure

WordPress still requires the version to exist in tracked plugin files, so the version bump cannot live purely in GitHub settings. The repo now handles that through Actions and scripts so you do not need to edit version strings manually for each release.

## Required GitHub Settings

These controls must be configured in GitHub and are not enforceable from the repo alone:

- protect `main`
- require pull requests before merge
- require at least one approval
- require status checks to pass
- require branches to be up to date before merge
- restrict direct pushes to `main`

If you want the ability to pause WordPress.org releases without changing code, set the Actions variable `WP_DEPLOY_ENABLED=false`. Prefer setting it on the `production` environment used by the release workflow.
