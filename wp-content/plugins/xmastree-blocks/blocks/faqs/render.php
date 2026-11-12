<?php
/**
 * Block Template: Visitor FAQs Accordion
 *
 * Fully dynamic: powered by ACF Block Fields.
 * All static code and hardcoded fallbacks have been removed.
 *
 * @package XmasTree_Blocks
 * @param array      $block      The block settings and attributes.
 * @param string     $content    The block inner HTML (empty).
 * @param bool       $is_preview True during AJAX preview in the editor.
 * @param int|string $post_id    The post ID this block is saved to.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// -----------------------------------------------------------------------------
// 1. Block Attributes & Wrappers
// -----------------------------------------------------------------------------
$block_id = 'faqs-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
	$block_id = (string) $block['anchor'];
}

$class_names = array( 'faqs-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . $block['align'];
}

// -----------------------------------------------------------------------------
// 2. Retrieve ACF Fields
// -----------------------------------------------------------------------------
$title      = function_exists( 'get_field' ) ? (string) get_field( 'faqs_title' ) : '';
$open_first = function_exists( 'get_field' ) ? get_field( 'faqs_open_first' ) : null;
$items_raw  = function_exists( 'get_field' ) ? get_field( 'faqs_items' ) : null;

// Default first-item-open setting to true if unset (matches default_value: 1 in ACF).
if ( null === $open_first ) {
	$open_first = true;
} else {
	$open_first = (bool) $open_first;
}

// Resolve repeater items via have_rows() or raw array fallback.
$faq_rows = array();
if ( function_exists( 'have_rows' ) && have_rows( 'faqs_items' ) ) {
	while ( have_rows( 'faqs_items' ) ) {
		the_row();
		$faq_rows[] = array(
			'question' => get_sub_field( 'question' ),
			'answer'   => get_sub_field( 'answer' ),
		);
	}
} elseif ( is_array( $items_raw ) && ! empty( $items_raw ) ) {
	foreach ( $items_raw as $row ) {
		if ( is_array( $row ) ) {
			$faq_rows[] = array(
				'question' => $row['question'] ?? '',
				'answer'   => $row['answer'] ?? '',
			);
		}
	}
}

// Normalize and filter out completely empty FAQ rows.
$rendered_faqs = array();
foreach ( $faq_rows as $row ) {
	$q = trim( (string) ( $row['question'] ?? '' ) );
	$a = trim( (string) ( $row['answer'] ?? '' ) );

	if ( '' === $q && '' === $a ) {
		continue;
	}

	$rendered_faqs[] = array(
		'question' => $q,
		'answer'   => $a,
	);
}

$has_title   = '' !== trim( $title );
$has_faqs    = ! empty( $rendered_faqs );
$has_content = $has_title || $has_faqs;

if ( ! $has_content && ! $is_preview ) {
	return;
}

// Unique accordion namespace so multiple FAQ blocks on one page never collide.
$accordion_uid = sanitize_html_class( $block_id );
$accordion_id  = $accordion_uid . '-accordion';

// AOS animation helper (active only on frontend).
$aos_header = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>">
	<div class="faqs-container">
		<?php if ( $has_content ) : ?>

			<?php if ( $has_title ) : ?>
				<div class="section-header text-center mb-5"<?php echo $aos_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<h2 class="section-title"><?php echo wp_kses_post( $title ); ?></h2>
				</div>
			<?php endif; ?>

			<?php if ( $has_faqs ) : ?>
				<div class="row justify-content-center">
					<div class="col-12">
						<div class="accordion custom-faq-accordion" id="<?php echo esc_attr( $accordion_id ); ?>">
							<?php
							foreach ( $rendered_faqs as $index => $faq ) :
								$item_num    = $index + 1;
								$heading_id  = $accordion_uid . '-heading-' . $item_num;
								$collapse_id = $accordion_uid . '-collapse-' . $item_num;
								$is_open     = ( 0 === $index && $open_first );
								$delay       = 100 + ( $index * 50 );
								$aos_item    = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="600" data-aos-delay="' . esc_attr( (string) $delay ) . '"';
								?>
								<div class="accordion-item"<?php echo $aos_item; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
									<h2 class="accordion-header" id="<?php echo esc_attr( $heading_id ); ?>">
										<button class="accordion-button<?php echo $is_open ? '' : ' collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo esc_attr( $collapse_id ); ?>" aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $collapse_id ); ?>"><?php echo esc_html( $faq['question'] ); ?></button>
									</h2>
									<div id="<?php echo esc_attr( $collapse_id ); ?>" class="accordion-collapse collapse<?php echo $is_open ? ' show' : ''; ?>" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>" data-bs-parent="#<?php echo esc_attr( $accordion_id ); ?>">
										<div class="accordion-body">
											<?php if ( '' !== $faq['answer'] ) : ?>
												<?php echo wp_kses_post( wpautop( $faq['answer'] ) ); ?>
											<?php endif; ?>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php endif; ?>

		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-4 border border-dashed text-muted">
				<p class="mb-0"><?php esc_html_e( 'Visitor FAQs Block — Click here to configure section heading and FAQ items in the sidebar.', 'xmastree-blocks' ); ?></p>
			</div>
		<?php endif; ?>
	</div>
</section>

