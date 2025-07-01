# WordPress Request Utils - Lean Request Detection & Handling

A lightweight WordPress library for request type detection, device identification, and safe request variable handling. Perfect for conditional logic, security, and responsive functionality.

## Features

* 🎯 **Clean API**: WordPress-style snake_case methods with consistent interfaces
* 🔍 **Request Detection**: Identify admin, AJAX, REST, cron, frontend, and more
* 📱 **Device Detection**: Mobile, desktop, and SSL detection
* 🛡️ **Safe Variables**: Sanitized access to $_GET, $_POST, and $_REQUEST data
* 🎨 **Editor Support**: Block editor and Gutenberg request detection
* 🔄 **Cached Results**: Efficient variable sanitization with caching
* ⚡ **WordPress Native**: Uses core WordPress functions when available

## Requirements

* PHP 7.4 or later
* WordPress 5.0 or later

## Installation

```bash
composer require arraypress/wp-request-utils
```

## Basic Usage

### Request Type Detection

```php
use ArrayPress\RequestUtils\Request;

// Check single request type
if ( Request::is_admin() ) {
	// Running in WordPress admin
}

if ( Request::is_ajax() ) {
	// Handle AJAX request
}

if ( Request::is_rest() ) {
	// REST API request
}

// Check multiple types at once
if ( Request::is( [ 'admin', 'ajax' ] ) ) {
	// Either admin OR ajax request
}

// Check all available types
$types = [ 'admin', 'ajax', 'cron', 'frontend', 'json', 'rest', 'cli', 'editor' ];
```

### Device & Environment Detection

```php
// Device detection
if ( Request::is_mobile() ) {
	// Mobile device (uses wp_is_mobile())
}

if ( Request::is_desktop() ) {
	// Desktop device
}

// Security detection
if ( Request::is_ssl() ) {
	// Secure HTTPS connection
}

// Special context detection
if ( Request::is_editor() ) {
	// Block/Gutenberg editor context
}
```

### Safe Request Variables

```php
// Get sanitized request variables
$search  = Request::get_var( 'search', '' );
$page    = Request::get_var( 'page', 1 );
$filters = Request::get_var( 'filters', [] );

// Specify request method
$post_data = Request::get_var( 'data', null, 'post' );
$get_param = Request::get_var( 'param', 'default', 'get' );

// Check if variable exists
if ( Request::has_var( 'action' ) ) {
	$action = Request::get_var( 'action' );
}

// Get all sanitized variables
$all_get     = Request::get_get_vars();
$all_post    = Request::get_post_vars();
$all_request = Request::get_request_vars();
```

### Pagination Helper

```php
// Get current page for pagination
$current_page = Request::get_current_page(); // Default parameter: 'paged'
$custom_page  = Request::get_current_page( 'custom_page' );

// Use in WP_Query
$query = new WP_Query( [
	'paged'          => Request::get_current_page(),
	'posts_per_page' => 10
] );
```

## Common Use Cases

### Conditional Asset Loading

```php
function enqueue_conditional_assets() {
	if ( Request::is_admin() ) {
		wp_enqueue_script( 'admin-script', 'admin.js' );
	}

	if ( Request::is_mobile() ) {
		wp_enqueue_style( 'mobile-styles', 'mobile.css' );
	}

	if ( Request::is_editor() ) {
		wp_enqueue_script( 'editor-blocks', 'blocks.js' );
	}
}
add_action( 'wp_enqueue_scripts', 'enqueue_conditional_assets' );
add_action( 'admin_enqueue_scripts', 'enqueue_conditional_assets' );
```

### AJAX Handler with Safety

```php
function handle_search_ajax() {
	if ( ! Request::is_ajax() ) {
		wp_die( 'Invalid request' );
	}

	$search_term = Request::get_var( 'search', '', 'post' );
	$page        = Request::get_var( 'page', 1, 'post' );

	if ( empty( $search_term ) ) {
		wp_send_json_error( 'Search term required' );
	}

	// Process search...
	wp_send_json_success( $results );
}
add_action( 'wp_ajax_search', 'handle_search_ajax' );
add_action( 'wp_ajax_nopriv_search', 'handle_search_ajax' );
```

