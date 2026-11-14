<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Asset\Assets;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Permission\CapabilityGrants;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\PluginBoot;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\PluginKernel;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Scheduling\SchedulerInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\WooCommerce\Logging\WooCommerceLogger;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\WooCommerce\WooCommerceRequirement;
use DeepWebSolutions\LinkedOrders\Scoped\DI\ContainerBuilder;
use DeepWebSolutions\LinkedOrders\Scoped\Psr\Log\LoggerInterface as PsrLoggerInterface;

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
	public const string SLUG = 'linked-orders-for-woocommerce';

	/**
	 * The prefix of the plugin's hook names, options, transients and scheduled jobs.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string PREFIX = 'dws_lowc';

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
		ChildCreation::class,
		MetaBox::class,
		ListTable::class,
		Autocompletion::class,
		OrderDeletion::class,
		Integration\SequentialOrderNumbersPro::class,
	);

	/**
	 * The plugin's stored data by kind, which uninstall and deactivation read.
	 *
	 * It also covers what v1 stored under its own names.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     array{option_prefixes: list<non-empty-string>, options: list<non-empty-string>, meta_prefixes: array<string, list<non-empty-string>>, user_meta_site_prefixes: list<non-empty-string>, user_meta_global_prefixes: list<non-empty-string>, user_meta_global: list<non-empty-string>, network_options: list<non-empty-string>, capabilities: list<non-empty-string>, cron_hooks: list<non-empty-string>, as_groups: list<non-empty-string>}
	 */
	public const array FOOTPRINT = array(
		'option_prefixes'           => array( self::PREFIX . '_', '_transient_' . self::PREFIX . '_', '_transient_timeout_' . self::PREFIX . '_', 'dws-lowc_' ),
		'options'                   => array( 'linked_orders_for_woocommerce_version', 'linked_orders_for_woocommerce_original_version', '_linked_orders_for_woocommerce_admin_notices' ),
		'meta_prefixes'             => array( 'order' => array( '_dws_lo_' ) ),
		'user_meta_site_prefixes'   => array( self::PREFIX . '_' ),
		'user_meta_global_prefixes' => array( self::PREFIX . '_' ),
		'user_meta_global'          => array( '_linked_orders_for_woocommerce_admin_notices' ),
		'network_options'           => array( 'dws_lowc_network_notices' ),
		'capabilities'              => array( Capabilities::CREATE_LINKED_CHILDREN, Capabilities::SEE_ORDER_LINKS, Capabilities::SEE_METABOX, Capabilities::SEE_TABLE_COLUMN ),
		'cron_hooks'                => array( 'linked_orders_for_woocommerce/migration/run' ),
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
		$this->identity  = new PluginIdentity( DWS_LOWC_FILE, self::SLUG, self::PREFIX );
		$this->logger    = new WooCommerceLogger( $this->identity, \defined( 'DWS_LOWC_DEBUG' ) && true === \filter_var( \constant( 'DWS_LOWC_DEBUG' ), \FILTER_VALIDATE_BOOL, \FILTER_NULL_ON_FAILURE ) ? 'debug' : null );
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
			Assets::class             => static fn (): Assets => new Assets( $identity ),
			CapabilityGrants::class   => static fn (): CapabilityGrants => new CapabilityGrants( $identity, Capabilities::GRANTS, $logger ),
		);
	}

	// endregion
}
