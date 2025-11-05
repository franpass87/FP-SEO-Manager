<?php
/**
 * Uninstall script for FP SEO Performance
 *
 * Removes all plugin data when plugin is deleted (not deactivated).
 *
 * @package FP\SEO
 * @author Francesco Passeri
 * @link https://francescopasseri.com
 */

// Exit if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up all plugin data
 */
function fp_seo_performance_uninstall() {
	global $wpdb;

	// Remove all plugin options
	delete_option( 'fp_seo_perf_options' );
	delete_option( 'fp_seo_performance' );

	// Remove all transients
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			'_transient_fp_seo_%',
			'_transient_timeout_fp_seo_%'
		)
	);

	// Remove all post meta keys
	$meta_keys = array(
		// Core SEO meta
		'_fp_seo_performance_exclude',
		'_fp_seo_focus_keyword',
		'_fp_seo_secondary_keywords',
		'_fp_seo_title',
		'_fp_seo_meta_description',
		'_fp_seo_canonical',

		// GEO meta
		'_fp_seo_geo_claims',
		'_fp_seo_geo_expose',
		'_fp_seo_geo_no_ai_reuse',

		// AI-First meta
		'_fp_seo_qa_pairs',
		'_fp_seo_conversational_variants',
		'_fp_seo_embeddings',
		'_fp_seo_image_optimization',
		'_fp_seo_entities',
		'_fp_seo_relationships',
		'_fp_seo_update_frequency',
		'_fp_seo_next_review_date',
		'_fp_seo_content_version',
		'_fp_seo_changelog',
		'_fp_seo_data_sources',
		'_fp_seo_content_type',
		'_fp_seo_fact_checked',
		'_fp_seo_peer_reviewed',
		'_fp_seo_sources',
		'_fp_seo_key_facts',
		'_fp_seo_content_hash',
		'_fp_seo_external_citations',
		'_fp_seo_views',
		'_fp_seo_shares',
		'_fp_seo_likes',

		// Social meta
		'_fp_seo_social_meta',

		// Schema meta
		'_fp_seo_schema',

		// Keywords meta
		'_fp_seo_keywords_data',

		// Links meta
		'_fp_seo_internal_links',
	);

	foreach ( $meta_keys as $meta_key ) {
		delete_post_meta_by_key( $meta_key );
	}

	// Remove all user meta keys
	$user_meta_keys = array(
		'fp_author_title',
		'fp_author_experience_years',
		'fp_author_education',
		'fp_author_certifications',
		'fp_author_expertise',
		'fp_author_followers',
		'fp_author_endorsements',
		'fp_author_speaking_engagements',
		'fp_author_verified',
		'fp_author_verified_at',
		'fp_author_citations',
	);

	foreach ( $user_meta_keys as $meta_key ) {
		delete_metadata( 'user', 0, $meta_key, '', true ); // Delete for all users
	}

	// Drop score history table
	$table_name = $wpdb->prefix . 'fp_seo_score_history';
	$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// Clear any remaining cache
	wp_cache_flush();

	/**
	 * Fires after plugin uninstall cleanup
	 *
	 * @since 0.9.0-pre.7
	 */
	do_action( 'fp_seo_after_uninstall' );
}

// Run uninstall
fp_seo_performance_uninstall();


