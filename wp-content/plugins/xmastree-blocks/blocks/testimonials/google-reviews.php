<?php
/**
 * Google Places Reviews Fetcher & Cache Helper for Testimonials Block.
 *
 * Supports both Google Places API (New v1) and Legacy Places Details API
 * with transient caching and zero hardcoded static fallback data.
 *
 * @package XmasTree_Blocks
 * @since 1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'xmastree_testimonials_fetch_google_reviews' ) ) {
	/**
	 * Fetch and normalize reviews from Google Places API for a given Place ID.
	 *
	 * @param string               $place_id Google Place ID.
	 * @param string               $api_key  Google Cloud / Places API Key.
	 * @param array<string, mixed> $args     Optional arguments: min_rating, max_reviews, sort_by, cache_duration, force_refresh.
	 * @return array{reviews: array<int, array<string, mixed>>, error: string, source: string}
	 */
	function xmastree_testimonials_fetch_google_reviews( string $place_id, string $api_key, array $args = array() ): array {
		$place_id = trim( $place_id );
		$api_key  = trim( $api_key );

		if ( '' === $place_id || '' === $api_key ) {
			return array(
				'reviews' => array(),
				'error'   => '',
				'source'  => 'none',
			);
		}

		$min_rating     = isset( $args['min_rating'] ) ? max( 0.0, (float) $args['min_rating'] ) : 0.0;
		$max_reviews    = isset( $args['max_reviews'] ) ? max( 0, (int) $args['max_reviews'] ) : 0;
		$sort_by        = ( isset( $args['sort_by'] ) && 'newest' === $args['sort_by'] ) ? 'newest' : 'most_relevant';
		$cache_duration = isset( $args['cache_duration'] ) ? max( 300, (int) $args['cache_duration'] ) : 43200;
		$force_refresh  = ! empty( $args['force_refresh'] );

		/**
		 * Allow pre-filtering / mocking raw Google Reviews result before cache or remote HTTP calls.
		 *
		 * @param array|null           $pre      Normalized response array or null to proceed normally.
		 * @param string               $place_id Google Place ID.
		 * @param string               $api_key  Google Places API key.
		 * @param array<string, mixed> $args     Query options.
		 */
		$pre_result = apply_filters( 'xmastree_pre_fetch_google_reviews', null, $place_id, $api_key, $args );
		if ( is_array( $pre_result ) && isset( $pre_result['reviews'] ) ) {
			$filtered = xmastree_testimonials_filter_google_reviews(
				(array) $pre_result['reviews'],
				$min_rating,
				$max_reviews,
				$sort_by
			);
			return array(
				'reviews' => $filtered,
				'error'   => (string) ( $pre_result['error'] ?? '' ),
				'source'  => (string) ( $pre_result['source'] ?? 'filter' ),
			);
		}

		$transient_key = 'xmastree_gr_' . md5( $place_id . '|' . $sort_by . '|' . md5( $api_key ) );
		if ( ! $force_refresh ) {
			$cached = get_transient( $transient_key );
			if ( is_array( $cached ) && isset( $cached['reviews'] ) ) {
				$filtered = xmastree_testimonials_filter_google_reviews(
					(array) $cached['reviews'],
					$min_rating,
					$max_reviews,
					$sort_by
				);
				return array(
					'reviews' => $filtered,
					'error'   => (string) ( $cached['error'] ?? '' ),
					'source'  => 'cache',
				);
			}
		}

		// 1. Try Google Places API (New v1) first.
		$raw_reviews = array();
		$error_msg   = '';
		$source      = 'places_new';

		$new_api_url = 'https://places.googleapis.com/v1/places/' . rawurlencode( $place_id );
		$new_resp    = wp_remote_get(
			$new_api_url,
			array(
				'timeout' => 10,
				'headers' => array(
					'X-Goog-Api-Key'   => $api_key,
					'X-Goog-FieldMask' => 'displayName,rating,userRatingCount,reviews',
				),
			)
		);

		$need_legacy_fallback = false;
		if ( is_wp_error( $new_resp ) ) {
			$error_msg            = $new_resp->get_error_message();
			$need_legacy_fallback = true;
		} else {
			$code     = (int) wp_remote_retrieve_response_code( $new_resp );
			$body_raw = wp_remote_retrieve_body( $new_resp );
			$decoded  = json_decode( $body_raw, true );

			if ( 200 === $code && is_array( $decoded ) && isset( $decoded['reviews'] ) && is_array( $decoded['reviews'] ) ) {
				foreach ( $decoded['reviews'] as $rev ) {
					if ( ! is_array( $rev ) ) {
						continue;
					}
					$normalized = xmastree_testimonials_normalize_new_api_review( $rev );
					if ( null !== $normalized ) {
						$raw_reviews[] = $normalized;
					}
				}
			} elseif ( 200 === $code && is_array( $decoded ) && empty( $decoded['error'] ) ) {
				// Valid response with zero reviews; avoid unnecessary fallback unless empty.
				$raw_reviews = array();
			} else {
				if ( is_array( $decoded ) && ! empty( $decoded['error']['message'] ) ) {
					$error_msg = (string) $decoded['error']['message'];
				} else {
					$error_msg = sprintf( 'Google Places API (New) HTTP %d', $code );
				}
				$need_legacy_fallback = true;
			}
		}

		// 2. Automatically fall back to Legacy Google Places Details API if Places (New) isn't enabled on this key.
		if ( $need_legacy_fallback ) {
			$legacy_url = add_query_arg(
				array(
					'place_id'     => $place_id,
					'fields'       => 'name,rating,user_ratings_total,reviews',
					'reviews_sort' => $sort_by,
					'key'          => $api_key,
				),
				'https://maps.googleapis.com/maps/api/place/details/json'
			);

			$legacy_resp = wp_remote_get(
				$legacy_url,
				array(
					'timeout' => 10,
				)
			);

			if ( is_wp_error( $legacy_resp ) ) {
				if ( '' === $error_msg ) {
					$error_msg = $legacy_resp->get_error_message();
				}
				$source = 'error';
			} else {
				$legacy_code = (int) wp_remote_retrieve_response_code( $legacy_resp );
				$legacy_body = json_decode( wp_remote_retrieve_body( $legacy_resp ), true );

				if ( 200 === $legacy_code && is_array( $legacy_body ) && isset( $legacy_body['status'] ) ) {
					$status = (string) $legacy_body['status'];
					if ( 'OK' === $status ) {
						$error_msg = '';
						$source    = 'places_legacy';
						$reviews   = $legacy_body['result']['reviews'] ?? array();
						if ( is_array( $reviews ) ) {
							foreach ( $reviews as $rev ) {
								if ( ! is_array( $rev ) ) {
									continue;
								}
								$normalized = xmastree_testimonials_normalize_legacy_api_review( $rev );
								if ( null !== $normalized ) {
									$raw_reviews[] = $normalized;
								}
							}
						}
					} elseif ( 'ZERO_RESULTS' === $status ) {
						$error_msg   = '';
						$source      = 'places_legacy';
						$raw_reviews = array();
					} else {
						$legacy_err = ! empty( $legacy_body['error_message'] ) ? (string) $legacy_body['error_message'] : $status;
						$error_msg  = $legacy_err;
						$source     = 'error';
					}
				} else {
					$source = 'error';
				}
			}
		}

		// Cache results: full duration on success, short 5-minute backoff on API errors to prevent hammering quotas.
		$payload = array(
			'reviews' => $raw_reviews,
			'error'   => $error_msg,
			'source'  => $source,
		);
		$ttl     = ( 'error' === $source && '' !== $error_msg ) ? 300 : $cache_duration;
		set_transient( $transient_key, $payload, $ttl );

		return array(
			'reviews' => xmastree_testimonials_filter_google_reviews( $raw_reviews, $min_rating, $max_reviews, $sort_by ),
			'error'   => $error_msg,
			'source'  => $source,
		);
	}
}

