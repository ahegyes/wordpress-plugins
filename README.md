# DWS WordPress plugins

Plugins built on the wp-framework packages are developed side by side here, so a change that crosses plugins lands and is tested in one place, while each plugin still ships from a mirror of its own.

| Plugin | Mirror | Distribution |
| --- | --- | --- |
| [Linked Orders for WooCommerce](./plugins/linked-orders-for-woocommerce) | [`wc-linked-orders`](https://github.com/ahegyes/wc-linked-orders) | wordpress.org |

Each plugin under `plugins/<slug>/` is a complete plugin repository, started from [wordpress-plugin-template](https://github.com/ahegyes/wordpress-plugin-template): its own Composer and npm manifests, prefixed framework, test environments, CI and release workflows. Every push to `trunk` splits it to its mirror, which runs the plugin's own CI and releases it.

## Working on one plugin

Work from the plugin's directory, as its `README.md` describes:

```sh
cd plugins/linked-orders-for-woocommerce
npm run setup
composer quality-check
```

## Working across the plugins

The root installs and builds every plugin, runs each one's checks, and starts a shared WordPress on <http://localhost:8820> in which every plugin is active beside the others:

```sh
npm run setup
composer plugins:quality-check
composer test:integration
npm run test:e2e
```

`composer test:integration` runs each plugin's Integration suite in the shared environment, and `npm run test:e2e` its smoke check.

## Releases

A plugin's release commit writes its changelog and sets its version, as its `CONTRIBUTING.md` describes. The tag `<slug>/v<version>` on that commit reaches the plugin's mirror as `v<version>`, where the plugin's own release workflow publishes it.

`AGENTS.md` holds the monorepo's conventions, and `CONTRIBUTING.md` how changes land.
