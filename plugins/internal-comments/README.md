# Internal Comments

Lets the people who run a site leave comments on its posts, pages, products and orders that only they can read.

After the post types that support internal comments are selected in the plugin's settings, a meta box on their edit screens lists a post's internal comments and lets users with the right capabilities post, reply to, edit and trash them in place. A comment can be marked private, a column in the posts list counts a post's comments and opens a quick view of them, the Comments screen lists every internal comment under its own view, and each comment's edit screen keeps its earlier texts. Internal comments are stored under a status of their own, which WordPress leaves out of every comment list, count, feed and REST response, even while the plugin is inactive.

## Requirements

The entry file's headers state the floors: `Requires PHP` and `Requires at least` for WordPress. On a site below them, WordPress shows its own notice and none of the plugin's features load. WooCommerce is optional; internal comments on its orders need the version `WC requires at least` names, in either of its order storages.

## Installation

Install the plugin from the WordPress plugin directory under Plugins → Add New Plugin, where its later releases arrive too, then open its settings page from the Settings link on its row of the Plugins screen. Each release also attaches `internal-comments.zip` to the repository's GitHub release.

## Extending

Other plugins create and read internal comments through the functions in `includes/comments.php`, such as `dws_ic_create_internal_comment()`, `dws_ic_get_internal_comments()` and `dws_ic_is_private_comment()`, and the `DWS_Internal_Comment` class they return; they check what a user may do with a comment through the `see_dws_internal_comment`, `edit_dws_internal_comment` and `trash_dws_internal_comment` capabilities. Every hook is named `dws_ic/area/name`, and each one v1 fired under `internal_comments/` still fires first, deprecated; `CHANGELOG.md` lists the renames and the removals.

## Development

Development needs PHP and Composer at the `Requires PHP` floor or later, Node.js and npm at `package.json`'s `engines`, Docker for `@wordpress/env`, and a POSIX shell for `npm run start`.

Install the dependencies, prefix the framework into `vendor-prefixed/`, build the scripts and start the development site on <http://localhost:8824>:

```sh
npm run setup
```

Rebuild the meta box's and quick view's scripts and styles on every change:

```sh
npm run start
```

Run the manifest and changelog checks, PHPCS, PHPStan, and the Unit and Integration suites, starting the tests site on <http://localhost:8825> as needed:

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
