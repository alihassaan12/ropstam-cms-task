<?php
/**
 * @package RS_Services
 */

defined( 'ABSPATH' ) || exit;

/**
 * Latest published services, newest modification first.
 *
 * @param int $limit How many to return.
 * @return WP_Post[]
 */
function rs_services_get_latest( $limit = 3 ) {
	$query = new WP_Query(
		array(
			'post_type'              => RS_SERVICES_CPT,
			'post_status'            => 'publish',
			'posts_per_page'         => absint( $limit ),
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	return $query->posts;
}
