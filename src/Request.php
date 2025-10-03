<?php
/**
 * Request Utility Class
 *
 * Provides utility functions for identifying and handling various types of
 * requests in a WordPress environment, including request type detection,
 * header handling, and request variable management.
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

	// ========================================
	// Request Type Detection
	// ========================================

	/**
	 * What type of request is this?
	 *
	 * @param string|array $type admin, ajax, cron, frontend, json, rest, cli, editor.
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
	 * Check if the request is an API request (REST or custom).
	 *
	 * @return bool True if API request.
	 */
	public static function is_api(): bool {
		// Check REST API
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// Check for API headers
		$api_headers = [
			'x-api-key',
			'authorization',
			'x-auth-token',
			'x-access-token'
		];

		foreach ( $api_headers as $header ) {
			if ( self::has_header( $header ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if the current connection is secure (SSL) with CloudFlare support.
	 *
	 * @return bool True if the connection is secure (SSL), false otherwise.
	 */
	public static function is_ssl(): bool {
		// Check WordPress core function first
		if ( is_ssl() ) {
			return true;
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

	/**
	 * Check if request is behind CloudFlare.
	 *
	 * @return bool True if behind CloudFlare.
	 */
	public static function is_cloudflare(): bool {
		return self::has_header( 'cf-ray' ) || self::has_header( 'cf-connecting-ip' );
	}

	// ========================================
	// HTTP Method Handling
	// ========================================

	/**
	 * Get the request method (GET, POST, PUT, DELETE, etc.).
	 *
	 * @return string Request method in uppercase.
	 */
	public static function get_method(): string {
		return strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' );
	}

	/**
	 * Check if request method matches.
	 *
	 * @param string|array $method Method(s) to check.
	 *
	 * @return bool True if method matches.
	 */
	public static function is_method( $method ): bool {
		$current = self::get_method();

		if ( is_string( $method ) ) {
			return $current === strtoupper( $method );
		}

		if ( is_array( $method ) ) {
			return in_array( $current, array_map( 'strtoupper', $method ), true );
		}

		return false;
	}

	// ========================================
	// Header Management
	// ========================================

	/**
	 * Get a specific HTTP header value.
	 *
	 * @param string $header  The header name (case-insensitive).
	 * @param mixed  $default Default value if header not found.
	 *
	 * @return mixed The header value or default.
	 */
	public static function get_header( string $header, $default = null ) {
		$header = strtolower( str_replace( '-', '_', $header ) );

		// Check with HTTP_ prefix in $_SERVER
		$server_key = 'HTTP_' . strtoupper( $header );
		if ( isset( $_SERVER[ $server_key ] ) ) {
			return sanitize_text_field( $_SERVER[ $server_key ] );
		}

		// Check without prefix for some headers
		$special_headers = [
			'content_type'   => 'CONTENT_TYPE',
			'content_length' => 'CONTENT_LENGTH',
		];

		if ( isset( $special_headers[ $header ] ) && isset( $_SERVER[ $special_headers[ $header ] ] ) ) {
			return sanitize_text_field( $_SERVER[ $special_headers[ $header ] ] );
		}

		// Use getallheaders if available
		if ( function_exists( 'getallheaders' ) ) {
			$all_headers      = getallheaders();
			$header_formatted = str_replace( '_', '-', $header );
			foreach ( $all_headers as $key => $value ) {
				if ( strtolower( $key ) === $header_formatted || strtolower( $key ) === $header ) {
					return sanitize_text_field( $value );
				}
			}
		}

		return $default;
	}

	/**
	 * Check if a header exists.
	 *
	 * @param string $header The header name (case-insensitive).
	 *
	 * @return bool True if header exists.
	 */
	public static function has_header( string $header ): bool {
		return self::get_header( $header ) !== null;
	}

	/**
	 * Get the real client IP address (considering proxies and CloudFlare).
	 *
	 * @return string Client IP address.
	 */
	public static function get_client_ip(): string {
		// CloudFlare
		if ( self::is_cloudflare() && self::has_header( 'cf-connecting-ip' ) ) {
			return self::get_header( 'cf-connecting-ip' );
		}

		// Standard proxy headers (in order of preference)
		$proxy_headers = [
			'x-real-ip',
			'x-forwarded-for',
			'client-ip',
			'x-client-ip',
			'x-cluster-client-ip'
		];

		foreach ( $proxy_headers as $header ) {
			$ip = self::get_header( $header );
			if ( $ip ) {
				// Handle comma-separated IPs (X-Forwarded-For)
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip  = trim( $ips[0] );
				}

				// Validate IP
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					return $ip;
				}
			}
		}

		// Fallback to REMOTE_ADDR
		return $_SERVER['REMOTE_ADDR'] ?? '';
	}

	// ========================================
	// Request Data Methods
	// ========================================

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
				$source = $_GET;
				break;
			case 'post':
				$source = $_POST;
				break;
			default:
				$source = $_REQUEST;
				break;
		}

		$sanitized_key = sanitize_key( $key );

		if ( ! isset( $source[ $sanitized_key ] ) ) {
			return $default;
		}

		$value = $source[ $sanitized_key ];

		if ( is_array( $value ) ) {
			return array_map( 'sanitize_text_field', $value );
		}

		return sanitize_text_field( $value );
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
				$source = $_GET;
				break;
			case 'post':
				$source = $_POST;
				break;
			default:
				$source = $_REQUEST;
				break;
		}

		return isset( $source[ sanitize_key( $key ) ] );
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
				return wp_doing_ajax();
			case 'cron':
				return wp_doing_cron();
			case 'rest':
				return defined( 'REST_REQUEST' ) && REST_REQUEST;
			case 'frontend':
				return self::is_frontend();
			case 'json':
				return wp_is_json_request();
			case 'editor':
				return self::is_editor();
			case 'cli':
				return self::is_cli();
			case 'api':
				return self::is_api();
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