<?php
/**
 * Request Utility Class
 *
 * Provides utility functions for identifying and handling various types of
 * requests in a WordPress environment, including request type detection,
 * device detection, and request variable handling.
 *
 * @package ArrayPress\RequestUtils
 * @since   1.0.0
 * @author  ArrayPress
 * @license GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace ArrayPress\RequestUtils;

use WP;

/**
 * Request Class
 *
 * Core operations for working with WordPress requests.
 */
class Request {

	/**
	 * What type of request is this?
	 *
	 * @param string|array $type admin, ajax, cron, frontend, json, api, rest, cli, editor.
	 *
	 * @return bool
	 */
	public static function is( $type ): bool {
		if ( is_string( $type ) ) {
			return self::is_type( $type );
		}

		if ( is_array( $type ) ) {
			foreach ( $type as $t ) {
				if ( self::is_type( $t ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Returns true if the request is a frontend request.
	 *
	 * @return bool
	 */
	public static function is_frontend(): bool {
		return ! self::is( [ 'admin', 'ajax', 'cron', 'rest', 'api', 'cli' ] );
	}

	/**
	 * Returns true if the request is an admin request.
	 *
	 * @return bool
	 */
	public static function is_admin(): bool {
		return is_admin();
	}

	/**
	 * Returns true if the request is an AJAX request.
	 *
	 * @return bool
	 */
	public static function is_ajax(): bool {
		return wp_doing_ajax();
	}

	/**
	 * Returns true if the request is a cron request.
	 *
	 * @return bool
	 */
	public static function is_cron(): bool {
		return wp_doing_cron();
	}

	/**
	 * Returns true if the request is a REST API request.
	 *
	 * @return bool
	 */
	public static function is_rest(): bool {
		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}

	/**
	 * Returns true if the request is a JSON request.
	 *
	 * @return bool
	 */
	public static function is_json(): bool {
		return wp_is_json_request();
	}

	/**
	 * Returns true if the request is a CLI request.
	 *
	 * @return bool
	 */
	public static function is_cli(): bool {
		return ( php_sapi_name() === 'cli' || defined( 'STDIN' ) );
	}

	/**
	 * Determines if the current request is related to the block editor.
	 *
	 * @return bool True if the request is related to the block editor, false otherwise.
	 */
	public static function is_editor(): bool {
		if ( is_admin() ) {
			return true;
		}

		if ( ! defined( 'REST_REQUEST' ) || ! is_user_logged_in() ) {
			return false;
		}

		global $wp;

		if ( ! $wp instanceof WP || empty( $wp->query_vars['rest_route'] ) ) {
			return false;
		}

		$route = $wp->query_vars['rest_route'];

		return str_contains( $route, '/block-renderer/' );
	}

	/**
	 * Checks if the current request is from a mobile device.
	 *
	 * @return bool True if the request is from a mobile device, false otherwise.
	 */
	public static function is_mobile(): bool {
		return wp_is_mobile();
	}

	/**
	 * Checks if the current request is from a desktop device.
	 *
	 * @return bool True if the request is from a desktop device, false otherwise.
	 */
	public static function is_desktop(): bool {
		return ! wp_is_mobile();
	}

	/**
	 * Checks if the current connection is secure (SSL).
	 *
	 * @return bool True if the connection is secure (SSL), false otherwise.
	 */
	public static function is_ssl(): bool {
		// Check WordPress core function first
		if ( is_ssl() ) {
			return true;
		}

		// Check WordPress HTTPS plugin if available
		global $wordpress_https;
		if ( class_exists( 'WordPressHTTPS' ) && isset( $wordpress_https ) ) {
			if ( method_exists( $wordpress_https, 'is_ssl' ) ) {
				return $wordpress_https->is_ssl();
			} elseif ( method_exists( $wordpress_https, 'isSsl' ) ) {
				return $wordpress_https->isSsl();
			}
		}

		// Check Cloudflare SSL indicator
		if ( isset( $_SERVER['HTTP_CF_VISITOR'] ) ) {
			$cf_visitor = json_decode( $_SERVER['HTTP_CF_VISITOR'] );
			if ( isset( $cf_visitor->scheme ) && $cf_visitor->scheme === 'https' ) {
				return true;
			}
		}

		return false;
	}

	// ========================================
	// Request Data Methods
	// ========================================

	/**
	 * Returns the sanitized version of the $_REQUEST superglobal array.
	 *
	 * @param bool $refresh Whether to refresh the cache.
	 *
	 * @return array The sanitized version of the $_REQUEST superglobal.
	 */
	public static function get_request_vars( bool $refresh = false ): array {
		static $cache = null;

		if ( null !== $cache && ! $refresh ) {
			return $cache;
		}

		$cache = [];
		foreach ( $_REQUEST as $key => $value ) {
			$sanitized_key = sanitize_key( $key );
			if ( is_array( $value ) ) {
				$cache[ $sanitized_key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$cache[ $sanitized_key ] = sanitize_text_field( $value );
			}
		}

		return $cache;
	}

	/**
	 * Returns the sanitized version of the $_POST superglobal array.
	 *
	 * @param bool $refresh Whether to refresh the cache.
	 *
	 * @return array The sanitized version of the $_POST superglobal.
	 */
	public static function get_post_vars( bool $refresh = false ): array {
		static $cache = null;

		if ( null !== $cache && ! $refresh ) {
			return $cache;
		}

		$cache = [];
		foreach ( $_POST as $key => $value ) {
			$sanitized_key = sanitize_key( $key );
			if ( is_array( $value ) ) {
				$cache[ $sanitized_key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$cache[ $sanitized_key ] = sanitize_text_field( $value );
			}
		}

		return $cache;
	}

	/**
	 * Returns the sanitized version of the $_GET superglobal array.
	 *
	 * @param bool $refresh Whether to refresh the cache.
	 *
	 * @return array The sanitized version of the $_GET superglobal.
	 */
	public static function get_get_vars( bool $refresh = false ): array {
		static $cache = null;

		if ( null !== $cache && ! $refresh ) {
			return $cache;
		}

		$cache = [];
		foreach ( $_GET as $key => $value ) {
			$sanitized_key = sanitize_key( $key );
			if ( is_array( $value ) ) {
				$cache[ $sanitized_key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$cache[ $sanitized_key ] = sanitize_text_field( $value );
			}
		}

		return $cache;
	}

	/**
	 * Get the current page number from a specified parameter.
	 *
	 * Retrieves and sanitizes the specified parameter from $_GET, defaulting to 1 if not set or invalid.
	 * This is primarily used for pagination in admin tables.
	 *
	 * @param string $param The URL parameter name to check for page number. Default 'paged'.
	 *
	 * @return int The current page number, minimum value of 1.
	 */
	public static function get_current_page( string $param = 'paged' ): int {
		return isset( $_GET[ $param ] ) ? max( 1, absint( $_GET[ $param ] ) ) : 1;
	}

	/**
	 * Get a sanitized request variable.
	 *
	 * @param string $key     The variable key.
	 * @param mixed  $default Default value if not found.
	 * @param string $method  Request method ('get', 'post', 'request'). Default 'request'.
	 *
	 * @return mixed The sanitized value or default.
	 */
	public static function get_var( string $key, $default = null, string $method = 'request' ) {
		switch ( strtolower( $method ) ) {
			case 'get':
				$vars = self::get_get_vars();
				break;
			case 'post':
				$vars = self::get_post_vars();
				break;
			default:
				$vars = self::get_request_vars();
				break;
		}

		return $vars[ sanitize_key( $key ) ] ?? $default;
	}

	/**
	 * Check if a request variable exists.
	 *
	 * @param string $key    The variable key.
	 * @param string $method Request method ('get', 'post', 'request'). Default 'request'.
	 *
	 * @return bool True if the variable exists.
	 */
	public static function has_var( string $key, string $method = 'request' ): bool {
		switch ( strtolower( $method ) ) {
			case 'get':
				$vars = self::get_get_vars();
				break;
			case 'post':
				$vars = self::get_post_vars();
				break;
			default:
				$vars = self::get_request_vars();
				break;
		}

		return isset( $vars[ sanitize_key( $key ) ] );
	}

	// ========================================
	// Private Helper Methods
	// ========================================

	/**
	 * Check if the request is of a certain type.
	 *
	 * @param string $type admin, ajax, cron, frontend, json, api, rest, cli, editor.
	 *
	 * @return bool
	 */
	private static function is_type( string $type ): bool {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return self::is_ajax();
			case 'cron':
				return self::is_cron();
			case 'rest':
				return self::is_rest();
			case 'frontend':
				return self::is_frontend();
			case 'json':
				return wp_is_json_request();
			case 'editor':
				return self::is_editor();
			case 'cli':
				return self::is_cli();
			default:
				return false;
		}
	}

	/**
	 * Get the current REST route.
	 *
	 * @return string|null The current REST route or null if not available.
	 */
	private static function get_current_rest_route(): ?string {
		global $wp;
		if ( $wp instanceof WP && ! empty( $wp->query_vars['rest_route'] ) ) {
			return $wp->query_vars['rest_route'];
		}

		return null;
	}

}