<?php
/**
 * The template for displaying all single blog posts (Blog Details).
 *
 * Mirrors the structure and aesthetic of html/blog-details.html using dynamic
 * WordPress post content, taxonomy terms, user profile data, prev/next links,
 * comments, related articles, and optional SCF Theme Options.
 *
 * @package XmasTree_Theme
 * @since 1.0.15
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();

$has_acf = function_exists( 'get_field' );

$read_bool_option = static function ( string $field_name, bool $default = true ) use ( $has_acf ): bool {
	if ( ! $has_acf ) {
		return $default;
	}
	$val = get_field( $field_name, 'option' );
	if ( null === $val || '' === $val ) {
		return $default;
	}
	return (bool) $val;
};

$read_int_option = static function ( string $field_name, int $default, int $min, int $max ) use ( $has_acf ): int {
	if ( ! $has_acf ) {
		return $default;
	}
	$val = get_field( $field_name, 'option' );
	if ( null === $val || '' === $val || ! is_numeric( $val ) ) {
		return $default;
	}
	return max( $min, min( $max, (int) $val ) );
};

$resolve_primary_category = static function ( int $target_post_id ): ?\WP_Term {
	$cats = get_the_category( $target_post_id );
	if ( empty( $cats ) || is_wp_error( $cats ) ) {
		return null;
	}
	foreach ( $cats as $cat ) {
		if ( 'uncategorized' !== $cat->slug ) {
			return $cat;
		}
	}
	return $cats[0] ?? null;
};

$get_post_thumb = static function ( int $target_post_id, string $size = 'large', string $fallback_alt = '' ): array {
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

$get_excerpt_trimmed = static function ( \WP_Post $target_post, int $word_limit = 22 ): string {
	$raw   = has_excerpt( $target_post ) ? (string) $target_post->post_excerpt : (string) $target_post->post_content;
	$clean = wp_strip_all_tags( strip_shortcodes( $raw ) );
	$clean = html_entity_decode( $clean, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$clean = trim( (string) preg_replace( '/\s+/u', ' ', str_replace( "\xc2\xa0", ' ', $clean ) ) );
	if ( '' === $clean ) {
		return '';
	}
	return wp_trim_words( $clean, $word_limit, '...' );
};

// Optional layout configuration from SCF Theme Options (defaults enabled; optional cards stay hidden until populated).
$show_sidebar     = $read_bool_option( 'blog_details_show_sidebar', true );
$show_author_bio  = $read_bool_option( 'blog_details_show_author_bio', true );
$show_post_nav    = $read_bool_option( 'blog_details_show_post_nav', true );
$show_related     = $read_bool_option( 'blog_details_show_related', true );
$related_count    = $read_int_option( 'blog_details_related_count', 3, 1, 6 );
$related_heading  = $has_acf ? trim( (string) get_field( 'blog_details_related_heading', 'option' ) ) : '';

$promo_badge      = $has_acf ? trim( (string) get_field( 'blog_details_promo_badge', 'option' ) ) : '';
$promo_title      = $has_acf ? trim( (string) get_field( 'blog_details_promo_title', 'option' ) ) : '';
$promo_desc       = $has_acf ? trim( (string) get_field( 'blog_details_promo_description', 'option' ) ) : '';
$promo_btn        = $has_acf ? get_field( 'blog_details_promo_button', 'option' ) : null;
$promo_btn_url    = '';
$promo_btn_title  = '';
$promo_btn_target = '';
if ( is_array( $promo_btn ) && ! empty( $promo_btn['url'] ) ) {
	$promo_btn_url    = trim( (string) $promo_btn['url'] );
	$promo_btn_title  = ! empty( $promo_btn['title'] ) ? trim( (string) $promo_btn['title'] ) : '';
	$promo_btn_target = ! empty( $promo_btn['target'] ) ? trim( (string) $promo_btn['target'] ) : '';
}
$has_promo_btn  = '' !== $promo_btn_url && '' !== $promo_btn_title;
$has_promo_card = '' !== $promo_badge || '' !== $promo_title || '' !== $promo_desc || $has_promo_btn;

$moments_title   = $has_acf ? trim( (string) get_field( 'blog_details_moments_title', 'option' ) ) : '';
$moments_gallery = $has_acf ? get_field( 'blog_details_moments_gallery', 'option' ) : null;
$moments_items   = array();
if ( is_array( $moments_gallery ) ) {
	foreach ( $moments_gallery as $gallery_item ) {
		$m_url = '';
		$m_alt = '';
		if ( is_array( $gallery_item ) && ! empty( $gallery_item['url'] ) ) {
			$m_url = (string) $gallery_item['url'];
			$m_alt = ! empty( $gallery_item['alt'] ) ? (string) $gallery_item['alt'] : ( ! empty( $gallery_item['title'] ) ? (string) $gallery_item['title'] : '' );
		} elseif ( is_numeric( $gallery_item ) && (int) $gallery_item > 0 ) {
			$src = wp_get_attachment_image_url( (int) $gallery_item, 'large' );
			if ( $src ) {
				$m_url = (string) $src;
				$m_alt = (string) get_post_meta( (int) $gallery_item, '_wp_attachment_image_alt', true );
			}
		}
		if ( '' !== $m_url ) {
			$moments_items[] = array(
				'url' => $m_url,
				'alt' => $m_alt,
			);
		}
	}
}

$blog_page_id     = xmastree_get_blog_page_id();
$blog_url         = xmastree_get_blog_page_url();
$blog_label       = $blog_page_id > 0 ? get_the_title( $blog_page_id ) : __( 'Blog', 'xmastree-theme' );
$parsed_blog_args = array();
$parsed_blog_url  = wp_parse_url( $blog_url );
if ( ! empty( $parsed_blog_url['query'] ) ) {
	parse_str( (string) $parsed_blog_url['query'], $parsed_blog_args );
}

$allowed_heading_tags = array(
	'span' => array(
		'class' => array(),
	),
	'br'   => array(),
);
?>

<main id="primary" class="site-main">
	<?php
	while ( have_posts() ) :
		the_post();

		$current_post_id   = (int) get_the_ID();
		$current_title     = get_the_title();
		$current_permalink = (string) get_permalink( $current_post_id );
		$primary_cat       = $resolve_primary_category( $current_post_id );
		$post_cats         = get_the_category( $current_post_id );
		$post_cat_ids      = array();
		if ( is_array( $post_cats ) ) {
			foreach ( $post_cats as $p_cat ) {
				if ( $p_cat instanceof \WP_Term ) {
					$post_cat_ids[] = (int) $p_cat->term_id;
				}
			}
		}

		$post_tags    = get_the_tags( $current_post_id );
		$post_tag_ids = array();
		if ( is_array( $post_tags ) ) {
			foreach ( $post_tags as $p_tag ) {
				if ( $p_tag instanceof \WP_Term ) {
					$post_tag_ids[] = (int) $p_tag->term_id;
				}
			}
		}

		$author_id     = (int) get_the_author_meta( 'ID' );
		$author_name   = trim( (string) get_the_author() );
		$author_bio    = trim( (string) get_the_author_meta( 'description', $author_id ) );
		$author_avatar = $author_id > 0
			? get_avatar(
				$author_id,
				96,
				'',
				$author_name,
				array( 'class' => 'blog-details-author-avatar' )
			)
			: '';
		$author_bio_avatar = $author_id > 0
			? get_avatar(
				$author_id,
				180,
				'',
				$author_name,
				array( 'class' => 'blog-author-bio-avatar' )
			)
			: '';

		$read_mins   = xmastree_get_reading_time( $current_post_id );
		$date_iso    = get_the_date( 'c', $current_post_id );
		$date_label  = get_the_date( 'j F Y', $current_post_id );
		$hero_thumb  = $get_post_thumb( $current_post_id, 'full', $current_title );

		// Functional share URLs targeting the real post permalink & title.
		$share_url_enc   = rawurlencode( $current_permalink );
		$share_title_enc = rawurlencode( wp_strip_all_tags( html_entity_decode( $current_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$fb_share_url    = 'https://www.facebook.com/sharer/sharer.php?u=' . $share_url_enc;
		$pin_share_url   = 'https://pinterest.com/pin/create/button/?url=' . $share_url_enc . '&description=' . $share_title_enc;
		if ( '' !== $hero_thumb['url'] ) {
			$pin_share_url .= '&media=' . rawurlencode( $hero_thumb['url'] );
		}
		$li_share_url    = 'https://www.linkedin.com/sharing/share-offsite/?url=' . $share_url_enc;
		$email_share_url = 'mailto:?subject=' . $share_title_enc . '&body=' . $share_url_enc;

		// Sidebar dynamic queries.
		$sidebar_categories = get_categories(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $sidebar_categories ) ) {
			$sidebar_categories = array();
		}

		$recent_q = new \WP_Query(
			array(
				'post_type'           => 'post',
				'post_status'         => 'publish',
				'posts_per_page'      => 3,
				'post__not_in'        => array( $current_post_id ),
				'ignore_sticky_posts' => true,
				'orderby'             => 'date',
				'order'               => 'DESC',
			)
		);
		$recent_posts = is_array( $recent_q->posts ) ? $recent_q->posts : array();
		wp_reset_postdata();

		$sidebar_tags = get_tags(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => true,
				'orderby'    => 'count',
				'order'      => 'DESC',
				'number'     => 12,
			)
		);
		if ( is_wp_error( $sidebar_tags ) || ! is_array( $sidebar_tags ) ) {
			$sidebar_tags = array();
		}

		$has_sidebar_content = $show_sidebar;
		$main_col_class      = $has_sidebar_content ? 'col-lg-8' : 'col-12';
		?>

		<!-- ======================================================================
		     Article Details Header & Breadcrumbs
		     ====================================================================== -->
		<section class="blog-details-header">
			<div class="container">
				<nav class="blog-breadcrumb-nav" aria-label="<?php esc_attr_e( 'Breadcrumb', 'xmastree-theme' ); ?>" data-aos="fade-down">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Home', 'xmastree-theme' ); ?></a>
					<i class="bi bi-chevron-right" aria-hidden="true"></i>
					<a href="<?php echo esc_url( $blog_url ); ?>"><?php echo esc_html( $blog_label ); ?></a>
					<?php if ( $primary_cat instanceof \WP_Term ) : ?>
						<?php $cat_crumb_url = add_query_arg( 'blog_cat', $primary_cat->slug, $blog_url ); ?>
						<i class="bi bi-chevron-right" aria-hidden="true"></i>
						<a href="<?php echo esc_url( $cat_crumb_url ); ?>"><?php echo esc_html( $primary_cat->name ); ?></a>
					<?php endif; ?>
					<i class="bi bi-chevron-right" aria-hidden="true"></i>
					<span><?php echo esc_html( $current_title ); ?></span>
				</nav>

				<h1 class="blog-details-title" data-aos="fade-up" data-aos-duration="800">
					<?php echo esc_html( $current_title ); ?>
				</h1>

				<div class="blog-details-meta" data-aos="fade-up" data-aos-duration="800" data-aos-delay="100">
					<div class="blog-details-author">
						<?php if ( ! empty( $author_avatar ) ) : ?>
							<?php echo $author_avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
						<div class="blog-details-author-info">
							<?php if ( '' !== $author_name ) : ?>
								<span><?php echo esc_html( $author_name ); ?></span>
							<?php endif; ?>
							<span>
								<time datetime="<?php echo esc_attr( (string) $date_iso ); ?>"><?php echo esc_html( $date_label ); ?></time>
								&bull;
								<?php
								/* translators: %d: estimated reading time in minutes */
								echo esc_html( sprintf( _n( '%d min read', '%d min read', $read_mins, 'xmastree-theme' ), $read_mins ) );
								?>
								<?php if ( $primary_cat instanceof \WP_Term ) : ?>
									&bull; <?php echo esc_html( $primary_cat->name ); ?>
								<?php endif; ?>
							</span>
						</div>
					</div>

					<div class="blog-social-share-bar">
						<span><?php esc_html_e( 'Share:', 'xmastree-theme' ); ?></span>
						<a href="<?php echo esc_url( $fb_share_url ); ?>" target="_blank" rel="noopener noreferrer" class="share-btn" aria-label="<?php esc_attr_e( 'Share on Facebook', 'xmastree-theme' ); ?>">
							<i class="bi bi-facebook" aria-hidden="true"></i>
						</a>
						<a href="<?php echo esc_url( $pin_share_url ); ?>" target="_blank" rel="noopener noreferrer" class="share-btn" aria-label="<?php esc_attr_e( 'Share on Pinterest', 'xmastree-theme' ); ?>">
							<i class="bi bi-pinterest" aria-hidden="true"></i>
						</a>
						<a href="<?php echo esc_url( $li_share_url ); ?>" target="_blank" rel="noopener noreferrer" class="share-btn" aria-label="<?php esc_attr_e( 'Share on LinkedIn', 'xmastree-theme' ); ?>">
							<i class="bi bi-linkedin" aria-hidden="true"></i>
						</a>
						<a href="<?php echo esc_url( $email_share_url ); ?>" class="share-btn" aria-label="<?php esc_attr_e( 'Share via Email', 'xmastree-theme' ); ?>">
							<i class="bi bi-envelope" aria-hidden="true"></i>
						</a>
					</div>
				</div>
			</div>
		</section>

		<!-- ======================================================================
		     Article Body + Sidebar Section
		     ====================================================================== -->
		<section class="blog-main-section">
			<div class="container">
				<div class="row g-4 g-lg-5">

					<!-- Left: Main Article Content -->
					<div class="<?php echo esc_attr( $main_col_class ); ?>">
						<article id="post-<?php the_ID(); ?>" <?php post_class( 'blog-article-content' ); ?> data-aos="fade-up" data-aos-duration="800">

							<?php if ( has_post_thumbnail() ) : ?>
								<div class="blog-details-featured-media">
									<?php
									the_post_thumbnail(
										'full',
										array(
											'class'   => 'blog-details-hero-img',
											'loading' => 'eager',
										)
									);
									?>
								</div>
							<?php endif; ?>

							<?php if ( has_excerpt() ) : ?>
								<div class="blog-lead-text">
									<?php echo wp_kses_post( get_the_excerpt() ); ?>
								</div>
							<?php endif; ?>

							<div class="blog-article-body">
								<?php
								the_content();

								wp_link_pages(
									array(
										'before' => '<nav class="page-links mt-4" aria-label="' . esc_attr__( 'Article pages', 'xmastree-theme' ) . '"><span class="page-links-title">' . esc_html__( 'Pages:', 'xmastree-theme' ) . '</span>',
										'after'  => '</nav>',
									)
								);
								?>
							</div>

							<?php
							$has_tags = is_array( $post_tags ) && ! empty( $post_tags ) && ! is_wp_error( $post_tags );
							?>
							<div class="blog-article-footer">
								<?php if ( $has_tags ) : ?>
									<div class="blog-article-tags">
										<span><?php esc_html_e( 'Tags:', 'xmastree-theme' ); ?></span>
										<?php foreach ( $post_tags as $tag_term ) : ?>
											<?php
											$tag_url   = add_query_arg( 'blog_tag', $tag_term->slug, $blog_url );
											$tag_label = str_starts_with( $tag_term->name, '#' ) ? $tag_term->name : '#' . $tag_term->name;
											?>
											<a href="<?php echo esc_url( $tag_url ); ?>" class="article-tag-link"><?php echo esc_html( $tag_label ); ?></a>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>

								<div class="blog-social-share-bar">
									<span><?php esc_html_e( 'Share Story:', 'xmastree-theme' ); ?></span>
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
							if ( $show_author_bio && ( '' !== $author_name || '' !== $author_bio ) ) :
								$author_story_count = $author_id > 0 ? (int) count_user_posts( $author_id, 'post', true ) : 0;
								?>
								<div class="blog-author-bio-box" data-aos="fade-up">
									<?php if ( ! empty( $author_bio_avatar ) ) : ?>
										<?php echo $author_bio_avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php endif; ?>
									<div class="blog-author-bio-content">
										<?php if ( '' !== $author_name ) : ?>
											<h4><?php echo esc_html( $author_name ); ?></h4>
										<?php endif; ?>
										<?php if ( $author_story_count > 0 ) : ?>
											<span>
												<?php
												/* translators: %d: number of published articles by author */
												echo esc_html( sprintf( _n( '%d Published Article', '%d Published Articles', $author_story_count, 'xmastree-theme' ), $author_story_count ) );
												?>
											</span>
										<?php endif; ?>
										<?php if ( '' !== $author_bio ) : ?>
											<?php echo wp_kses_post( wpautop( $author_bio ) ); ?>
										<?php endif; ?>
									</div>
								</div>
							<?php endif; ?>

							<?php
							if ( $show_post_nav ) :
								$prev_post = get_previous_post();
								$next_post = get_next_post();
								if ( $prev_post instanceof \WP_Post || $next_post instanceof \WP_Post ) :
									?>
									<nav class="blog-post-navigation" aria-label="<?php esc_attr_e( 'Article navigation', 'xmastree-theme' ); ?>">
										<?php if ( $prev_post instanceof \WP_Post ) : ?>
											<a href="<?php echo esc_url( (string) get_permalink( $prev_post ) ); ?>" class="post-nav-card" rel="prev">
												<div class="post-nav-label">
													<i class="bi bi-arrow-left" aria-hidden="true"></i> <?php esc_html_e( 'Previous Article', 'xmastree-theme' ); ?>
												</div>
												<h5 class="post-nav-title"><?php echo esc_html( get_the_title( $prev_post ) ); ?></h5>
											</a>
										<?php endif; ?>

										<?php if ( $next_post instanceof \WP_Post ) : ?>
											<a href="<?php echo esc_url( (string) get_permalink( $next_post ) ); ?>" class="post-nav-card post-nav-next" rel="next">
												<div class="post-nav-label">
													<?php esc_html_e( 'Next Article', 'xmastree-theme' ); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i>
												</div>
												<h5 class="post-nav-title"><?php echo esc_html( get_the_title( $next_post ) ); ?></h5>
											</a>
										<?php endif; ?>
									</nav>
								<?php endif; ?>
							<?php endif; ?>

							<?php
							if ( comments_open() || get_comments_number() ) {
								comments_template();
							}
							?>

						</article>
					</div>

					<!-- Right: Dynamic Sidebar -->
					<?php if ( $has_sidebar_content ) : ?>
						<div class="col-lg-4">
							<aside class="blog-sidebar" data-aos="fade-left" data-aos-duration="800">

								<!-- Search Widget -->
								<div class="blog-sidebar-widget">
									<h4 class="widget-title"><?php esc_html_e( 'Search Articles', 'xmastree-theme' ); ?></h4>
									<form class="widget-search-form" role="search" method="get" action="<?php echo esc_url( $blog_url ); ?>">
										<?php
										foreach ( $parsed_blog_args as $p_key => $p_val ) :
											if ( is_string( $p_val ) ) :
												?>
												<input type="hidden" name="<?php echo esc_attr( $p_key ); ?>" value="<?php echo esc_attr( $p_val ); ?>">
												<?php
											endif;
										endforeach;
										?>
										<div class="input-group">
											<label class="visually-hidden" for="single-sidebar-search"><?php esc_html_e( 'Search Articles', 'xmastree-theme' ); ?></label>
											<input
												type="search"
												id="single-sidebar-search"
												name="blog_s"
												class="form-control"
												placeholder="<?php esc_attr_e( 'Search farm blog...', 'xmastree-theme' ); ?>"
												autocomplete="off"
											>
											<button type="submit" class="btn-search" aria-label="<?php esc_attr_e( 'Search', 'xmastree-theme' ); ?>">
												<i class="bi bi-search" aria-hidden="true"></i>
											</button>
										</div>
									</form>
								</div>

								<!-- Categories Widget -->
								<?php if ( ! empty( $sidebar_categories ) ) : ?>
									<div class="blog-sidebar-widget">
										<h4 class="widget-title"><?php esc_html_e( 'Farm Categories', 'xmastree-theme' ); ?></h4>
										<ul class="widget-category-list">
											<?php
											foreach ( $sidebar_categories as $cat_term ) :
												$cat_url   = add_query_arg( 'blog_cat', $cat_term->slug, $blog_url );
												$is_active = in_array( (int) $cat_term->term_id, $post_cat_ids, true );
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

								<!-- Recent Stories Widget -->
								<?php if ( ! empty( $recent_posts ) ) : ?>
									<div class="blog-sidebar-widget">
										<h4 class="widget-title"><?php esc_html_e( 'Recent Stories', 'xmastree-theme' ); ?></h4>
										<div class="widget-recent-posts">
											<?php
											foreach ( $recent_posts as $r_post ) :
												$r_id        = (int) $r_post->ID;
												$r_title     = get_the_title( $r_id );
												$r_permalink = (string) get_permalink( $r_id );
												$r_date      = get_the_date( 'd M Y', $r_id );
												$r_date_iso  = get_the_date( 'c', $r_id );
												$r_thumb     = $get_post_thumb( $r_id, 'thumbnail', $r_title );
												?>
												<div class="widget-recent-item">
													<?php if ( '' !== $r_thumb['url'] ) : ?>
														<div class="widget-recent-thumb">
															<a href="<?php echo esc_url( $r_permalink ); ?>">
																<img src="<?php echo esc_url( $r_thumb['url'] ); ?>" alt="<?php echo esc_attr( $r_thumb['alt'] ); ?>">
															</a>
														</div>
													<?php endif; ?>
													<div class="widget-recent-content">
														<?php if ( '' !== $r_date ) : ?>
															<time class="widget-recent-date" datetime="<?php echo esc_attr( (string) $r_date_iso ); ?>"><?php echo esc_html( $r_date ); ?></time>
														<?php endif; ?>
														<h5 class="widget-recent-title">
															<a href="<?php echo esc_url( $r_permalink ); ?>"><?php echo esc_html( $r_title ); ?></a>
														</h5>
													</div>
												</div>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endif; ?>

								<!-- Optional Promo Card (Theme Options — hidden unless populated) -->
								<?php if ( $has_promo_card ) : ?>
									<div class="widget-promo-card">
										<?php if ( '' !== $promo_badge ) : ?>
											<div class="widget-promo-badge"><?php echo esc_html( $promo_badge ); ?></div>
										<?php endif; ?>
										<?php if ( '' !== $promo_title ) : ?>
											<h4 class="widget-promo-title"><?php echo esc_html( $promo_title ); ?></h4>
										<?php endif; ?>
										<?php if ( '' !== $promo_desc ) : ?>
											<p><?php echo nl2br( esc_html( $promo_desc ) ); ?></p>
										<?php endif; ?>
										<?php if ( $has_promo_btn ) : ?>
											<a href="<?php echo esc_url( $promo_btn_url ); ?>"<?php echo '' !== $promo_btn_target ? ' target="' . esc_attr( $promo_btn_target ) . '" rel="noopener noreferrer"' : ''; ?> class="widget-promo-btn">
												<?php echo esc_html( $promo_btn_title ); ?> <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
											</a>
										<?php endif; ?>
									</div>
								<?php endif; ?>

								<!-- Popular Tags Widget -->
								<?php if ( ! empty( $sidebar_tags ) ) : ?>
									<div class="blog-sidebar-widget">
										<h4 class="widget-title"><?php esc_html_e( 'Popular Tags', 'xmastree-theme' ); ?></h4>
										<div class="widget-tags-cloud">
											<?php
											foreach ( $sidebar_tags as $tag_term ) :
												$tag_link  = add_query_arg( 'blog_tag', $tag_term->slug, $blog_url );
												$tag_label = str_starts_with( $tag_term->name, '#' ) ? $tag_term->name : '#' . $tag_term->name;
												$is_active = in_array( (int) $tag_term->term_id, $post_tag_ids, true );
												?>
												<a href="<?php echo esc_url( $tag_link ); ?>" class="widget-tag-item<?php echo $is_active ? ' active' : ''; ?>"><?php echo esc_html( $tag_label ); ?></a>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endif; ?>

							</aside>
						</div>
					<?php endif; ?>

				</div>
			</div>
		</section>

		<?php
		// =====================================================================
		// Related Articles Section
		// =====================================================================
		if ( $show_related ) :
			$related_posts = array();
			$exclude_ids   = array( $current_post_id );

			if ( ! empty( $post_cat_ids ) ) {
				$cat_rel_q = new \WP_Query(
					array(
						'post_type'           => 'post',
						'post_status'         => 'publish',
						'posts_per_page'      => $related_count,
						'post__not_in'        => $exclude_ids,
						'category__in'        => $post_cat_ids,
						'ignore_sticky_posts' => true,
						'orderby'             => 'date',
						'order'               => 'DESC',
					)
				);
				if ( is_array( $cat_rel_q->posts ) ) {
					foreach ( $cat_rel_q->posts as $rel_p ) {
						$related_posts[] = $rel_p;
						$exclude_ids[]   = (int) $rel_p->ID;
					}
				}
				wp_reset_postdata();
			}

			if ( count( $related_posts ) < $related_count ) {
				$needed         = $related_count - count( $related_posts );
				$backfill_rel_q = new \WP_Query(
					array(
						'post_type'           => 'post',
						'post_status'         => 'publish',
						'posts_per_page'      => $needed,
						'post__not_in'        => $exclude_ids,
						'ignore_sticky_posts' => true,
						'orderby'             => 'date',
						'order'               => 'DESC',
					)
				);
				if ( is_array( $backfill_rel_q->posts ) ) {
					foreach ( $backfill_rel_q->posts as $rel_p ) {
						$related_posts[] = $rel_p;
					}
				}
				wp_reset_postdata();
			}

			if ( ! empty( $related_posts ) ) :
				$related_heading_html = '' !== $related_heading
					? wp_kses( $related_heading, $allowed_heading_tags )
					: esc_html__( 'YOU MAY ALSO', 'xmastree-theme' ) . ' <span>' . esc_html__( 'ENJOY', 'xmastree-theme' ) . '</span>';
				?>
				<section class="related-articles-section">
					<div class="container">
						<div class="related-section-header" data-aos="fade-up">
							<h2 class="related-section-title">
								<?php echo $related_heading_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</h2>
						</div>

						<div class="row g-4">
							<?php
							foreach ( $related_posts as $index => $rel_post ) :
								$rel_id         = (int) $rel_post->ID;
								$rel_title      = get_the_title( $rel_id );
								$rel_permalink  = (string) get_permalink( $rel_id );
								$rel_thumb      = $get_post_thumb( $rel_id, 'large', $rel_title );
								$rel_has_img    = '' !== $rel_thumb['url'];
								$rel_cat        = $resolve_primary_category( $rel_id );
								$rel_date_iso   = get_the_date( 'c', $rel_id );
								$rel_date_label = get_the_date( 'd M Y', $rel_id );
								$rel_read_mins  = xmastree_get_reading_time( $rel_id );
								$rel_excerpt    = $get_excerpt_trimmed( $rel_post, 20 );
								$aos_delay      = 100 + ( ( $index % 3 ) * 100 );
								?>
								<div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-duration="600" data-aos-delay="<?php echo esc_attr( (string) $aos_delay ); ?>">
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
												<?php if ( '' !== $rel_date_label ) : ?>
													<div class="blog-meta-date">
														<i class="bi bi-calendar3" aria-hidden="true"></i>
														<time datetime="<?php echo esc_attr( (string) $rel_date_iso ); ?>"><?php echo esc_html( $rel_date_label ); ?></time>
													</div>
												<?php endif; ?>
												<div class="blog-meta-readtime">
													<i class="bi bi-clock" aria-hidden="true"></i>
													<span>
														<?php
														/* translators: %d: estimated reading minutes */
														echo esc_html( sprintf( __( '%d min', 'xmastree-theme' ), $rel_read_mins ) );
														?>
													</span>
												</div>
											</div>

											<h3 class="blog-card-title">
												<a href="<?php echo esc_url( $rel_permalink ); ?>"><?php echo esc_html( $rel_title ); ?></a>
											</h3>

											<?php if ( '' !== $rel_excerpt ) : ?>
												<p><?php echo esc_html( $rel_excerpt ); ?></p>
											<?php endif; ?>

											<div class="blog-card-footer">
												<a href="<?php echo esc_url( $rel_permalink ); ?>" class="blog-read-more">
													<?php esc_html_e( 'Read More', 'xmastree-theme' ); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i>
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
		<?php endif; ?>

		<?php
		// =====================================================================
		// Optional Bottom Moments Carousel (Hidden unless Gallery populated)
		// =====================================================================
		if ( ! empty( $moments_items ) ) :
			$moments_heading_html = '' !== $moments_title
				? wp_kses( $moments_title, $allowed_heading_tags )
				: esc_html__( 'MOMENTS FROM', 'xmastree-theme' ) . ' <span>' . esc_html__( 'THE FARM', 'xmastree-theme' ) . '</span>';
			?>
			<section class="events-moments-section">
				<div class="container">
					<div class="section-header text-center mb-5" data-aos="fade-up" data-aos-duration="800">
						<h2 class="section-title">
							<?php echo $moments_heading_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</h2>
					</div>

					<div class="moments-carousel-wrapper" data-aos="fade-up" data-aos-duration="800" data-aos-delay="150">
						<button type="button" class="moments-prev" aria-label="<?php esc_attr_e( 'Previous image', 'xmastree-theme' ); ?>">
							<i class="bi bi-chevron-left" aria-hidden="true"></i>
						</button>

						<div class="owl-carousel owl-theme moments-carousel">
							<?php foreach ( $moments_items as $m_item ) : ?>
								<div class="item">
									<div class="moments-card">
										<img src="<?php echo esc_url( $m_item['url'] ); ?>" alt="<?php echo esc_attr( $m_item['alt'] ); ?>">
									</div>
								</div>
							<?php endforeach; ?>
						</div>

						<button type="button" class="moments-next" aria-label="<?php esc_attr_e( 'Next image', 'xmastree-theme' ); ?>">
							<i class="bi bi-chevron-right" aria-hidden="true"></i>
						</button>
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
