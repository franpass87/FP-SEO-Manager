<?php
/**
 * QA Metabox service provider.
 *
 * Registers the Q&A pairs metabox for WordPress editor.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Infrastructure\Providers\Metaboxes;

use FP\SEO\Infrastructure\Container;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Admin\QAMetabox;
use FP\SEO\Integrations\OpenAiClient;

/**
 * QA Metabox service provider.
 *
 * Registers the Q&A pairs metabox.
 * Note: The metabox content is integrated into the main SEO metabox,
 * but this service is still registered for asset enqueuing and other functionality.
 */
class QAMetaboxServiceProvider extends AbstractMetaboxServiceProvider {

	/**
	 * Get an array of service provider class names that this provider depends on.
	 *
	 * @return array<class-string<ServiceProviderInterface>> An array of fully qualified class names.
	 */
	public function get_dependencies(): array {
		return array(
			\FP\SEO\Infrastructure\Providers\CoreServiceProvider::class,
		);
	}

	/**
	 * Get the metabox class name that this provider manages.
	 *
	 * @return string The metabox class name.
	 */
	protected function get_metabox_class(): string {
		return QAMetabox::class;
	}

	/**
	 * Register QA metabox service in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register QA metabox with QAPairExtractor, OpenAiClient, and HookManager dependencies
		$container->singleton( QAMetabox::class, function( Container $container ) {
			$extractor     = $container->get( \FP\SEO\AI\QAPairExtractor::class );
			$openai_client = $container->get( OpenAiClient::class );
			$hook_manager  = $container->get( HookManagerInterface::class );
			return new QAMetabox( $extractor, $openai_client, $hook_manager );
		} );
	}
}





