<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments\Tests\Integration;

use DeepWebSolutions\InternalComments\Plugin;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\WooCommerce\Setting\SettingsTab;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SettingsTest extends TestCase {
	protected const array KEYS = array( DWS_IC_OPTION_FEATURE, DWS_IC_OPTION_GREETING, DWS_IC_OPTION_GREETING_ORDERS, DWS_IC_OPTION_DELETE_DATA );

	protected static SettingsTab $tab;

	protected array $stored = array();

	#[\Override]
	public static function setUpBeforeClass(): void {
		require_once WC_ABSPATH . 'includes/admin/settings/class-wc-settings-page.php';
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-settings.php';

		// WooCommerce builds its settings pages once per request, so the class shares one build of the tab.
		foreach ( apply_filters( 'woocommerce_get_settings_pages', array() ) as $page ) {
			if ( $page instanceof SettingsTab && Plugin::PREFIX === $page->get_id() ) {
				self::$tab = $page;
			}
		}
	}

	public static function invalid_order_counts(): array {
		return array(
			'zero'     => array( '0' ),
			'negative' => array( '-2' ),
			'decimal'  => array( '2.5' ),
			'word'     => array( 'three' ),
			'empty'    => array( '' ),
		);
	}

	#[Before]
	protected function store_rows(): void {
		foreach ( self::KEYS as $key ) {
			$this->stored[ $key ] = get_option( $key );
		}
	}

	#[After]
	protected function restore_rows(): void {
		foreach ( $this->stored as $key => $value ) {
			false === $value ? delete_option( $key ) : update_option( $key, $value );
		}

		( new \ReflectionProperty( \WC_Admin_Settings::class, 'errors' ) )->setValue( null, array() );
	}

	public function test_the_tab_shows_the_greeting_and_data_fields_with_their_defaults(): void {
		foreach ( self::KEYS as $key ) {
			delete_option( $key );
		}

		$form = $this->render();

		self::assertSame( 'Internal Comments', self::$tab->get_label() );
		self::assertFalse( $form->getElementById( DWS_IC_OPTION_FEATURE )?->hasAttribute( 'checked' ) );
		self::assertSame( '', $form->getElementById( DWS_IC_OPTION_GREETING )?->getAttribute( 'value' ) );
		self::assertSame( dws_ic_get_option_text( DWS_IC_OPTION_GREETING ), $form->getElementById( DWS_IC_OPTION_GREETING )?->getAttribute( 'placeholder' ) );
		self::assertSame( '1', $form->getElementById( DWS_IC_OPTION_GREETING_ORDERS )?->getAttribute( 'value' ) );
		self::assertSame( '1', $form->getElementById( DWS_IC_OPTION_GREETING_ORDERS )?->getAttribute( 'min' ) );
		self::assertFalse( $form->getElementById( DWS_IC_OPTION_DELETE_DATA )?->hasAttribute( 'checked' ) );
	}

	public function test_a_save_that_leaves_the_message_untouched_stores_no_translated_text(): void {
		delete_option( DWS_IC_OPTION_GREETING );

		$this->save( array( DWS_IC_OPTION_GREETING => $this->render()->getElementById( DWS_IC_OPTION_GREETING )?->getAttribute( 'value' ) ) );

		self::assertSame( '', get_option( DWS_IC_OPTION_GREETING ) );
		self::assertSame( dws_ic_get_option_text( DWS_IC_OPTION_GREETING ), dws_ic_get_string_option( DWS_IC_OPTION_GREETING ) );
	}

	public function test_the_retention_opt_in_round_trips_through_woocommerces_save(): void {
		$this->save( array( DWS_IC_OPTION_DELETE_DATA => '1' ) );

		self::assertSame( 'yes', get_option( DWS_IC_OPTION_DELETE_DATA ) );
		self::assertTrue( dws_ic_get_bool_option( DWS_IC_OPTION_DELETE_DATA ) );
		self::assertTrue( $this->render()->getElementById( DWS_IC_OPTION_DELETE_DATA )?->hasAttribute( 'checked' ) );

		$this->save( array() );

		self::assertSame( 'no', get_option( DWS_IC_OPTION_DELETE_DATA ) );
		self::assertFalse( dws_ic_get_bool_option( DWS_IC_OPTION_DELETE_DATA ) );
	}

	#[DataProvider( 'invalid_order_counts' )]
	public function test_an_order_count_that_is_not_a_whole_number_of_at_least_one_keeps_the_stored_count( string $posted ): void {
		update_option( DWS_IC_OPTION_GREETING_ORDERS, '4' );

		$this->save( array( DWS_IC_OPTION_GREETING_ORDERS => $posted ) );

		self::assertSame( '4', get_option( DWS_IC_OPTION_GREETING_ORDERS ) );
		self::assertSame( array( 'Orders before the greeting was not saved, so it keeps its previous value. Enter a whole number of 1 or more, such as 3.' ), ( new \ReflectionProperty( \WC_Admin_Settings::class, 'errors' ) )->getValue() );
	}

	public function test_a_whole_order_count_of_at_least_one_is_stored(): void {
		$this->save( array( DWS_IC_OPTION_GREETING_ORDERS => ' 3 ' ) );

		self::assertSame( '3', get_option( DWS_IC_OPTION_GREETING_ORDERS ) );
		self::assertSame( 3, dws_ic_get_int_option( DWS_IC_OPTION_GREETING_ORDERS ) );
	}

	protected function render(): \Dom\HTMLDocument {
		\ob_start();
		\WC_Admin_Settings::output_fields( self::$tab->get_settings_for_section( '' ) );

		return \Dom\HTMLDocument::createFromString( '<!DOCTYPE html><html><body>' . \ob_get_clean() . '</body></html>', \LIBXML_NOERROR );
	}

	// WooCommerce's form always posts its save button, so a save that changes nothing still runs.
	protected function save( array $posted ): void {
		\WC_Admin_Settings::save_fields( self::$tab->get_settings_for_section( '' ), $posted + array( 'save' => 'Save changes' ) );
		wp_cache_flush();
	}
}
