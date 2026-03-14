<?php
/**
 * Redirect Manager admin page - 301/302 redirects + bulk import.
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

declare(strict_types=1);

namespace FP\SEO\Admin;

use FP\SEO\GEO\HtmlSitemap;
use FP\SEO\Monitoring\SeoMonitorRepository;
use FP\SEO\Redirects\RedirectRepository;
use FP\SEO\Redirects\RedirectsOptions;
use FP\SEO\Redirects\XmlSitemap;
use FP\SEO\Infrastructure\Contracts\HookManagerInterface;
use FP\SEO\Utils\OptionsHelper;
use function add_submenu_page;
use function admin_url;
use function check_admin_referer;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html__;
use function esc_textarea;
use function esc_js;
use function esc_url;
use function home_url;
use function sanitize_text_field;
use function wp_die;
use function wp_enqueue_style;
use function wp_nonce_field;
use function sanitize_textarea_field;
use function wp_safe_redirect;
use function wp_unslash;

/**
 * Admin page for managing 301/302 redirects and bulk import.
 */
class RedirectManagerPage {

	private const PAGE_SLUG      = 'fp-seo-redirects';
	private const PAGE_PARENT    = 'fp-seo-performance';
	private const NONCE_ACTION   = 'fp_seo_redirects';
	private const AJAX_ACTION    = 'fp_seo_redirects';
	private const BULK_ACTION    = 'fp_seo_redirects_bulk_import';
	private const SETTINGS_ACTION = 'fp_seo_redirects_settings';
	private const IMPORT_ACTION   = 'fp_seo_import_external_meta';

	/**
	 * Hook manager instance.
	 *
	 * @var HookManagerInterface
	 */
	private HookManagerInterface $hook_manager;

