<?php
/**
 * XmasTree Theme functions and definitions
 *
 * @package XmasTree_Theme
 * @since 1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Theme version constant for cache busting.
 */
if ( ! defined( 'XMASTREE_THEME_VERSION' ) ) {
	define( 'XMASTREE_THEME_VERSION', '1.0.29' );
}

if ( ! function_exists( 'xmastree_theme_setup' ) ) :
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * Note that this function is hooked into the after_setup_theme hook, which
	 * runs before the init hook. The init hook is too late for some features, such
	 * as indicating support for post thumbnails.
	 *
	 * @return void
	 */
	function xmastree_theme_setup(): void {
		// Make theme available for translation.
		load_theme_textdomain( 'xmastree-theme', get_template_directory() . '/languages' );

		// Let WordPress manage the document title.
		add_theme_support( 'title-tag' );

		// Enable support for Post Thumbnails on posts and pages.
		add_theme_support( 'post-thumbnails' );

		// Enable custom logo support.
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 100,
				'width'       => 350,
				'flex-height' => true,
				'flex-width'  => true,
				'header-text' => array( 'site-title', 'site-description' ),
			)
		);

		// Switch default core markup for search form, comment form, and comments to output valid HTML5.
		add_theme_support(
			'html5',
			array(
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'style',
				'script',
				'navigation-widgets',
			)
		);

		// Add support for responsive embedded content.
		add_theme_support( 'responsive-embeds' );

		// Add support for wide and full alignment in Gutenberg blocks.
		add_theme_support( 'align-wide' );

		// Add support for editor styles.
		add_theme_support( 'editor-styles' );

		// Enqueue editor stylesheet for the block editor.
		add_editor_style( 'assets/css/style.css' );

		// Register theme navigation menu locations.
		register_nav_menus(
			array(
				'primary-menu'         => esc_html__( 'Primary Navigation Menu', 'xmastree-theme' ),
				'footer-quick-links'   => esc_html__( 'Footer Quick Links Menu', 'xmastree-theme' ),
				'footer-helpful-links' => esc_html__( 'Footer Helpful Links Menu', 'xmastree-theme' ),
			)
		);
	}
endif;
add_action( 'after_setup_theme', 'xmastree_theme_setup' );

if ( ! function_exists( 'xmastree_widgets_init' ) ) :
	/**
	 * Register widget area support.
	 *
	 * @return void
	 */
	function xmastree_widgets_init(): void {
		register_sidebar(
			array(
				'name'          => esc_html__( 'Primary Sidebar', 'xmastree-theme' ),
				'id'            => 'sidebar-1',
				'description'   => esc_html__( 'Add widgets here to appear in your primary sidebar.', 'xmastree-theme' ),
				'before_widget' => '<section id="%1$s" class="widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h3 class="widget-title">',
				'after_title'   => '</h3>',
			)
		);
	}
endif;
add_action( 'widgets_init', 'xmastree_widgets_init' );

if ( ! function_exists( 'xmastree_resource_hints' ) ) :
	/**
	 * Add preconnect resource hints for Google Fonts.
	 *
	 * @param array<int, array<string, string>|string> $urls          URLs to print for resource hints.
	 * @param string                                  $relation_type  The relation type the URLs are printed for.
	 * @return array<int, array<string, string>|string> Filtered resource hint URLs.
	 */
	function xmastree_resource_hints( array $urls, string $relation_type ): array {
		if ( 'preconnect' === $relation_type ) {
			$urls[] = array(
				'href' => 'https://fonts.googleapis.com',
			);
			$urls[] = array(
				'href'        => 'https://fonts.gstatic.com',
				'crossorigin' => 'anonymous',
			);
		}
		return $urls;
	}
endif;
add_filter( 'wp_resource_hints', 'xmastree_resource_hints', 10, 2 );

