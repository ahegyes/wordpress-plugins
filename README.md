# DWS WordPress Plugins

Monorepo for the v2 DWS WordPress plugins. Each plugin under `plugins/<name>/` is fully self-contained (own `composer.json`, scoping prefix, scoped framework + PHP-DI) and is split-published to its own GitHub mirror via `splitsh-lite`.

| Plugin                                                                                       | Mirror                                                                              | Status |
|----------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------|--------|
| [`internal-comments`](./plugins/internal-comments)                                           | [`wp-internal-comments`](https://github.com/ahegyes/wp-internal-comments)           | shell  |
| [`linked-orders-for-woocommerce`](./plugins/linked-orders-for-woocommerce)                   | [`wc-linked-orders`](https://github.com/ahegyes/wc-linked-orders)                   | shell  |
| [`locked-payment-methods-for-woocommerce`](./plugins/locked-payment-methods-for-woocommerce) | [`wc-locked-payment-methods`](https://github.com/ahegyes/wc-locked-payment-methods) | shell  |

## Layout

```
wordpress-plugins/
├── composer.json                # Monorepo orchestration scripts (plugins:install, plugins:lint, ...)
├── package.json                 # Cross-plugin wp-env + Playwright tooling
├── playwright.config.js         # Cross-plugin E2E config (port 8820)
├── .wp-env.tests.json           # Cross-plugin WordPress (port 8820, all 3 + WC mounted)
├── plugins/
│   ├── internal-comments/                          (port 8821)
│   ├── linked-orders-for-woocommerce/              (port 8822)
│   └── locked-payment-methods-for-woocommerce/     (port 8823)
├── tests/
│   ├── e2e/                     # Cross-plugin Playwright specs
│   └── Fixtures/multi-plugin-smoke/   # Fixture mounted into wp-env for smoke testing
├── changelog/                   # Monorepo-level changelog fragments
└── .github/workflows/           # quality, tests, split-plugins, codeql
```

## Working on a single plugin

The split-published mirrors are runnable standalone — clone a mirror and you have the full plugin with no monorepo dependencies. The same workflow works in-monorepo from the plugin's directory:

```sh
cd plugins/internal-comments
composer packages-install   # deps + scoping → dependencies/
npm install                 # wp-env + Playwright
npm run wp-env:start        # WordPress on the plugin's own port

composer test:unit
composer test:integration
npm run test:e2e

npm run wp-env:stop
```

## Working across all plugins

```sh
# From the monorepo root
npm run install:all                   # composer + npm install across root + all 3 plugins
npm run wp-env:start                  # WordPress with all 3 plugins + WC mounted (port 8820)

# Shared env — all 3 plugins active together (catches cross-plugin pollution)
npm run test:integration              # PHPUnit Integration across all 3 in shared env
npm run test:e2e                      # Cross-plugin Playwright suite (tests/e2e/)

# Fan-out — each plugin in its own isolated env (mirrors what split-published mirrors run in CI)
composer plugins:test:integration     # PHPUnit Integration, per-plugin isolated env
npm run plugins:test:e2e              # Playwright, per-plugin isolated env

composer plugins:quality-check        # lint:php + test:unit across all 3 (no Docker)

npm run wp-env:stop
```

## Port scheme

Workspace-wide convention (880X = framework, 881X = plugin templates, 882X = real plugins; 8888 + 8889 reserved for wp-env defaults):

- `8801` — `wordpress-framework` monorepo
- `8811` — `wordpress-plugin-template`
- `8820` — `wordpress-plugins` monorepo (cross-plugin shared env)
- `8821` — `internal-comments`
- `8822` — `linked-orders-for-woocommerce`
- `8823` — `locked-payment-methods-for-woocommerce`

## Releases

Per-plugin releases use `composer changelog:write` inside the plugin's directory; the next splitsh-lite run propagates the tag to the mirror, where `release.yml` (deferred) will publish to wp.org. Monorepo-level changelog tracks scaffold-only changes.

See [`CONTRIBUTING.md`](./CONTRIBUTING.md) for the full workflow.
