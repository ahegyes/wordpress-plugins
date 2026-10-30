# wordpress-plugins

The monorepo of the DWS v2 WordPress plugins, each built on the wp-framework packages from wordpress-plugin-template. Every directory under `plugins/` is a complete plugin repository, split to its own read-only mirror on each push to `trunk`, where the plugin's own CI runs and its releases are made. Composer `ahegyes/wordpress-plugins`, GPL-3.0-or-later. Each plugin's `AGENTS.md` covers its code; this file covers the monorepo.

## Layout

```
wordpress-plugins/
├── plugins/
│   └── linked-orders-for-woocommerce/    Linked Orders for WooCommerce → wc-linked-orders, ports 8822 and 8823, on wordpress.org
├── tests/
│   ├── e2e/                              the shared environment's smoke check, over the plugins .wp-env.tests.json maps
│   └── Fixtures/multi-plugin-smoke/      reserved for a fixture plugin the shared environment mounts
├── .github/workflows/
│   ├── plugins.yml                       lists the plugins, their mirrors and readmes and each one's WordPress versions for the other workflows
│   ├── quality.yml, tests.yml, tests-nightly.yml, audit.yml    the template's lanes, once per plugin with project-path plugins/<slug>
│   ├── codeql.yml, workflow-checks.yml   the template's, over the root's workflows
│   └── split-plugins.yml                 splits each plugin to the mirror its composer.json name gives, and forwards <slug>/vX.Y.Z as vX.Y.Z
├── .wp-env.tests.json                    the shared environment on port 8820: every plugin and WooCommerce from vendor/, as production
├── composer.json                         changelogger, WooCommerce for the shared environment, the shared Integration run and the plugins:* fan-outs
└── package.json                          wp-env and Playwright for the shared environment, and the plugins:* fan-outs
```

## Commands

```sh
npm run setup                     # every install, then every plugin's build
composer plugins:quality-check    # each plugin's quality-check, in its own environments
composer test:integration         # starts the shared environment and runs each plugin's Integration suite in it
npm run test:e2e                  # the shared environment's smoke check
npm run plugins:test:e2e          # each plugin's end-to-end suite, in its own environment
actionlint .github/workflows/*.yml plugins/*/.github/workflows/*.yml && zizmor .github/ plugins/*/.github/
```

## Conventions

1. **Plugins.** A plugin starts from wordpress-plugin-template's trunk through the template's fill-in script, run in a clone of the template, and its directory keeps the template's files but for `CONTRIBUTING.md`, the pull-request and issue templates, `.github/dependabot.yml` and the fill-in's workflow and script: its mirror is a public repository users land on, while contributions, issues and dependency updates belong here. Its `composer.json` name is its mirror, `ahegyes/wc-<name>` for a WooCommerce plugin and `ahegyes/wp-<name>` otherwise.
2. **Root lanes.** The root's workflows find the plugins through `plugins.yml`, so a plugin joins every lane without a workflow edit. The `plugins:*` fan-outs, the shared environment's mappings and activation, and `test:integration:run` name each plugin, one line each.
3. **Ports.** Each plugin takes the next free pair of wp-env ports above 8820, its development site then its tests site; the shared environment is 8820.
4. **v1 successors.** A plugin released before v2 keeps its wordpress.org slug, text domain, main file name, option and meta keys, capabilities, and every hook, function and constant another plugin can rely on, and starts at 2.0.0. Its v1 hook literals fire as deprecated beside their v2 names until the next major, and every break is listed in its changelog and its readme's Upgrade Notice.
5. **Releases.** A plugin releases from its mirror: the tag `<slug>/vX.Y.Z` here reaches the mirror as `vX.Y.Z`, never force-pushed, and the mirror holds the `wp-org-release` environment where the plugin is on wordpress.org.

## Style

Each plugin's `AGENTS.md` states its code style, the template's. Markdown keeps one line per paragraph, sentence-case headings and an impersonal, present-tense voice. Commit subjects are capitalized imperatives of 72 characters or fewer, with no `type(scope):` prefix and no trailing period; the body says what was wrong and why before what changed, and an agent-assisted commit ends with `Assisted-by: <agent>:<model-id>`, never `Co-Authored-By`.

## Limitations

- CI runs twice per change: the root's lanes on the pull request, and the mirror's own after the split.
- The root's workflow checks cover the root's workflows; a plugin's own are checked on its mirror after the split, and locally by the command above.
- Until a second plugin lands, the shared environment runs its one plugin's suites a second time.
