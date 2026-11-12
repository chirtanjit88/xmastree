<?php
/**
 * The template for displaying a single Christmas Event (xmastree_event CPT).
 *
 * Designed in the Christmas Tree Farm theme palette (#102F1F / #071E14 pine green,
 * #AB0030 festive crimson, #C59B27 subtle gold, #FAF7F2 warm holiday cream) with a
 * deep forest hero header, calendar date stamp badge, event overview sidebar card,
 * structured highlight lists, Prev/Next navigation, and More Farm Events grid.
 *
 * @package XmasTree_Theme
 * @since 1.0.17
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();

$event_taxonomy = 'event_category';

$resolve_primary_event_cat = static function ( int $target_post_id ) use ( $event_taxonomy ): ?\WP_Term {
	$terms = get_the_terms( $target_post_id, $event_taxonomy );
	if ( empty( $terms ) || is_wp_error( $terms ) || ! is_array( $terms ) ) {
		return null;
	}
	return $terms[0] instanceof \WP_Term ? $terms[0] : null;
};

$format_event_date_range = static function ( int $target_post_id ): array {
	$start_raw = trim( (string) get_post_meta( $target_post_id, 'event_start_date', true ) );
	$end_raw   = trim( (string) get_post_meta( $target_post_id, 'event_end_date', true ) );

	if ( '' === $start_raw ) {
		$ts = (int) get_post_time( 'U', false, $target_post_id );
		return array(
			'iso'   => (string) get_the_date( 'Y-m-d', $target_post_id ),
			'label' => (string) get_the_date( 'j M Y', $target_post_id ),
			'month' => $ts ? strtoupper( date_i18n( 'M', $ts ) ) : '',
			'day'   => $ts ? date_i18n( 'j', $ts ) : '',
		);
	}

	$start_ts = strtotime( $start_raw );
	if ( ! $start_ts ) {
		return array(
			'iso'   => $start_raw,
			'label' => $start_raw,
			'month' => '',
			'day'   => $start_raw,
		);
	}

	$label = date_i18n( 'j M Y', $start_ts );
	$month = strtoupper( date_i18n( 'M', $start_ts ) );
	$day   = date_i18n( 'j', $start_ts );

	if ( '' !== $end_raw && $end_raw !== $start_raw ) {
		$end_ts = strtotime( $end_raw );
		if ( $end_ts && $end_ts > $start_ts ) {
			if ( gmdate( 'Y-m', $start_ts ) === gmdate( 'Y-m', $end_ts ) ) {
				$label = date_i18n( 'j', $start_ts ) . ' – ' . date_i18n( 'j M Y', $end_ts );
				$day   = date_i18n( 'j', $start_ts ) . '–' . date_i18n( 'j', $end_ts );
			} elseif ( gmdate( 'Y', $start_ts ) === gmdate( 'Y', $end_ts ) ) {
				$label = date_i18n( 'j M', $start_ts ) . ' – ' . date_i18n( 'j M Y', $end_ts );
				$month = strtoupper( date_i18n( 'M', $start_ts ) . '–' . date_i18n( 'M', $end_ts ) );
				$day   = date_i18n( 'j', $start_ts ) . '–' . date_i18n( 'j', $end_ts );
			} else {
				$label = date_i18n( 'j M Y', $start_ts ) . ' – ' . date_i18n( 'j M Y', $end_ts );
			}
		}
	}

	return array(
		'iso'   => $start_raw,
		'label' => $label,
		'month' => $month,
		'day'   => $day,
	);
};

$get_event_thumb = static function ( int $target_post_id, string $size = 'large', string $fallback_alt = '' ): array {
	$thumb_id = get_post_thumbnail_id( $target_post_id );
	if ( ! $thumb_id ) {
		return array(
			'url' => '',
			'alt' => $fallback_alt,
		);
	}
	$url = wp_get_attachment_image_url( (int) $thumb_id, $size );
	if ( ! $url ) {
		$url = wp_get_attachment_image_url( (int) $thumb_id, 'full' );
	}
	$alt_meta = get_post_meta( (int) $thumb_id, '_wp_attachment_image_alt', true );
	$alt      = ! empty( $alt_meta ) ? (string) $alt_meta : $fallback_alt;

	return array(
		'url' => $url ? (string) $url : '',
		'alt' => $alt,
	);
};

$get_event_excerpt = static function ( \WP_Post $target_post, int $word_limit = 20 ): string {
	$summary = trim( (string) get_post_meta( (int) $target_post->ID, 'event_short_description', true ) );
	if ( '' !== $summary ) {
		return wp_trim_words( $summary, $word_limit, '...' );
	}
	$raw   = has_excerpt( $target_post ) ? (string) $target_post->post_excerpt : (string) $target_post->post_content;
	$clean = wp_strip_all_tags( strip_shortcodes( $raw ) );
	$clean = html_entity_decode( $clean, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$clean = trim( (string) preg_replace( '/\s+/u', ' ', str_replace( "\xc2\xa0", ' ', $clean ) ) );
	if ( '' === $clean ) {
		return '';
	}
	return wp_trim_words( $clean, $word_limit, '...' );
};

$events_page_id     = function_exists( 'xmastree_get_events_page_id' ) ? xmastree_get_events_page_id() : 0;
$events_url         = function_exists( 'xmastree_get_events_page_url' ) ? xmastree_get_events_page_url() : home_url( '/' );
$events_label       = $events_page_id > 0 ? get_the_title( $events_page_id ) : __( 'Christmas Events', 'xmastree-theme' );
$parsed_events_args = array();
$parsed_events_url  = wp_parse_url( $events_url );
if ( ! empty( $parsed_events_url['query'] ) ) {
	parse_str( (string) $parsed_events_url['query'], $parsed_events_args );
}
?>

<main id="primary" class="site-main">
	<?php
	while ( have_posts() ) :
		the_post();

		$current_id        = (int) get_the_ID();
		$current_title     = get_the_title();
		$current_permalink = (string) get_permalink( $current_id );
		$primary_cat       = $resolve_primary_event_cat( $current_id );
		$event_terms       = get_the_terms( $current_id, $event_taxonomy );
		$event_term_ids    = array();
		if ( is_array( $event_terms ) && ! is_wp_error( $event_terms ) ) {
			foreach ( $event_terms as $term_item ) {
				if ( $term_item instanceof \WP_Term ) {
					$event_term_ids[] = (int) $term_item->term_id;
				}
			}
		}

		$date_data   = $format_event_date_range( $current_id );
		$time_label  = trim( (string) get_post_meta( $current_id, 'event_time_label', true ) );
		$location    = trim( (string) get_post_meta( $current_id, 'event_location', true ) );
		$short_desc  = trim( (string) get_post_meta( $current_id, 'event_short_description', true ) );
		$custom_cta  = function_exists( 'get_field' ) ? get_field( 'event_cta_link', $current_id ) : null;
		$cta_url     = '';
		$cta_title   = '';
		$cta_target  = '';
		if ( is_array( $custom_cta ) && ! empty( $custom_cta['url'] ) ) {
			$cta_url    = trim( (string) $custom_cta['url'] );
			$cta_title  = ! empty( $custom_cta['title'] ) ? trim( (string) $custom_cta['title'] ) : __( 'Book / Learn More', 'xmastree-theme' );
			$cta_target = ! empty( $custom_cta['target'] ) ? trim( (string) $custom_cta['target'] ) : '';
		}
		$has_cta_btn = '' !== $cta_url && '' !== $cta_title;

		$hero_thumb      = $get_event_thumb( $current_id, 'full', $current_title );
		$share_url_enc   = rawurlencode( $current_permalink );
		$share_title_enc = rawurlencode( wp_strip_all_tags( html_entity_decode( $current_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$fb_share_url    = 'https://www.facebook.com/sharer/sharer.php?u=' . $share_url_enc;
		$pin_share_url   = 'https://pinterest.com/pin/create/button/?url=' . $share_url_enc . '&description=' . $share_title_enc;
		if ( '' !== $hero_thumb['url'] ) {
			$pin_share_url .= '&media=' . rawurlencode( $hero_thumb['url'] );
		}
		$li_share_url    = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $share_url_enc;
		$email_share_url = 'mailto:?subject=' . $share_title_enc . '&body=' . $share_url_enc;

		// Sidebar Event Categories.
		$sidebar_categories = get_terms(
			array(
				'taxonomy'   => $event_taxonomy,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $sidebar_categories ) || ! is_array( $sidebar_categories ) ) {
			$sidebar_categories = array();
		}

		// Sidebar Other Upcoming Events (ordered by start date ascending).
		$other_events_q = new \WP_Query(
			array(
				'post_type'      => 'xmastree_event',
				'post_status'    => 'publish',
				'posts_per_page' => 4,
				'post__not_in'   => array( $current_id ),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation'               => 'OR',
					'event_date_sort_clause' => array(
						'key'     => 'event_start_date',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'event_start_date',
						'compare' => 'NOT EXISTS',
					),
				),
				'orderby'        => array(
					'event_date_sort_clause' => 'ASC',
					'date'                   => 'ASC',
				),
			)
		);
		$other_events = is_array( $other_events_q->posts ) ? $other_events_q->posts : array();
		wp_reset_postdata();
		?>

		<!-- ======================================================================
		     Event Details Forest-Green Hero Banner
		     ====================================================================== -->
		<section class="event-details-hero">
			<div class="container">
				<nav class="event-details-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'xmastree-theme' ); ?>">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'xmastree-theme' ); ?></a>
					<i class="bi bi-chevron-right" aria-hidden="true"></i>
					<a href="<?php echo esc_url( $events_url ); ?>"><?php echo esc_html( $events_label ); ?></a>
					<?php if ( $primary_cat instanceof \WP_Term ) : ?>
						<?php $cat_crumb_url = add_query_arg( 'event_cat', $primary_cat->slug, $events_url ); ?>
						<i class="bi bi-chevron-right" aria-hidden="true"></i>
						<a href="<?php echo esc_url( $cat_crumb_url ); ?>"><?php echo esc_html( $primary_cat->name ); ?></a>
					<?php endif; ?>
					<i class="bi bi-chevron-right" aria-hidden="true"></i>
					<span><?php echo esc_html( $current_title ); ?></span>
				</nav>

				<?php if ( $primary_cat instanceof \WP_Term ) : ?>
					<div class="event-details-hero-badge-row">
						<a href="<?php echo esc_url( add_query_arg( 'event_cat', $primary_cat->slug, $events_url ) ); ?>" class="event-hero-cat-badge">
							<i class="bi bi-tag-fill" aria-hidden="true"></i>
							<span><?php echo esc_html( $primary_cat->name ); ?></span>
						</a>
					</div>
				<?php endif; ?>

				<h1 class="event-details-hero-title">
					<?php echo esc_html( $current_title ); ?>
				</h1>

				<div class="event-details-hero-bar">
					<div class="event-details-hero-pills">
						<?php if ( '' !== $date_data['label'] ) : ?>
							<span class="event-hero-pill">
								<i class="bi bi-calendar-event-fill" aria-hidden="true"></i>
								<time datetime="<?php echo esc_attr( $date_data['iso'] ); ?>"><?php echo esc_html( $date_data['label'] ); ?></time>
							</span>
						<?php endif; ?>

						<?php if ( '' !== $time_label ) : ?>
							<span class="event-hero-pill">
								<i class="bi bi-clock-fill" aria-hidden="true"></i>
								<span><?php echo esc_html( $time_label ); ?></span>
							</span>
						<?php endif; ?>

						<?php if ( '' !== $location ) : ?>
							<span class="event-hero-pill">
								<i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
								<span><?php echo esc_html( $location ); ?></span>
							</span>
						<?php endif; ?>
					</div>

					<div class="event-hero-share">
						<span><?php esc_html_e( 'Share Event:', 'xmastree-theme' ); ?></span>
						<a href="<?php echo esc_url( $fb_share_url ); ?>" target="_blank" rel="noopener noreferrer" class="event-hero-share-btn" aria-label="<?php esc_attr_e( 'Share on Facebook', 'xmastree-theme' ); ?>">
							<i class="bi bi-facebook" aria-hidden="true"></i>
						</a>
						<a href="<?php echo esc_url( $pin_share_url ); ?>" target="_blank" rel="noopener noreferrer" class="event-hero-share-btn" aria-label="<?php esc_attr_e( 'Share on Pinterest', 'xmastree-theme' ); ?>">
							<i class="bi bi-pinterest" aria-hidden="true"></i>
						</a>
						<a href="<?php echo esc_url( $li_share_url ); ?>" target="_blank" rel="noopener noreferrer" class="event-hero-share-btn" aria-label="<?php esc_attr_e( 'Share on LinkedIn', 'xmastree-theme' ); ?>">
							<i class="bi bi-linkedin" aria-hidden="true"></i>
						</a>
						<a href="<?php echo esc_url( $email_share_url ); ?>" class="event-hero-share-btn" aria-label="<?php esc_attr_e( 'Share via Email', 'xmastree-theme' ); ?>">
							<i class="bi bi-envelope" aria-hidden="true"></i>
						</a>
					</div>
				</div>
			</div>
		</section>

		<!-- ======================================================================
		     Event Details Body + Theme Sidebar
		     ====================================================================== -->
		<section class="event-details-main-section">
			<div class="container">
				<div class="row g-4 g-lg-5">

					<!-- Left: Main Event Story & Schedule Breakdown -->
					<div class="col-lg-8">
						<article id="post-<?php the_ID(); ?>" <?php post_class( 'event-details-article' ); ?>>

							<?php if ( has_post_thumbnail() ) : ?>
								<div class="event-details-hero-card">
									<?php if ( '' !== $date_data['day'] ) : ?>
										<div class="event-details-date-badge" aria-hidden="true">
											<?php if ( '' !== $date_data['month'] ) : ?>
												<span class="event-date-badge-month"><?php echo esc_html( $date_data['month'] ); ?></span>
											<?php endif; ?>
											<span class="event-date-badge-day"><?php echo esc_html( $date_data['day'] ); ?></span>
										</div>
									<?php endif; ?>

									<?php
									the_post_thumbnail(
										'full',
										array(
											'class'   => 'event-details-hero-img',
											'loading' => 'eager',
										)
									);
									?>
								</div>
							<?php endif; ?>

							<?php if ( '' !== $short_desc ) : ?>
								<div class="event-details-lead">
									<p><?php echo nl2br( esc_html( $short_desc ) ); ?></p>
								</div>
							<?php endif; ?>

							<?php
							$raw_content = trim( (string) get_the_content() );
							if ( '' !== $raw_content ) :
								?>
								<div class="blog-article-body event-details-prose">
									<?php
									the_content();

									wp_link_pages(
										array(
											'before' => '<nav class="page-links mt-4" aria-label="' . esc_attr__( 'Event pages', 'xmastree-theme' ) . '"><span class="page-links-title">' . esc_html__( 'Pages:', 'xmastree-theme' ) . '</span>',
											'after'  => '</nav>',
										)
									);
									?>
								</div>
							<?php endif; ?>

							<div class="event-details-action-bar">
								<?php if ( $has_cta_btn ) : ?>
									<a href="<?php echo esc_url( $cta_url ); ?>"<?php echo '' !== $cta_target ? ' target="' . esc_attr( $cta_target ) . '" rel="noopener noreferrer"' : ''; ?> class="blog-btn-crimson">
										<?php echo esc_html( $cta_title ); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i>
									</a>
								<?php endif; ?>

								<a href="<?php echo esc_url( $events_url ); ?>" class="event-details-back-btn">
									<i class="bi bi-arrow-left" aria-hidden="true"></i> <?php esc_html_e( 'Back to All Events', 'xmastree-theme' ); ?>
								</a>
							</div>

							<div class="blog-article-footer">
								<?php if ( is_array( $event_terms ) && ! empty( $event_terms ) && ! is_wp_error( $event_terms ) ) : ?>
									<div class="blog-article-tags">
										<span><?php esc_html_e( 'Categories:', 'xmastree-theme' ); ?></span>
										<?php foreach ( $event_terms as $term_item ) : ?>
											<?php $term_filter_url = add_query_arg( 'event_cat', $term_item->slug, $events_url ); ?>
											<a href="<?php echo esc_url( $term_filter_url ); ?>" class="article-tag-link"><?php echo esc_html( $term_item->name ); ?></a>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>

								<div class="blog-social-share-bar">
									<span><?php esc_html_e( 'Share Event:', 'xmastree-theme' ); ?></span>
									<a href="<?php echo esc_url( $fb_share_url ); ?>" target="_blank" rel="noopener noreferrer" class="share-btn" aria-label="<?php esc_attr_e( 'Facebook', 'xmastree-theme' ); ?>">
										<i class="bi bi-facebook" aria-hidden="true"></i>
									</a>
									<a href="<?php echo esc_url( $pin_share_url ); ?>" target="_blank" rel="noopener noreferrer" class="share-btn" aria-label="<?php esc_attr_e( 'Pinterest', 'xmastree-theme' ); ?>">
										<i class="bi bi-pinterest" aria-hidden="true"></i>
									</a>
									<a href="<?php echo esc_url( $li_share_url ); ?>" target="_blank" rel="noopener noreferrer" class="share-btn" aria-label="<?php esc_attr_e( 'LinkedIn', 'xmastree-theme' ); ?>">
										<i class="bi bi-linkedin" aria-hidden="true"></i>
									</a>
									<a href="<?php echo esc_url( $email_share_url ); ?>" class="share-btn" aria-label="<?php esc_attr_e( 'Email', 'xmastree-theme' ); ?>">
										<i class="bi bi-envelope" aria-hidden="true"></i>
									</a>
								</div>
							</div>

							<?php
							$prev_event = get_previous_post();
							$next_event = get_next_post();
							if ( $prev_event instanceof \WP_Post || $next_event instanceof \WP_Post ) :
								?>
								<nav class="blog-post-navigation" aria-label="<?php esc_attr_e( 'Event navigation', 'xmastree-theme' ); ?>">
									<?php if ( $prev_event instanceof \WP_Post ) : ?>
										<a href="<?php echo esc_url( (string) get_permalink( $prev_event ) ); ?>" class="post-nav-card" rel="prev">
											<div class="post-nav-label">
												<i class="bi bi-arrow-left" aria-hidden="true"></i> <?php esc_html_e( 'Previous Event', 'xmastree-theme' ); ?>
											</div>
											<h5 class="post-nav-title"><?php echo esc_html( get_the_title( $prev_event ) ); ?></h5>
										</a>
									<?php endif; ?>

									<?php if ( $next_event instanceof \WP_Post ) : ?>
										<a href="<?php echo esc_url( (string) get_permalink( $next_event ) ); ?>" class="post-nav-card post-nav-next" rel="next">
											<div class="post-nav-label">
												<?php esc_html_e( 'Next Event', 'xmastree-theme' ); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i>
											</div>
											<h5 class="post-nav-title"><?php echo esc_html( get_the_title( $next_event ) ); ?></h5>
										</a>
									<?php endif; ?>
								</nav>
							<?php endif; ?>

						</article>
					</div>

					<!-- Right: Forest Green Summary Card & Sidebar Widgets -->
					<div class="col-lg-4">
						<aside class="blog-sidebar">

							<!-- Forest Green Event Summary Card -->
							<div class="event-details-dark-card">
								<div class="event-dark-card-eyebrow"><?php esc_html_e( 'At a Glance', 'xmastree-theme' ); ?></div>
								<h4 class="event-dark-card-title"><?php esc_html_e( 'Event Schedule & Venue', 'xmastree-theme' ); ?></h4>

								<ul class="event-dark-summary-list">
									<?php if ( '' !== $date_data['label'] ) : ?>
										<li>
											<span class="event-summary-icon"><i class="bi bi-calendar-event" aria-hidden="true"></i></span>
											<div>
												<strong><?php esc_html_e( 'Date', 'xmastree-theme' ); ?></strong>
												<span><?php echo esc_html( $date_data['label'] ); ?></span>
											</div>
										</li>
									<?php endif; ?>

									<?php if ( '' !== $time_label ) : ?>
										<li>
											<span class="event-summary-icon"><i class="bi bi-clock" aria-hidden="true"></i></span>
											<div>
												<strong><?php esc_html_e( 'Hours', 'xmastree-theme' ); ?></strong>
												<span><?php echo esc_html( $time_label ); ?></span>
											</div>
										</li>
									<?php endif; ?>

									<?php if ( '' !== $location ) : ?>
										<li>
											<span class="event-summary-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
											<div>
												<strong><?php esc_html_e( 'Location', 'xmastree-theme' ); ?></strong>
												<span><?php echo esc_html( $location ); ?></span>
											</div>
										</li>
									<?php endif; ?>

									<?php if ( $primary_cat instanceof \WP_Term ) : ?>
										<li>
											<span class="event-summary-icon"><i class="bi bi-tag" aria-hidden="true"></i></span>
											<div>
												<strong><?php esc_html_e( 'Category', 'xmastree-theme' ); ?></strong>
												<span><?php echo esc_html( $primary_cat->name ); ?></span>
											</div>
										</li>
									<?php endif; ?>
								</ul>

								<?php if ( $has_cta_btn ) : ?>
									<a href="<?php echo esc_url( $cta_url ); ?>"<?php echo '' !== $cta_target ? ' target="' . esc_attr( $cta_target ) . '" rel="noopener noreferrer"' : ''; ?> class="event-dark-card-cta">
										<?php echo esc_html( $cta_title ); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i>
									</a>
								<?php elseif ( $primary_cat instanceof \WP_Term ) : ?>
									<a href="<?php echo esc_url( add_query_arg( 'event_cat', $primary_cat->slug, $events_url ) ); ?>" class="event-dark-card-cta">
										<?php
										/* translators: %s: Event category name */
										echo esc_html( sprintf( __( 'More %s Events', 'xmastree-theme' ), $primary_cat->name ) );
										?>
										<i class="bi bi-arrow-right" aria-hidden="true"></i>
									</a>
								<?php endif; ?>
							</div>

							<!-- Search Events Widget -->
							<div class="blog-sidebar-widget event-sidebar-widget">
								<h4 class="widget-title"><?php esc_html_e( 'Search Events', 'xmastree-theme' ); ?></h4>
								<form class="widget-search-form" role="search" method="get" action="<?php echo esc_url( $events_url ); ?>">
									<?php
									foreach ( $parsed_events_args as $p_key => $p_val ) :
										if ( is_string( $p_val ) ) :
											?>
											<input type="hidden" name="<?php echo esc_attr( $p_key ); ?>" value="<?php echo esc_attr( $p_val ); ?>">
											<?php
										endif;
									endforeach;
									?>
									<div class="input-group">
										<label class="visually-hidden" for="single-event-sidebar-search"><?php esc_html_e( 'Search Events', 'xmastree-theme' ); ?></label>
										<input
											type="search"
											id="single-event-sidebar-search"
											name="event_s"
											class="form-control"
											placeholder="<?php esc_attr_e( 'Search for events...', 'xmastree-theme' ); ?>"
											autocomplete="off"
										>
										<button type="submit" class="btn-search" aria-label="<?php esc_attr_e( 'Search', 'xmastree-theme' ); ?>">
											<i class="bi bi-search" aria-hidden="true"></i>
										</button>
									</div>
								</form>
							</div>

							<!-- Event Categories Widget -->
							<?php if ( ! empty( $sidebar_categories ) ) : ?>
								<div class="blog-sidebar-widget event-sidebar-widget">
									<h4 class="widget-title"><?php esc_html_e( 'Event Categories', 'xmastree-theme' ); ?></h4>
									<ul class="widget-category-list">
										<?php
										foreach ( $sidebar_categories as $cat_term ) :
											$cat_url   = add_query_arg( 'event_cat', $cat_term->slug, $events_url );
											$is_active = in_array( (int) $cat_term->term_id, $event_term_ids, true );
											?>
											<li class="widget-category-item">
												<a href="<?php echo esc_url( $cat_url ); ?>" class="widget-category-link<?php echo $is_active ? ' active' : ''; ?>">
													<?php echo esc_html( $cat_term->name ); ?>
													<span><?php echo esc_html( (string) $cat_term->count ); ?></span>
												</a>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>

							<!-- Upcoming Events Widget -->
							<?php if ( ! empty( $other_events ) ) : ?>
								<div class="blog-sidebar-widget event-sidebar-widget">
									<h4 class="widget-title"><?php esc_html_e( 'Upcoming Farm Events', 'xmastree-theme' ); ?></h4>
									<div class="widget-recent-posts">
										<?php
										foreach ( $other_events as $o_event ) :
											$o_id        = (int) $o_event->ID;
											$o_title     = get_the_title( $o_id );
											$o_permalink = (string) get_permalink( $o_id );
											$o_date      = $format_event_date_range( $o_id );
											$o_thumb     = $get_event_thumb( $o_id, 'thumbnail', $o_title );
											?>
											<div class="widget-recent-item">
												<?php if ( '' !== $o_thumb['url'] ) : ?>
													<div class="widget-recent-thumb">
														<a href="<?php echo esc_url( $o_permalink ); ?>">
															<img src="<?php echo esc_url( $o_thumb['url'] ); ?>" alt="<?php echo esc_attr( $o_thumb['alt'] ); ?>">
														</a>
													</div>
												<?php endif; ?>
												<div class="widget-recent-content">
													<?php if ( '' !== $o_date['label'] ) : ?>
														<time class="widget-recent-date" datetime="<?php echo esc_attr( $o_date['iso'] ); ?>"><?php echo esc_html( $o_date['label'] ); ?></time>
													<?php endif; ?>
													<h5 class="widget-recent-title">
														<a href="<?php echo esc_url( $o_permalink ); ?>"><?php echo esc_html( $o_title ); ?></a>
													</h5>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>

						</aside>
					</div>

				</div>
			</div>
		</section>

		<?php
		// =====================================================================
		// More Farm Events Section (Warm Cream Background + Crimson Card Borders)
		// =====================================================================
		$related_events = array();
		$exclude_ids    = array( $current_id );

		if ( ! empty( $event_term_ids ) ) {
			$rel_cat_q = new \WP_Query(
				array(
					'post_type'      => 'xmastree_event',
					'post_status'    => 'publish',
					'posts_per_page' => 3,
					'post__not_in'   => $exclude_ids,
					'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						array(
							'taxonomy' => $event_taxonomy,
							'field'    => 'term_id',
							'terms'    => $event_term_ids,
						),
					),
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			if ( is_array( $rel_cat_q->posts ) ) {
				foreach ( $rel_cat_q->posts as $rel_p ) {
					$related_events[] = $rel_p;
					$exclude_ids[]    = (int) $rel_p->ID;
				}
			}
			wp_reset_postdata();
		}

		if ( count( $related_events ) < 3 ) {
			$needed     = 3 - count( $related_events );
			$backfill_q = new \WP_Query(
				array(
					'post_type'      => 'xmastree_event',
					'post_status'    => 'publish',
					'posts_per_page' => $needed,
					'post__not_in'   => $exclude_ids,
					'orderby'        => 'date',
					'order'          => 'DESC',
				)
			);
			if ( is_array( $backfill_q->posts ) ) {
				foreach ( $backfill_q->posts as $rel_p ) {
					$related_events[] = $rel_p;
				}
			}
			wp_reset_postdata();
		}

		if ( ! empty( $related_events ) ) :
			?>
			<section class="related-articles-section event-details-related-section">
				<div class="container">
					<div class="related-section-header">
						<h2 class="related-section-title">
							<?php esc_html_e( 'MORE FARM', 'xmastree-theme' ); ?> <span><?php esc_html_e( 'EVENTS', 'xmastree-theme' ); ?></span>
						</h2>
					</div>

					<div class="row g-4">
						<?php
						foreach ( $related_events as $rel_event ) :
							$rel_id        = (int) $rel_event->ID;
							$rel_title     = get_the_title( $rel_id );
							$rel_permalink = (string) get_permalink( $rel_id );
							$rel_thumb     = $get_event_thumb( $rel_id, 'large', $rel_title );
							$rel_has_img   = '' !== $rel_thumb['url'];
							$rel_cat       = $resolve_primary_event_cat( $rel_id );
							$rel_date      = $format_event_date_range( $rel_id );
							$rel_time      = trim( (string) get_post_meta( $rel_id, 'event_time_label', true ) );
							$rel_excerpt   = $get_event_excerpt( $rel_event, 20 );
							?>
							<div class="col-lg-4 col-md-6">
								<article class="blog-card">
									<?php if ( $rel_has_img ) : ?>
										<div class="blog-card-img-wrap">
											<?php if ( $rel_cat instanceof \WP_Term ) : ?>
												<div class="blog-card-badge"><?php echo esc_html( $rel_cat->name ); ?></div>
											<?php endif; ?>
											<a href="<?php echo esc_url( $rel_permalink ); ?>">
												<img src="<?php echo esc_url( $rel_thumb['url'] ); ?>" alt="<?php echo esc_attr( $rel_thumb['alt'] ); ?>" class="blog-card-img">
											</a>
										</div>
									<?php endif; ?>

									<div class="blog-card-body">
										<?php if ( ! $rel_has_img && $rel_cat instanceof \WP_Term ) : ?>
											<div class="blog-card-badge blog-card-badge--inline"><?php echo esc_html( $rel_cat->name ); ?></div>
										<?php endif; ?>

										<div class="blog-meta">
											<?php if ( '' !== $rel_date['label'] ) : ?>
												<div class="blog-meta-date">
													<i class="bi bi-calendar3" aria-hidden="true"></i>
													<time datetime="<?php echo esc_attr( $rel_date['iso'] ); ?>"><?php echo esc_html( $rel_date['label'] ); ?></time>
												</div>
											<?php endif; ?>
											<?php if ( '' !== $rel_time ) : ?>
												<div class="blog-meta-readtime">
													<i class="bi bi-clock" aria-hidden="true"></i>
													<span><?php echo esc_html( $rel_time ); ?></span>
												</div>
											<?php endif; ?>
										</div>

										<h3 class="blog-card-title">
											<a href="<?php echo esc_url( $rel_permalink ); ?>"><?php echo esc_html( $rel_title ); ?></a>
										</h3>

										<?php if ( '' !== $rel_excerpt ) : ?>
											<p><?php echo esc_html( $rel_excerpt ); ?></p>
										<?php endif; ?>

										<div class="blog-card-footer">
											<a href="<?php echo esc_url( $rel_permalink ); ?>" class="blog-read-more">
												<?php esc_html_e( 'View Details', 'xmastree-theme' ); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i>
											</a>
										</div>
									</div>
								</article>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</section>
		<?php endif; ?>

		<?php
	endwhile;
	?>
</main>

<?php
get_footer();
