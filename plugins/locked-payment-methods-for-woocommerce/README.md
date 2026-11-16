# Locked Payment Methods for WooCommerce

Hides the payment methods you lock from customers at checkout until you unlock them for a user, a role, an order, a group or a membership plan.

A store locks a payment method it trusts only some customers with, such as cash on delivery or pay by invoice, and unlocks it per customer on their profile, for every customer with given roles, for one unpaid order, or for the members of a Groups group or a WooCommerce Memberships plan. The lock holds while the plugin cannot boot, so an outdated runtime or a broken deploy never shows a locked payment method to everyone.

## Requirements

The entry file's headers state the floors: `Requires PHP`, `Requires at least` for WordPress, and `Requires Plugins` with `WC requires at least` for WooCommerce. On a site below them, WordPress shows its own notice and none of the plugin's features load, while the lock still holds.

## Installation

Install the plugin from the WordPress plugin directory under Plugins → Add New Plugin, where its later releases arrive too, then open its section under WooCommerce → Settings → Advanced from the Settings link on its row of the Plugins screen. Each release also attaches `locked-payment-methods-for-woocommerce.zip` to the repository's GitHub release.

## Extending

Other plugins integrate through the plugin's functions, its hooks and WordPress's and WooCommerce's APIs on the names below, never through its classes in `src/` or `Safeguards.php`. They call its functions no earlier than `plugins_loaded`, and behind `function_exists()`, since the plugin may load later or stay active below its floors without defining them, and register their extensions on `locked_payment_methods_for_woocommerce/boot/succeeded`, hooked before `plugins_loaded` reaches priority 15, where the plugin boots.

- `dws_lpmwc_is_payment_method_locked()` answers whether a payment method is locked for a user and an order, and `dws_lpmwc_get_locked_payment_method_ids()` lists the locked payment methods, both before the boot too. Neither applies v1's deprecated `lock_manager` filters, which only checkout still fires, so a v1 callback on them can lock or unlock a method there that these functions answer otherwise.
- `locked_payment_methods_for_woocommerce/payment_method/locked_ids` locks more payment methods, and returning `false` from `locked_payment_methods_for_woocommerce/payment_method/should_lock` unlocks one for a user and an order once the plugin has booted.
- `dws_lpmwc_get_plugin_slug()`, `dws_lpmwc_get_plugin_name()` and `dws_lpmwc_get_plugin_version()` read the plugin's header.
- Every hook is named `locked_payment_methods_for_woocommerce/area/name`, under v1's root, and each name v1 fired there still fires, deprecated, where v1 fired it; `CHANGELOG.md` lists the renames and the removals. `locked_payment_methods_for_woocommerce/boot/succeeded`, `locked_payment_methods_for_woocommerce/boot/blocked` and `locked_payment_methods_for_woocommerce/boot/failed` fire once the plugin's boot has an outcome.
- The settings are the options `dws_lpmwc_locked_payment_methods`, `dws_lpmwc_unlock_via_user_roles`, `dws_lpmwc_full_access_user_roles`, `dws_lpmwc_unlock_via_user_profiles`, `dws_lpmwc_unlock_via_orders`, `dws_lpmwc_unlock_via_groups`, `dws_lpmwc_unlock_via_wc_memberships` and `dws_lpmwc_delete_data`, which `get_option()` reads, and the capabilities are `unlock_dws_lpm_via_user_profiles`, `unlock_dws_lpm_via_orders`, `unlock_dws_lpm_via_groups` and `unlock_dws_lpm_via_wc_memberships`.

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
