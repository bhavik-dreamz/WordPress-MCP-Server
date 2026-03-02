<?php
/**
 * Plugin Name: WP MCP Server
 * Plugin URI:  https://example.org/wp-mcp-server
 * Description: Exposes WordPress as an MCP server for Claude Desktop and other MCP clients.
 * Version:     1.0.0
 * Author:      Generated
 * Text Domain: wp-mcp-server
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Composer autoload (vendor may be absent in this repo)
$autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
    require_once $autoload;
}

// Plugin autoloader (handles Core/Admin PSR-style files and Tools WordPress-style files)
spl_autoload_register( function ( $class ) {
    $map = array(
        'WP_MCP\\Core\\'  => __DIR__ . '/includes/',
        'WP_MCP\\Admin\\' => __DIR__ . '/admin/',
    );

    foreach ( $map as $prefix => $dir ) {
        $len = strlen( $prefix );
        if ( strncmp( $class, $prefix, $len ) !== 0 ) {
            continue;
        }

        $relative = substr( $class, $len );
        $file = $dir . str_replace( '\\', '/', $relative ) . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
        return;
    }

    $tools_prefix = 'WP_MCP\\Tools\\';
    $tools_len = strlen( $tools_prefix );
    if ( strncmp( $class, $tools_prefix, $tools_len ) === 0 ) {
        $short = substr( $class, $tools_len );
        $slug  = strtolower( str_replace( '_', '-', $short ) );
        $file  = __DIR__ . '/includes/tools/class-' . $slug . '.php';
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }
} );

// Initialize
add_action( 'plugins_loaded', function () {
      
    // Load core
    if ( class_exists( 'WP_MCP\Core\REST_Controller' ) ) {
        
        WP_MCP\Core\REST_Controller::init();
    }

    if ( is_admin() && class_exists( 'WP_MCP\Admin\Admin_Page' ) ) {
        WP_MCP\Admin\Admin_Page::init();
    }

    // WooCommerce notice
    add_action( 'admin_notices', function () {
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<div class="notice notice-warning"><p>' . esc_html__( 'WP MCP Server: WooCommerce not active - WooCommerce tools will be unavailable.', 'wp-mcp-server' ) . '</p></div>';
        }
    } );
} );
