# Locked Payment Methods for WooCommerce

Hides the payment methods you lock from customers at checkout until you unlock them for a user, a role, an order, a group or a membership plan.

A store locks a payment method it trusts only some customers with, such as cash on delivery or pay by invoice, and unlocks it per customer on their profile, for every customer with given roles, for one unpaid order, or for the members of a Groups group or a WooCommerce Memberships plan. The lock holds while the plugin cannot boot, so an outdated runtime or a broken deploy never shows a locked payment method to everyone.

## Requirements

The entry file's headers state the floors: `Requires PHP`, `Requires at least` for WordPress, and `Requires Plugins` with `WC requires at least` for WooCommerce. On a site below them, WordPress shows its own notice and none of the plugin's features load, while the lock still holds.

## Installation

Install the plugin from the WordPress plugin directory under Plugins → Add New Plugin, where its later releases arrive too, then open its section under WooCommerce → Settings → Advanced from the Settings link on its row of the Plugins screen. Each release also attaches `locked-payment-methods-for-woocommerce.zip` to the repository's GitHub release.

## Extending

Other plugins lock more payment methods through `dws_lpmwc/payment_method/locked_ids`, unlock one for a user and an order by returning `false` from `dws_lpmwc/payment_method/should_lock`, and ask `dws_lpmwc_is_payment_method_locked()` whether one is locked. Every hook is named `dws_lpmwc/area/name`, and each one v1 fired under `locked_payment_methods_for_woocommerce/` still fires, deprecated, where v1 fired it; `CHANGELOG.md` lists the renames and the removals.

## Development

Development needs PHP and Composer at the `Requires PHP` floor or later, Node.js and npm at `package.json`'s `engines`, and Docker for `@wordpress/env`.

Install the dependencies, prefix the framework into `vendor-prefixed/` and start the development site, with WooCommerce and Groups, on <http://localhost:8826>:

```sh
npm run setup
```

Run the manifest and changelog checks, PHPCS, PHPStan, and the Unit and Integration suites, starting the tests site on <http://localhost:8827> as needed:

```sh
composer quality-check
```

Install the browser the end-to-end suite runs in, once per machine, then run the suite against the tests site:

```sh
npx playwright install chromium
npm run test:e2e
```

Lint the end-to-end scripts and types:

```sh
npm run lint
```

`docs/ARCHITECTURE.md` describes how the plugin loads, boots and stores its data, `tests/README.md` describes the test suites, and `AGENTS.md` holds the conventions for people and agents working on the code. The plugin template's [recipes](https://github.com/ahegyes/wordpress-plugin-template/blob/trunk/docs/recipes.md) hold the step-by-step changes a plugin built from it makes, under the template's `dws_tpl` prefix.

The plugin is developed in the [wordpress-plugins](https://github.com/ahegyes/wordpress-plugins) monorepo under `plugins/locked-payment-methods-for-woocommerce/`, and its mirror is read-only, so issues and pull requests go to the monorepo, and vulnerability reports follow `SECURITY.md`.
