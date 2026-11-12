<?php
/**
 * Block Template: Testimonials Carousel
 *
 * Fully dynamic: powered by ACF Block Fields with optional automatic
 * Google Reviews fetching via Google Places API when manual reviews are empty
 * (or combined / forced based on Google Source Mode).
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
$block_id = 'testimonials-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
	$block_id = (string) $block['anchor'];
}

$class_names = array( 'testimonials-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . $block['align'];
}

// -----------------------------------------------------------------------------
// 2. Retrieve ACF Fields (Manual + Google Reviews Settings)
// -----------------------------------------------------------------------------
$title     = function_exists( 'get_field' ) ? (string) get_field( 'testimonials_title' ) : '';
$items_raw = function_exists( 'get_field' ) ? get_field( 'testimonials_items' ) : null;

$google_mode_raw = function_exists( 'get_field' ) ? get_field( 'google_source_mode' ) : '';
$google_mode     = ( is_string( $google_mode_raw ) && '' !== trim( $google_mode_raw ) ) ? trim( $google_mode_raw ) : 'fallback';
$google_place_id = function_exists( 'get_field' ) ? trim( (string) get_field( 'google_place_id' ) ) : '';
$google_api_key  = function_exists( 'get_field' ) ? trim( (string) get_field( 'google_api_key' ) ) : '';
$min_rating_raw  = function_exists( 'get_field' ) ? get_field( 'google_min_rating' ) : '';
$max_reviews_raw = function_exists( 'get_field' ) ? get_field( 'google_max_reviews' ) : '';
$google_sort     = function_exists( 'get_field' ) ? trim( (string) get_field( 'google_sort_by' ) ) : '';
$google_cache    = function_exists( 'get_field' ) ? get_field( 'google_cache_duration' ) : '';

// -----------------------------------------------------------------------------
// 3. Resolve Manual Testimonials Repeater
// -----------------------------------------------------------------------------
$manual_rows = array();
if ( function_exists( 'have_rows' ) && have_rows( 'testimonials_items' ) ) {
	while ( have_rows( 'testimonials_items' ) ) {
		the_row();
		$manual_rows[] = array(
			'author_avatar'   => get_sub_field( 'author_avatar' ),
			'author_name'     => get_sub_field( 'author_name' ),
			'author_location' => get_sub_field( 'author_location' ),
			'rating'          => get_sub_field( 'rating' ),
			'quote_title'     => get_sub_field( 'quote_title' ),
			'quote_content'   => get_sub_field( 'quote_content' ),
		);
	}
} elseif ( is_array( $items_raw ) && ! empty( $items_raw ) ) {
	foreach ( $items_raw as $row ) {
		if ( is_array( $row ) ) {
			$manual_rows[] = array(
				'author_avatar'   => $row['author_avatar'] ?? null,
				'author_name'     => $row['author_name'] ?? '',
				'author_location' => $row['author_location'] ?? '',
				'rating'          => $row['rating'] ?? '',
				'quote_title'     => $row['quote_title'] ?? '',
				'quote_content'   => $row['quote_content'] ?? '',
			);
		}
	}
}

$manual_items = array();
foreach ( $manual_rows as $row ) {
	$author_name     = trim( (string) ( $row['author_name'] ?? '' ) );
	$author_location = trim( (string) ( $row['author_location'] ?? '' ) );
	$quote_title     = trim( (string) ( $row['quote_title'] ?? '' ) );
	$quote_content   = trim( (string) ( $row['quote_content'] ?? '' ) );

	// Resolve Avatar Image (array, ID, or URL string).
	$avatar_field = $row['author_avatar'] ?? null;
	$avatar_url   = '';
	$avatar_alt   = $author_name;
	if ( is_array( $avatar_field ) && ! empty( $avatar_field['url'] ) ) {
		$avatar_url = (string) $avatar_field['url'];
		if ( ! empty( $avatar_field['alt'] ) ) {
			$avatar_alt = (string) $avatar_field['alt'];
		}
	} elseif ( is_numeric( $avatar_field ) && (int) $avatar_field > 0 ) {
		$img = wp_get_attachment_image_url( (int) $avatar_field, 'thumbnail' );
		if ( ! $img ) {
			$img = wp_get_attachment_image_url( (int) $avatar_field, 'full' );
		}
		if ( $img ) {
			$avatar_url = (string) $img;
			$alt_meta   = get_post_meta( (int) $avatar_field, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt_meta ) ) {
				$avatar_alt = (string) $alt_meta;
			}
		}
	} elseif ( is_string( $avatar_field ) && '' !== trim( $avatar_field ) ) {
		$avatar_url = trim( $avatar_field );
	}

	// Resolve Star Rating (0.5 - 5.0).
	$rating_raw = $row['rating'] ?? '';
	if ( is_array( $rating_raw ) ) {
		$rating_raw = $rating_raw['value'] ?? '';
	}
	$rating_str = trim( (string) $rating_raw );
	$rating_val = ( '' !== $rating_str && is_numeric( $rating_str ) ) ? (float) $rating_str : 0.0;
	if ( $rating_val > 0 ) {
		$rating_val = round( min( 5.0, max( 0.0, $rating_val ) ) * 2 ) / 2;
	}

	if ( '' === $avatar_url && '' === $author_name && '' === $author_location && $rating_val <= 0 && '' === $quote_title && '' === $quote_content ) {
		continue;
	}

	$manual_items[] = array(
		'avatar_url'      => $avatar_url,
		'avatar_alt'      => $avatar_alt,
		'author_name'     => $author_name,
		'author_location' => $author_location,
		'rating'          => $rating_val,
		'quote_title'     => $quote_title,
		'quote_content'   => $quote_content,
		'is_external'     => false,
	);
}

// -----------------------------------------------------------------------------
// 4. Pull Google Reviews (when needed based on mode & manual entries)
// -----------------------------------------------------------------------------
$should_pull_google = false;
if ( 'disabled' !== $google_mode ) {
	if ( 'google_only' === $google_mode || 'merge' === $google_mode ) {
		$should_pull_google = true;
	} elseif ( 'fallback' === $google_mode && empty( $manual_items ) ) {
		$should_pull_google = true;
	}
}

$google_items       = array();
$google_notice_msg  = '';

if ( $should_pull_google ) {
	if ( '' !== $google_place_id && '' !== $google_api_key ) {
		require_once __DIR__ . '/google-reviews.php';

		if ( is_array( $min_rating_raw ) ) {
			$min_rating_raw = $min_rating_raw['value'] ?? '';
		}

		$google_response = xmastree_testimonials_fetch_google_reviews(
			$google_place_id,
			$google_api_key,
			array(
				'min_rating'     => is_numeric( $min_rating_raw ) ? (float) $min_rating_raw : 0.0,
				'max_reviews'    => is_numeric( $max_reviews_raw ) ? (int) $max_reviews_raw : 0,
				'sort_by'        => ( 'newest' === $google_sort ) ? 'newest' : 'most_relevant',
				'cache_duration' => is_numeric( $google_cache ) && (int) $google_cache > 0 ? (int) $google_cache : 43200,
			)
		);

		$google_items      = $google_response['reviews'] ?? array();
		$google_notice_msg = (string) ( $google_response['error'] ?? '' );
	} elseif ( '' !== $google_place_id || '' !== $google_api_key ) {
		$google_notice_msg = __( 'Both Google Place ID and Google Places API Key are required to pull Google Reviews.', 'xmastree-blocks' );
	}
}

// Combine or select final review items.
if ( 'google_only' === $google_mode ) {
	$rendered_items = $google_items;
} elseif ( 'merge' === $google_mode ) {
	$rendered_items = array_merge( $manual_items, $google_items );
} else {
	$rendered_items = ! empty( $manual_items ) ? $manual_items : $google_items;
}

$has_title        = '' !== trim( $title );
$has_testimonials = ! empty( $rendered_items );
$has_content      = $has_title || $has_testimonials;

if ( ! $has_content && ! $is_preview ) {
	return;
}

// AOS attributes (only active on frontend).
$aos_header   = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800"';
$aos_carousel = $is_preview ? '' : ' data-aos="fade-up" data-aos-duration="800" data-aos-delay="150"';

/**
 * Helper closure to render a single testimonial card consistently across preview and frontend.
 *
 * @param array<string, mixed> $item        Normalized testimonial data.
 * @param string               $card_class  Additional classes for the .testimonial-card wrapper.
 */
