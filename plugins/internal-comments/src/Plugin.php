<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\AdminNotice\AdminNotices;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Logging\ErrorLogLogger;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Migration\MigrationRunner;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Permission\CapabilityGrants;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\PluginBoot;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\PluginIdentity;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\PluginKernel;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Scheduling\Scheduler;
use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Scheduling\SchedulerInterface;
use DeepWebSolutions\InternalComments\Scoped\DI\ContainerBuilder;
use DeepWebSolutions\InternalComments\Scoped\Psr\Log\LoggerInterface as PsrLoggerInterface;

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
	public const string SLUG = 'internal-comments';

	/**
	 * The prefix of the plugin's hook names, options, transients and scheduled jobs.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     string
	 */
	public const string PREFIX = 'dws_ic';

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
		Permissions::class,
		Comments::class,
		MetaBox::class,
		RestRoutes::class,
		AdminScreens::class,
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
	 * The scheduler, which the components and the deactivation callback share.
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
		$this->identity  = new PluginIdentity( DWS_IC_FILE, self::SLUG, self::PREFIX );
		$this->logger    = new ErrorLogLogger( $this->identity );
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
			foreach ( dws_ic_get_footprint()[ $kind ] ?? array() as $hook ) {
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
	 * Registers the scheduler and the migrations, then builds the kernel and boots the plugin's components.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  bool
	 */
	protected function compose(): bool {
		$this->scheduler->register_hooks();
		$this->migrations = new MigrationRunner( $this->identity, dws_ic_get_migration_steps(), $this->logger, $this->scheduler );
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
			CapabilityGrants::class   => static fn (): CapabilityGrants => new CapabilityGrants( $identity, DWS_IC_CAPABILITY_GRANTS, $logger ),
		);
	}

	// endregion
}
