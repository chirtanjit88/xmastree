<?php
/**
 * The template for displaying comments on single posts.
 *
 * Matches the layout and visual system from html/blog-details.html
 * (.blog-comments-container, .comment-list, .comment-item, .blog-comment-form-wrap)
 * using native WordPress comment rendering and form submission.
 *
 * @package XmasTree_Theme
 * @since 1.0.15
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( post_password_required() ) {
	return;
}

if ( ! have_comments() && ! comments_open() ) {
	return;
}

$comments_count = (int) get_comments_number();
?>

<div id="comments" class="blog-comments-container">
	<?php if ( have_comments() ) : ?>
		<h3 class="blog-comments-title">
			<?php
			printf(
				/* translators: %d: number of approved comments */
				esc_html( _n( 'Comment (%d)', 'Comments (%d)', $comments_count, 'xmastree-theme' ) ),
				$comments_count // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			);
			?>
		</h3>

		<div class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'div',
					'short_ping'  => true,
					'avatar_size' => 50,
					'callback'    => 'xmastree_comment_callback',
				)
			);
			?>
		</div>

		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php if ( ! comments_open() && $comments_count > 0 && post_type_supports( get_post_type(), 'comments' ) ) : ?>
		<p class="no-comments text-muted mt-3"><?php esc_html_e( 'Comments are closed for this article.', 'xmastree-theme' ); ?></p>
	<?php endif; ?>

	<?php if ( comments_open() ) : ?>
		<?php
		$commenter     = wp_get_current_commenter();
		$req           = (bool) get_option( 'require_name_email' );
		$aria_req      = $req ? ' required aria-required="true"' : '';
		$req_star      = $req ? ' *' : '';
		$user          = wp_get_current_user();
		$user_identity = $user->exists() ? $user->display_name : '';
		$post_id_curr  = get_the_ID();
		$permalink_cur = get_permalink( $post_id_curr );

		$logged_in_markup = sprintf(
			'<div class="col-12"><p class="logged-in-as">%1$s %2$s</p></div>',
			sprintf(
				/* translators: 1: User profile edit URL, 2: User display name, 3: Logout URL */
				wp_kses_post( __( 'Logged in as %2$s. <a href="%1$s">Edit your profile</a>. <a href="%3$s">Log out?</a>', 'xmastree-theme' ) ),
				esc_url( get_edit_user_link() ),
				esc_html( $user_identity ),
				esc_url( wp_logout_url( apply_filters( 'the_permalink', (string) $permalink_cur, $post_id_curr ) ) )
			),
			'<span class="required-field-message">' . esc_html__( 'Required fields are marked *', 'xmastree-theme' ) . '</span>'
		);

		$must_log_in_markup = sprintf(
			'<div class="col-12"><p class="must-log-in">%s</p></div>',
			sprintf(
				/* translators: %s: Login URL */
				wp_kses_post( __( 'You must be <a href="%s">logged in</a> to post a comment.', 'xmastree-theme' ) ),
				esc_url( wp_login_url( apply_filters( 'the_permalink', (string) $permalink_cur, $post_id_curr ) ) )
			)
		);

		$comment_fields = array(
			'author' => sprintf(
				'<div class="col-md-6"><label class="visually-hidden" for="author">%1$s</label><input id="author" name="author" type="text" class="form-control" value="%2$s" placeholder="%1$s" maxlength="245" autocomplete="name"%3$s></div>',
				esc_attr__( 'Your Name', 'xmastree-theme' ) . $req_star,
				esc_attr( (string) ( $commenter['comment_author'] ?? '' ) ),
				$aria_req
			),
			'email'  => sprintf(
				'<div class="col-md-6"><label class="visually-hidden" for="email">%1$s</label><input id="email" name="email" type="email" class="form-control" value="%2$s" placeholder="%1$s" maxlength="100" autocomplete="email"%3$s></div>',
				esc_attr__( 'Your Email Address', 'xmastree-theme' ) . $req_star,
				esc_attr( (string) ( $commenter['comment_author_email'] ?? '' ) ),
				$aria_req
			),
			'url'    => sprintf(
				'<div class="col-12"><label class="visually-hidden" for="url">%1$s</label><input id="url" name="url" type="url" class="form-control" value="%2$s" placeholder="%1$s" maxlength="200" autocomplete="url"></div>',
				esc_attr__( 'Website (Optional)', 'xmastree-theme' ),
				esc_attr( (string) ( $commenter['comment_author_url'] ?? '' ) )
			),
		);

		$cookies_consent = ! empty( $commenter['comment_author_email'] ) ? ' checked="checked"' : '';
		if ( has_action( 'set_comment_cookies', 'wp_set_comment_cookies' ) && get_option( 'show_comments_cookies_opt_in' ) ) {
			$comment_fields['cookies'] = sprintf(
				'<div class="col-12"><div class="comment-form-cookies-consent form-check"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" class="form-check-input" value="yes"%1$s><label class="form-check-label" for="wp-comment-cookies-consent">%2$s</label></div></div>',
				$cookies_consent,
				esc_html__( 'Save my name, email, and website in this browser for the next time I comment.', 'xmastree-theme' )
			);
		}
		?>
		<div class="blog-comment-form-wrap" data-aos="fade-up">
			<?php
			$xmastree_reorder_comment_fields = static function ( array $fields ): array {
				$ordered = array();
				foreach ( array( 'author', 'email', 'url', 'comment', 'cookies' ) as $key ) {
					if ( isset( $fields[ $key ] ) ) {
						$ordered[ $key ] = $fields[ $key ];
						unset( $fields[ $key ] );
					}
				}
				return $ordered + $fields;
			};

			add_filter( 'comment_form_fields', $xmastree_reorder_comment_fields, 20 );
			comment_form(
				array(
					'id_form'              => 'commentform',
					'class_form'           => 'comment-form row g-3',
					'title_reply'          => __( 'Leave a Comment', 'xmastree-theme' ),
					'title_reply_to'       => __( 'Leave a Reply to %s', 'xmastree-theme' ),
					'title_reply_before'   => '<h4 id="reply-title" class="comment-reply-title">',
					'title_reply_after'    => '</h4>',
					'cancel_reply_before'  => ' <small class="cancel-reply-wrap">',
					'cancel_reply_after'   => '</small>',
					'cancel_reply_link'    => __( 'Cancel reply', 'xmastree-theme' ),
					'must_log_in'          => $must_log_in_markup,
					'logged_in_as'         => $logged_in_markup,
					'comment_notes_before' => '<div class="col-12"><p class="comment-notes">' . esc_html__( 'Your email address will not be published. Required fields are marked *', 'xmastree-theme' ) . '</p></div>',
					'fields'               => $comment_fields,
					'comment_field'        => sprintf(
						'<div class="col-12"><label class="visually-hidden" for="comment">%1$s</label><textarea id="comment" name="comment" class="form-control" rows="5" placeholder="%1$s" maxlength="65525" required aria-required="true"></textarea></div>',
						esc_attr__( 'Write your comment here...', 'xmastree-theme' )
					),
					'class_container'      => 'comment-respond',
					'class_submit'         => 'blog-btn-crimson',
					'label_submit'         => __( 'Post Comment', 'xmastree-theme' ),
					'submit_field'         => '<div class="col-12 form-submit">%1$s %2$s</div>',
					'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s <i class="bi bi-arrow-right" aria-hidden="true"></i></button>',
					'format'               => 'html5',
				)
			);
			remove_filter( 'comment_form_fields', $xmastree_reorder_comment_fields, 20 );
			?>
		</div>
	<?php endif; ?>
</div>
