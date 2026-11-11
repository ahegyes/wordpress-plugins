# Contributing

Changes land here, in pull requests against `trunk`. The plugin mirrors are read-only copies that the split rewrites on every push, so they take neither issues nor pull requests.

## Checks

Before opening a pull request, run the checks of every plugin the change touches, from its directory, and the root's when the change crosses plugins:

```sh
composer quality-check
npm run test:e2e
```

## Changelog entries

Each user-visible change to a plugin adds one fragment, from that plugin's directory; the monorepo's own changes are recorded in its history and pull requests:

```sh
composer changelog:add
```

CI validates the fragments with `composer changelog:validate` in every plugin.

## Releases

A plugin's release commit, made from its directory, writes the fragments into a new version block of its `CHANGELOG.md`, whose version the fragments' significance sets, and copies the changelog into `readme.txt`:

```sh
composer changelog:write
```

A plugin's first release from this repository dates the `2.0.0 - unreleased` block it starts with, adding the fragments to it, since a plain write would put a new version above it. Composer hands the arguments after `--` to both commands of `changelog:write`, so that release runs them apart:

```sh
vendor/bin/changelogger write --amend
vendor/bin/pronamic-changelog-md-to-wordpress-plugin-readme-txt
```

The release commit also sets the main file's `Version:` header and `readme.txt`'s `Stable tag:` to that version, because the release refuses a tag that either differs from.

Land the release commit on trunk. The split pushes it to the plugin's mirror, and the release publishes only a mirror commit whose Quality and Tests runs passed, so wait for those; Release Rehearsal has already built and tested the release on the pull request. Then sign a tag on the commit here and push it:

```sh
git tag -s <slug>/v<version> -m <slug>/v<version>
git push origin <slug>/v<version>
```

The split forwards the tag to the mirror as `v<version>`, which starts the release there.
