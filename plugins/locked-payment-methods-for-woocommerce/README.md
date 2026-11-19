# Locked Payment Methods for WooCommerce

Keeps risky payment methods away from customers a store has not chosen to trust.

A store locks a payment method it trusts only some customers with, such as cash on delivery or pay by invoice, and unlocks it per customer on their profile, for every customer with given roles, for one unpaid order, or for the members of a Groups group or a WooCommerce Memberships plan. The lock holds while the plugin cannot boot, so an outdated runtime or a broken deploy never shows a locked payment method to everyone.

## Requirements

The entry file's headers state the floors: `Requires PHP`, `Requires at least` for WordPress, and `Requires Plugins` with `WC requires at least` for WooCommerce. Below the PHP or WordPress floor or without WooCommerce, WordPress shows its own notice, below WooCommerce's version floor the plugin shows one, and none of the plugin's features load, while the lock still holds.

## Installation

Install the plugin from the WordPress plugin directory under Plugins → Add New Plugin, where its later releases arrive too, then open its section under WooCommerce → Settings → Advanced from the Settings link on its row of the Plugins screen. Each release also attaches `locked-payment-methods-for-woocommerce.zip` to the repository's GitHub release.

## Extending

Other plugins integrate through the plugin's functions, its hooks and WordPress's and WooCommerce's APIs on the names below, never through its classes in `src/` or `Safeguards.php`. They call its functions no earlier than `plugins_loaded`, and behind `function_exists()`, since the plugin may load later or stay active below its floors without defining them, and register their extensions on `locked_payment_methods_for_woocommerce/boot/succeeded`, hooked before `plugins_loaded` reaches priority 15, where the plugin boots.

- `dws_lpmwc_is_payment_method_locked()` answers whether a payment method is locked for a user and an order, as checkout judges it, and `dws_lpmwc_get_locked_payment_method_ids()` lists the locked payment methods, both before the boot too, when every locked method stays locked. Only checkout fires v1's deprecated locked-list filter, which can lock more of the methods it offers.
- `locked_payment_methods_for_woocommerce/payment_method/locked_ids` locks more payment methods, and returning `false` from `locked_payment_methods_for_woocommerce/payment_method/should_lock` unlocks one for a user and an order once the plugin has booted.
- A way of unlocking implements `DWS_LPMWC_Unlock_Strategy`, whose `unlocks()` answers for a payment method, a user and an order, and registers through `dws_lpmwc_register_unlock_strategy()`, which refuses a second strategy under a registered id. `dws_lpmwc_get_unlock_strategy()` and `dws_lpmwc_get_unlock_strategies()` read the registered ones, the plugin's own under `user_role`, `user_profile`, `order`, `groups` and `wc_memberships` while their checkbox is on. A strategy that throws is logged and unlocks nothing.
- The `user_profile` and `order` strategies implement `DWS_LPMWC_Grant_Strategy`, whose `grant()` and `revoke()` change a user's grants on the current site or an unpaid order's, keep the subject's other grants, check no capability and return `true` or a `WP_Error` coded `dws_lpmwc_subject_not_found`, `dws_lpmwc_order_paid` or `dws_lpmwc_grants_not_stored`. A revoked payment method can stay unlocked through another strategy, and two writes to one subject at the same moment can lose one, since each rewrites the whole list.
- `locked_payment_methods_for_woocommerce/grants/updated` fires after every change the plugin makes to a subject's grants, from its screens and its functions alike, with the strategy's id, `user_profile`, `order`, `groups` or `wc_memberships`, the subject's id, the new list and the previous one. Deleting a user, an order or a plan fires nothing, since its grants go with it; deleting a Groups group that holds grants fires it with an empty list while the Groups checkbox is on.
- `dws_lpmwc_get_plugin_slug()`, `dws_lpmwc_get_plugin_name()` and `dws_lpmwc_get_plugin_version()` read the plugin's header.
- Every hook is named `locked_payment_methods_for_woocommerce/area/name`, under v1's root, and each name v1 fired there still fires, deprecated, before its replacement; `CHANGELOG.md` lists the renames and the removals. `locked_payment_methods_for_woocommerce/boot/succeeded`, `locked_payment_methods_for_woocommerce/boot/blocked` and `locked_payment_methods_for_woocommerce/boot/failed` fire once the plugin's boot has an outcome.
- The settings are the options `dws_lpmwc_locked_payment_methods`, `dws_lpmwc_unlock_via_user_roles`, `dws_lpmwc_full_access_user_roles`, `dws_lpmwc_unlock_via_user_profiles`, `dws_lpmwc_unlock_via_orders`, `dws_lpmwc_unlock_via_groups`, `dws_lpmwc_unlock_via_wc_memberships` and `dws_lpmwc_delete_data`, which `get_option()` reads, and the capabilities are `unlock_dws_lpm_via_user_profiles`, `unlock_dws_lpm_via_orders`, `unlock_dws_lpm_via_groups` and `unlock_dws_lpm_via_wc_memberships`. The grants live under the user option `dws_lpmwc_unlocked_payment_methods` on each site's table prefix, the order and plan meta `_dws_lpmwc_unlocked_payment_methods` and the option `dws_lpmwc_group_{id}_unlocked_payment_methods`.

A plugin that unlocks cash on delivery for customers who passed its credit check registers its strategy from its main file:

```php
add_action(
	'locked_payment_methods_for_woocommerce/boot/succeeded',
	static function (): void {
		dws_lpmwc_register_unlock_strategy(
			new class() implements DWS_LPMWC_Unlock_Strategy {
				public function get_id(): string {
					return 'acme_credit_check';
				}

				public function get_label(): string {
					return __( 'Credit check', 'acme-credit-check' );
				}

				public function unlocks( string $gateway_id, int $user_id, int $order_id ): bool {
					return 'cod' === $gateway_id && 'passed' === get_user_meta( $user_id, 'acme_credit_check', true );
				}
			}
		);
	}
);
```

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
