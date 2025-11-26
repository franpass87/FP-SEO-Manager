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
use FP\SEO\Admin\QAMetaBox;

/**
 * QA Metabox service provider.
 *
 * Registers the Q&A pairs metabox.
 * Note: The metabox content is integrated into the main SEO metabox,
 * but this service is still registered for asset enqueuing and other functionality.
 */
class QAMetaboxServiceProvider extends AbstractMetaboxServiceProvider {

	/**
	 * Get the metabox class name that this provider manages.
	 *
	 * @return string The metabox class name.
	 */
	protected function get_metabox_class(): string {
		return QAMetaBox::class;
	}

	/**
	 * Register QA metabox service in the container.
	 *
	 * @param Container $container The container instance.
	 * @return void
	 */
	protected function register_admin( Container $container ): void {
		// Register QA metabox as singleton
		$container->singleton( QAMetaBox::class );
	}
}