if ( ! function_exists( 'xmastree_enqueue_scripts' ) ) :
	/**
	 * Enqueue styles and scripts for the front-end.
	 *
	 * @return void
	 */
	function xmastree_enqueue_scripts(): void {
		$theme_version = defined( 'XMASTREE_THEME_VERSION' ) ? XMASTREE_THEME_VERSION : wp_get_theme()->get( 'Version' );
		$template_uri  = get_template_directory_uri();

		// -------------------------------------------------------------------------
		// Stylesheets
		// -------------------------------------------------------------------------

		// 1. Google Fonts.
		wp_enqueue_style(
			'xmastree-google-fonts',
			'https://fonts.googleapis.com/css2?family=Frijole&family=Inter:wght@400;500;600;700&family=Kaushan+Script&family=Lato:wght@400;700&family=Open+Sans:wght@600&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Rye&display=swap',
			array(),
			null // Version null so query string does not interfere with Google CDN.
		);

		// 2. Bootstrap 5 CSS.
		wp_enqueue_style(
			'xmastree-bootstrap',
			$template_uri . '/assets/css/bootstrap.min.css',
			array(),
			'5.3.3'
		);

		// 3. Bootstrap Icons CSS.
		wp_enqueue_style(
			'xmastree-bootstrap-icons',
			$template_uri . '/assets/css/bootstrap-icons.min.css',
			array(),
			'1.11.3'
		);

		// 4. Owl Carousel CSS.
		wp_enqueue_style(
			'xmastree-owl-carousel',
			$template_uri . '/assets/css/owl.carousel.min.css',
			array(),
			'2.3.4'
		);

		// 5. Owl Carousel Default Theme CSS.
		wp_enqueue_style(
			'xmastree-owl-theme-default',
			$template_uri . '/assets/css/owl.theme.default.min.css',
			array( 'xmastree-owl-carousel' ),
			'2.3.4'
		);

		// 6. AOS (Animate on Scroll) CSS.
		wp_enqueue_style(
			'xmastree-aos',
			$template_uri . '/assets/css/aos.css',
			array(),
			'2.3.4'
		);

		// 7. Reference Theme Stylesheet (from HTML project).
		wp_enqueue_style(
			'xmastree-main',
			$template_uri . '/assets/css/style.css',
			array(
				'xmastree-bootstrap',
				'xmastree-bootstrap-icons',
				'xmastree-owl-carousel',
				'xmastree-owl-theme-default',
				'xmastree-aos',
			),
			$theme_version
		);

		// 8. Active WordPress Theme Root Stylesheet.
		wp_enqueue_style(
			'xmastree-style',
			get_stylesheet_uri(),
			array( 'xmastree-main' ),
			$theme_version
		);

		// -------------------------------------------------------------------------
		// JavaScript Scripts
		// -------------------------------------------------------------------------

		// 1. WordPress Core jQuery.
		wp_enqueue_script( 'jquery' );

		// Provide global '$' alias for jQuery to ensure compatibility with reference scripts.
		wp_add_inline_script( 'jquery', 'window.$ = window.jQuery;' );

		// 2. Bootstrap 5 Bundle JS (includes Popper).
		wp_enqueue_script(
			'xmastree-bootstrap',
			$template_uri . '/assets/js/bootstrap.bundle.min.js',
			array(),
			'5.3.3',
			true
		);

		// 3. Owl Carousel JS.
		wp_enqueue_script(
			'xmastree-owl-carousel',
			$template_uri . '/assets/js/owl.carousel.min.js',
			array( 'jquery' ),
			'2.3.4',
			true
		);

		// 4. AOS JS.
		wp_enqueue_script(
			'xmastree-aos',
			$template_uri . '/assets/js/aos.js',
			array(),
			'2.3.4',
			true
		);

		// 5. Custom JavaScript (from HTML reference).
		wp_enqueue_script(
			'xmastree-custom',
			$template_uri . '/assets/js/custom.js',
			array( 'jquery', 'xmastree-bootstrap', 'xmastree-owl-carousel', 'xmastree-aos' ),
			$theme_version,
			true
		);

		// Threaded comments script.
		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}
endif;
add_action( 'wp_enqueue_scripts', 'xmastree_enqueue_scripts' );

/**
 * Resolve the WordPress page ID serving the Blog Listing page.
 *
 * Checks reading settings (`page_for_posts`) first, then falls back to the
 * published page with slug `blog`.
 *
 * @return int Page ID or 0 when unavailable.
 */
function xmastree_get_blog_page_id(): int {
	$posts_page_id = (int) get_option( 'page_for_posts', 0 );
	if ( $posts_page_id > 0 && 'publish' === get_post_status( $posts_page_id ) ) {
		return $posts_page_id;
	}

	$blog_page = get_page_by_path( 'blog', OBJECT, 'page' );
	if ( $blog_page instanceof \WP_Post && 'publish' === $blog_page->post_status ) {
		return (int) $blog_page->ID;
	}

	return 0;
}

