<?php declare( strict_types=1 );

namespace DeepWebSolutions\LinkedOrders;

use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\PluginBoot;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\PluginKernel;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\Core\Scheduling\SchedulerInterface;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\WooCommerce\Logging\WooCommerceLogger;
use DeepWebSolutions\LinkedOrders\Scoped\DeepWebSolutions\Framework\WooCommerce\WooCommerceRequirement;
use DeepWebSolutions\LinkedOrders\Scoped\DI\ContainerBuilder;
use DeepWebSolutions\LinkedOrders\Scoped\Psr\Log\LoggerInterface as PsrLoggerInterface;

\defined( 'ABSPATH' ) || exit;

/**
 * Composes the plugin from its logger, scheduler, migrations and components, inside the framework's boot boundary.
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
		Settings::class,
		Blocks::class,
		Feature\Feature::class,
	);

	/**
	 * The boot boundary, whose status tells whether the plugin booted.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     PluginBoot
	 */
	public readonly PluginBoot $boot;

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

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	public function __construct() {
		$this->logger    = new WooCommerceLogger( self::SLUG, \defined( 'DWS_LOWC_DEBUG' ) && true === \constant( 'DWS_LOWC_DEBUG' ) ? 'debug' : null );
		$this->scheduler = new Scheduler( self::PREFIX );
		$this->boot      = new PluginBoot( DWS_LOWC_FILE, self::PREFIX, $this->logger );
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
		$this->boot->run( $this->compose( ... ) );
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
			foreach ( dws_lowc_get_footprint()[ $kind ] ?? array() as $hook ) {
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
		if ( ! ( new WooCommerceRequirement( DWS_LOWC_FILE ) )->check() ) {
			return false;
		}

		$this->migrations = new MigrationRunner( DWS_LOWC_FILE, self::PREFIX . '_revision', dws_lowc_get_migration_steps(), $this->logger, $this->scheduler );
		$this->migrations->register_hooks();

		$this->kernel = new PluginKernel( new ContainerBuilder()->addDefinitions( $this->get_definitions( $this->logger, $this->scheduler ) )->build() );
		$this->kernel->boot( self::COMPONENTS );

		return true;
	}

	/**
	 * Returns the container's definitions of the services the root builds, which the kernel's components share.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @param   PsrLoggerInterface $logger    The plugin's logger.
	 * @param   Scheduler          $scheduler The plugin's scheduler.
	 *
	 * @return  array<string, \Closure(): object>
	 */
	protected function get_definitions( PsrLoggerInterface $logger, Scheduler $scheduler ): array {
		return array(
			PsrLoggerInterface::class => static fn (): PsrLoggerInterface => $logger,
			Scheduler::class          => static fn (): Scheduler => $scheduler,
			SchedulerInterface::class => static fn (): Scheduler => $scheduler,
			AdminNotices::class       => static fn (): AdminNotices => new AdminNotices( self::PREFIX, $logger ),
		);
	}

	// endregion
}
