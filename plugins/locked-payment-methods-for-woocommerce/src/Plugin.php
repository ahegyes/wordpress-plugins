<?php declare( strict_types=1 );

namespace DeepWebSolutions\LockedPaymentMethods;

use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\Permission\CapabilityGrants;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\PluginBoot;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\PluginKernel;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\Core\Scheduling\SchedulerInterface;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\WooCommerce\Logging\WooCommerceLogger;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DeepWebSolutions\Framework\WooCommerce\WooCommerceRequirement;
use DeepWebSolutions\LockedPaymentMethods\Scoped\DI\ContainerBuilder;
use DeepWebSolutions\LockedPaymentMethods\Scoped\Psr\Log\LoggerInterface as PsrLoggerInterface;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\GroupsStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\OrderStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\UserProfileStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\UserRoleStrategy;
use DeepWebSolutions\LockedPaymentMethods\UnlockStrategies\WooCommerceMembershipsStrategy;

\defined( 'ABSPATH' ) || exit;

/**
 * Composes the plugin inside the framework's boot boundary.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Plugin {
	// region FIELDS AND CONSTANTS

	/**
	 * The plugin's slug, which is also its text domain and its log source.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string SLUG = 'locked-payment-methods-for-woocommerce';

	/**
	 * The prefix of the plugin's hook names, options, transients and scheduled jobs.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string PREFIX = 'dws_lpmwc';

	/**
	 * The top-level components, in hook-registration order.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     list<class-string>
	 */
	public const array COMPONENTS = array(
		Scheduler::class,
		AdminNotices::class,
		CapabilityGrants::class,
		Settings::class,
		UserRoleStrategy::class,
		UserProfileStrategy::class,
		OrderStrategy::class,
		GroupsStrategy::class,
		WooCommerceMembershipsStrategy::class,
	);

	/**
	 * The plugin's stored data by kind, which uninstall and deactivation read.
	 *
	 * It also covers the grants on Groups groups and WooCommerce Memberships plans, and what v1 stored under its own names.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array{option_prefixes: list<non-empty-string>, options: list<non-empty-string>, meta: array<string, list<non-empty-string>>, meta_prefixes: array<string, list<non-empty-string>>, user_meta_site_prefixes: list<non-empty-string>, user_meta_global_prefixes: list<non-empty-string>, user_meta_global: list<non-empty-string>, network_options: list<non-empty-string>, capabilities: list<non-empty-string>, cron_hooks: list<non-empty-string>, as_groups: list<non-empty-string>}
	 */
	public const array FOOTPRINT = array(
		'option_prefixes'           => array( self::PREFIX . '_', '_transient_' . self::PREFIX . '_', '_transient_timeout_' . self::PREFIX . '_', 'dws-wc-lpm_' ),
		'options'                   => array( 'locked_payment_methods_for_woocommerce_version', 'locked_payment_methods_for_woocommerce_original_version', '_locked_payment_methods_for_woocommerce_admin_notices' ),
		'meta'                      => array( 'post' => array( '_dws_lpmwc_unlocked_payment_methods', '_dws_wc_lpm_unlocked_payment_methods' ) ),
		'meta_prefixes'             => array( 'order' => array( '_' . self::PREFIX . '_', '_dws_wc_lpm_' ) ),
		'user_meta_site_prefixes'   => array( self::PREFIX . '_' ),
		'user_meta_global_prefixes' => array( self::PREFIX . '_', 'dws_wc_lpm_grant_access_' ),
		'user_meta_global'          => array( '_locked_payment_methods_for_woocommerce_admin_notices' ),
		'network_options'           => array( 'dws_lpmwc_network_notices' ),
		'capabilities'              => array( Capabilities::UNLOCK_VIA_USER_PROFILES, Capabilities::UNLOCK_VIA_ORDERS, Capabilities::UNLOCK_VIA_GROUPS, Capabilities::UNLOCK_VIA_WC_MEMBERSHIPS ),
		'cron_hooks'                => array( 'locked_payment_methods_for_woocommerce/migration/run' ),
		'as_groups'                 => array( self::PREFIX ),
	);

	/**
	 * The boot boundary, whose status tells whether the plugin booted.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     PluginBoot
	 */
	public readonly PluginBoot $boundary;

	/**
	 * The scheduler, which registers its WP-Cron schedules even while the plugin is blocked.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     Scheduler
	 */
	public readonly Scheduler $scheduler;

	/**
	 * The migration runner, once the composition built it.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     MigrationRunner|null
	 */
	protected(set) ?MigrationRunner $migrations = null;

	/**
	 * The kernel, once the composition built it, which tells which components loaded and which were gated off.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     PluginKernel|null
	 */
	protected(set) ?PluginKernel $kernel = null;

	/**
	 * The logger every part of the plugin writes to.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     PsrLoggerInterface
	 */
	protected readonly PsrLoggerInterface $logger;

	/**
	 * The plugin's identity, which every framework class the root builds takes.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     PluginIdentity
	 */
	protected readonly PluginIdentity $identity;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function __construct() {
		$this->identity  = new PluginIdentity( DWS_LPMWC_FILE, self::SLUG, self::PREFIX );
		$this->logger    = new WooCommerceLogger( $this->identity, \defined( 'DWS_LPMWC_DEBUG' ) && true === \constant( 'DWS_LPMWC_DEBUG' ) ? 'debug' : null );
		$this->scheduler = new Scheduler( $this->identity );
		$this->boundary  = new PluginBoot( $this->identity, $this->logger );
	}

	// endregion

	// region METHODS

	/**
	 * Runs the composition inside the framework's boot boundary.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function boot(): void {
		$this->boundary->run( $this->compose( ... ) );
	}

	/**
	 * Unschedules the footprint's jobs on the current site, logging each one that could not be unscheduled.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function deactivate(): void {
		foreach ( array( 'cron_hooks', 'as_hooks' ) as $kind ) {
			foreach ( self::FOOTPRINT[ $kind ] ?? array() as $hook ) {
				$unscheduled = $this->scheduler->unschedule_hook( $hook );
				if ( true !== $unscheduled ) {
					$this->logger->warning(
						'Could not unschedule {hook} on deactivation: {error}',
						array(
							'hook'      => $hook,
							'error'     => $unscheduled->get_error_message(),
							'exception' => $unscheduled->get_error_data(),
						)
					);
				}
			}
		}
	}

	// endregion

	// region HELPERS

	/**
	 * Builds the plugin and boots its components, returning false when WooCommerce is missing or too old.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	protected function compose(): bool {
		$this->scheduler->register_hooks(); // Before the WooCommerce check, so the plugin's recurring WP-Cron events keep their schedules while it is blocked.
		if ( ! WooCommerceRequirement::check( $this->identity ) ) {
			return false;
		}

		$this->migrations = new MigrationRunner( $this->identity, Migrations::get_steps(), $this->logger, $this->scheduler );
		$this->migrations->register_hooks();

		$this->kernel = new PluginKernel( new ContainerBuilder()->addDefinitions( $this->get_definitions( $this->identity, $this->logger, $this->scheduler ) )->build() );
		$this->kernel->boot( self::COMPONENTS );
		return true;
	}

	/**
	 * Returns the container's definitions of the services the root builds, which the kernel's components share.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   PluginIdentity     $identity  The plugin's identity.
	 * @param   PsrLoggerInterface $logger    The plugin's logger.
	 * @param   Scheduler          $scheduler The plugin's scheduler.
	 *
	 * @return  array<string, \Closure(): object>
	 */
	protected function get_definitions( PluginIdentity $identity, PsrLoggerInterface $logger, Scheduler $scheduler ): array {
		return array(
			PluginIdentity::class     => static fn (): PluginIdentity => $identity,
			PsrLoggerInterface::class => static fn (): PsrLoggerInterface => $logger,
			Scheduler::class          => static fn (): Scheduler => $scheduler,
			SchedulerInterface::class => static fn (): Scheduler => $scheduler,
			AdminNotices::class       => static fn (): AdminNotices => new AdminNotices( $identity, $logger ),
			CapabilityGrants::class   => static fn (): CapabilityGrants => new CapabilityGrants( $identity, Capabilities::GRANTS, $logger ),
		);
	}

	// endregion
}
