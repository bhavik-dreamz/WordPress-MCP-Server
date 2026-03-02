<?php
/**
 * Bootstrap file for WP MCP Server unit tests.
 *
 * Sets up Brain Monkey stubs so WordPress functions are available without a
 * full WordPress install.  Each test class calls Brain\Monkey\setUp() /
 * tearDown() via the WP_MCP\Tests\TestCase base class.
 */

define( 'ABSPATH', __DIR__ . '/' );

require_once __DIR__ . '/../vendor/autoload.php';

// Provide minimal WP stubs that Brain Monkey does not cover automatically.
// Brain Monkey stubs add_action, add_filter, apply_filters, do_action, etc.
// but we need a few extra constants and classes.
if ( ! defined( 'WPINC' ) ) {
    define( 'WPINC', 'wp-includes' );
}

// ---------------------------------------------------------------------------
// Custom autoloader for the plugin source files.
//
// The plugin uses WordPress naming convention (class-tool-posts.php) rather
// than PSR-4 (Tool_Posts.php), so Composer's generated autoloader cannot
// find the classes.  We register a bespoke loader that converts class names:
//
//   WP_MCP\Core\Auth          → includes/class-auth.php
//   WP_MCP\Tools\Tool_Posts   → includes/tools/class-tool-posts.php
//   WP_MCP\Admin\Admin_Page   → admin/class-admin-page.php
// ---------------------------------------------------------------------------
spl_autoload_register( function ( string $class ): void {
    $plugin_root = dirname( __DIR__ );

    $map = array(
        'WP_MCP\\Core\\'  => $plugin_root . '/includes/',
        'WP_MCP\\Tools\\' => $plugin_root . '/includes/tools/',
        'WP_MCP\\Admin\\' => $plugin_root . '/admin/',
    );

    foreach ( $map as $prefix => $dir ) {
        $len = strlen( $prefix );
        if ( strncmp( $class, $prefix, $len ) !== 0 ) {
            continue;
        }

        // Convert "Tool_Posts" → "tool-posts", "Admin_Page" → "admin-page"
        $short = substr( $class, $len );
        $wp_style = $dir . 'class-' . strtolower( str_replace( '_', '-', $short ) ) . '.php';
        $psr_style = $dir . str_replace( '\\', '/', $short ) . '.php';

        if ( file_exists( $wp_style ) ) {
            require_once $wp_style;
            return;
        }

        if ( file_exists( $psr_style ) ) {
            require_once $psr_style;
            return;
        }
    }
} );

/**
 * Minimal WP_Error stub used by tests.
 */
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public string $code;
        public string $message;
        public array  $data;

        public function __construct( string $code = '', string $message = '', $data = array() ) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = is_array( $data ) ? $data : array( 'status' => $data );
        }

        public function get_error_code(): string {
            return $this->code;
        }

        public function get_error_message(): string {
            return $this->message;
        }

        public function get_error_data( $code = '' ) {
            return $this->data;
        }
    }
}

/**
 * Minimal WP_User stub.
 */
if ( ! class_exists( 'WP_User' ) ) {
    class WP_User {
        public int    $ID   = 1;
        public string $user_login = 'testuser';
        private array $caps = array();

        public function has_cap( string $cap ): bool {
            return isset( $this->caps[ $cap ] ) ? $this->caps[ $cap ] : true;
        }

        public function set_cap( string $cap, bool $value = true ): void {
            $this->caps[ $cap ] = $value;
        }
    }
}

/**
 * Minimal WP_REST_Request stub.
 */
if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        private array  $headers = array();
        private string $body    = '';

        public function get_header( string $key ): string {
            return $this->headers[ strtolower( $key ) ] ?? '';
        }

        public function set_header( string $key, string $value ): void {
            $this->headers[ strtolower( $key ) ] = $value;
        }

        public function get_body(): string {
            return $this->body;
        }

        public function set_body( string $body ): void {
            $this->body = $body;
        }
    }
}

/**
 * is_wp_error() global helper.
 */
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ): bool {
        return $thing instanceof WP_Error;
    }
}

// ---------------------------------------------------------------------------
// WordPress i18n function stubs.
// Brain Monkey intercepts user-defined functions but does not pre-stub __().
// ---------------------------------------------------------------------------
if ( ! function_exists( '__' ) ) {
    function __( string $text, string $domain = 'default' ): string {
        return $text;
    }
}
if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( string $text, string $domain = 'default' ): string {
        return htmlspecialchars( $text, ENT_QUOTES );
    }
}

// ---------------------------------------------------------------------------
// Minimal WP_Query stub.
// Tests that exercise tools using WP_Query receive an empty result set.
// Individual tests can override the class before running if needed.
// ---------------------------------------------------------------------------
if ( ! class_exists( 'WP_Query' ) ) {
    class WP_Query {
        /** @var \stdClass[] */
        public array $posts       = array();
        public int   $found_posts = 0;

        public function __construct( array $args = array() ) {}
    }
}
