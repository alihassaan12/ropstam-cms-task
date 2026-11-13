<?php
/**
 * Markup shared by the block and the shortcode. Money is kept in cents.
 *
 * @package RS_Services
 */

defined( 'ABSPATH' ) || exit;

function rs_services_default_attributes() {
	return array(
		'heading'        => __( 'Our latest services', 'rs-services' ),
		'currencySymbol' => '$',
		'showExcerpt'    => true,
		'addons'         => array(
			array(
				'label' => __( 'Priority Turnaround', 'rs-services' ),
				'price' => 50,
				'days'  => -2,
			),
			array(
				'label' => __( 'Extra Revision Round', 'rs-services' ),
				'price' => 25,
				'days'  => 1,
			),
			array(
				'label' => __( 'Source Files', 'rs-services' ),
				'price' => 30,
				'days'  => 0,
			),
		),
	);
}

/**
 * Block attributes live in post content, so treat them as user input.
 *
 * @param array $attributes Raw attributes.
 * @return array
 */
function rs_services_normalize_attributes( $attributes ) {
	$defaults = rs_services_default_attributes();
	$atts     = wp_parse_args( is_array( $attributes ) ? $attributes : array(), $defaults );

	$clean = array(
		'heading'        => sanitize_text_field( $atts['heading'] ),
		'currencySymbol' => mb_substr( sanitize_text_field( $atts['currencySymbol'] ), 0, 4 ),
		'showExcerpt'    => rest_sanitize_boolean( $atts['showExcerpt'] ),
		'addons'         => array(),
	);

	if ( '' === $clean['currencySymbol'] ) {
		$clean['currencySymbol'] = $defaults['currencySymbol'];
	}

	$addons = is_array( $atts['addons'] ) ? array_slice( $atts['addons'], 0, 6 ) : array();

	foreach ( $addons as $addon ) {
		if ( ! is_array( $addon ) || empty( $addon['label'] ) ) {
			continue;
		}

		$clean['addons'][] = array(
			'label' => sanitize_text_field( $addon['label'] ),
			'cents' => (int) round( rs_services_sanitize_price( $addon['price'] ?? 0 ) * 100 ),
			'days'  => max( -30, min( 30, (int) ( $addon['days'] ?? 0 ) ) ),
		);
	}

	return $clean;
}

function rs_services_format_money( $cents, $symbol ) {
	return $symbol . number_format_i18n( $cents / 100, 2 );
}

function rs_services_format_days( $days ) {
	/* translators: %d: number of days. */
	return sprintf( _n( '%d day', '%d days', $days, 'rs-services' ), $days );
}

function rs_services_format_day_delta( $days ) {
	if ( 0 === $days ) {
		return '';
	}

	return ( $days > 0 ? '+' : '−' ) . rs_services_format_days( abs( $days ) );
}

/**
 * @param array $attributes Block or shortcode attributes.
 * @return string
 */
function rs_services_render_calculator( $attributes = array() ) {
	$atts     = rs_services_normalize_attributes( $attributes );
	$services = rs_services_get_latest( 3 );

	wp_enqueue_style( 'rs-services-calculator' );

	$heading_id = wp_unique_id( 'rs-services-heading-' );

	ob_start();
	?>
	<section class="rs-services"
		<?php if ( $atts['heading'] ) : ?>
			aria-labelledby="<?php echo esc_attr( $heading_id ); ?>"
		<?php endif; ?>
		data-rs-services
		data-currency="<?php echo esc_attr( $atts['currencySymbol'] ); ?>"
		data-locale="<?php echo esc_attr( str_replace( '_', '-', determine_locale() ) ); ?>"
		data-day="<?php /* translators: %d: number of days. */ echo esc_attr( _n( '%d day', '%d days', 1, 'rs-services' ) ); ?>"
		data-days="<?php /* translators: %d: number of days. */ echo esc_attr( _n( '%d day', '%d days', 2, 'rs-services' ) ); ?>">

		<?php if ( $atts['heading'] ) : ?>
			<h2 id="<?php echo esc_attr( $heading_id ); ?>" class="rs-services__heading"><?php echo esc_html( $atts['heading'] ); ?></h2>
		<?php endif; ?>

		<?php if ( empty( $services ) ) : ?>
			<p class="rs-services__empty"><?php esc_html_e( 'No services are available yet. Please check back soon.', 'rs-services' ); ?></p>
		<?php else : ?>
			<ul class="rs-services__list" role="list">
				<?php
				foreach ( $services as $service ) {
					rs_services_render_card( $service, $atts );
				}
				?>
			</ul>
		<?php endif; ?>
	</section>
	<?php
	if ( ! empty( $services ) ) {
		wp_enqueue_script( 'rs-services-calculator-view' );
	}

	return (string) ob_get_clean();
}

