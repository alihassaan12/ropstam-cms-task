<?php
/**
 * @package RS_Services
 */

defined( 'ABSPATH' ) || exit;

function rs_services_register_post_type() {
	$labels = array(
		'name'               => _x( 'Services', 'post type general name', 'rs-services' ),
		'singular_name'      => _x( 'Service', 'post type singular name', 'rs-services' ),
		'menu_name'          => _x( 'Services', 'admin menu', 'rs-services' ),
		'add_new_item'       => __( 'Add New Service', 'rs-services' ),
		'edit_item'          => __( 'Edit Service', 'rs-services' ),
		'new_item'           => __( 'New Service', 'rs-services' ),
		'view_item'          => __( 'View Service', 'rs-services' ),
		'search_items'       => __( 'Search Services', 'rs-services' ),
		'not_found'          => __( 'No services found.', 'rs-services' ),
		'not_found_in_trash' => __( 'No services found in Trash.', 'rs-services' ),
		'all_items'          => __( 'All Services', 'rs-services' ),
	);

	register_post_type(
		RS_SERVICES_CPT,
		array(
			'labels'       => $labels,
			'public'       => true,
			'has_archive'  => true,
			'rewrite'      => array( 'slug' => 'services' ),
			'menu_icon'    => 'dashicons-hammer',
			'show_in_rest' => true,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'revisions' ),
		)
	);
}