$render_testimonial_card = static function ( array $item, string $card_class = '' ): void {
	$full_card_class = trim( 'testimonial-card ' . $card_class );
	$has_author_meta = '' !== $item['author_name'] || '' !== $item['author_location'] || $item['rating'] > 0;
	$is_external     = ! empty( $item['is_external'] );
	?>
	<div class="<?php echo esc_attr( $full_card_class ); ?>">
		<?php if ( '' !== $item['avatar_url'] || $has_author_meta ) : ?>
			<div class="testimonial-author-row">
				<?php if ( '' !== $item['avatar_url'] ) : ?>
					<img src="<?php echo esc_url( $item['avatar_url'] ); ?>" alt="<?php echo esc_attr( $item['avatar_alt'] ); ?>" class="author-avatar"<?php echo $is_external ? ' referrerpolicy="no-referrer" loading="lazy"' : ''; ?>>
				<?php endif; ?>

				<?php if ( $has_author_meta ) : ?>
					<div class="author-meta">
						<?php if ( '' !== $item['author_name'] ) : ?>
							<h5><?php echo esc_html( $item['author_name'] ); ?></h5>
						<?php endif; ?>

						<?php if ( '' !== $item['author_location'] ) : ?>
							<p><?php echo esc_html( $item['author_location'] ); ?></p>
						<?php endif; ?>

						<?php if ( $item['rating'] > 0 ) : ?>
							<div class="star-rating" role="img" aria-label="<?php echo esc_attr( sprintf( __( '%s out of 5 stars', 'xmastree-blocks' ), (string) $item['rating'] ) ); ?>">
								<?php
								for ( $s = 1; $s <= 5; $s++ ) :
									if ( $item['rating'] >= $s ) :
										echo '<i class="bi bi-star-fill" aria-hidden="true"></i>';
									elseif ( $item['rating'] >= ( $s - 0.5 ) ) :
										echo '<i class="bi bi-star-half" aria-hidden="true"></i>';
									else :
										echo '<i class="bi bi-star" aria-hidden="true"></i>';
									endif;
								endfor;
								?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $item['quote_title'] ) : ?>
			<h4 class="testimonial-quote-title"><?php echo esc_html( $item['quote_title'] ); ?></h4>
		<?php endif; ?>

		<?php if ( '' !== $item['quote_content'] ) : ?>
			<?php
			$safe_quote = $is_external ? esc_html( (string) $item['quote_content'] ) : (string) $item['quote_content'];
			echo wp_kses_post( wpautop( $safe_quote ) );
			?>
		<?php endif; ?>
	</div>
	<?php
};
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>">
	<div class="container">
		<?php if ( $has_content ) : ?>

			<?php if ( $has_title ) : ?>
				<div class="section-header text-center mb-5"<?php echo $aos_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
					<h2 class="section-title"><?php echo wp_kses_post( $title ); ?></h2>
				</div>
			<?php endif; ?>

			<?php if ( $is_preview && '' !== $google_notice_msg && current_user_can( 'edit_posts' ) ) : ?>
				<div class="alert alert-warning text-center mb-4" role="alert">
					<?php echo esc_html( $google_notice_msg ); ?>
				</div>
			<?php endif; ?>

			<?php if ( $has_testimonials ) : ?>
				<?php if ( $is_preview ) : ?>
					<!-- Editor Preview Grid -->
					<div class="row g-4">
						<?php foreach ( $rendered_items as $item ) : ?>
							<div class="col-lg-4 col-md-6">
								<?php $render_testimonial_card( $item, 'h-100' ); ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<!-- Owl Carousel Slider -->
					<div class="owl-carousel owl-theme testimonial-carousel"<?php echo $aos_carousel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
						<?php foreach ( $rendered_items as $item ) : ?>
							<div class="item">
								<?php $render_testimonial_card( $item ); ?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( count( $rendered_items ) > 1 ) : ?>
					<!-- Custom Carousel Controls -->
					<div class="custom-carousel-nav">
						<button type="button" class="carousel-arrow testimonial-prev" aria-label="<?php esc_attr_e( 'Previous review', 'xmastree-blocks' ); ?>">
							<i class="bi bi-chevron-left" aria-hidden="true"></i>
						</button>
						<button type="button" class="carousel-arrow testimonial-next" aria-label="<?php esc_attr_e( 'Next review', 'xmastree-blocks' ); ?>">
							<i class="bi bi-chevron-right" aria-hidden="true"></i>
						</button>
					</div>
				<?php endif; ?>
			<?php elseif ( $is_preview && '' === $google_notice_msg ) : ?>
				<div class="text-center p-4 border border-dashed text-muted">
					<p class="mb-0"><?php esc_html_e( 'No reviews found yet — add manual testimonials or configure Google Place ID & API Key in the Google Reviews tab.', 'xmastree-blocks' ); ?></p>
				</div>
			<?php endif; ?>

		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-4 border border-dashed text-muted">
				<p class="mb-0"><?php esc_html_e( 'Testimonials Carousel Block — Add manual testimonials or configure Google Reviews in the sidebar.', 'xmastree-blocks' ); ?></p>
				<?php if ( '' !== $google_notice_msg && current_user_can( 'edit_posts' ) ) : ?>
					<p class="text-danger mt-2 mb-0"><?php echo esc_html( $google_notice_msg ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
