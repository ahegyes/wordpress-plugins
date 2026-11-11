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

A plugin's release commit runs `composer changelog:write` in its directory and sets its version, as the plugin's own `CONTRIBUTING.md` describes. Push the tag `<slug>/v<version>` on that commit, and the split forwards it to the plugin's mirror as `v<version>`, which starts the release there.