/**
 * @param WP_Post $service Service post.
 * @param array   $atts    Normalized attributes.
 */
function rs_services_render_card( $service, $atts ) {
	$base_cents = (int) round( rs_services_sanitize_price( get_post_meta( $service->ID, 'base_price', true ) ) * 100 );
	$days       = rs_services_sanitize_days( get_post_meta( $service->ID, 'delivery_time_days', true ) );
	$has_price  = $base_cents > 0;
	$title_id   = wp_unique_id( 'rs-service-' . $service->ID . '-' );
	$addons_id  = $title_id . '-addons';
	?>
	<li class="rs-service"
		data-rs-service
		data-base="<?php echo esc_attr( $base_cents ); ?>"
		data-delivery="<?php echo esc_attr( $days ); ?>">
		<article class="rs-service__card" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
			<h3 id="<?php echo esc_attr( $title_id ); ?>" class="rs-service__title">
				<a href="<?php echo esc_url( get_permalink( $service ) ); ?>"><?php echo esc_html( get_the_title( $service ) ); ?></a>
			</h3>

			<?php if ( $atts['showExcerpt'] && has_excerpt( $service ) ) : ?>
				<p class="rs-service__excerpt"><?php echo esc_html( get_the_excerpt( $service ) ); ?></p>
			<?php endif; ?>

			<dl class="rs-service__meta">
				<div>
					<dt><?php esc_html_e( 'Base price', 'rs-services' ); ?></dt>
					<dd>
						<?php
						echo $has_price
							? esc_html( rs_services_format_money( $base_cents, $atts['currencySymbol'] ) )
							: esc_html__( 'On request', 'rs-services' );
						?>
					</dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Delivery', 'rs-services' ); ?></dt>
					<dd><?php echo esc_html( rs_services_format_days( $days ) ); ?></dd>
				</div>
			</dl>

			<?php if ( $has_price && $atts['addons'] ) : ?>
				<?php // Toggles do nothing without JS, so view.js un-hides them. ?>
				<div class="rs-service__addons" role="group" aria-labelledby="<?php echo esc_attr( $addons_id ); ?>" data-rs-addons hidden>
					<p id="<?php echo esc_attr( $addons_id ); ?>" class="rs-service__addons-label"><?php esc_html_e( 'Optional add-ons', 'rs-services' ); ?></p>
					<?php foreach ( $atts['addons'] as $addon ) : ?>
						<button type="button" class="rs-addon" aria-pressed="false"
							data-price="<?php echo esc_attr( $addon['cents'] ); ?>"
							data-days="<?php echo esc_attr( $addon['days'] ); ?>">
							<span class="rs-addon__switch" aria-hidden="true"></span>
							<span class="rs-addon__label"><?php echo esc_html( $addon['label'] ); ?></span>
							<span class="rs-addon__price">
								+<?php echo esc_html( rs_services_format_money( $addon['cents'], $atts['currencySymbol'] ) ); ?>
								<?php if ( $addon['days'] ) : ?>
									<span class="rs-addon__days">(<?php echo esc_html( rs_services_format_day_delta( $addon['days'] ) ); ?>)</span>
								<?php endif; ?>
							</span>
						</button>
					<?php endforeach; ?>
				</div>

				<div class="rs-service__estimate" aria-live="polite" aria-atomic="true">
					<p>
						<span><?php esc_html_e( 'Estimated total', 'rs-services' ); ?></span>
						<strong data-rs-total><?php echo esc_html( rs_services_format_money( $base_cents, $atts['currencySymbol'] ) ); ?></strong>
					</p>
					<p>
						<span><?php esc_html_e( 'Estimated delivery', 'rs-services' ); ?></span>
						<strong data-rs-days><?php echo esc_html( rs_services_format_days( $days ) ); ?></strong>
					</p>
				</div>
			<?php endif; ?>
		</article>
	</li>
	<?php
}
