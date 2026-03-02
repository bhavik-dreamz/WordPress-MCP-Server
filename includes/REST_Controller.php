<?php
namespace WP_MCP\Core;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class REST_Controller {
    const NAMESPACE = 'wp-mcp/v1';

    /** Cached authenticated user set by permission_mcp, consumed by handle_mcp. */
    private static $mcp_user = null;

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route( self::NAMESPACE, '/info', array(
            'methods'  => 'GET',
            'callback' => array( __CLASS__, 'handle_info' ),
            'permission_callback' => '__return_true',
        ) );

        register_rest_route( self::NAMESPACE, '/mcp', array(
            'methods'  => 'POST',
            'callback' => array( __CLASS__, 'handle_mcp' ),
            'permission_callback' => array( __CLASS__, 'permission_mcp' ),
        ) );
    }

    public static function handle_info( WP_REST_Request $request ) {
        $enabled = get_option( 'wp_mcp_enabled_tools', array() );
        $tools = array_values( (array) $enabled );

        $data = array(
            'plugin' => 'wp-mcp-server',
            'version' => '1.0.0',
            'site_name' => get_bloginfo( 'name' ),
            'site_url' => get_option( 'wp_mcp_site_url', home_url() ),
            'available_tools' => $tools,
        );

        return rest_ensure_response( $data );
    }

    public static function permission_mcp( WP_REST_Request $request ) {
        $auth = Auth::authenticate_request( $request );
        if ( is_wp_error( $auth ) ) {
            return false;
        }
        self::$mcp_user = $auth;
        return true;
    }

    public static function handle_mcp( WP_REST_Request $request ) {
        $auth = self::$mcp_user;
        if ( ! $auth ) {
            return rest_ensure_response( new \WP_Error( 'not_authenticated', __( 'Not authenticated', 'wp-mcp-server' ), array( 'status' => 401 ) ) );
        }

        // Read raw body
        $body = $request->get_body();
        if ( empty( $body ) ) {
            return rest_ensure_response( new \WP_Error( 'empty_body', __( 'Empty request body', 'wp-mcp-server' ), array( 'status' => 400 ) ) );
        }

        $mcp_server = new MCP_Server( $auth );

        print_r($body);
        try {
            $result = $mcp_server->handle_raw( $body );
            return rest_ensure_response( $result );
        } catch ( \Exception $e ) {
            return rest_ensure_response( new \WP_Error( 'mcp_error', $e->getMessage(), array( 'status' => 500 ) ) );
        }
    }
}
