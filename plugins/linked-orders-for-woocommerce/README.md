# Linked Orders for WooCommerce

A WooCommerce extension for creating orders that are logically connected to existing ones.

An order can have children, and each child its own, down to a maximum depth; a warranty extension linked to the order of the product it covers, or a fee paid separately, stays one click away from the order it belongs to. Links show beside each order's editor and in the orders list, whether WooCommerce stores orders in its order tables or as posts.

## Requirements

The entry file's headers state the floors: `Requires PHP`, `Requires at least` for WordPress, and `Requires Plugins` with `WC requires at least` for WooCommerce. Below the PHP or WordPress floor WordPress shows its own notice, below WooCommerce's the plugin shows one, and none of the plugin's features load.

## Installation

Install the plugin from the WordPress plugin directory under Plugins → Add New Plugin, where its later releases arrive too, then open its section from the Settings link on its row of the Plugins screen. Each release also attaches `linked-orders-for-woocommerce.zip` to the repository's GitHub release.

## Extending

Other plugins integrate through the plugin's functions, its `DWS_Order_Node` model, its hooks and WordPress's and WooCommerce's APIs on the names below, never through its classes in `src/`. They call its functions no earlier than `plugins_loaded`, and behind `function_exists()`, since the plugin may load later or stay active below its floors without defining them, and register their extensions on `linked_orders_for_woocommerce/boot/succeeded`, hooked before `plugins_loaded` reaches priority 15, where the plugin boots. Until it has booted, and so while WooCommerce is inactive, the functions that read orders answer null.

- `dws_lowc_link_orders()` links two orders, and `dws_lowc_create_linked_child()` creates a linked child of an order and returns the child's id.
- `dws_lowc_get_order_node()` returns an order's `DWS_Order_Node`, which answers its depth, parent and children and whether a user may create a child of it; `dws_lowc_get_root_order()` returns the node at the top of an order's tree, and `dws_lowc_get_orders_tree()` the ids of an order and its descendants.
- `dws_lowc_is_supported_order()` answers whether an order's type can be linked, `dws_lowc_get_supported_order_types()` lists those types and `dws_lowc_get_valid_statuses_for_new_child()` the statuses an order needs for a child.
- `dws_lowc_get_plugin_slug()`, `dws_lowc_get_plugin_name()` and `dws_lowc_get_plugin_version()` read the plugin's header.
- Every hook is named `linked_orders_for_woocommerce/area/name`, under v1's root: six keep their v1 names, and each other name v1 fired still fires first, deprecated; `CHANGELOG.md` lists the renames and the removals. `linked_orders_for_woocommerce/boot/succeeded`, `linked_orders_for_woocommerce/boot/blocked` and `linked_orders_for_woocommerce/boot/failed` fire once the plugin's boot has an outcome.
- A new child opens through `admin-post.php` with the `dws_lowc_create_linked_child` action and the parent's id as `parent_id`, behind a nonce for `dws_create_linked_child`.
- The settings are the options `dws-lowc_general_max-depth`, `dws-lowc_general_autocomplete-descendants` and `dws-lowc_plugin_remove-data-uninstall`, which `get_option()` reads, and the capabilities are `create_dws_linked_children`, `see_dws_order_links`, `see_dws_linked_orders_metabox` and `see_dws_linked_orders_column`.

A child takes the type its creator makes, so an order type whose registrant swaps in a creator through `linked_orders_for_woocommerce/linked_order/creation_args` also words the add-child phrase through the `linked_orders_for_woocommerce_add_new_child` label it registers, such as "Add new child quote"; without it the plugin says "Add new child order".

## Development

Development needs PHP and Composer at the `Requires PHP` floor or later, Node.js and npm at `package.json`'s `engines`, and Docker for `@wordpress/env`.

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
npm run lint
```

`docs/ARCHITECTURE.md` describes how the plugin loads, boots and stores its data, `tests/README.md` describes the test suites, and `AGENTS.md` holds the conventions for people and agents working on the code. The plugin template's [recipes](https://github.com/ahegyes/wordpress-plugin-template/blob/trunk/docs/recipes.md) hold the step-by-step changes a plugin built from it makes, under the template's `dws_tpl` prefix.

The plugin is developed in the [wordpress-plugins](https://github.com/ahegyes/wordpress-plugins) monorepo under `plugins/linked-orders-for-woocommerce/`, and its mirror is read-only, so issues and pull requests go to the monorepo, and vulnerability reports follow `SECURITY.md`.
