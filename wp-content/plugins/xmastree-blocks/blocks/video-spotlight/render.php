<?php
/**
 * Block Template: Christmas Magic / Video Spotlight
 *
 * Renders the Ma's Christmas Corner featured story + video section from mas-corner.html
 * with an autoplaying video lightbox popup supporting uploaded MP4/WebM videos,
 * YouTube URLs/IDs/embeds, and Vimeo URLs/IDs/embeds.
 * Fully dynamic: powered by SCF/ACF Block Fields with zero hardcoded content or fallback media.
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
// 1. Block Attributes & Normalization Helpers
// -----------------------------------------------------------------------------
$block_id = 'video-spotlight-' . ( $block['id'] ?? uniqid() );
if ( ! empty( $block['anchor'] ) ) {
	$block_id = (string) $block['anchor'];
}

$normalize_select = static function ( $value ): string {
	if ( is_array( $value ) ) {
		if ( isset( $value['value'] ) ) {
			$value = $value['value'];
		} else {
			return '';
		}
	}

	if ( is_bool( $value ) || null === $value ) {
		return '';
	}

	return trim( (string) $value );
};

$extract_plain_text = static function ( string $html ): string {
	$with_breaks = str_ireplace( array( '<br>', '<br/>', '<br />' ), ' ', $html );
	$stripped    = wp_strip_all_tags( $with_breaks );
	$decoded     = html_entity_decode( $stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$cleaned     = str_replace( "\xc2\xa0", ' ', $decoded );
	$normalized  = preg_replace( '/\s+/u', ' ', $cleaned );

	return trim( is_string( $normalized ) ? $normalized : $cleaned );
};

$has_rich_content = static function ( string $html ) use ( $extract_plain_text ): bool {
	if ( '' !== $extract_plain_text( $html ) ) {
		return true;
	}

	return 1 === preg_match( '/<(?:img|svg|iframe|video|audio|source|picture)\b/i', $html );
};

$resolve_image = static function ( $img_field, string $default_alt = '' ): array {
	$url = '';
	$alt = $default_alt;

	if ( is_array( $img_field ) && ! empty( $img_field['url'] ) ) {
		$url = (string) $img_field['url'];
		if ( ! empty( $img_field['alt'] ) ) {
			$alt = (string) $img_field['alt'];
		} elseif ( ! empty( $img_field['title'] ) && '' === $alt ) {
			$alt = (string) $img_field['title'];
		}
	} elseif ( is_numeric( $img_field ) && (int) $img_field > 0 ) {
		$id  = (int) $img_field;
		$src = wp_get_attachment_image_url( $id, 'full' );
		if ( $src ) {
			$url      = (string) $src;
			$alt_meta = get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( ! empty( $alt_meta ) ) {
				$alt = (string) $alt_meta;
			} else {
				$att_title = get_the_title( $id );
				if ( ! empty( $att_title ) && '' === $alt ) {
					$alt = (string) $att_title;
				}
			}
		}
	} elseif ( is_string( $img_field ) && '' !== trim( $img_field ) ) {
		$url = trim( $img_field );
	}

	return array(
		'url' => $url,
		'alt' => $alt,
	);
};

$resolve_file = static function ( $file_field ): array {
	$url  = '';
	$mime = '';

	if ( is_array( $file_field ) && ! empty( $file_field['url'] ) ) {
		$url  = trim( (string) $file_field['url'] );
		$mime = ! empty( $file_field['mime_type'] ) ? trim( (string) $file_field['mime_type'] ) : '';
	} elseif ( is_numeric( $file_field ) && (int) $file_field > 0 ) {
		$id      = (int) $file_field;
		$att_url = wp_get_attachment_url( $id );
		if ( $att_url ) {
			$url  = trim( (string) $att_url );
			$mime = trim( (string) get_post_mime_type( $id ) );
		}
	} elseif ( is_string( $file_field ) && '' !== trim( $file_field ) ) {
		$url = trim( $file_field );
	}

	if ( '' !== $url && '' === $mime ) {
		$checked = wp_check_filetype( $url );
		if ( ! empty( $checked['type'] ) ) {
			$mime = (string) $checked['type'];
		}
	}

	return array(
		'url'  => $url,
		'mime' => '' !== $mime ? $mime : 'video/mp4',
	);
};

$extract_iframe_src = static function ( string $input ): string {
	$trimmed = trim( $input );
	if ( '' === $trimmed ) {
		return '';
	}

	if ( 1 === preg_match( '/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\']/i', $trimmed, $matches ) ) {
		return html_entity_decode( trim( $matches[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	return $trimmed;
};

$build_youtube_embed_url = static function ( string $raw ) use ( $extract_iframe_src ): string {
	$candidate = $extract_iframe_src( $raw );
	if ( '' === $candidate ) {
		return '';
	}

	if ( 1 === preg_match( '/^[A-Za-z0-9_-]{11}$/', $candidate ) ) {
		return 'https://www.youtube.com/embed/' . $candidate . '?autoplay=1&rel=0&playsinline=1&enablejsapi=1';
	}

	$video_id = '';
	$query    = array();

	if ( false !== stripos( $candidate, 'youtu.be/' ) || false !== stripos( $candidate, 'youtube.com' ) || false !== stripos( $candidate, 'youtube-nocookie.com' ) ) {
		if ( 1 !== preg_match( '~^(?:https?:)?//~i', $candidate ) ) {
			$candidate = 'https://' . ltrim( $candidate, '/' );
		}

		$parsed_url = wp_parse_url( $candidate );
		$path       = isset( $parsed_url['path'] ) ? (string) $parsed_url['path'] : '';

		if ( ! empty( $parsed_url['query'] ) ) {
			parse_str( (string) $parsed_url['query'], $query );
		}

		if ( isset( $query['v'] ) && 1 === preg_match( '/^[A-Za-z0-9_-]{11}$/', (string) $query['v'] ) ) {
			$video_id = (string) $query['v'];
			unset( $query['v'] );
		} elseif ( false !== stripos( (string) ( $parsed_url['host'] ?? '' ), 'youtu.be' ) && 1 === preg_match( '~^/([A-Za-z0-9_-]{11})~', $path, $m ) ) {
			$video_id = $m[1];
		} elseif ( 1 === preg_match( '~^/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})~i', $path, $m ) ) {
			$video_id = $m[1];
		}
	}

	if ( '' === $video_id ) {
		return '';
	}

	$embed_query = array();
	if ( ! empty( $query['list'] ) && is_string( $query['list'] ) ) {
		$embed_query['list'] = sanitize_text_field( $query['list'] );
	}
	if ( ! empty( $query['start'] ) && is_numeric( $query['start'] ) ) {
		$embed_query['start'] = (int) $query['start'];
	} elseif ( ! empty( $query['t'] ) && preg_match( '/^(\d+)/', (string) $query['t'], $tm ) ) {
		$embed_query['start'] = (int) $tm[1];
	}

	$embed_query['autoplay']    = '1';
	$embed_query['rel']         = '0';
	$embed_query['playsinline'] = '1';
	$embed_query['enablejsapi'] = '1';

	$host = ( false !== stripos( $candidate, 'youtube-nocookie.com' ) ) ? 'www.youtube-nocookie.com' : 'www.youtube.com';

	return 'https://' . $host . '/embed/' . $video_id . '?' . http_build_query( $embed_query, '', '&', PHP_QUERY_RFC3986 );
};

$build_vimeo_embed_url = static function ( string $raw ) use ( $extract_iframe_src ): string {
	$candidate = $extract_iframe_src( $raw );
	if ( '' === $candidate ) {
		return '';
	}

	if ( 1 === preg_match( '/^\d+$/', $candidate ) ) {
		return 'https://player.vimeo.com/video/' . $candidate . '?autoplay=1&playsinline=1';
	}

	if ( false === stripos( $candidate, 'vimeo.com' ) ) {
		return '';
	}

	if ( 1 !== preg_match( '~^(?:https?:)?//~i', $candidate ) ) {
		$candidate = 'https://' . ltrim( $candidate, '/' );
	}

	$parsed_url = wp_parse_url( $candidate );
	$path       = isset( $parsed_url['path'] ) ? (string) $parsed_url['path'] : '';
	$query      = array();

	if ( ! empty( $parsed_url['query'] ) ) {
		parse_str( (string) $parsed_url['query'], $query );
	}

	$video_id = '';
	$hash     = '';

	if ( 1 === preg_match( '~/(?:channels/[^/]+/|groups/[^/]+/videos/|video/)?(\d+)(?:/([a-f0-9]+))?(?:/|$)~i', $path, $m ) ) {
		$video_id = $m[1];
		if ( ! empty( $m[2] ) ) {
			$hash = $m[2];
		}
	}

	if ( '' === $video_id ) {
		return '';
	}

	$embed_query = array();
	if ( '' !== $hash ) {
		$embed_query['h'] = $hash;
	} elseif ( ! empty( $query['h'] ) && is_string( $query['h'] ) && 1 === preg_match( '/^[a-f0-9]+$/i', $query['h'] ) ) {
		$embed_query['h'] = $query['h'];
	}

	$embed_query['autoplay']    = '1';
	$embed_query['playsinline'] = '1';

	return 'https://player.vimeo.com/video/' . $video_id . '?' . http_build_query( $embed_query, '', '&', PHP_QUERY_RFC3986 );
};

// -----------------------------------------------------------------------------
// 2. Retrieve SCF / ACF Fields (Matching group_xmastree_video_spotlight)
// -----------------------------------------------------------------------------
$title        = function_exists( 'get_field' ) ? trim( (string) get_field( 'spotlight_title' ) ) : '';
$description  = function_exists( 'get_field' ) ? trim( (string) get_field( 'spotlight_description' ) ) : '';
$button       = function_exists( 'get_field' ) ? get_field( 'spotlight_button' ) : null;
$cover_image  = function_exists( 'get_field' ) ? get_field( 'spotlight_cover_image' ) : null;
$video_source = function_exists( 'get_field' ) ? $normalize_select( get_field( 'spotlight_video_source' ) ) : '';
$video_file   = function_exists( 'get_field' ) ? get_field( 'spotlight_video_file' ) : null;
$youtube_raw  = function_exists( 'get_field' ) ? trim( (string) get_field( 'spotlight_youtube_url' ) ) : '';
$vimeo_raw    = function_exists( 'get_field' ) ? trim( (string) get_field( 'spotlight_vimeo_url' ) ) : '';
$video_pos    = function_exists( 'get_field' ) ? $normalize_select( get_field( 'spotlight_video_position' ) ) : '';
$bg_color     = function_exists( 'get_field' ) ? $normalize_select( get_field( 'spotlight_background_color' ) ) : '';

// -----------------------------------------------------------------------------
// 3. Resolve Section Classes, Alignment, Optional Background & CTA Link
// -----------------------------------------------------------------------------
$class_names = array( 'mas-magic-section' );
if ( ! empty( $block['className'] ) ) {
	$class_names[] = (string) $block['className'];
}
if ( ! empty( $block['align'] ) ) {
	$class_names[] = 'align' . (string) $block['align'];
}

if ( 'left' !== $video_pos ) {
	$video_pos = 'right';
}

$style_attr   = '';
$sanitized_bg = '' !== $bg_color ? sanitize_hex_color( $bg_color ) : null;
if ( is_string( $sanitized_bg ) && '' !== $sanitized_bg ) {
	$style_attr = ' style="' . esc_attr( 'background-color: ' . strtoupper( $sanitized_bg ) . ';' ) . '"';
}

$btn_url    = '';
$btn_title  = '';
$btn_target = '';
if ( is_array( $button ) && ! empty( $button['url'] ) ) {
	$btn_url    = trim( (string) $button['url'] );
	$btn_title  = ! empty( $button['title'] ) ? trim( (string) $button['title'] ) : '';
	$btn_target = ! empty( $button['target'] ) ? trim( (string) $button['target'] ) : '';
} elseif ( is_string( $button ) && '' !== trim( $button ) ) {
	$btn_url = trim( $button );
}

// -----------------------------------------------------------------------------
// 4. Resolve Text Visibility, Cover Media & Popup Video Target
// -----------------------------------------------------------------------------
$title_plain = $extract_plain_text( $title );
$has_title   = '' !== $title_plain;
$has_desc    = $has_rich_content( $description );
$has_button  = '' !== $btn_url && '' !== $btn_title;

$resolved_cover = $resolve_image( $cover_image, $has_title ? $title_plain : '' );
$cover_url      = $resolved_cover['url'];
$cover_alt      = $resolved_cover['alt'];
$has_cover      = '' !== $cover_url;

$resolved_file = $resolve_file( $video_file );
$yt_embed_url  = $build_youtube_embed_url( $youtube_raw );
$vm_embed_url  = $build_vimeo_embed_url( $vimeo_raw );

$popup_type = '';
$popup_src  = '';
$popup_mime = '';

if ( 'upload' === $video_source ) {
	if ( '' !== $resolved_file['url'] ) {
		$popup_type = 'upload';
		$popup_src  = $resolved_file['url'];
		$popup_mime = $resolved_file['mime'];
	}
} elseif ( 'youtube' === $video_source ) {
	if ( '' !== $yt_embed_url ) {
		$popup_type = 'youtube';
		$popup_src  = $yt_embed_url;
	}
} elseif ( 'vimeo' === $video_source ) {
	if ( '' !== $vm_embed_url ) {
		$popup_type = 'vimeo';
		$popup_src  = $vm_embed_url;
	}
} else {
	// Source select not yet chosen: resolve whichever video input is populated.
	if ( '' !== $resolved_file['url'] ) {
		$popup_type = 'upload';
		$popup_src  = $resolved_file['url'];
		$popup_mime = $resolved_file['mime'];
	} elseif ( '' !== $yt_embed_url ) {
		$popup_type = 'youtube';
		$popup_src  = $yt_embed_url;
	} elseif ( '' !== $vm_embed_url ) {
		$popup_type = 'vimeo';
		$popup_src  = $vm_embed_url;
	}
}

$has_video       = '' !== $popup_type && '' !== $popup_src;
$has_media_col   = $has_cover || $has_video;
$has_text_col    = $has_title || $has_desc || $has_button;
$has_any_content = $has_text_col || $has_media_col;

if ( ! $has_any_content && ! $is_preview ) {
	return;
}

$formatted_title = '';
if ( $has_title ) {
	$has_explicit_br = 1 === preg_match( '/<br\b/i', $title );
	$formatted_title = $has_explicit_br ? wp_kses_post( $title ) : nl2br( wp_kses_post( $title ) );
}

// Column classes based on layout and active columns.
if ( $has_text_col && ! $has_media_col ) {
	$text_col_class = 'col-lg-10';
} elseif ( 'left' === $video_pos ) {
	$text_col_class = 'col-lg-6 ps-lg-5';
} else {
	$text_col_class = 'col-lg-6 mb-5 mb-lg-0 pe-lg-5';
}

if ( ! $has_text_col && $has_media_col ) {
	$media_col_class = 'col-lg-8 mx-auto text-center';
} elseif ( 'left' === $video_pos ) {
	$media_col_class = 'col-lg-6 mb-5 mb-lg-0 text-center text-lg-start';
} else {
	$media_col_class = 'col-lg-6 text-center text-lg-end';
}

// AOS attributes only on frontend so block remains visible inside the Gutenberg editor.
$aos_text  = '';
$aos_media = '';
if ( ! $is_preview ) {
	$aos_text  = 'left' === $video_pos
		? ' data-aos="fade-left" data-aos-duration="800"'
		: ' data-aos="fade-right" data-aos-duration="800"';
	$aos_media = 'left' === $video_pos
		? ' data-aos="fade-right" data-aos-duration="800"'
		: ' data-aos="fade-left" data-aos-duration="800"';
}

$render_text_col = static function () use ( $text_col_class, $aos_text, $has_title, $formatted_title, $has_desc, $description, $has_button, $btn_url, $btn_title, $btn_target ): void {
	?>
	<div class="<?php echo esc_attr( $text_col_class ); ?>"<?php echo $aos_text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php if ( $has_title ) : ?>
			<h2 class="mas-magic-title mb-4"><?php echo $formatted_title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
		<?php endif; ?>

		<?php if ( $has_desc ) : ?>
			<?php echo wp_kses_post( wpautop( $description ) ); ?>
		<?php endif; ?>

		<?php if ( $has_button ) : ?>
			<a href="<?php echo esc_url( $btn_url ); ?>"<?php echo ! empty( $btn_target ) ? ' target="' . esc_attr( $btn_target ) . '" rel="noopener noreferrer"' : ''; ?> class="mas-magic-btn">
				<?php echo esc_html( $btn_title ); ?> <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i>
			</a>
		<?php endif; ?>
	</div>
	<?php
};

$render_media_col = static function () use ( $media_col_class, $aos_media, $has_text_col, $video_pos, $has_cover, $cover_url, $cover_alt, $has_video, $popup_type, $popup_src, $popup_mime, $has_title, $title_plain, $is_preview ): void {
	$wrapper_class = 'mas-video-wrapper d-inline-block';
	if ( ! $has_text_col ) {
		$wrapper_class .= ' mx-auto';
	} elseif ( 'left' === $video_pos ) {
		$wrapper_class .= ' me-lg-auto';
	} else {
		$wrapper_class .= ' ms-lg-auto';
	}

	if ( $has_video && ! $is_preview ) {
		$wrapper_class .= ' mas-video-clickable';
	}

	$play_aria_label = $has_title
		? sprintf( __( 'Play video: %s', 'xmastree-blocks' ), $title_plain )
		: __( 'Play video', 'xmastree-blocks' );
	?>
	<div class="<?php echo esc_attr( $media_col_class ); ?>"<?php echo $aos_media; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="<?php echo esc_attr( $wrapper_class ); ?>">
			<?php if ( $has_cover ) : ?>
				<img src="<?php echo esc_url( $cover_url ); ?>" alt="<?php echo esc_attr( $cover_alt ); ?>">
			<?php elseif ( 'upload' === $popup_type && '' !== $popup_src ) : ?>
				<video src="<?php echo esc_url( $popup_src ); ?>#t=0.5" muted playsinline preload="metadata" class="mas-video-preview-poster" aria-hidden="true"></video>
			<?php endif; ?>

			<?php if ( $has_video || $is_preview ) : ?>
				<div class="mas-video-overlay">
					<?php if ( $has_video && ! $is_preview ) : ?>
						<button
							type="button"
							class="mas-play-btn"
							data-mas-video-trigger="1"
							data-video-type="<?php echo esc_attr( $popup_type ); ?>"
							data-video-src="<?php echo esc_url( $popup_src ); ?>"
							data-video-mime="<?php echo esc_attr( $popup_mime ); ?>"
							data-video-title="<?php echo esc_attr( $title_plain ); ?>"
							aria-label="<?php echo esc_attr( $play_aria_label ); ?>"
						>
							<span class="mas-play-icon" aria-hidden="true"></span>
						</button>
					<?php else : ?>
						<span class="mas-play-btn" aria-hidden="true">
							<span class="mas-play-icon"></span>
						</span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php
};
?>

<section id="<?php echo esc_attr( $block_id ); ?>" class="<?php echo esc_attr( implode( ' ', $class_names ) ); ?>"<?php echo $style_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<div class="container">
		<?php if ( $has_any_content ) : ?>
			<div class="row align-items-center">
				<?php
				if ( 'left' === $video_pos ) {
					if ( $has_media_col ) {
						$render_media_col();
					}
					if ( $has_text_col ) {
						$render_text_col();
					}
				} else {
					if ( $has_text_col ) {
						$render_text_col();
					}
					if ( $has_media_col ) {
						$render_media_col();
					}
				}
				?>
			</div>
		<?php elseif ( $is_preview ) : ?>
			<div class="text-center p-5 border border-dashed text-muted bg-white bg-opacity-75 rounded">
				<span><?php esc_html_e( 'Video Spotlight Block — Click here to add heading, description, button, video cover, and upload/YouTube/Vimeo video in the sidebar.', 'xmastree-blocks' ); ?></span>
			</div>
		<?php endif; ?>
	</div>
</section>
