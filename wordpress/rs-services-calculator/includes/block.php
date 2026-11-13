<?php
/**
 * Assets, block and shortcode registration.
 *
 * @package RS_Services
 */

defined( 'ABSPATH' ) || exit;

function rs_services_register_assets() {
	wp_register_style(
		'rs-services-calculator',
		RS_SERVICES_URL . 'assets/css/calculator.css',
		array(),
		RS_SERVICES_VERSION
	);

	wp_register_script(
		'rs-services-calculator-view',
		RS_SERVICES_URL . 'assets/js/view.js',
		array(),
		RS_SERVICES_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	wp_register_script(
		'rs-services-calculator-editor',
		RS_SERVICES_URL . 'assets/js/editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
		RS_SERVICES_VERSION,
		true
	);

	wp_set_script_translations( 'rs-services-calculator-editor', 'rs-services' );
}

function rs_services_register_block() {
	register_block_type(
		RS_SERVICES_DIR . 'block',
		array( 'render_callback' => 'rs_services_render_block' )
	);
}

/**
 * @param array $attributes Block attributes.
 * @return string
 */
function rs_services_render_block( $attributes ) {
	return sprintf(
		'<div %1$s>%2$s</div>',
		get_block_wrapper_attributes(),
		rs_services_render_calculator( $attributes )
	);
}

function rs_services_register_shortcode() {
	add_shortcode( 'rs_services_calculator', 'rs_services_shortcode' );
}

/**
 * [rs_services_calculator heading="..." currency="$" excerpt="no"]
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function rs_services_shortcode( $atts ) {
	$defaults = rs_services_default_attributes();
	$atts     = shortcode_atts(
		array(
			'heading'  => $defaults['heading'],
			'currency' => $defaults['currencySymbol'],
			'excerpt'  => 'yes',
		),
		$atts,
		'rs_services_calculator'
	);

	return rs_services_render_calculator(
		array(
			'heading'        => $atts['heading'],
			'currencySymbol' => $atts['currency'],
			'showExcerpt'    => 'no' !== $atts['excerpt'],
		)
	);
}
