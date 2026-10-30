# Linked Orders for WooCommerce

A WooCommerce extension for creating orders that are logically connected to existing ones.

An order can have children, and each child its own, down to a maximum depth; a warranty extension linked to the order of the product it covers, or a fee paid separately, stays one click away from the order it belongs to. Links show beside each order's editor and in the orders list, whether WooCommerce stores orders in its order tables or as posts.

## Requirements

The entry file's headers state the floors: `Requires PHP`, `Requires at least` for WordPress, and `Requires Plugins` with `WC requires at least` for WooCommerce. On a site below them, WordPress shows its own notice and none of the plugin's features load.

## Installation

Install the plugin from the WordPress plugin directory under Plugins → Add New Plugin, where its later releases arrive too, then open its section from the Settings link on its row of the Plugins screen. Each release also attaches `linked-orders-for-woocommerce.zip` to the repository's GitHub release.

## Extending

Other plugins link and walk orders through the functions in `includes/linking.php` and `includes/post-type.php`, such as `dws_lowc_link_orders()`, `dws_lowc_get_root_order()` and `dws_lowc_create_linked_child()`, and the `DWS_Order_Node` class they return. Every hook is named `dws_lowc/area/name`, and each one v1 fired under `linked_orders_for_woocommerce/` still fires first, deprecated; `CHANGELOG.md` lists the renames and the removals.

## Development

Development needs PHP and Composer at the `Requires PHP` floor or later, Node.js and npm at `package.json`'s `engines`, Docker for `@wordpress/env`, and a POSIX shell for `npm run start`.

Install the dependencies, prefix the framework into `vendor-prefixed/`, build the scripts and start the development site on <http://localhost:8822>:

```sh
npm run setup
```

Rebuild the orders list's script and styles on every change:

```sh
npm run start
```

Run the manifest and changelog checks, PHPCS, PHPStan, and the Unit and Integration suites, starting the tests site on <http://localhost:8823> as needed:

```sh
composer quality-check
```

Install the browser the end-to-end suite runs in, once per machine, then run the suite against the tests site:

```sh
npx playwright install chromium
npm run test:e2e
```

Lint the scripts, styles and types:

```sh
npm run lint:scripts && npm run lint:styles && npm run lint:types
```

`docs/ARCHITECTURE.md` describes how the plugin loads, boots and stores its data, `tests/README.md` describes the test suites, and `AGENTS.md` holds the conventions for people and agents working on the code. The plugin template's [recipes](https://github.com/ahegyes/wordpress-plugin-template/blob/trunk/docs/recipes.md) hold the step-by-step changes a plugin built from it makes, under the template's `dws_tpl` prefix.
