<?php
/**
 * Plugin Name:       RS Services Calculator
 * Description:       Services post type with a block that lists the latest services and a live add-on price estimate.
 * Version:           1.0.0
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Ali Hassaan
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rs-services
 *
 * @package RS_Services
 */

defined( 'ABSPATH' ) || exit;

define( 'RS_SERVICES_VERSION', '1.0.0' );
define( 'RS_SERVICES_DIR', plugin_dir_path( __FILE__ ) );
define( 'RS_SERVICES_URL', plugin_dir_url( __FILE__ ) );
define( 'RS_SERVICES_CPT', 'rs_service' );

require_once RS_SERVICES_DIR . 'includes/post-type.php';
require_once RS_SERVICES_DIR . 'includes/meta.php';

add_action( 'init', 'rs_services_register_post_type' );
add_action( 'init', 'rs_services_register_meta' );

add_action( 'add_meta_boxes_' . RS_SERVICES_CPT, 'rs_services_add_meta_box' );
add_action( 'save_post_' . RS_SERVICES_CPT, 'rs_services_save_meta' );

register_activation_hook( __FILE__, 'rs_services_activate' );
register_deactivation_hook( __FILE__, 'rs_services_deactivate' );

function rs_services_activate() {
	rs_services_register_post_type();
	flush_rewrite_rules();
}

function rs_services_deactivate() {
	unregister_post_type( RS_SERVICES_CPT );
	flush_rewrite_rules();
}
