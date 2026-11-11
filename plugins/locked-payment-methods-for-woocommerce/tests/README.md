# Tests

This document describes the plugin's three test suites, the environments they run in and how CI runs them.

## Suites

| Suite | Where | Runs on | Covers |
| --- | --- | --- | --- |
| Unit | `tests/Unit/` | PHP alone, without WordPress | the component tree, the footprint's shape and coverage of v1's data, the readme's version and floors, the entry's `WC tested up to` against the locked WooCommerce, the entry's lack of a `Tested up to`, which the readme alone holds, and the stub in every shipped directory |
| Integration | `tests/Integration/` | the tests site, with WordPress, WooCommerce, Groups and order tables loaded, and the multisite site, without Groups, for its `multisite` group | the boot, the lock and its relaxation, fresh requests below the PHP and WooCommerce floors, every strategy and its form, the settings, the capabilities, v1's deprecated functions and hooks, the migrations from v1, deactivation, uninstall, the peer getters, and a partial deploy: a missing build file or engine, or an include that throws, and the boot and settings on a second site of a network; every row whose outcome the order storage can change runs on all four storages through `OrderStorageTrait`, the Groups rows run in the `groups` group against Groups itself, and the Memberships rows in the `wc-memberships` group against a double of its one function |
| End to end | `tests/EndToEnd/` | the tests site, in a browser through Playwright | a locked payment method a customer is granted on the block checkout, the Store API's refusal of a guest's checkout with a locked payment method, and the plugin with WooCommerce deactivated; the monorepo's shared environment checks a locked payment method's absence from a guest's classic and block checkout |

PHPUnit runs both PHP suites in random order, so each row sets up what it needs and restores what it changes; its cache lives in `tests/.cache/phpunit/`, and Playwright's output in `tests/.cache/artifacts/`.

## Environments

- `.wp-env.json` is the development site on <http://localhost:8826>, as `local`, where the framework rethrows boot and migration failures.
- `.wp-env.tests.json` is the tests site on <http://localhost:8827>, as `production`, which the Integration and end-to-end suites share. A row whose separate process changes the database flushes the parent's object cache, and the end-to-end specs set their own preconditions, because either suite may run first.
- `.wp-env.multisite.json` is a network on <http://localhost:8830>, as `production`, with WooCommerce and the plugin network-activated, where the Integration suite's `multisite` group runs and the default run skips it.

Each starts WordPress on PHP 8.5 with WooCommerce from `vendor/` and WooCommerce's order tables on, and the development and tests sites load Groups from `vendor/` too.

## Running

Run the Unit suite:

```sh
composer test:unit
```

Start the tests site and run the Integration suite:

```sh
composer test:integration
```

Start the multisite site, build the scripts and run the Integration suite's `multisite` group:

```sh
composer test:integration:multisite
```

Run the end-to-end suite, which starts the tests site when it is down and needs, once per machine, Playwright's Chromium:

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
- **Quality** runs PHPCS, PHPStan, the PHP 7.4 parse of the entry file, `uninstall.php` and `includes/`, the script and type lints, the `readme.txt` lint and the changelog fragments.

## Conventions

Test classes are `final`, named after the class or the behavior they cover with a `Test` suffix, with `test_snake_case` methods, kebab-case data provider keys and no docblocks. They use real instances and WordPress's own APIs, never mocks of the code under test.
