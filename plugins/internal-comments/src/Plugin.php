<?php declare( strict_types=1 );

namespace DeepWebSolutions\InternalComments;

use DeepWebSolutions\InternalComments\Scoped\DeepWebSolutions\Framework\Core\Kernel\PluginKernel;
use DeepWebSolutions\InternalComments\Scoped\DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

/**
 * Plugin singleton — holds the DI container, the {@see PluginKernel}, and
 * dispatches the activation / deactivation / boot lifecycle.
 *
 * Boot runs on `plugins_loaded` priority 15 so plugins hooking at the default
 * priority 10 (e.g. WooCommerce) are already initialized.
 *
 * @since   2.0.0
 * @version 2.0.0
 */
final class Plugin {
	// region FIELDS AND CONSTANTS

	/**
	 * Singleton instance.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     self|null
	 */
	private static ?self $instance = null;

	/**
	 * PSR-11 container resolving registered components.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     ContainerInterface
	 */
	private ContainerInterface $container;

	/**
	 * Lifecycle dispatcher resolved from the container.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @var     PluginKernel
	 */
	private PluginKernel $kernel;

	// endregion

	// region MAGIC METHODS

	/**
	 * Builds the container, resolves the kernel, and registers components.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 */
	private function __construct() {
		$builder = new ContainerBuilder();
		$builder->addDefinitions( __DIR__ . '/../config/container.php' );

		$this->container = $builder->build();
		$this->kernel    = $this->container->get( PluginKernel::class );

		$this->kernel->register( AdminNotice::class );
	}

	// endregion

	// region GETTERS

	/**
	 * Returns the plugin's singleton instance, creating it on first call.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  self
	 */
	public static function get_instance(): self {
		return self::$instance ??= new self();
	}

	// endregion

	// region METHODS

	/**
	 * Runs the kernel's two-pass component lifecycle. Idempotent.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function boot(): void {
		$this->kernel->boot();
	}

	/**
	 * Plugin activation hook — install-time setup.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function activate(): void {
	}

	/**
	 * Plugin deactivation hook — install-time teardown.
	 *
	 * @since   2.0.0
	 * @version 2.0.0
	 *
	 * @return  void
	 */
	public function deactivate(): void {
	}

	// endregion
}