/**
 * Resolve the canonical URL for the Blog index page.
 *
 * @return string Blog page permalink or home URL fallback.
 */
function xmastree_get_blog_page_url(): string {
	$blog_page_id = xmastree_get_blog_page_id();
	if ( $blog_page_id > 0 ) {
		$permalink = get_permalink( $blog_page_id );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			return $permalink;
		}
	}

	return home_url( '/' );
}

/**
 * Resolve the WordPress page ID serving the Christmas Events Listing page.
 *
 * Checks published pages with slug `christmas-events` or `events`.
 *
 * @return int Page ID or 0 when unavailable.
 */
function xmastree_get_events_page_id(): int {
	foreach ( array( 'christmas-events', 'events' ) as $slug ) {
		$events_page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $events_page instanceof \WP_Post && 'publish' === $events_page->post_status ) {
			return (int) $events_page->ID;
		}
	}

	return 0;
}

/**
 * Resolve the canonical URL for the Christmas Events index page.
 *
 * @return string Events page permalink or home URL fallback.
 */
function xmastree_get_events_page_url(): string {
	$events_page_id = xmastree_get_events_page_id();
	if ( $events_page_id > 0 ) {
		$permalink = get_permalink( $events_page_id );
		if ( is_string( $permalink ) && '' !== $permalink ) {
			return $permalink;
		}
	}

	return home_url( '/' );
}

/**
 * Estimate reading time in minutes from a post's content word count.
 *
 * @param int $post_id Post ID.
 * @return int Estimated minutes (minimum 1).
 */
function xmastree_get_reading_time( int $post_id ): int {
	$post_obj = get_post( $post_id );
	if ( ! $post_obj instanceof \WP_Post ) {
		return 1;
	}

	$stripped = trim( wp_strip_all_tags( strip_shortcodes( (string) $post_obj->post_content ) ) );
	if ( '' === $stripped ) {
		return 1;
	}

	$words = preg_split( '/\s+/u', $stripped, -1, PREG_SPLIT_NO_EMPTY );
	$count = is_array( $words ) ? count( $words ) : 0;

	return max( 1, (int) ceil( $count / 200 ) );
}

/**
 * Custom WordPress comment renderer matching blog-details.html (.comment-item).
 *
 * @param \WP_Comment $comment Comment object.
 * @param array       $args    wp_list_comments arguments.
 * @param int         $depth   Nesting depth.
 * @return void
 */
function xmastree_comment_callback( \WP_Comment $comment, array $args, int $depth ): void {
	$tag       = ( 'div' === ( $args['style'] ?? 'div' ) ) ? 'div' : 'li';
	$add_below = 'div-comment';
	$avatar    = get_avatar(
		$comment,
		(int) ( $args['avatar_size'] ?? 50 ),
		'',
		get_comment_author( $comment ),
		array( 'class' => 'comment-avatar' )
	);
	?>
	<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> id="comment-<?php comment_ID(); ?>" <?php comment_class( empty( $args['has_children'] ) ? '' : 'parent', $comment ); ?>>
		<div id="div-comment-<?php comment_ID(); ?>" class="comment-item">
			<?php if ( ! empty( $avatar ) ) : ?>
				<?php echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<div class="comment-content">
				<div class="comment-header">
					<div class="comment-author-name"><?php comment_author_link( $comment ); ?></div>
					<time class="comment-date" datetime="<?php echo esc_attr( get_comment_time( 'c' ) ); ?>">
						<?php
						/* translators: 1: date, 2: time */
						echo esc_html( sprintf( __( '%1$s at %2$s', 'xmastree-theme' ), get_comment_date( 'd M Y', $comment ), get_comment_time( 'g:i a' ) ) );
						?>
					</time>
				</div>

				<?php if ( '0' === (string) $comment->comment_approved ) : ?>
					<p class="comment-awaiting-moderation"><?php esc_html_e( 'Your comment is awaiting moderation.', 'xmastree-theme' ); ?></p>
				<?php endif; ?>

				<div class="comment-text">
					<?php comment_text(); ?>
				</div>

				<?php
				comment_reply_link(
					array_merge(
						$args,
						array(
							'add_below'  => $add_below,
							'depth'      => $depth,
							'max_depth'  => $args['max_depth'],
							'reply_text' => '<i class="bi bi-reply-fill" aria-hidden="true"></i> ' . esc_html__( 'Reply', 'xmastree-theme' ),
							'before'     => '<div class="comment-reply-wrap">',
							'after'      => '</div>',
						)
					)
				);
				?>
			</div>
		</div>
	<?php
}

