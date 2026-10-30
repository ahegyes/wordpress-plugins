# Recipes

These are the changes most plugins built from this template make, each as the files to add or change. A recipe that stores data lists its keys for `includes/footprint.php`, which no check enforces. Options, transients and user meta under the plugin's `dws_lowc_` prefix are in the footprint already; meta on posts, terms, comments or orders needs its own `meta_prefixes` entry per object type.

- [A safeguard](#a-safeguard)
- [A one-time notice](#a-one-time-notice)
- [A WordPress settings page](#a-wordpress-settings-page)
- [A product data tab](#a-product-data-tab)
- [A form on an edit screen](#a-form-on-an-edit-screen)
- [A section on WooCommerce's Payments tab](#a-section-on-woocommerces-payments-tab)
- [Recurring jobs](#recurring-jobs)
- [Rewrite rules after activation](#rewrite-rules-after-activation)
- [A script module](#a-script-module)
- [Renaming a public hook](#renaming-a-public-hook)
- [Distribution through wordpress.org](#distribution-through-wordpressorg)
- [Without WooCommerce](#without-woocommerce)

## A safeguard

A safeguard is a restriction that holds even when the rest of the plugin fails to load, such as a capability that stays locked unless the plugin unlocks it. It lives in `includes/safeguards.php`, which the entry file loads before the autoloader, so it calls only WordPress and what that file defines. The plugin relaxes it only through the safeguard's own filter, with an explicit `false`, and only after `dws_lowc/boot/succeeded`, which fires once the plugin has booted, so a half-loaded build never relaxes it.

Replace `includes/safeguards.php` with the helper and the safeguard. The file must still parse on PHP 7.4, because the entry loads it before the floor gate passes on a site without the plugin's built files:

```php
<?php declare( strict_types=1 );
/**
 * Holds the plugin's safeguards, which load before its autoloader, so they hold even when the rest of the build fails to load.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'map_meta_cap', 'dws_lowc_map_export_capability', 10, 2 );

/**
 * Returns whether a safeguard's filtered value relaxes it, which takes an explicit false once the plugin has finished booting.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $filtered The value the safeguard's filter returned for true.
 *
 * @return  bool
 */
function dws_lowc_is_relaxed( $filtered ): bool {
	return 0 < did_action( 'dws_lowc/boot/succeeded' ) && false === $filtered;
}

/**
 * Maps the data export capability to do_not_allow, or to managing options while the plugin relaxes the export lock.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string[] $caps The primitive capabilities the capability maps to.
 * @param   string   $cap  The capability being checked.
 *
 * @return  string[]
 */
function dws_lowc_map_export_capability( array $caps, string $cap ): array {
	if ( 'dws_lowc_export_data' !== $cap ) {
		return $caps;
	}

	/**
	 * Filters whether the data export stays locked; an explicit false unlocks it once the plugin has booted.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   bool $restricted Whether the data export stays locked.
	 */
	return dws_lowc_is_relaxed( apply_filters( 'dws_lowc/data_export/should_restrict', true ) ) ? array( 'manage_options' ) : array( 'do_not_allow' );
}
```

Every further safeguard calls `dws_lowc_is_relaxed()` around its own literal `apply_filters()`, so each filter keeps its hook docblock and the helper's rule has one home.

A component relaxes the lock only around the operation that needs it, and removes the relaxation even when the operation throws:

```php
add_filter( 'dws_lowc/data_export/should_restrict', '__return_false' );
try {
	$this->export(); // Checks current_user_can( 'dws_lowc_export_data' ).
} finally {
	remove_filter( 'dws_lowc/data_export/should_restrict', '__return_false' );
}
```

Add the safeguard's failure test as `tests/Integration/SafeguardsTest.php`. The plugin has booted when the Integration suite runs, so the last row takes `dws_lowc/boot/succeeded` back for its duration:

```php
<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SafeguardsTest extends TestCase {
	protected const string FILTER = 'dws_lowc/data_export/should_restrict';

	public static function relaxations(): array {
		return array(
			'none'         => array( array(), false ),
			'null'         => array( array( 'relaxation' => null ), false ),
			'zero'         => array( array( 'relaxation' => 0 ), false ),
			'empty-string' => array( array( 'relaxation' => '' ), false ),
			'false'        => array( array( 'relaxation' => false ), true ),
		);
	}

	#[DataProvider( 'relaxations' )]
	public function test_only_an_explicit_false_relaxes_the_export_lock( array $spec, bool $relaxed ): void {
		$relaxation = static fn (): mixed => $spec['relaxation'];
		if ( \array_key_exists( 'relaxation', $spec ) ) {
			add_filter( self::FILTER, $relaxation );
		}

		try {
			self::assertSame( $relaxed ? array( 'manage_options' ) : array( 'do_not_allow' ), map_meta_cap( 'dws_lowc_export_data', 0 ) );
		} finally {
			remove_filter( self::FILTER, $relaxation );
		}
	}

	public function test_an_explicit_false_does_not_relax_the_export_lock_before_the_plugin_booted(): void {
		global $wp_actions;
		self::assertGreaterThan( 0, did_action( 'dws_lowc/boot/succeeded' ) );
		$fired = $wp_actions['dws_lowc/boot/succeeded'];
		unset( $wp_actions['dws_lowc/boot/succeeded'] );
		add_filter( self::FILTER, '__return_false' );

		try {
			self::assertSame( array( 'do_not_allow' ), map_meta_cap( 'dws_lowc_export_data', 0 ) );
		} finally {
			remove_filter( self::FILTER, '__return_false' );
			$wp_actions['dws_lowc/boot/succeeded'] = $fired;
		}
	}
}
```

## A one-time notice

A one-time notice shows once, on the user's next admin screen, such as after a form post redirects. The plugin's `AdminNotices` is in `Plugin::COMPONENTS` and in the container, so a component takes it in its constructor:

```php
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\Notice;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\NoticeType;

// ...

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNotices $notices The plugin's admin notices.
	 */
	public function __construct(
		protected AdminNotices $notices
	) {}

	// ...

	/**
	 * Tells the current user, on their next admin screen, how many rows the export wrote.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   int $rows The number of rows the export wrote.
	 *
	 * @return  void
	 */
	protected function report_export( int $rows ): void {
		$this->notices->add(
			new Notice(
				Plugin::PREFIX . '-export-' . time(), // A viewer's delivery outlives the notice, so each occasion takes a new id.
				/* translators: %d: The number of rows. */
				esc_html( sprintf( _n( 'The export wrote %d row.', 'The export wrote %d rows.', $rows, 'linked-orders-for-woocommerce' ), $rows ) ),
				NoticeType::Success
			)
		);
	}
```

The notice's scope defaults to the current user and its message is HTML, so escape it. A notice for every administrator of the site takes `NoticeScope::Site`, a capability and `per_viewer: true`, since without it the first administrator's viewing consumes it for everyone; one that stays until dismissed takes `persistent: true`.

## A WordPress settings page

An options page adds a screen under a WordPress admin menu and stores each tab's fields in one option through the Settings API. WooCommerce settings belong on a WooCommerce tab, as `src/Settings.php` does.

Add the option, its field and its default, a reader that guards the field and the validator to `includes/options.php`, which stays PHP 7.4-parseable. The non-WooCommerce recipe below uses the same names, with the uninstall consent as a second field:

```php
/**
 * The option that holds the settings page's fields, keyed by field id.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_OPTION_SETTINGS = Plugin::PREFIX . '_settings';

/**
 * The settings field that holds the limit.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_SETTING_LIMIT = 'limit';

/**
 * The value each settings field reads as while nothing valid is stored.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, int>
 */
const DWS_LOWC_SETTING_DEFAULTS = array(
	DWS_LOWC_SETTING_LIMIT => 1,
);

/**
 * Returns a settings field as an integer.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $field The field's id.
 *
 * @return  int
 */
function dws_lowc_get_int_setting( string $field ): int {
	$default = DWS_LOWC_SETTING_DEFAULTS[ $field ] ?? null;
	$stored  = get_option( DWS_LOWC_OPTION_SETTINGS );
	$stored  = is_array( $stored ) && array_key_exists( $field, $stored ) ? $stored[ $field ] : $default;

	return filter_var( $stored, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? ( is_int( $default ) ? $default : 0 );
}

/**
 * Returns the limit as the settings page stores it, or an error when the submission is not a whole number of at least 1.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $submitted The submitted value.
 *
 * @return  int|WP_Error
 */
function dws_lowc_validate_limit( $submitted ) {
	$limit = is_string( $submitted ) ? filter_var( $submitted, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;

	return false === $limit ? new WP_Error( 'invalid_number', __( 'Enter a whole number of 1 or more, such as 3.', 'linked-orders-for-woocommerce' ) ) : $limit;
}
```

Add the page as a component, `src/SettingsPage.php`, and list it in `Plugin::COMPONENTS`:

```php
<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPageDefinitionInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds the plugin's settings page under Settings.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class SettingsPage implements ComponentInterface, OptionsPageDefinitionInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		new OptionsPage( Plugin::PREFIX, $this )->register_hooks();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_options_page( string $page_id ): array {
		return array(
			'parent'     => 'options-general.php',
			'capability' => 'manage_options',
			'page_title' => __( 'Linked Orders for WooCommerce', 'linked-orders-for-woocommerce' ),
			'menu_title' => __( 'Linked Orders for WooCommerce', 'linked-orders-for-woocommerce' ),
			'tabs'       => array(
				array(
					'slug'   => 'general',
					'title'  => __( 'General', 'linked-orders-for-woocommerce' ),
					'option' => DWS_LOWC_OPTION_SETTINGS,
					'fields' => array(
						array(
							'type'              => 'number',
							'id'                => DWS_LOWC_SETTING_LIMIT,
							'title'             => __( 'Limit', 'linked-orders-for-woocommerce' ),
							'default'           => (string) DWS_LOWC_SETTING_DEFAULTS[ DWS_LOWC_SETTING_LIMIT ],
							'custom_attributes' => array(
								'min'  => '1',
								'step' => '1',
							),
							'validate'          => 'dws_lowc_validate_limit',
						),
					),
				),
			),
		);
	}

	// endregion
}
```

Read the limit with `dws_lowc_get_int_setting( DWS_LOWC_SETTING_LIMIT )`, which returns the default until a valid limit is stored. The option is under the plugin's prefix, so the footprint covers it; a migration step that stores `DWS_LOWC_SETTING_DEFAULTS` autoloaded saves the first request's query, as the non-WooCommerce recipe's step does.

## A product data tab

A product data tab adds a panel to the product editor's Product data box, whose fields WooCommerce renders and saves as the product's meta. Add it as a component, `src/ProductData.php`, and list it in `Plugin::COMPONENTS`:

```php
<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\WooCommerce\ProductData\ProductDataTab;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\WooCommerce\ProductData\ProductDataTabDefinitionInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds the packing tab to the product editor.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class ProductData implements ComponentInterface, ProductDataTabDefinitionInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		new ProductDataTab( Plugin::PREFIX, $this )->register_hooks();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_product_data_tab( string $tab_id, \WC_Product $product ): array {
		return array(
			'label'  => __( 'Packing', 'linked-orders-for-woocommerce' ),
			'groups' => array(
				array(
					'fields' => array(
						array(
							'type'  => 'text',
							'id'    => '_' . Plugin::PREFIX . '_box',
							'label' => __( 'Box', 'linked-orders-for-woocommerce' ),
						),
					),
				),
			),
		);
	}

	// endregion
}
```

Read the stored value with `ProductMeta::get_value( $product, '_dws_lowc_box' )`. The leading underscore hides the key from WordPress's Custom Fields box. Post meta is not in the footprint until its type is, so declare the prefix for uninstall in `includes/footprint.php`:

```php
'meta_prefixes' => array( 'post' => array( '_' . Plugin::PREFIX . '_' ) ),
```

A field with its own `render` callable returns its control as inline markup, escapes, names and describes it, and prints a hidden empty input before it unless the field is disabled. Without that input, a cleared control posts nothing, and the save leaves the stored value untouched. It also takes a `validate` callable that returns the value to store or a `WP_Error`, since the framework refuses to save a custom control's value that nothing validates.

## A form on an edit screen

An object form adds fields to an edit screen, such as a post's meta box, a user's profile or a term's form, and stores each field on the edited object. It reports a rejected value through the plugin's `AdminNotices`. Add it as a component, `src/PostNote.php`, and list it in `Plugin::COMPONENTS`:

```php
<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\Hosts\PostMetaBox;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectForm;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Settings\EditScreen\ObjectFormDefinitionInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds an editorial note to the post editor.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class PostNote implements ComponentInterface, ObjectFormDefinitionInterface {
	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   AdminNotices $notices The plugin's admin notices, which report the form's rejections.
	 */
	public function __construct(
		protected AdminNotices $notices
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		new ObjectForm( Plugin::PREFIX . '_note', $this, new PostMetaBox( array( 'post' ), 'side' ), $this->notices )->register_hooks();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_object_form( string $form_id, int $object_id ): array {
		return array(
			'title'      => __( 'Editorial note', 'linked-orders-for-woocommerce' ),
			'capability' => 'edit_posts',
			'fields'     => array(
				array(
					'type'  => 'textarea',
					'id'    => '_' . Plugin::PREFIX . '_note',
					'title' => __( 'Note', 'linked-orders-for-woocommerce' ),
				),
			),
		);
	}

	// endregion
}
```

The host checks that the user can edit the post itself, and the form's `capability` is one more capability the user needs, checked without an object, so it names a primitive capability such as `edit_posts` rather than `edit_post`. The form verifies its own nonce before it asks for the definition on a save. Declare the meta for uninstall in `includes/footprint.php`, as the product data tab does. A field with its own `render` callable prints a hidden empty input before its control unless the field is disabled, and takes a `validate` callable, for the reasons that recipe gives.

## A section on WooCommerce's Payments tab

A section on an existing WooCommerce tab, here the Payments tab (`checkout`), holds settings that belong beside WooCommerce's own. WooCommerce renders the Payments tab as its own page and lists no extension sections there, so the section is reached through its direct link, which the plugin's row on the Plugins screen carries. Add it as a component, `src/PaymentSettings.php`, and list it in `Plugin::COMPONENTS`:

```php
<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use Automattic\WooCommerce\Admin\Settings\SettingsSectionRegistry;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\WooCommerce\Setting\SectionFieldsDefinitionInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\WooCommerce\Setting\SettingsSection;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds the plugin's section to WooCommerce's Payments tab, and links to it from the plugin's row.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class PaymentSettings implements ComponentInterface, SectionFieldsDefinitionInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( 'woocommerce_settings_sections_registration', array( $this, 'register_section' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DWS_LOWC_FILE ), array( $this, 'add_settings_link' ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_section_fields( string $tab_id, string $section_id ): array {
		return array(
			array(
				'type'  => 'title',
				'id'    => Plugin::PREFIX . '_payment_options',
				'title' => __( 'Linked Orders for WooCommerce', 'linked-orders-for-woocommerce' ),
			),
			array(
				'type'  => 'checkbox',
				'id'    => Plugin::PREFIX . '_payment_notes',
				'title' => __( 'Payment notes', 'linked-orders-for-woocommerce' ),
				'desc'  => __( 'Add a note to each order when its payment completes', 'linked-orders-for-woocommerce' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => Plugin::PREFIX . '_payment_options',
			),
		);
	}

	// endregion

	// region HOOKS

	/**
	 * Registers the plugin's section on the Payments tab.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SettingsSectionRegistry $registry WooCommerce's registry of settings sections.
	 *
	 * @return  void
	 */
	public function register_section( SettingsSectionRegistry $registry ): void {
		$registry->register(
			new SettingsSection(
				Plugin::PREFIX,
				array(
					'tab'   => 'checkout',
					'label' => __( 'Linked Orders for WooCommerce', 'linked-orders-for-woocommerce' ),
				),
				$this
			)
		);
	}

	/**
	 * Adds the link to the plugin's section to its row on the Plugins screen.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   string[] $links The row's action links.
	 *
	 * @return  string[]
	 */
	public function add_settings_link( array $links ): array {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . Plugin::PREFIX ) ), esc_html__( 'Settings', 'linked-orders-for-woocommerce' ) ) );

		return $links;
	}

	// endregion
}
```

WooCommerce stores each field in its own option under its id, flat rather than in a group, and a WooCommerce checkbox stores `yes` or `no`; read it with `dws_lowc_get_bool_option()` once the key and its default are in `includes/options.php`.

## Recurring jobs

A recurring job runs a hook at an interval through the plugin's scheduler, which uses Action Scheduler where it is loaded and WP-Cron otherwise. Either can run an invocation twice or skip one, so the job's callback leaves its data consistent after a duplicate or a missed run.

Add the repair gate's option to `includes/options.php`:

```php
/**
 * The option that holds when the plugin last ensured its recurring jobs, which lets a request skip ensuring them for an hour.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_OPTION_RECURRING_ENSURED_AT = Plugin::PREFIX . '_recurring_ensured_at';
```

Add the job as a component, `src/Pruning.php`, and list it in `Plugin::COMPONENTS`. It ensures the schedule on `wp_loaded` at most once an hour, writing the time first so concurrent requests skip, and again when Action Scheduler asks every day:

```php
<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Scheduling\Recurrence;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Scheduling\ScheduledHook;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Scheduling\SchedulerInterface;
use DeepWebSolutions\LinkedOrders\Scoped\Psr\Log\LoggerInterface as PsrLoggerInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Prunes the plugin's expired records once a day.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Pruning implements ComponentInterface {
	// region FIELDS AND CONSTANTS

	/**
	 * The hook the daily prune runs on.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string HOOK = Plugin::PREFIX . '/records/prune';

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   SchedulerInterface $scheduler The plugin's scheduler.
	 * @param   PsrLoggerInterface $logger    The plugin's logger.
	 */
	public function __construct(
		protected SchedulerInterface $scheduler,
		protected PsrLoggerInterface $logger
	) {}

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		add_action( self::HOOK, array( $this, 'prune' ) );
		add_action( 'wp_loaded', array( $this, 'repair_schedule' ) );
		add_action( 'action_scheduler_ensure_recurring_actions', array( $this, 'ensure_schedule' ) );
	}

	// endregion

	// region HOOKS

	/**
	 * Ensures the daily prune unless a request did so within the last hour.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function repair_schedule(): void {
		$ensured_at = dws_lowc_get_int_option( DWS_LOWC_OPTION_RECURRING_ENSURED_AT );
		if ( time() - $ensured_at < HOUR_IN_SECONDS ) {
			return;
		}

		update_option( DWS_LOWC_OPTION_RECURRING_ENSURED_AT, time(), true );
		$this->ensure_schedule();
	}

	/**
	 * Schedules the daily prune unless it runs at that interval already, logging a scheduler that refuses.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function ensure_schedule(): void {
		$ensured = $this->scheduler->ensure_recurring( new ScheduledHook( self::HOOK ), Recurrence::from_interval( DAY_IN_SECONDS ) );
		if ( true !== $ensured ) {
			$this->logger->warning(
				'Could not schedule {hook}: {error}',
				array(
					'hook'      => self::HOOK,
					'error'     => $ensured->get_error_message(),
					'exception' => $ensured->get_error_data(),
				)
			);
		}
	}

	/**
	 * Deletes the records that have expired.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function prune(): void {
		// Delete by expiry, so a second run in a day finds nothing left and a missed day catches up.
	}

	// endregion
}
```

Then:
- Add `Plugin::PREFIX . '/records/prune'` to `cron_hooks` in `includes/footprint.php`. Deactivation unschedules every hook there through the plugin's scheduler, and uninstall clears WP-Cron's copies, while Action Scheduler's are in the footprint's `as_groups` already.
- Delete the repair gate's option on activation, so the next request ensures the jobs: add `register_activation_hook( DWS_LOWC_FILE, 'dws_lowc_activate' );` to `functions.php` beside the deactivation hook, and a `dws_lowc_activate()` that calls `delete_option( DWS_LOWC_OPTION_RECURRING_ENSURED_AT )`.
- Change an interval in code only: `ensure_recurring()` replaces a schedule whose interval differs on the next pass of the repair gate. A "run now" schedules the same hook with an argument that sets it apart, or calls `prune()` directly.

## Rewrite rules after activation

WordPress builds its rewrite rules from the post types and taxonomies registered on `init`. The request that activates the plugin does not boot it, because WordPress includes the plugin's file after `plugins_loaded`, so flushing there would drop the plugin's rules. Delete the stored rules instead: the next request rebuilds them once the plugin has registered its types.

In `functions.php`, beside the deactivation hook:

```php
register_activation_hook( DWS_LOWC_FILE, 'dws_lowc_activate' );

// ...

/**
 * Lets the next request rebuild the site's rewrite rules with the plugin's post types and taxonomies.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @return  void
 */
function dws_lowc_activate(): void {
	delete_option( 'rewrite_rules' );
}
```

Add the same `delete_option( 'rewrite_rules' )` to `dws_lowc_deactivate()`, so the next request rebuilds the rules without them. Both act on the current site only, as WordPress's own activation does; a network activation leaves the other sites' rules until they next rebuild them.

## A script module

A script module is a JavaScript module WordPress loads through its import map, such as a block's view script for the Interactivity API. wp-scripts builds script modules only from a block's `block.json`, and WordPress registers each one from the block's metadata, with the imports wp-scripts recorded, and enqueues it when the block renders, so the plugin registers nothing itself.

Name the module in the block's `block.json`, here `blocks/src/example-notice/block.json`:

```json
"viewScriptModule": "file:./view.js"
```

Add the module beside it, as `blocks/src/example-notice/view.ts`:

```ts
import { store } from '@wordpress/interactivity';

store( 'linked-orders-for-woocommerce/example-notice', {} );
```

Declare the module's import, which today arrives only through `@wordpress/block-editor`:

```sh
npm install --save-dev @wordpress/interactivity
```

Build the blocks' modules with their scripts, by adding `--experimental-modules` to `build:blocks` and `start:blocks` in `package.json`:

```json
"build:blocks": "wp-scripts build --webpack-src-dir=blocks/src --output-path=blocks/build --blocks-manifest --experimental-modules",
"start:blocks": "wp-scripts start --webpack-src-dir=blocks/src --output-path=blocks/build --blocks-manifest --experimental-modules"
```

The build writes `view.js` with a `view.asset.php` whose `type` is `module`, and `src/Blocks.php`'s registration from the manifest picks it up.

## Renaming a public hook

A public hook's name is part of the plugin's contract, as its arguments, its options and its REST routes are. To rename one in a released plugin:

1. Rename the literal at every `do_action()` or `apply_filters()` and in its docblock, which keeps its `@since` and moves its `@version` to the release.
2. Add a changelog fragment with `composer changelog:add` that names the old and the new hook, with a major significance.
3. Migrate stored data that holds the name, such as a scheduled job's hook, in a migration step that converges from any partial state: unschedule the old name, then ensure the new one.

Ship no deprecated alias: the major release is the migration path.

## Distribution through wordpress.org

A plugin listed on wordpress.org gets its updates and its language packs from there, so it drops the GitHub updater's header and adds the directory's `readme.txt`.

1. In the entry file, delete the `Update URI` header, which turns the bundled updater inert, and the `load_plugin_textdomain()` line, since language packs load just in time.
2. Add `readme.txt`, whose `Stable tag` the release checks against the `Version` header, whose floors repeat the entry file's headers, and whose changelog block `composer changelog:write` fills:

```text
=== Linked Orders for WooCommerce ===
Contributors: TODO
Tags: TODO
Tested up to: TODO, the newest WordPress branch the plugin is tested on
Stable tag: TODO, the Version header
Requires at least: TODO, the Requires at least header
Requires PHP: TODO, the Requires PHP header
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

TODO: the plugin's one sentence, at most 150 characters.

== Description ==

TODO: what the plugin does, for the directory's listing.

== Installation ==

1. Install the plugin from Plugins → Add New Plugin.
2. Activate it.

== Changelog ==

<!-- Start changelog -->

<!-- End changelog -->

[See the full changelog on GitHub.](https://github.com/ahegyes/wc-linked-orders/blob/trunk/CHANGELOG.md)
```

3. Wrap `CHANGELOG.md`'s version blocks in the same `<!-- Start changelog -->` and `<!-- End changelog -->` markers, require `pronamic/changelog-md-to-wordpress-plugin-readme-txt` in `require-dev`, and make `changelog:write` sync the file:

```json
"changelog:write": [
  "@php vendor/bin/changelogger write",
  "@php vendor/bin/pronamic-changelog-md-to-wordpress-plugin-readme-txt"
]
```

4. Lint the file in `.github/workflows/quality.yml`:

```yaml
  readme-lint:
    name: readme.txt lint
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
        with:
          persist-credentials: false
      - uses: thetwopct/wporg-plugin-readme-linter@1f869aba959265ecc90935b2380c6569d56646ac # v1.0.2
        with:
          path: readme.txt
          fail-on: error
```

5. In `.github/workflows/release.yml`, pass `wp-org: true` to the reusable release. A tag then also deploys the zip and the `.wordpress-org/` assets to the directory's SVN from the `wp-org-release` environment: create it, store `SVN_USERNAME` and `SVN_PASSWORD` there and only there, and protect it with required reviewers.

The wordpress.org build runs Plugin Check's full set but for the checks `exclude-checks` names; Plugin Check's `prefixing` and `direct_file_access` stop at PHP 8.4 syntax such as `protected(set)`, which wordpress.org's own scan may meet too. The bundled updater ships inert under `vendor-prefixed/`, which wordpress.org's reviewers may still ask to remove.

## Without WooCommerce

A plugin that does not need WooCommerce drops the WooCommerce package, the WooCommerce demo and its tooling, and keeps its settings on a WordPress options page.

1. In `composer.json`, delete the `vcs` repository entry whose `url` is `https://github.com/ahegyes/wp-framework-woocommerce`. Then remove the WooCommerce packages, which leaves every other locked version as it is:

```sh
composer remove --dev --ignore-platform-req=php+ --no-interaction ahegyes/wp-framework-woocommerce php-stubs/woocommerce-stubs wp-plugin/woocommerce
```

2. In `phpstan.dist.neon`, delete the `scanFiles` block that names the WooCommerce stubs:

```neon
    scanFiles:
        - vendor/php-stubs/woocommerce-stubs/woocommerce-stubs.php
        - vendor/php-stubs/woocommerce-stubs/woocommerce-packages-stubs.php
```

3. In `linked-orders-for-woocommerce.php`, delete the `Requires Plugins`, `WC requires at least` and `WC tested up to` headers.

4. In `functions.php`, delete the `FeaturesUtil` import, the `before_woocommerce_init` hook and `dws_lowc_declare_compatibility()`, and drop "declares its WooCommerce feature compatibility" from the file's summary:

```php
use DeepWebSolutions\LinkedOrders\Plugin;

defined( 'ABSPATH' ) || exit;

add_action( 'plugins_loaded', 'dws_lowc_boot', 15 );
register_deactivation_hook( DWS_LOWC_FILE, 'dws_lowc_deactivate' );
```

5. In `src/Plugin.php`, log through core's `ErrorLogLogger`, which drops records below warning unless `WP_DEBUG` is on, so `DWS_LOWC_DEBUG` goes. Compose without the WooCommerce check, and leave the greeting out of `COMPONENTS`:

```php
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
// ...

	public const array COMPONENTS = array(
		Scheduler::class,
		AdminNotices::class,
		Settings::class,
		Blocks::class,
	);

	/**
	 * The scheduler, which the components and the deactivation callback share.
	 // ...
	 */
	public readonly Scheduler $scheduler;

	// ...

	public function __construct() {
		$this->logger    = new ErrorLogLogger( self::SLUG );
		$this->scheduler = new Scheduler( self::PREFIX );
		$this->boot      = new PluginBoot( DWS_LOWC_FILE, self::PREFIX, $this->logger );
	}

	// ...

	/**
	 * Registers the scheduler and the migrations, then builds the kernel and boots the plugin's components.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	protected function compose(): bool {
		$this->scheduler->register_hooks();
		$this->migrations = new MigrationRunner( DWS_LOWC_FILE, self::PREFIX . '_revision', dws_lowc_get_migration_steps(), $this->logger, $this->scheduler );
		$this->migrations->register_hooks();

		$this->kernel = new PluginKernel( new ContainerBuilder()->addDefinitions( $this->get_definitions( $this->logger, $this->scheduler ) )->build() );
		$this->kernel->boot( self::COMPONENTS );

		return true;
	}
```

6. Delete the greeting: `src/Feature/`, `tests/Integration/Feature/` and `assets/src/greeting.scss`, then its entries in `package.json`:

```json
"build:assets": "wp-scripts build editor=./assets/src/editor.ts --output-path=assets/build",
"start:assets": "wp-scripts start editor=./assets/src/editor.ts --output-path=assets/build"
```

7. Replace `src/Settings.php` with an options page under Settings, which stores both fields in one option through the Settings API:

```php
<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\ComponentInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPage;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Settings\AdminPage\OptionsPageDefinitionInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Adds the plugin's settings page under Settings, which holds the limit and the consent to delete the plugin's data on uninstall.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Settings implements ComponentInterface, OptionsPageDefinitionInterface {
	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function register_hooks(): void {
		new OptionsPage( Plugin::PREFIX, $this )->register_hooks();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	#[\Override]
	public function get_options_page( string $page_id ): array {
		return array(
			'parent'     => 'options-general.php',
			'capability' => 'manage_options',
			'page_title' => __( 'Linked Orders for WooCommerce', 'linked-orders-for-woocommerce' ),
			'menu_title' => __( 'Linked Orders for WooCommerce', 'linked-orders-for-woocommerce' ),
			'tabs'       => array(
				array(
					'slug'   => 'general',
					'title'  => __( 'General', 'linked-orders-for-woocommerce' ),
					'option' => DWS_LOWC_OPTION_SETTINGS,
					'fields' => array(
						array(
							'type'              => 'number',
							'id'                => DWS_LOWC_SETTING_LIMIT,
							'title'             => __( 'Limit', 'linked-orders-for-woocommerce' ),
							'default'           => (string) DWS_LOWC_SETTING_DEFAULTS[ DWS_LOWC_SETTING_LIMIT ],
							'custom_attributes' => array(
								'min'  => '1',
								'step' => '1',
							),
							'validate'          => 'dws_lowc_validate_limit',
						),
						array(
							'type'  => 'checkbox',
							'id'    => DWS_LOWC_SETTING_DELETE_DATA,
							'title' => __( 'Data on uninstall', 'linked-orders-for-woocommerce' ),
							'desc'  => __( 'Delete the plugin\'s data when it is uninstalled', 'linked-orders-for-woocommerce' ),
						),
					),
				),
			),
		);
	}

	// endregion
}
```

8. Replace `includes/options.php` with the one settings option, its fields, their defaults, and readers that guard each field. The file stays PHP 7.4-parseable, because Quality's syntax job lints `includes/`:

```php
<?php declare( strict_types=1 );
/**
 * Holds the plugin's settings option, its fields and their defaults, and reads each field as its type, guarding the stored value.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @package DeepWebSolutions\LinkedOrders
 */

use DeepWebSolutions\LinkedOrders\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * The option that holds the settings page's fields, keyed by field id.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_OPTION_SETTINGS = Plugin::PREFIX . '_settings';

/**
 * The settings field that holds 'yes' where the site consents to uninstall removing the plugin's data.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_SETTING_DELETE_DATA = 'delete_data';

/**
 * The settings field that holds the limit.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     string
 */
const DWS_LOWC_SETTING_LIMIT = 'limit';

/**
 * The value each settings field reads as while nothing valid is stored.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @var     array<string, bool|int>
 */
const DWS_LOWC_SETTING_DEFAULTS = array(
	DWS_LOWC_SETTING_DELETE_DATA => false,
	DWS_LOWC_SETTING_LIMIT       => 1,
);

/**
 * Returns a settings field as a boolean, which reads checkbox values such as 'yes' and 'no'.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $field The field's id.
 *
 * @return  bool
 */
function dws_lowc_get_bool_setting( string $field ): bool {
	$default = dws_lowc_get_setting_default( $field );

	return filter_var( dws_lowc_get_setting( $field, $default ), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE ) ?? ( true === $default );
}

/**
 * Returns a settings field as an integer.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $field The field's id.
 *
 * @return  int
 */
function dws_lowc_get_int_setting( string $field ): int {
	$default = dws_lowc_get_setting_default( $field );

	return filter_var( dws_lowc_get_setting( $field, $default ), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE ) ?? ( is_int( $default ) ? $default : 0 );
}

/**
 * Returns the limit as the settings page stores it, or an error when the submission is not a whole number of at least 1.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   mixed $submitted The submitted value.
 *
 * @return  int|WP_Error
 */
function dws_lowc_validate_limit( $submitted ) {
	$limit = is_string( $submitted ) ? filter_var( $submitted, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) : false;

	return false === $limit ? new WP_Error( 'invalid_number', __( 'Enter a whole number of 1 or more, such as 3.', 'linked-orders-for-woocommerce' ) ) : $limit;
}

/**
 * Returns a settings field's stored value, or the fallback while the settings hold none.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $field    The field's id.
 * @param   mixed  $fallback The value to return while nothing is stored.
 *
 * @return  mixed
 */
function dws_lowc_get_setting( string $field, $fallback ) {
	$settings = get_option( DWS_LOWC_OPTION_SETTINGS );

	return is_array( $settings ) && array_key_exists( $field, $settings ) ? $settings[ $field ] : $fallback;
}

/**
 * Returns a settings field's default, or null for a field without one.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @param   string $field The field's id.
 *
 * @return  mixed
 */
function dws_lowc_get_setting_default( string $field ) {
	return DWS_LOWC_SETTING_DEFAULTS[ $field ] ?? null;
}
```

9. In `includes/migrations.php`, the first step stores the defaults as they are. A stored `false` renders as an unchecked checkbox, the readers guard it, and uninstall consents only on `'yes'`:

```php
/**
 * Stores the settings' defaults, autoloaded, so a site that never saved the settings page reads them without a query.
 *
 * @since   2.0.0
 * @version 2.0.0
 *
 * @throws  RuntimeException Thrown when the defaults could not be stored.
 *
 * @return  void
 */
function dws_lowc_add_settings(): void {
	if ( false === get_option( DWS_LOWC_OPTION_SETTINGS ) && ! add_option( DWS_LOWC_OPTION_SETTINGS, DWS_LOWC_SETTING_DEFAULTS, '', true ) ) {
		throw new RuntimeException( 'The option ' . DWS_LOWC_OPTION_SETTINGS . ' could not be stored.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer-facing, never rendered.
	}
}
```

10. In `uninstall.php`, delete the `MetaTypes` import, and take consent from the settings row's `delete_data` key:

```php
	$dws_lowc_footprint = dws_lowc_get_footprint();
	$dws_lowc_consent   = array(
		'option' => DWS_LOWC_OPTION_SETTINGS,
		'key'    => DWS_LOWC_SETTING_DELETE_DATA,
	);
// ...

Uninstaller::run(
	$dws_lowc_file,
	$dws_lowc_footprint,
	array( 'consent' => $dws_lowc_consent )
);
```

11. In `.wp-env.json` and `.wp-env.tests.json`, delete the WooCommerce mapping, and let `afterStart` only activate the plugin; `.wp-env.json`'s command takes no `--config`:

```json
"mappings": {
    "wp-content/plugins/linked-orders-for-woocommerce": "."
},
"lifecycleScripts": {
    "afterStart": "node_modules/.bin/wp-env --config .wp-env.tests.json run cli wp plugin activate linked-orders-for-woocommerce"
}
```

12. Adapt the tests:
- Delete `tests/e2e/without-woocommerce.spec.ts`.
- `OptionsTest` reads through `dws_lowc_get_bool_setting()` and `dws_lowc_get_int_setting()` from a `DWS_LOWC_OPTION_SETTINGS` row; a malformed limit reads its default 1; the validator rows call `dws_lowc_validate_limit()`, which returns integers.
- `MigrationsTest` expects `DWS_LOWC_SETTING_DEFAULTS` stored autoloaded, a stored row kept as it is, and the throw when that option's `INSERT` fails.
- `UninstallTest` consents through `array( DWS_LOWC_SETTING_DELETE_DATA => 'yes' )`, seeds and asserts no Action Scheduler action, and expects the defaults stored again afterwards.
- `PluginBootTest` expects nothing in `$plugin->kernel->skipped`.
- `SettingsTest` builds `new OptionsPage( Plugin::PREFIX, new Settings() )` and calls its `register_settings()` in `setUpBeforeClass()`, which first requires `wp-admin/includes/template.php`. `tearDownAfterClass()` removes the page's `pre_update_option_` and `option_page_capability_` filters and calls `unregister_setting()`. Each test resets `$GLOBALS['wp_settings_errors']`, renders through `render_page()` and saves as `options.php` does:

```php
	// options.php saves a submission through update_option(), whose filter the page hooks for its own option page only.
	protected function save( array $posted ): void {
		$GLOBALS['pagenow']      = 'options.php';
		$_REQUEST['option_page'] = DWS_LOWC_OPTION_SETTINGS;

		try {
			update_option( DWS_LOWC_OPTION_SETTINGS, $posted );
		} finally {
			unset( $GLOBALS['pagenow'], $_REQUEST['option_page'] );
		}
	}
```

- `tests/e2e/settings.spec.ts` drives the page's limit field with the same checks: it focuses `page.getByLabel( 'Limit' )`, tabs to the `Save Changes` button and scopes axe to the form:

```ts
const SAVED = 'Settings saved.';
const REJECTED =
	'Limit was not saved, so it keeps its previous value. Enter a whole number of 1 or more, such as 3.';
const FORM = 'form[action="options.php"]';

// ...

	test.beforeEach( async ( { admin } ) => {
		await admin.visitAdminPage( 'options-general.php', 'page=dws_lowc' );
	} );
```

13. Update `AGENTS.md`, `README.md` and `docs/ARCHITECTURE.md` wherever they name WooCommerce, the greeting, the settings tab or the order-tables declaration.
