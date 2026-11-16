# Internal Comments

Lets the people who run a site leave comments on its posts, pages, products and orders that only they can read.

After the post types that support internal comments are selected in the plugin's settings, a meta box on their edit screens lists a post's internal comments and lets users with the right capabilities post, reply to, edit and trash them in place. A comment can be marked private, a column in the posts list counts a post's comments and opens a quick view of them, the Comments screen lists every internal comment under its own view, and each comment's edit screen keeps its earlier texts. Internal comments are stored under a status of their own, which keeps them out of the comment lists, counts, feeds and REST responses WordPress shows visitors, even while the plugin is inactive, and while the plugin is active but cannot boot, WordPress refuses every change to them.

## Requirements

The entry file's headers state the floors: `Requires PHP` and `Requires at least` for WordPress. On a site below them, WordPress shows its own notice and none of the plugin's features load. WooCommerce is optional; internal comments on its orders need the version `WC requires at least` names, in either of its order storages.

## Installation

Install the plugin from the WordPress plugin directory under Plugins → Add New Plugin, where its later releases arrive too, then open its settings page from the Settings link on its row of the Plugins screen. Each release also attaches `internal-comments.zip` to the repository's GitHub release.

## Extending

Other plugins integrate through the plugin's functions, its `DWS_Internal_Comment` model, its hooks and WordPress's APIs on the names below, never through its classes in `src/`. They call its functions no earlier than `plugins_loaded`, and behind `function_exists()`, since the plugin may load later or stay active below its floors without defining them, and register their extensions on `internal_comments/boot/succeeded`, hooked before `plugins_loaded` reaches priority 15, where the plugin boots.

- The functions in `includes/comments.php` create and read internal comments, such as `dws_ic_create_internal_comment()`, which returns the new comment's id or null, `dws_ic_get_internal_comments()`, which returns a post's comments the current user can see as `DWS_Internal_Comment` objects, their replies too, `dws_ic_is_internal_comment()`, and `dws_ic_is_private_comment()`, which answers true or false for an internal comment and null for any other. `DWS_Internal_Comment::get_revisions()` lists a comment's earlier texts, newest first.
- `dws_ic_get_selected_post_types()` lists the post types that take internal comments, and `dws_ic_are_enabled_private_comments()` answers whether one can be marked private.
- `dws_ic_get_plugin_slug()`, `dws_ic_get_plugin_name()` and `dws_ic_get_plugin_version()` read the plugin's header.
- Every hook is named `internal_comments/area/name`, under v1's root, and each name v1 fired there still fires first, deprecated; `CHANGELOG.md` lists the renames and the removals. `internal_comments/boot/succeeded`, `internal_comments/boot/blocked` and `internal_comments/boot/failed` fire once the plugin's boot has an outcome, and `internal_comments/core_comment_actions/should_restrict` decides whether WordPress refuses its own edits, moderation, trashing and deletion of an internal comment, which it does until the plugin has booted.
- The meta box and the quick view use the `internal-comments/v1` REST routes, and internal comments are comments of the `dws_internal` type and status.
- The settings are the options `dws_ic_post_types`, `dws_ic_private_comments_enabled` and `dws_ic_delete_data`, which `get_option()` reads. What a user may do with a comment is checked through the `see_dws_internal_comment`, `edit_dws_internal_comment` and `trash_dws_internal_comment` capabilities.

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
npm run lint
```

`docs/ARCHITECTURE.md` describes how the plugin loads, boots and stores its data, `tests/README.md` describes the test suites, and `AGENTS.md` holds the conventions for people and agents working on the code. The plugin template's [recipes](https://github.com/ahegyes/wordpress-plugin-template/blob/trunk/docs/recipes.md) hold the step-by-step changes a plugin built from it makes, under the template's `dws_tpl` prefix.

The plugin is developed in the [wordpress-plugins](https://github.com/ahegyes/wordpress-plugins) monorepo under `plugins/internal-comments/`, and its mirror is read-only, so issues and pull requests go to the monorepo, and vulnerability reports follow `SECURITY.md`.