if ( ! function_exists( 'xmastree_testimonials_normalize_new_api_review' ) ) {
	/**
	 * Normalize a review entry from Google Places API (New v1).
	 *
	 * @param array<string, mixed> $rev Raw review array from v1 response.
	 * @return array<string, mixed>|null
	 */
	function xmastree_testimonials_normalize_new_api_review( array $rev ): ?array {
		$author      = $rev['authorAttribution'] ?? array();
		$author_name = trim( (string) ( $author['displayName'] ?? '' ) );
		$avatar_url  = trim( (string) ( $author['photoUri'] ?? '' ) );
		if ( '' !== $avatar_url && str_starts_with( $avatar_url, '//' ) ) {
			$avatar_url = 'https:' . $avatar_url;
		}

		$rel_time    = trim( (string) ( $rev['relativePublishTimeDescription'] ?? '' ) );
		$rating_raw  = isset( $rev['rating'] ) && is_numeric( $rev['rating'] ) ? (float) $rev['rating'] : 0.0;
		$rating_val  = $rating_raw > 0 ? round( min( 5.0, max( 0.0, $rating_raw ) ) * 2 ) / 2 : 0.0;
		$text        = trim( (string) ( $rev['text']['text'] ?? ( $rev['originalText']['text'] ?? '' ) ) );
		$publish_ts  = ! empty( $rev['publishTime'] ) ? (int) strtotime( (string) $rev['publishTime'] ) : 0;

		if ( '' === $author_name && '' === $text && $rating_val <= 0 ) {
			return null;
		}

		return array(
			'avatar_url'      => $avatar_url,
			'avatar_alt'      => $author_name,
			'author_name'     => $author_name,
			'author_location' => $rel_time,
			'rating'          => $rating_val,
			'quote_title'     => '',
			'quote_content'   => $text,
			'timestamp'       => $publish_ts,
			'is_external'     => true,
		);
	}
}

