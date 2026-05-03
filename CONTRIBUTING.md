# Contributing

## Where work lives

This is a monorepo. Each plugin is a self-contained subtree under `plugins/<name>/` and is split-published to its own GitHub mirror via `.github/workflows/split-plugins.yml`.

Develop in this repo, on `trunk`. Don't open PRs against the mirror repos — they're read-only output.

## Per-plugin work

Each plugin has its own `composer.json`, `package.json`, `phpcs.dist.xml`, `phpstan.dist.neon`, `phpunit.dist.xml`, and `.wp-env.tests.json` (port 8821 / 8822 / 8823 — see [README "Port scheme"](./README.md#port-scheme)).

Run from the plugin's own directory:

```bash
cd plugins/internal-comments
composer packages-install   # PHP deps
npm install                 # wp-env + Playwright
npm run wp-env:start        # plugin-isolated WordPress
composer quality-check      # lint + unit tests
npm run test:integration    # PHPUnit Integration suite via wp-env
npm run test:e2e            # Playwright E2E
```

## Cross-plugin work

The monorepo root's `.wp-env.tests.json` mounts all 3 plugins + WooCommerce on a shared port (8820) for testing how they behave together. Two distinct test modes from root:

- **Shared env** — all 3 plugins active in one wp-env. Catches cross-plugin pollution.
- **Per-plugin isolated fan-out** — each plugin runs in its own wp-env (ports 8821/8822/8823). Mirrors the per-plugin CI on the split-published mirror repos.

```bash
npm run install:all                 # composer + npm install across root + all 3 plugins
npm run wp-env:start                # all-plugins WordPress (shared env)

# Shared env mode
npm run test:integration            # PHPUnit Integration across all 3 in shared env
npm run test:e2e                    # cross-plugin Playwright suite (tests/e2e/)

# Per-plugin isolated fan-out
composer plugins:test:integration   # PHPUnit Integration, per-plugin isolated env
npm run plugins:test:e2e            # Playwright, per-plugin isolated env

composer plugins:quality-check      # lint + unit tests across all 3 (no Docker)
```

## Changelog entries

Every user-visible change gets a fragment so PRs don't conflict on `CHANGELOG.md`:

```bash
composer changelog:add               # monorepo-level changes (workflows, scaffold)
composer --working-dir=plugins/internal-comments changelog:add   # per-plugin
```

CI validates fragments via `composer changelog:validate`.

## Releases

Per-plugin releases:

```bash
cd plugins/internal-comments
composer changelog:write   # aggregates fragments → CHANGELOG.md + readme.txt
git commit -am "Release <slug> <version>"
git tag plugins/internal-comments/<version>
```

The mirror gets the corresponding tag during the next splitsh-lite run.

Monorepo-level changelog tracks scaffold/workflow changes only.