### REST API Endpoint Protection

```php
function secure_api_endpoint( $request ) {
	// Block non-SSL requests in production
	if ( ! Request::is_ssl() && ! Request::is_cli() ) {
		return new WP_Error( 'ssl_required', 'HTTPS required', [ 'status' => 403 ] );
	}

	// Different logic for different request types
	if ( Request::is_admin() ) {
		// Admin context logic
	} elseif ( Request::is_frontend() ) {
		// Public API logic
	}

	return $response;
}
```

### Smart Caching by Context

```php
function get_cache_key( $base_key ) {
	$context_parts = [];

	if ( Request::is_mobile() ) {
		$context_parts[] = 'mobile';
	}

	if ( Request::is_ssl() ) {
		$context_parts[] = 'ssl';
	}

	if ( Request::is_admin() ) {
		$context_parts[] = 'admin';
	}

	return $base_key . '_' . implode( '_', $context_parts );
}

$cache_key = get_cache_key( 'user_data' );
```

### Form Processing with Safety

```php
function process_contact_form() {
	// Only process POST requests
	if ( ! Request::has_var( 'submit', 'post' ) ) {
		return;
	}

	// Get sanitized form data
	$name    = Request::get_var( 'name', '', 'post' );
	$email   = Request::get_var( 'email', '', 'post' );
	$message = Request::get_var( 'message', '', 'post' );

	// Validate and process...
	if ( empty( $name ) || empty( $email ) ) {
		wp_redirect( add_query_arg( 'error', 'missing_fields' ) );
		exit;
	}

	// Process form...
}
```

### Debug Information

```php
function show_debug_info() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	echo '<div class="debug-info">';
	echo '<h3>Request Information</h3>';
	echo '<p>Type: ';

	$types = [ 'admin', 'ajax', 'rest', 'cron', 'frontend', 'mobile' ];
	foreach ( $types as $type ) {
		if ( Request::is( $type ) ) {
			echo ucfirst( $type ) . ' ';
		}
	}

	echo '</p>';
	echo '<p>SSL: ' . ( Request::is_ssl() ? 'Yes' : 'No' ) . '</p>';
	echo '</div>';
}
```

## Method Reference

### Request Type Detection
- `is(string|array $type)` - Check single or multiple request types
- `is_admin()` - WordPress admin area
- `is_ajax()` - AJAX request (uses `wp_doing_ajax()`)
- `is_rest()` - REST API request
- `is_cron()` - Cron request (uses `wp_doing_cron()`)
- `is_frontend()` - Frontend request (not admin/ajax/rest/cron/cli)
- `is_json()` - JSON request (uses `wp_is_json_request()`)
- `is_cli()` - Command line interface
- `is_editor()` - Block/Gutenberg editor context

### Device & Environment
- `is_mobile()` - Mobile device (uses `wp_is_mobile()`)
- `is_desktop()` - Desktop device
- `is_ssl()` - Secure HTTPS connection

### Request Variables
- `get_var( string $key, $default, string $method )` - Get sanitized variable
- `has_var( string $key, string $method )` - Check if variable exists
- `get_request_vars( bool $refresh )` - All $_REQUEST variables
- `get_post_vars( bool $refresh )` - All $_POST variables
- `get_get_vars( bool $refresh )` - All $_GET variables
- `get_current_page( string $param )` - Current page number for pagination

## Performance

- **Cached sanitization** - Request variables are sanitized once and cached
- **WordPress native** - Uses core WP functions when available (`wp_doing_ajax()`, `wp_is_mobile()`, etc.)
- **Efficient detection** - Request type detection uses fast native checks

## Security

All request variables are automatically sanitized using WordPress functions:
- Keys sanitized with `sanitize_key()`
- Values sanitized with `sanitize_text_field()`
- Arrays recursively sanitized
- No raw `$_GET`/`$_POST`/`$_REQUEST` access needed

## Requirements

- PHP 7.4+
- WordPress 5.0+

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the GPL-2.0-or-later License.

## Support

- [Documentation](https://github.com/arraypress/wp-request-utils)
- [Issue Tracker](https://github.com/arraypress/wp-request-utils/issues)