	/**
	 * Redirect repository.
	 *
	 * @var RedirectRepository
	 */
	private RedirectRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param HookManagerInterface   $hook_manager Hook manager.
	 * @param RedirectRepository|null $repository  Redirect repository.
	 */
	public function __construct( HookManagerInterface $hook_manager, ?RedirectRepository $repository = null ) {
		$this->hook_manager = $hook_manager;
		$this->repository   = $repository ?? new RedirectRepository();
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		$this->hook_manager->add_action( 'admin_menu', array( $this, 'add_page' ) );
		$this->hook_manager->add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		$this->hook_manager->add_action( 'admin_post_' . self::BULK_ACTION, array( $this, 'handle_bulk_import' ) );
		$this->hook_manager->add_action( 'admin_post_' . self::SETTINGS_ACTION, array( $this, 'handle_settings_save' ) );
		$this->hook_manager->add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'handle_external_import' ) );
		$this->hook_manager->add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle_ajax' ) );
	}

	/**
	 * Add submenu page.
	 */
	public function add_page(): void {
		$capability = OptionsHelper::get_capability();

		add_submenu_page(
			self::PAGE_PARENT,
			__( 'Redirect Manager', 'fp-seo-performance' ),
			__( 'Redirect Manager', 'fp-seo-performance' ),
			$capability,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'fp-seo-performance_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'fp-seo-performance-admin' );
		wp_enqueue_style( 'fp-seo-ui-system' );
	}

	/**
	 * Handle bulk import form submission.
	 */
	public function handle_bulk_import(): void {
		if ( ! current_user_can( OptionsHelper::get_capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'fp-seo-performance' ) );
		}

		check_admin_referer( self::NONCE_ACTION . '_bulk', 'fp_seo_redirects_bulk_nonce' );

		$csv_raw = isset( $_POST['fp_seo_redirects_csv'] ) ? sanitize_textarea_field( wp_unslash( $_POST['fp_seo_redirects_csv'] ) ) : '';

		if ( '' === $csv_raw ) {
			wp_safe_redirect( add_query_arg( array(
				'page'   => self::PAGE_SLUG,
				'error'  => 'empty',
			), admin_url( 'admin.php' ) ) );
			exit;
		}

		$rows   = $this->parse_csv( $csv_raw );
		$result = $this->repository->bulk_create( $rows );

		wp_safe_redirect( add_query_arg( array(
			'page'     => self::PAGE_SLUG,
			'imported' => $result['inserted'],
			'skipped'  => $result['skipped'],
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle settings form submission (redirects + sitemap options).
	 */
	public function handle_settings_save(): void {
		if ( ! current_user_can( OptionsHelper::get_capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'fp-seo-performance' ) );
		}

		check_admin_referer( self::NONCE_ACTION . '_settings', 'fp_seo_redirects_settings_nonce' );

		$redirects_enabled = isset( $_POST['redirects_enabled'] ) && '1' === $_POST['redirects_enabled'];
		$redirect_priority = isset( $_POST['redirect_priority'] ) ? absint( $_POST['redirect_priority'] ) : 1;
		$redirect_priority = $redirect_priority >= 1 && $redirect_priority <= 99 ? $redirect_priority : 1;

		RedirectsOptions::save_redirects( array(
			'enabled'  => $redirects_enabled,
			'priority' => $redirect_priority,
		) );

		$sitemap_enabled     = isset( $_POST['html_sitemap_enabled'] ) && '1' === $_POST['html_sitemap_enabled'];
		$sitemap_max_section = isset( $_POST['html_sitemap_max'] ) ? absint( $_POST['html_sitemap_max'] ) : 500;
		$sitemap_max_section = $sitemap_max_section >= 10 && $sitemap_max_section <= 2000 ? $sitemap_max_section : 500;
		$sitemap_cache_ttl   = isset( $_POST['html_sitemap_cache_ttl'] ) ? absint( $_POST['html_sitemap_cache_ttl'] ) : 3600;
		$sitemap_cache_ttl   = $sitemap_cache_ttl >= 60 && $sitemap_cache_ttl <= 86400 ? $sitemap_cache_ttl : 3600;

		RedirectsOptions::save_html_sitemap( array(
			'enabled'         => $sitemap_enabled,
			'max_per_section' => $sitemap_max_section,
			'cache_ttl'       => $sitemap_cache_ttl,
		) );

		$xml_enabled      = isset( $_POST['xml_sitemap_enabled'] ) && '1' === $_POST['xml_sitemap_enabled'];
		$xml_max_urls     = isset( $_POST['xml_sitemap_max_urls'] ) ? absint( $_POST['xml_sitemap_max_urls'] ) : 1000;
		$xml_max_urls     = $xml_max_urls >= 100 && $xml_max_urls <= 5000 ? $xml_max_urls : 1000;
		$xml_cache_ttl    = isset( $_POST['xml_sitemap_cache_ttl'] ) ? absint( $_POST['xml_sitemap_cache_ttl'] ) : 3600;
		$xml_cache_ttl    = $xml_cache_ttl >= 60 && $xml_cache_ttl <= 86400 ? $xml_cache_ttl : 3600;
		$xml_post_types   = isset( $_POST['xml_sitemap_post_types'] ) && is_array( $_POST['xml_sitemap_post_types'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['xml_sitemap_post_types'] ) )
			: array( 'post', 'page' );

		RedirectsOptions::save_xml_sitemap(
			array(
				'enabled'           => $xml_enabled,
				'max_urls_per_file' => $xml_max_urls,
				'cache_ttl'         => $xml_cache_ttl,
				'post_types'        => $xml_post_types,
			)
		);

		$hreflang_enabled  = isset( $_POST['hreflang_enabled'] ) && '1' === $_POST['hreflang_enabled'];
		$hreflang_xdefault = isset( $_POST['hreflang_xdefault'] ) && '1' === $_POST['hreflang_xdefault'];
		RedirectsOptions::save_meta_rendering(
			array(
				'hreflang_enabled'  => $hreflang_enabled,
				'include_x_default' => $hreflang_xdefault,
			)
		);

		$robots_enabled = isset( $_POST['robots_enabled'] ) && '1' === $_POST['robots_enabled'];
		$robots_rules   = isset( $_POST['robots_extra_rules'] ) ? sanitize_textarea_field( wp_unslash( $_POST['robots_extra_rules'] ) ) : '';
		RedirectsOptions::save_robots(
			array(
				'enabled'     => $robots_enabled,
				'extra_rules' => $robots_rules,
			)
		);

		$breadcrumb_enabled = isset( $_POST['breadcrumb_enabled'] ) && '1' === $_POST['breadcrumb_enabled'];
		$breadcrumb_home    = isset( $_POST['breadcrumb_show_home'] ) && '1' === $_POST['breadcrumb_show_home'];
		RedirectsOptions::save_breadcrumb(
			array(
				'enabled'   => $breadcrumb_enabled,
				'show_home' => $breadcrumb_home,
			)
		);

		HtmlSitemap::flush_cache();
		XmlSitemap::flush_cache();

		wp_safe_redirect( add_query_arg( array(
			'page'    => self::PAGE_SLUG,
			'saved'   => '1',
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle one-shot import from Yoast/RankMath/AIOSEO.
	 */
	public function handle_external_import(): void {
		if ( ! current_user_can( OptionsHelper::get_capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'fp-seo-performance' ) );
		}

		check_admin_referer( self::NONCE_ACTION . '_import', 'fp_seo_import_nonce' );
		$source = isset( $_POST['import_source'] ) ? sanitize_key( wp_unslash( $_POST['import_source'] ) ) : '';

		$map = array(
			'yoast' => array(
				'title'       => '_yoast_wpseo_title',
				'description' => '_yoast_wpseo_metadesc',
				'focus'       => '_yoast_wpseo_focuskw',
			),
			'rankmath' => array(
				'title'       => 'rank_math_title',
				'description' => 'rank_math_description',
				'focus'       => 'rank_math_focus_keyword',
			),
			'aioseo' => array(
				'title'       => '_aioseo_title',
				'description' => '_aioseo_description',
				'focus'       => '_aioseo_focus_keyphrase',
			),
		);

		if ( ! isset( $map[ $source ] ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'import_status' => 'invalid' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		$imported = 0;
		$posts    = get_posts(
			array(
				'post_type'              => get_post_types( array( 'public' => true ), 'names' ),
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'         => 200,
				'suppress_filters'       => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $posts as $post ) {
			$post_id = (int) $post->ID;
			$title   = (string) get_post_meta( $post_id, $map[ $source ]['title'], true );
			$desc    = (string) get_post_meta( $post_id, $map[ $source ]['description'], true );
			$focus   = (string) get_post_meta( $post_id, $map[ $source ]['focus'], true );

			$updated = false;
			if ( '' !== trim( $title ) && '' === (string) get_post_meta( $post_id, '_fp_seo_title', true ) ) {
				update_post_meta( $post_id, '_fp_seo_title', sanitize_text_field( $title ) );
				$updated = true;
			}
			if ( '' !== trim( $desc ) && '' === (string) get_post_meta( $post_id, '_fp_seo_meta_description', true ) ) {
				update_post_meta( $post_id, '_fp_seo_meta_description', sanitize_textarea_field( $desc ) );
				$updated = true;
			}
			if ( '' !== trim( $focus ) && '' === (string) get_post_meta( $post_id, '_fp_seo_focus_keyword', true ) ) {
				update_post_meta( $post_id, '_fp_seo_focus_keyword', sanitize_text_field( $focus ) );
				$updated = true;
			}

			if ( $updated ) {
				++$imported;
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => self::PAGE_SLUG,
					'import_status' => 'ok',
					'import_source' => $source,
					'imported'      => $imported,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle AJAX (delete, toggle, add).
	 */
	public function handle_ajax(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( OptionsHelper::get_capability() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'fp-seo-performance' ) ) );
		}

		$action = isset( $_POST['sub_action'] ) ? sanitize_key( wp_unslash( $_POST['sub_action'] ) ) : '';

		switch ( $action ) {
			case 'delete':
				$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
				if ( $id && $this->repository->delete( $id ) ) {
					wp_send_json_success( array( 'message' => __( 'Redirect deleted.', 'fp-seo-performance' ) ) );
				}
				wp_send_json_error( array( 'message' => __( 'Failed to delete.', 'fp-seo-performance' ) ) );
				break;

			case 'toggle':
				$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
				$redirects = $this->repository->get_all( array( 'per_page' => 1000 ) );
				$current   = null;
				foreach ( $redirects as $r ) {
					if ( (int) $r['id'] === $id ) {
						$current = $r;
						break;
					}
				}
				if ( $current ) {
					$new_active = (int) $current['is_active'] ? 0 : 1;
					if ( $this->repository->update( $id, array( 'is_active' => (bool) $new_active ) ) ) {
						wp_send_json_success( array( 'active' => $new_active ) );
					}
				}
				wp_send_json_error( array( 'message' => __( 'Failed to update.', 'fp-seo-performance' ) ) );
				break;

			case 'add':
				$source = isset( $_POST['source_url'] ) ? sanitize_text_field( wp_unslash( $_POST['source_url'] ) ) : '';
				$target = isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '';
				$type   = isset( $_POST['redirect_type'] ) && $_POST['redirect_type'] === '302' ? '302' : '301';

				if ( '' === $source || '' === $target ) {
					wp_send_json_error( array( 'message' => __( 'Source and target URLs are required.', 'fp-seo-performance' ) ) );
				}

				$id = $this->repository->create( array(
					'source_url'    => $source,
					'target_url'    => $target,
					'redirect_type' => $type,
				) );

				if ( $id ) {
					wp_send_json_success( array( 'id' => $id, 'message' => __( 'Redirect added.', 'fp-seo-performance' ) ) );
				}
				wp_send_json_error( array( 'message' => __( 'Failed to add (duplicate source?).', 'fp-seo-performance' ) ) );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Invalid action.', 'fp-seo-performance' ) ) );
		}
	}

	/**
	 * Parse CSV content into rows.
	 *
	 * @param string $csv Raw CSV.
	 * @return array<int, array{source_url: string, target_url: string, redirect_type?: string}>
	 */
	private function parse_csv( string $csv ): array {
		$lines = preg_split( '/\r\n|\r|\n/', trim( $csv ) );
		$rows  = array();

		foreach ( (array) $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}

			$parts = str_getcsv( $line );
			$source = isset( $parts[0] ) ? trim( $parts[0] ) : '';
			$target = isset( $parts[1] ) ? trim( $parts[1] ) : '';
			$type   = isset( $parts[2] ) ? ( trim( $parts[2] ) === '302' ? '302' : '301' ) : '301';

			if ( '' !== $source && '' !== $target ) {
				$rows[] = array(
					'source_url'    => $source,
					'target_url'    => $target,
					'redirect_type' => $type,
				);
			}
		}

		return $rows;
	}

	/**
	 * Render the page.
	 */
	public function render(): void {
		if ( ! current_user_can( OptionsHelper::get_capability() ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'fp-seo-performance' ) );
		}

		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$type     = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		$paged    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 20;
		$offset   = ( $paged - 1 ) * $per_page;

		$args = array(
			'search'   => $search,
			'type'     => in_array( $type, array( '301', '302' ), true ) ? $type : '',
			'per_page' => $per_page,
			'offset'   => $offset,
		);

		$redirects   = $this->repository->get_all( $args );
		$total       = $this->repository->count( array_filter( array( 'search' => $search, 'type' => $args['type'] ) ) );
		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

		$sitemap_url     = home_url( '/sitemap/' );
		$xml_sitemap_url = home_url( '/fp-sitemap.xml' );
		$imported        = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;
		$skipped         = isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0;
		$error           = isset( $_GET['error'] ) ? sanitize_key( $_GET['error'] ) : '';
		$settings_saved  = isset( $_GET['saved'] ) && '1' === $_GET['saved'];
		$import_status   = isset( $_GET['import_status'] ) ? sanitize_key( $_GET['import_status'] ) : '';
		$import_source   = isset( $_GET['import_source'] ) ? sanitize_key( $_GET['import_source'] ) : '';
		$imported_count  = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;

		$redirects_opts   = RedirectsOptions::get_redirects();
		$sitemap_opts     = RedirectsOptions::get_html_sitemap();
		$xml_opts         = RedirectsOptions::get_xml_sitemap();
		$meta_opts        = RedirectsOptions::get_meta_rendering();
		$robots_opts      = RedirectsOptions::get_robots();
		$breadcrumb_opts  = RedirectsOptions::get_breadcrumb();
		$public_post_types = get_post_types( array( 'public' => true ), 'objects' );
		$top_404          = SeoMonitorRepository::top_404( 10 );
		$broken_links     = SeoMonitorRepository::get_broken_links( 10 );

		?>
		<div class="wrap fp-seo-admin-page">
			<div class="fp-seo-page-header">
				<div class="fp-seo-page-header-content">
					<h1><span class="dashicons dashicons-external"></span> <?php esc_html_e( 'Redirect Manager', 'fp-seo-performance' ); ?></h1>
					<p><?php esc_html_e( 'Manage 301 and 302 redirects. Redirects are applied before any page loads.', 'fp-seo-performance' ); ?></p>
				</div>
				<span class="fp-seo-page-header-badge"><?php echo esc_html( (string) $total ); ?> <?php esc_html_e( 'redirects', 'fp-seo-performance' ); ?></span>
			</div>

			<?php if ( $imported > 0 || $skipped > 0 ) : ?>
				<div class="fp-seo-alert fp-seo-alert-success">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php
					printf(
						/* translators: 1: Number inserted, 2: Number skipped */
						esc_html__( 'Bulk import: %1$d redirects added, %2$d skipped.', 'fp-seo-performance' ),
						$imported,
						$skipped
					);
					?>
				</div>
			<?php endif; ?>

			<?php if ( $error === 'empty' ) : ?>
				<div class="fp-seo-alert fp-seo-alert-warning">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Please paste CSV content to import.', 'fp-seo-performance' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $settings_saved ) : ?>
				<div class="fp-seo-alert fp-seo-alert-success">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php esc_html_e( 'Settings saved.', 'fp-seo-performance' ); ?>
				</div>
			<?php endif; ?>

			<?php if ( 'ok' === $import_status ) : ?>
				<div class="fp-seo-alert fp-seo-alert-success">
					<span class="dashicons dashicons-yes-alt"></span>
					<?php
					printf(
						/* translators: 1: source plugin, 2: imported posts count */
						esc_html__( 'Import %1$s completato: %2$d contenuti aggiornati.', 'fp-seo-performance' ),
						esc_html( strtoupper( $import_source ) ),
						$imported_count
					);
					?>
				</div>
			<?php elseif ( 'invalid' === $import_status ) : ?>
				<div class="fp-seo-alert fp-seo-alert-warning">
					<span class="dashicons dashicons-warning"></span>
					<?php esc_html_e( 'Sorgente import non valida.', 'fp-seo-performance' ); ?>
				</div>
			<?php endif; ?>

			<div class="fp-seo-card">
				<div class="fp-seo-card-header">
					<div class="fp-seo-card-header-left">
						<span class="dashicons dashicons-admin-generic"></span>
						<h2><?php esc_html_e( 'Impostazioni redirect e sitemap', 'fp-seo-performance' ); ?></h2>
					</div>
				</div>
				<div class="fp-seo-card-body">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( self::NONCE_ACTION . '_settings', 'fp_seo_redirects_settings_nonce' ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::SETTINGS_ACTION ); ?>">

						<div class="fp-seo-fields-grid">
							<div class="fp-seo-field">
								<label>
									<input type="checkbox" name="redirects_enabled" value="1" <?php checked( $redirects_opts['enabled'] ); ?>>
									<?php esc_html_e( 'Abilita redirect 301/302', 'fp-seo-performance' ); ?>
								</label>
								<span class="fp-seo-hint"><?php esc_html_e( 'Applica i redirect configurati al caricamento della pagina.', 'fp-seo-performance' ); ?></span>
							</div>
							<div class="fp-seo-field">
								<label for="redirect_priority"><?php esc_html_e( 'Priorità hook redirect', 'fp-seo-performance' ); ?></label>
								<input type="number" id="redirect_priority" name="redirect_priority" value="<?php echo esc_attr( (string) $redirects_opts['priority'] ); ?>" min="1" max="99" style="width:80px">
								<span class="fp-seo-hint"><?php esc_html_e( '1 = prima di tutto, 99 = dopo altri plugin. Filtro: fp_seo_redirect_priority', 'fp-seo-performance' ); ?></span>
							</div>
							<div class="fp-seo-field">
								<label>
									<input type="checkbox" name="html_sitemap_enabled" value="1" <?php checked( $sitemap_opts['enabled'] ); ?>>
									<?php esc_html_e( 'Abilita sitemap HTML', 'fp-seo-performance' ); ?>
								</label>
								<span class="fp-seo-hint"><?php esc_html_e( 'Serve la sitemap user-friendly su /sitemap/', 'fp-seo-performance' ); ?></span>
							</div>
							<div class="fp-seo-field">
								<label for="html_sitemap_max"><?php esc_html_e( 'Max elementi per sezione sitemap', 'fp-seo-performance' ); ?></label>
								<input type="number" id="html_sitemap_max" name="html_sitemap_max" value="<?php echo esc_attr( (string) $sitemap_opts['max_per_section'] ); ?>" min="10" max="2000" style="width:100px">
							</div>
							<div class="fp-seo-field">
								<label for="html_sitemap_cache_ttl"><?php esc_html_e( 'Cache sitemap (secondi)', 'fp-seo-performance' ); ?></label>
								<input type="number" id="html_sitemap_cache_ttl" name="html_sitemap_cache_ttl" value="<?php echo esc_attr( (string) $sitemap_opts['cache_ttl'] ); ?>" min="60" max="86400" style="width:100px">
								<span class="fp-seo-hint"><?php esc_html_e( '60–86400. 3600 = 1 ora.', 'fp-seo-performance' ); ?></span>
							</div>
							<div class="fp-seo-field">
								<label>
									<input type="checkbox" name="xml_sitemap_enabled" value="1" <?php checked( $xml_opts['enabled'] ); ?>>
									<?php esc_html_e( 'Abilita XML sitemap SEO', 'fp-seo-performance' ); ?>
								</label>
								<span class="fp-seo-hint"><?php esc_html_e( 'Espone /fp-sitemap.xml per i motori di ricerca.', 'fp-seo-performance' ); ?></span>
							</div>
							<div class="fp-seo-field">
								<label for="xml_sitemap_max_urls"><?php esc_html_e( 'URL max per file XML', 'fp-seo-performance' ); ?></label>
								<input type="number" id="xml_sitemap_max_urls" name="xml_sitemap_max_urls" value="<?php echo esc_attr( (string) $xml_opts['max_urls_per_file'] ); ?>" min="100" max="5000" style="width:100px">
							</div>
							<div class="fp-seo-field">
								<label for="xml_sitemap_cache_ttl"><?php esc_html_e( 'Cache XML sitemap (secondi)', 'fp-seo-performance' ); ?></label>
								<input type="number" id="xml_sitemap_cache_ttl" name="xml_sitemap_cache_ttl" value="<?php echo esc_attr( (string) $xml_opts['cache_ttl'] ); ?>" min="60" max="86400" style="width:100px">
							</div>
							<div class="fp-seo-field" style="grid-column: 1 / -1;">
								<label><?php esc_html_e( 'Post type inclusi nella XML sitemap', 'fp-seo-performance' ); ?></label>
								<div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:6px;">
									<?php foreach ( $public_post_types as $post_type_obj ) : ?>
										<?php if ( 'attachment' === $post_type_obj->name ) { continue; } ?>
										<label>
											<input type="checkbox" name="xml_sitemap_post_types[]" value="<?php echo esc_attr( $post_type_obj->name ); ?>" <?php checked( in_array( $post_type_obj->name, $xml_opts['post_types'], true ) ); ?>>
											<?php echo esc_html( $post_type_obj->labels->name ); ?>
										</label>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="fp-seo-field">
								<label>
									<input type="checkbox" name="hreflang_enabled" value="1" <?php checked( $meta_opts['hreflang_enabled'] ); ?>>
									<?php esc_html_e( 'Abilita hreflang automatico', 'fp-seo-performance' ); ?>
								</label>
							</div>
							<div class="fp-seo-field">
								<label>
									<input type="checkbox" name="hreflang_xdefault" value="1" <?php checked( $meta_opts['include_x_default'] ); ?>>
									<?php esc_html_e( 'Aggiungi x-default nelle alternate', 'fp-seo-performance' ); ?>
								</label>
							</div>
							<div class="fp-seo-field">
								<label>
									<input type="checkbox" name="robots_enabled" value="1" <?php checked( $robots_opts['enabled'] ); ?>>
									<?php esc_html_e( 'Abilita Robots Manager', 'fp-seo-performance' ); ?>
								</label>
							</div>
							<div class="fp-seo-field">
								<label>
									<input type="checkbox" name="breadcrumb_enabled" value="1" <?php checked( $breadcrumb_opts['enabled'] ); ?>>
									<?php esc_html_e( 'Abilita breadcrumb shortcode [fp_breadcrumb]', 'fp-seo-performance' ); ?>
								</label>
							</div>
							<div class="fp-seo-field">
								<label>
									<input type="checkbox" name="breadcrumb_show_home" value="1" <?php checked( $breadcrumb_opts['show_home'] ); ?>>
									<?php esc_html_e( 'Mostra Home nel breadcrumb', 'fp-seo-performance' ); ?>
								</label>
							</div>
							<div class="fp-seo-field" style="grid-column:1 / -1;">
								<label for="robots_extra_rules"><?php esc_html_e( 'Regole robots.txt aggiuntive', 'fp-seo-performance' ); ?></label>
								<textarea id="robots_extra_rules" name="robots_extra_rules" rows="4" class="large-text code" placeholder="User-agent: *&#10;Disallow: /wp-admin/"><?php echo esc_textarea( (string) $robots_opts['extra_rules'] ); ?></textarea>
								<span class="fp-seo-hint"><?php esc_html_e( 'Sono accettate solo direttive standard (User-agent, Allow, Disallow, Crawl-delay, Host).', 'fp-seo-performance' ); ?></span>
							</div>
							<div class="fp-seo-field" style="align-self:flex-end">
								<button type="submit" class="fp-seo-btn fp-seo-btn-primary"><?php esc_html_e( 'Salva impostazioni', 'fp-seo-performance' ); ?></button>
							</div>
						</div>
					</form>
				</div>
			</div>

			<div class="fp-seo-card">
				<div class="fp-seo-card-header">
					<div class="fp-seo-card-header-left">
						<span class="dashicons dashicons-admin-links"></span>
						<h2><?php esc_html_e( 'HTML Sitemap', 'fp-seo-performance' ); ?></h2>
					</div>
					<a href="<?php echo esc_url( $sitemap_url ); ?>" class="fp-seo-btn fp-seo-btn-secondary" target="_blank" rel="noopener"><?php esc_html_e( 'View sitemap', 'fp-seo-performance' ); ?></a>
				</div>
				<div class="fp-seo-card-body">
					<p class="description"><?php esc_html_e( 'User-friendly HTML sitemap available at', 'fp-seo-performance' ); ?> <code><?php echo esc_html( $sitemap_url ); ?></code></p>
					<p class="description"><?php esc_html_e( 'XML sitemap index for Google/Bing:', 'fp-seo-performance' ); ?> <code><?php echo esc_html( $xml_sitemap_url ); ?></code></p>
					<p class="description"><?php esc_html_e( 'After adding this plugin, go to Settings → Permalinks and click Save to refresh rewrite rules.', 'fp-seo-performance' ); ?></p>
				</div>
			</div>

			<div class="fp-seo-card">
				<div class="fp-seo-card-header">
					<div class="fp-seo-card-header-left">
						<span class="dashicons dashicons-chart-line"></span>
						<h2><?php esc_html_e( 'Monitoraggio 404 e Broken Links', 'fp-seo-performance' ); ?></h2>
					</div>
				</div>
				<div class="fp-seo-card-body">
					<div class="fp-seo-fields-grid" style="grid-template-columns: 1fr 1fr;">
						<div class="fp-seo-field">
							<strong><?php esc_html_e( 'Top 404 recenti', 'fp-seo-performance' ); ?></strong>
							<?php if ( empty( $top_404 ) ) : ?>
								<p class="description"><?php esc_html_e( 'Nessun 404 registrato finora.', 'fp-seo-performance' ); ?></p>
							<?php else : ?>
								<ul style="margin: 8px 0 0; list-style: disc; padding-left: 18px;">
									<?php foreach ( $top_404 as $row ) : ?>
										<li>
											<code><?php echo esc_html( (string) $row['path'] ); ?></code>
											(<?php echo esc_html( (string) $row['hits'] ); ?> hit)
											<a href="#" onclick="document.getElementById('fp_seo_source').value='<?php echo esc_js( (string) $row['path'] ); ?>'; return false;">
												<?php esc_html_e( 'Crea redirect', 'fp-seo-performance' ); ?>
											</a>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
						<div class="fp-seo-field">
							<strong><?php esc_html_e( 'Broken internal links', 'fp-seo-performance' ); ?></strong>
							<?php if ( empty( $broken_links ) ) : ?>
								<p class="description"><?php esc_html_e( 'Nessun link interno rotto nello scan più recente.', 'fp-seo-performance' ); ?></p>
							<?php else : ?>
								<ul style="margin: 8px 0 0; list-style: disc; padding-left: 18px;">
									<?php foreach ( $broken_links as $row ) : ?>
										<li>
											<?php echo esc_html( (string) $row['source_post_title'] ); ?> →
											<code><?php echo esc_html( (string) $row['broken_url'] ); ?></code>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>

			<div class="fp-seo-card">
				<div class="fp-seo-card-header">
					<div class="fp-seo-card-header-left">
						<span class="dashicons dashicons-migrate"></span>
						<h2><?php esc_html_e( 'Import SEO da plugin terzi', 'fp-seo-performance' ); ?></h2>
					</div>
				</div>
				<div class="fp-seo-card-body">
					<p class="description"><?php esc_html_e( 'Import one-shot di title, meta description e focus keyword da Yoast, RankMath o AIOSEO.', 'fp-seo-performance' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="fp-seo-inline-form">
						<?php wp_nonce_field( self::NONCE_ACTION . '_import', 'fp_seo_import_nonce' ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>">
						<select name="import_source">
							<option value="yoast">Yoast SEO</option>
							<option value="rankmath">Rank Math</option>
							<option value="aioseo">AIOSEO</option>
						</select>
						<button type="submit" class="fp-seo-btn fp-seo-btn-secondary"><?php esc_html_e( 'Avvia import', 'fp-seo-performance' ); ?></button>
					</form>
				</div>
			</div>

			<div class="fp-seo-card">
				<div class="fp-seo-card-header">
					<div class="fp-seo-card-header-left">
						<span class="dashicons dashicons-plus-alt"></span>
						<h2><?php esc_html_e( 'Add redirect', 'fp-seo-performance' ); ?></h2>
					</div>
				</div>
				<div class="fp-seo-card-body">
					<div class="fp-seo-fields-grid" id="fp-seo-add-redirect-form">
						<div class="fp-seo-field">
							<label for="fp_seo_source"><?php esc_html_e( 'Source URL (from)', 'fp-seo-performance' ); ?></label>
							<input type="text" id="fp_seo_source" class="regular-text" placeholder="/old-page/">
							<span class="fp-seo-hint"><?php esc_html_e( 'Path or full URL, e.g. /old-page/ or /category/old-post/', 'fp-seo-performance' ); ?></span>
						</div>
						<div class="fp-seo-field">
							<label for="fp_seo_target"><?php esc_html_e( 'Target URL (to)', 'fp-seo-performance' ); ?></label>
							<input type="text" id="fp_seo_target" class="regular-text" placeholder="https://example.com/new-page/">
							<span class="fp-seo-hint"><?php esc_html_e( 'Full URL or path', 'fp-seo-performance' ); ?></span>
						</div>
						<div class="fp-seo-field">
							<label for="fp_seo_type"><?php esc_html_e( 'Type', 'fp-seo-performance' ); ?></label>
							<select id="fp_seo_type">
								<option value="301">301 (Permanent)</option>
								<option value="302">302 (Temporary)</option>
							</select>
						</div>
						<div class="fp-seo-field" style="align-self: flex-end;">
							<button type="button" class="fp-seo-btn fp-seo-btn-primary" id="fp-seo-add-redirect"><?php esc_html_e( 'Add redirect', 'fp-seo-performance' ); ?></button>
						</div>
					</div>
				</div>
			</div>

			<div class="fp-seo-card">
				<div class="fp-seo-card-header">
					<div class="fp-seo-card-header-left">
						<span class="dashicons dashicons-upload"></span>
						<h2><?php esc_html_e( 'Bulk import', 'fp-seo-performance' ); ?></h2>
					</div>
				</div>
				<div class="fp-seo-card-body">
					<p class="description"><?php esc_html_e( 'Paste CSV with columns: source_url, target_url, redirect_type (301 or 302). One redirect per line.', 'fp-seo-performance' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( self::NONCE_ACTION . '_bulk', 'fp_seo_redirects_bulk_nonce' ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::BULK_ACTION ); ?>">
						<textarea name="fp_seo_redirects_csv" rows="8" class="large-text code" placeholder="/old-1/,https://example.com/new-1/,301
/old-2/,/new-page/,302"></textarea>
						<p style="margin-top: 0.5rem;">
							<button type="submit" class="fp-seo-btn fp-seo-btn-primary"><?php esc_html_e( 'Import redirects', 'fp-seo-performance' ); ?></button>
						</p>
					</form>
				</div>
			</div>

			<div class="fp-seo-card">
				<div class="fp-seo-card-header">
					<div class="fp-seo-card-header-left">
						<span class="dashicons dashicons-list-view"></span>
						<h2><?php esc_html_e( 'Redirects', 'fp-seo-performance' ); ?></h2>
					</div>
					<form method="get" class="fp-seo-inline-form">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>">
						<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search…', 'fp-seo-performance' ); ?>" class="regular-text">
						<select name="type">
							<option value=""><?php esc_html_e( 'All types', 'fp-seo-performance' ); ?></option>
							<option value="301" <?php selected( $type, '301' ); ?>>301</option>
							<option value="302" <?php selected( $type, '302' ); ?>>302</option>
						</select>
						<button type="submit" class="fp-seo-btn fp-seo-btn-secondary"><?php esc_html_e( 'Filter', 'fp-seo-performance' ); ?></button>
					</form>
				</div>
				<div class="fp-seo-card-body">
					<table class="fp-seo-table wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width:30%"><?php esc_html_e( 'Source', 'fp-seo-performance' ); ?></th>
								<th style="width:35%"><?php esc_html_e( 'Target', 'fp-seo-performance' ); ?></th>
								<th style="width:70px"><?php esc_html_e( 'Type', 'fp-seo-performance' ); ?></th>
								<th style="width:60px"><?php esc_html_e( 'Hits', 'fp-seo-performance' ); ?></th>
								<th style="width:80px"><?php esc_html_e( 'Status', 'fp-seo-performance' ); ?></th>
								<th style="width:100px"><?php esc_html_e( 'Actions', 'fp-seo-performance' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $redirects ) ) : ?>
								<tr>
									<td colspan="6"><?php esc_html_e( 'No redirects yet. Add one above or import in bulk.', 'fp-seo-performance' ); ?></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $redirects as $r ) : ?>
									<tr data-id="<?php echo esc_attr( (string) $r['id'] ); ?>">
										<td><code><?php echo esc_html( $r['source_url'] ); ?></code></td>
										<td><code><?php echo esc_html( $r['target_url'] ); ?></code></td>
										<td><span class="fp-seo-badge fp-seo-badge-neutral"><?php echo esc_html( $r['redirect_type'] ); ?></span></td>
										<td><?php echo esc_html( (string) ( $r['hits'] ?? 0 ) ); ?></td>
										<td>
											<span class="fp-seo-status-pill <?php echo (int) ( $r['is_active'] ?? 1 ) ? 'is-active' : 'is-inactive'; ?>" data-active="<?php echo (int) ( $r['is_active'] ?? 1 ); ?>">
												<?php echo (int) ( $r['is_active'] ?? 1 ) ? esc_html__( 'Active', 'fp-seo-performance' ) : esc_html__( 'Inactive', 'fp-seo-performance' ); ?>
											</span>
										</td>
										<td>
											<button type="button" class="fp-seo-btn fp-seo-btn-secondary fp-seo-btn-sm fp-seo-toggle-redirect" data-id="<?php echo esc_attr( (string) $r['id'] ); ?>"><?php esc_html_e( 'Toggle', 'fp-seo-performance' ); ?></button>
											<button type="button" class="fp-seo-btn fp-seo-btn-danger fp-seo-btn-sm fp-seo-delete-redirect" data-id="<?php echo esc_attr( (string) $r['id'] ); ?>"><?php esc_html_e( 'Delete', 'fp-seo-performance' ); ?></button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>

					<?php if ( $total_pages > 1 ) : ?>
						<div class="fp-seo-pagination">
							<?php
							$base = add_query_arg( array( 'page' => self::PAGE_SLUG, 's' => $search, 'type' => $type, 'paged' => '%#%' ), admin_url( 'admin.php' ) );
							echo wp_kses_post( paginate_links( array(
								'base'      => $base,
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $total_pages,
								'current'   => $paged,
							) ) );
							?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<script>
		(function() {
			var nonce = '<?php echo esc_js( wp_create_nonce( self::NONCE_ACTION ) ); ?>';
			var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

			function addRedirect() {
				var source = document.getElementById('fp_seo_source').value.trim();
				var target = document.getElementById('fp_seo_target').value.trim();
				var type = document.getElementById('fp_seo_type').value;
				if (!source || !target) return;

				var form = new FormData();
				form.append('action', '<?php echo esc_js( self::AJAX_ACTION ); ?>');
				form.append('nonce', nonce);
				form.append('sub_action', 'add');
				form.append('source_url', source);
				form.append('target_url', target);
				form.append('redirect_type', type);

				fetch(ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' })
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (data.success) location.reload();
						else alert(data.data && data.data.message ? data.data.message : 'Error');
					});
			}

			document.getElementById('fp-seo-add-redirect').addEventListener('click', addRedirect);

			document.querySelectorAll('.fp-seo-delete-redirect').forEach(function(btn) {
				btn.addEventListener('click', function() {
					if (!confirm('<?php echo esc_js( __( 'Delete this redirect?', 'fp-seo-performance' ) ); ?>')) return;
					var id = btn.dataset.id;
					var form = new FormData();
					form.append('action', '<?php echo esc_js( self::AJAX_ACTION ); ?>');
					form.append('nonce', nonce);
					form.append('sub_action', 'delete');
					form.append('id', id);
					fetch(ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' })
						.then(function(r) { return r.json(); })
						.then(function(data) {
							if (data.success) btn.closest('tr').remove();
							else alert(data.data && data.data.message ? data.data.message : 'Error');
						});
				});
			});

			document.querySelectorAll('.fp-seo-toggle-redirect').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var id = btn.dataset.id;
					var form = new FormData();
					form.append('action', '<?php echo esc_js( self::AJAX_ACTION ); ?>');
					form.append('nonce', nonce);
					form.append('sub_action', 'toggle');
					form.append('id', id);
					fetch(ajaxUrl, { method: 'POST', body: form, credentials: 'same-origin' })
						.then(function(r) { return r.json(); })
						.then(function(data) {
							if (data.success) location.reload();
						});
				});
			});
		})();
		</script>
		<?php
	}
}
