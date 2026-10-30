# Tests

This document describes the plugin's three test suites, the environments they run in and how CI runs them.

## Suites

| Suite | Where | Runs on | Covers |
| --- | --- | --- | --- |
| Unit | `tests/Unit/` | PHP alone, without WordPress | the component tree and the footprint's shape and coverage |
| Integration | `tests/Integration/` | the tests site, with WordPress, WooCommerce and order tables loaded | the boot, every component, the options and their readers, the migrations, deactivation, uninstall, the assets and blocks, the peer getters, and a partial deploy: a missing build file or engine, or an include that throws |
| End to end | `tests/e2e/` | the tests site, in a browser through Playwright | the settings tab by keyboard, a rejected and a corrected value, accessibility through axe, and the plugin with WooCommerce deactivated |

PHPUnit runs both PHP suites in random order, so each row sets up what it needs and restores what it changes; its cache lives in `tests/.cache/phpunit/`, and Playwright's output in `tests/.cache/artifacts/`.

## Environments

- `.wp-env.json` is the development site on <http://localhost:8824>, as `local`, where the framework rethrows boot and migration failures.
- `.wp-env.tests.json` is the tests site on <http://localhost:8825>, as `production`, which the Integration and end-to-end suites share. A row whose separate process changes the database flushes the parent's object cache, and the end-to-end specs set their own preconditions, because either suite may run first.

Both start WordPress on PHP 8.5 with WooCommerce from `vendor/` and its order tables on.

## Running

Run the Unit suite:

```sh
composer test:unit
```

Start the tests site, build the scripts and run the Integration suite:

```sh
composer test:integration
```

Run the end-to-end suite, which starts the tests site when it is down and needs the built scripts and, once per machine, Playwright's Chromium:

```sh
npx playwright install chromium
npm run test:e2e
```

Run every check CI runs on PHP, Integration included:

```sh
composer quality-check
```

## Continuous integration

- **Tests** runs Unit once, and Integration and the end-to-end suite once per WordPress branch, from the entry file's `Requires at least` up to the newest release, which WordPress's version API lists, so a new release joins without an edit.
- **Tests (nightly)** runs Integration and the end-to-end suite on WordPress trunk every day, outside pull requests.
- **Release** builds the zip on every pull request as a tag would, runs Plugin Check and the archive checks, then activates the zip on a site and runs the end-to-end suite against it.
- **Quality** runs PHPCS, PHPStan, the PHP 7.4 parse of the entry file, `uninstall.php` and `includes/`, the script, style and type lints, the `block.json` schema and the changelog fragments.

## Conventions

Test classes are `final`, named after the class or the behavior they cover with a `Test` suffix, with `test_snake_case` methods, kebab-case data provider keys and no docblocks. They use real instances and WordPress's own APIs, never mocks of the code under test.
