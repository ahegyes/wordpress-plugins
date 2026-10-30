<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders\Tests\Integration;

use DeepWebSolutions\LinkedOrders\Plugin;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\WooCommerce\Setting\SettingsTab;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class SettingsTest extends TestCase {
	protected const array KEYS = array( DWS_LOWC_OPTION_DELETE_DATA );

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

	public function test_the_tab_shows_the_data_field_with_its_default(): void {
		foreach ( self::KEYS as $key ) {
			delete_option( $key );
		}

		$form = $this->render();

		self::assertSame( 'Linked Orders for WooCommerce', self::$tab->get_label() );
		self::assertFalse( $form->getElementById( DWS_LOWC_OPTION_DELETE_DATA )?->hasAttribute( 'checked' ) );
	}

	public function test_the_retention_opt_in_round_trips_through_woocommerces_save(): void {
		$this->save( array( DWS_LOWC_OPTION_DELETE_DATA => '1' ) );

		self::assertSame( 'yes', get_option( DWS_LOWC_OPTION_DELETE_DATA ) );
		self::assertTrue( dws_lowc_get_bool_option( DWS_LOWC_OPTION_DELETE_DATA ) );
		self::assertTrue( $this->render()->getElementById( DWS_LOWC_OPTION_DELETE_DATA )?->hasAttribute( 'checked' ) );

		$this->save( array() );

		self::assertSame( 'no', get_option( DWS_LOWC_OPTION_DELETE_DATA ) );
		self::assertFalse( dws_lowc_get_bool_option( DWS_LOWC_OPTION_DELETE_DATA ) );
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