if ( ! function_exists( 'xmastree_testimonials_normalize_legacy_api_review' ) ) {
	/**
	 * Normalize a review entry from Legacy Google Places Details API.
	 *
	 * @param array<string, mixed> $rev Raw review array from legacy JSON response.
	 * @return array<string, mixed>|null
	 */
	function xmastree_testimonials_normalize_legacy_api_review( array $rev ): ?array {
		$author_name = trim( (string) ( $rev['author_name'] ?? '' ) );
		$avatar_url  = trim( (string) ( $rev['profile_photo_url'] ?? '' ) );
		if ( '' !== $avatar_url && str_starts_with( $avatar_url, '//' ) ) {
			$avatar_url = 'https:' . $avatar_url;
		}

		$rel_time   = trim( (string) ( $rev['relative_time_description'] ?? '' ) );
		$rating_raw = isset( $rev['rating'] ) && is_numeric( $rev['rating'] ) ? (float) $rev['rating'] : 0.0;
		$rating_val = $rating_raw > 0 ? round( min( 5.0, max( 0.0, $rating_raw ) ) * 2 ) / 2 : 0.0;
		$text       = trim( (string) ( $rev['text'] ?? '' ) );
		$timestamp  = isset( $rev['time'] ) && is_numeric( $rev['time'] ) ? (int) $rev['time'] : 0;

		if ( '' === $author_name && '' === $text && $rating_val <= 0 ) {
			return null;
		}

		return array(
			'avatar_url'      => $avatar_url,
			'avatar_alt'      => $author_name,
			'author_name'     => $author_name,
			'author_location' => $rel_time,
			'rating'          => $rating_val,
			'quote_title'     => '',
			'quote_content'   => $text,
			'timestamp'       => $timestamp,
			'is_external'     => true,
		);
	}
}

if ( ! function_exists( 'xmastree_testimonials_filter_google_reviews' ) ) {
	/**
	 * Apply sorting, minimum rating filter, and max review count limit.
	 *
	 * @param array<int, array<string, mixed>> $reviews     Normalized review items.
	 * @param float                            $min_rating  Minimum star rating (0 = all).
	 * @param int                              $max_reviews Maximum items to return (0 = all).
	 * @param string                           $sort_by     'most_relevant' or 'newest'.
	 * @return array<int, array<string, mixed>>
	 */
	function xmastree_testimonials_filter_google_reviews( array $reviews, float $min_rating = 0.0, int $max_reviews = 0, string $sort_by = 'most_relevant' ): array {
		if ( 'newest' === $sort_by && count( $reviews ) > 1 ) {
			usort(
				$reviews,
				static function ( array $a, array $b ): int {
					return (int) ( $b['timestamp'] ?? 0 ) <=> (int) ( $a['timestamp'] ?? 0 );
				}
			);
		}

		if ( $min_rating > 0 ) {
			$reviews = array_values(
				array_filter(
					$reviews,
					static function ( array $item ) use ( $min_rating ): bool {
						return (float) ( $item['rating'] ?? 0 ) >= $min_rating;
					}
				)
			);
		}

		if ( $max_reviews > 0 && count( $reviews ) > $max_reviews ) {
			$reviews = array_slice( $reviews, 0, $max_reviews );
		}

		return array_values( $reviews );
	}
}