/**
 * Determine whether a primary navigation item should display the active state for blog views.
 *
 * @param \WP_Post $item Menu item object.
 * @return bool
 */
function xmastree_is_blog_menu_item_active( \WP_Post $item ): bool {
	if ( ! is_singular( 'post' ) && ! is_category() && ! is_tag() && ! is_date() ) {
		return false;
	}

	$blog_page_id = xmastree_get_blog_page_id();
	return $blog_page_id > 0 && (int) $item->object_id === $blog_page_id;
}

/**
 * Determine whether a primary navigation item should display the active state for events views.
 *
 * @param \WP_Post $item Menu item object.
 * @return bool
 */
function xmastree_is_events_menu_item_active( \WP_Post $item ): bool {
	if ( ! is_singular( 'xmastree_event' ) && ! is_tax( 'event_category' ) ) {
		return false;
	}

	$events_page_id = xmastree_get_events_page_id();
	return $events_page_id > 0 && (int) $item->object_id === $events_page_id;
}

/**
 * Filter nav menu <li> item CSS classes to integrate with Bootstrap 5 navbar.
 *
 * @param array<string> $classes Current CSS classes.
 * @param \WP_Post      $item    The current menu item object.
 * @param \stdClass     $args    An object of wp_nav_menu() arguments.
 * @return array<string> Filtered CSS classes.
 */
function xmastree_nav_menu_classes( array $classes, \WP_Post $item, \stdClass $args ): array {
	if ( isset( $args->theme_location ) && 'primary-menu' === $args->theme_location ) {
		$classes[] = 'nav-item';
		if (
			in_array( 'current-menu-item', $classes, true ) ||
			in_array( 'current_page_item', $classes, true ) ||
			xmastree_is_blog_menu_item_active( $item ) ||
			xmastree_is_events_menu_item_active( $item )
		) {
			$classes[] = 'active';
		}
	}
	return $classes;
}
add_filter( 'nav_menu_css_class', 'xmastree_nav_menu_classes', 10, 3 );

/**
 * Filter nav menu <a> anchor attributes to integrate with Bootstrap 5 navbar.
 *
 * @param array<string, string> $atts Anchor HTML attributes.
 * @param \WP_Post              $item The current menu item object.
 * @param \stdClass             $args An object of wp_nav_menu() arguments.
 * @return array<string, string> Filtered attributes.
 */
function xmastree_nav_menu_link_attributes( array $atts, \WP_Post $item, \stdClass $args ): array {
	if ( isset( $args->theme_location ) && 'primary-menu' === $args->theme_location ) {
		$atts['class'] = isset( $atts['class'] ) ? $atts['class'] . ' nav-link' : 'nav-link';
		if (
			in_array( 'current-menu-item', $item->classes, true ) ||
			in_array( 'current_page_item', $item->classes, true ) ||
			xmastree_is_blog_menu_item_active( $item ) ||
			xmastree_is_events_menu_item_active( $item )
		) {
			$atts['class']       .= ' active';
			$atts['aria-current'] = 'page';
		}
	}
	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'xmastree_nav_menu_link_attributes', 10, 3 );

/**
 * Ensure SCF/ACF icon_picker always passes a compatible array with 'type' and 'value'
 * to format_value, preventing TypeErrors when a raw string is saved or passed.
 *
 * @param mixed $value Raw field value from the database.
 * @return mixed Normalized value.
 */
function xmastree_normalize_icon_picker_value( $value ) {
	if ( is_string( $value ) && '' !== $value ) {
		return array(
			'type'  => 'custom',
			'value' => $value,
		);
	}
	return $value;
}
add_filter( 'acf/load_value/type=icon_picker', 'xmastree_normalize_icon_picker_value', 5, 1 );

/**
 * SVG Upload Support
 */
function allow_svg_img( $svg_mime ) {
	$svg_mime['svg'] = 'image/svg+xml';
	return $svg_mime;
}
add_filter( 'upload_mimes', 'allow_svg_img' );

/**
 * Dynamic Year Shortcode [year]
 */
function year_shortcode() {
	return date_i18n( 'Y' );
}
add_shortcode( 'year', 'year_shortcode' );

/**
 * Bootstrap Icons support for ACF / SCF Icon Picker.
 */
require_once get_template_directory() . '/inc/acf-bootstrap-icons.php';
