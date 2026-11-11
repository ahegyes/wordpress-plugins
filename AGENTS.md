# wordpress-plugins

The monorepo of the DWS v2 WordPress plugins, each built on the wp-framework packages from wordpress-plugin-template. Every directory under `plugins/` is a complete plugin repository, split to its own read-only mirror on each push to `trunk`, where the plugin's own CI runs and its releases are made. Composer `ahegyes/wordpress-plugins`, GPL-3.0-or-later. Each plugin's `AGENTS.md` covers its code; this file covers the monorepo.

## Layout

```
wordpress-plugins/
├── plugins/
│   ├── linked-orders-for-woocommerce/    Linked Orders for WooCommerce → wc-linked-orders, ports 8822 and 8823, on wordpress.org
│   ├── internal-comments/                Internal Comments → wp-internal-comments, ports 8824 and 8825, on wordpress.org
│   └── locked-payment-methods-for-woocommerce/    Locked Payment Methods for WooCommerce → wc-locked-payment-methods, ports 8826 and 8827, on wordpress.org
├── tests/
│   └── EndToEnd/                         the shared environment's checks: every plugin is active and boots, and the plugins work side by side
├── .github/workflows/
│   ├── plugins.yml                       lists the plugins, their mirrors and readmes for the other workflows, checks a release tag names one, and lists each one's WordPress versions for Tests
│   ├── quality.yml, tests.yml, tests-nightly.yml, audit.yml    the plugins' own lanes, once per plugin; Quality's script lint and the audit also over the root, and Tests and the nightly also over the shared environment
│   ├── codeql.yml, workflow-checks.yml   the template's, workflow-checks.yml also linting each plugin's workflows
│   ├── release-rehearsal.yml             rehearses each plugin's release, from build to the built-artifact test, on pull requests and trunk, publishing nothing
│   └── split-plugins.yml                 splits each plugin to the mirror its composer.json name gives, and forwards <slug>/vX.Y.Z as vX.Y.Z
├── .wp-env.tests.json                    the shared environment on port 8820: every plugin and WooCommerce from vendor/, as production
├── composer.json                         WooCommerce for the shared environment, the shared Integration run and the plugins:* fan-outs
└── package.json                          wp-env, Playwright and the shared spec's lint, and the plugins:* fan-outs
```

## Commands

```sh
npm run setup                     # every install, then every plugin's build
composer plugins:quality-check    # each plugin's quality-check, in its own environments
composer test:integration         # starts the shared environment and runs each plugin's Integration suite in it
npm run test:e2e                  # the shared environment's checks
npm run plugins:test:e2e          # each plugin's end-to-end suite, in its own environment
npm run lint:all                  # the shared spec's checks, then every plugin's PHP, script, style and type checks
npm run quality-check:all         # every check above, the shared ones first
actionlint .github/workflows/*.yml plugins/*/.github/workflows/*.yml && zizmor .github/ plugins/*/.github/
```

## Conventions

1. **Plugins.** A plugin starts from wordpress-plugin-template's trunk through the template's fill-in script, run in a clone of the template, and its directory keeps the template's tooling, docs and workflows but for `CONTRIBUTING.md`, the pull-request and issue templates, `.github/dependabot.yml` and the fill-in's workflow and script: its mirror is a public repository users land on, while contributions, issues and dependency updates belong here. Its `composer.json` name is its mirror, `ahegyes/wc-<name>` for a WooCommerce plugin and `ahegyes/wp-<name>` otherwise.
2. **Root lanes.** The root's workflows find the plugins through `plugins.yml`, so a plugin joins every lane without a workflow edit. The `plugins:*` fan-outs, the shared environment's mappings and activation, `test:integration:run`, the shared spec's boot row, the README table, the layout tree above and both issue forms name each plugin.
3. **Ports.** wp-env ports are allocated in threes: the shared environment takes 8820 and the root's multisite environment 8821, the three plugins here take their development and tests pairs from 8822 to 8827 and their multisite ports from 8828 to 8830, and a new plugin takes the next free three, from 8831, for its development, tests and multisite sites.
4. **v1 successors.** A plugin released before v2 keeps its wordpress.org slug, text domain, main file name, capabilities, and every hook, function and constant another plugin can rely on, and starts at 2.0.0. It keeps its option and meta keys too, unless a major version migrates them in a way that preserves the data, with a changelog line and an Upgrade Notice line, as Locked Payment Methods' 2.0.0 does. Its v1 hook literals fire as deprecated beside their v2 names until the next major, and every break is listed in its changelog and its readme's Upgrade Notice.
5. **Releases.** A plugin releases from its mirror: the tag `<slug>/vX.Y.Z` here reaches the mirror as `vX.Y.Z`, never force-pushed, and the mirror holds the `wp-org-release` environment where the plugin is on wordpress.org. `CONTRIBUTING.md` holds the release step.

## Style

Each plugin's `AGENTS.md` states its code style, the template's. Markdown keeps one line per paragraph, sentence-case headings and an impersonal, present-tense voice. Commit subjects are capitalized imperatives of 72 characters or fewer, with no `type(scope):` prefix and no trailing period; the body says what was wrong and why before what changed, and an agent-assisted commit ends with `Assisted-by: <agent>:<model-id>`, never `Co-Authored-By`.

## Limitations

- CI runs twice per change: the root's lanes on the pull request, and the mirror's own after the split.
- Each plugin's Integration suite runs twice: in the plugin's own environments and in the shared one, which leaves out the rows of a plugin the shared environment does not install, such as Locked Payment Methods' Groups group.
