<?php
/**
 * base_price / delivery_time_days meta and the admin meta box.
 *
 * @package RS_Services
 */

defined( 'ABSPATH' ) || exit;

function rs_services_sanitize_price( $value ) {
	$value = is_numeric( $value ) ? (float) $value : 0.0;
	return round( max( 0, $value ), 2 );
}

function rs_services_sanitize_days( $value ) {
	return min( 365, max( 1, absint( $value ) ) );
}

function rs_services_register_meta() {
	$auth = static function ( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	};

	register_post_meta(
		RS_SERVICES_CPT,
		'base_price',
		array(
			'type'              => 'number',
			'description'       => __( 'Base price of the service.', 'rs-services' ),
			'single'            => true,
			'default'           => 0,
			'show_in_rest'      => true,
			'sanitize_callback' => 'rs_services_sanitize_price',
			'auth_callback'     => $auth,
		)
	);

	register_post_meta(
		RS_SERVICES_CPT,
		'delivery_time_days',
		array(
			'type'              => 'integer',
			'description'       => __( 'Standard delivery time in days.', 'rs-services' ),
			'single'            => true,
			'default'           => 1,
			'show_in_rest'      => true,
			'sanitize_callback' => 'rs_services_sanitize_days',
			'auth_callback'     => $auth,
		)
	);
}

function rs_services_add_meta_box() {
	add_meta_box(
		'rs-service-details',
		__( 'Service details', 'rs-services' ),
		'rs_services_render_meta_box',
		RS_SERVICES_CPT,
		'side',
		'high'
	);
}

/**
 * @param WP_Post $post Current post.
 */
function rs_services_render_meta_box( $post ) {
	$price = get_post_meta( $post->ID, 'base_price', true );
	$days  = get_post_meta( $post->ID, 'delivery_time_days', true );

	wp_nonce_field( 'rs_services_save_meta', 'rs_services_meta_nonce' );
	?>
	<p>
		<label for="rs-base-price"><?php esc_html_e( 'Base price', 'rs-services' ); ?></label><br />
		<input type="number" id="rs-base-price" name="rs_base_price" class="widefat"
			min="0" step="0.01" value="<?php echo esc_attr( $price ); ?>" />
	</p>
	<p>
		<label for="rs-delivery-days"><?php esc_html_e( 'Delivery time (days)', 'rs-services' ); ?></label><br />
		<input type="number" id="rs-delivery-days" name="rs_delivery_time_days" class="widefat"
			min="1" max="365" step="1" value="<?php echo esc_attr( $days ); ?>" />
	</p>
	<?php
}

/**
 * @param int $post_id Post ID.
 */
function rs_services_save_meta( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! isset( $_POST['rs_services_meta_nonce'] ) ||
		! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['rs_services_meta_nonce'] ) ), 'rs_services_save_meta' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['rs_base_price'] ) ) {
		$price = rs_services_sanitize_price( sanitize_text_field( wp_unslash( $_POST['rs_base_price'] ) ) );
		update_post_meta( $post_id, 'base_price', $price );
	}

	if ( isset( $_POST['rs_delivery_time_days'] ) ) {
		$days = rs_services_sanitize_days( sanitize_text_field( wp_unslash( $_POST['rs_delivery_time_days'] ) ) );
		update_post_meta( $post_id, 'delivery_time_days', $days );
	}
}
