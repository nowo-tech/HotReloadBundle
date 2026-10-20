# Release process

1. Update [CHANGELOG.md](CHANGELOG.md): move entries from `[Unreleased]` to a new `[X.Y.Z] - YYYY-MM-DD` section. (This project does not store version in `composer.json`; Packagist uses the git tag.)
2. Update [UPGRADING.md](UPGRADING.md) if the release has upgrade notes.
3. Run pre-release checks: `make release-check` (includes `check-no-cursor-coauthor`, cs-fix, cs-check, rector-dry, phpstan, test-coverage, and demo release-check).
4. Commit all changes, create an annotated tag (e.g. `v1.0.0`), and push branch and tag. The release workflow creates the GitHub Release from the changelog.
5. Confirm Packagist auto-update (or trigger manual sync).

Do **not** include Cursor or Claude `Co-authored-by` trailers in release commits (REQ-GIT-001). See [GITHUB_CI.md](GITHUB_CI.md).

After creating the release commit and tag, run `make check-no-cursor-coauthor` again **before** `git push` (REQ-GIT-001). The release commit itself is not covered by an earlier `release-check` run.

## Example for v1.0.0

```bash
git add docs/CHANGELOG.md docs/UPGRADING.md docs/RELEASE.md
git status   # review
git commit -m "Release v1.0.0: HotReloadBundle initial release"
git tag -a v1.0.0 -m "Release v1.0.0: HotReloadBundle initial release"
make check-no-cursor-coauthor   # after the release commit, before push (REQ-GIT-001)
git push origin main
git push origin v1.0.0
```
