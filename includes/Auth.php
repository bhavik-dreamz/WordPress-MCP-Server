<?php
namespace WP_MCP\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Auth {
    /**
     * Authenticate a REST request using Application Passwords.
     * Returns WP_User on success, WP_Error on failure.
     *
     * @param \WP_REST_Request $request
     * @return \WP_User|\WP_Error
     */
    public static function authenticate_request( \WP_REST_Request $request ) {
        $auth = $request->get_header( 'authorization' );
        if ( empty( $auth ) ) {
            return new \WP_Error( 'no_auth', __( 'Authorization header missing', 'wp-mcp-server' ), array( 'status' => 401 ) );
        }

        if ( stripos( $auth, 'basic ' ) === 0 ) {
            $credentials = self::parse_basic_header( substr( $auth, 6 ) );
        } elseif ( stripos( $auth, 'bearer ' ) === 0 ) {
            $credentials = self::parse_bearer_header( substr( $auth, 7 ) );
        } else {
            return new \WP_Error( 'invalid_auth_scheme', __( 'Unsupported authorization scheme', 'wp-mcp-server' ), array( 'status' => 401 ) );
        }

        if ( is_wp_error( $credentials ) ) {
            return $credentials;
        }

        list( $username, $password ) = $credentials;
        return self::authenticate_with_application_password( $username, $password );
    }

    private static function parse_basic_header( $encoded ) {
        $decoded = base64_decode( $encoded );
        if ( $decoded === false || $decoded === '' ) {
            return new \WP_Error( 'invalid_auth', __( 'Invalid Authorization header', 'wp-mcp-server' ), array( 'status' => 401 ) );
        }

        return self::split_credentials( $decoded );
    }

    private static function parse_bearer_header( $token ) {
        $token = trim( $token );
        if ( $token === '' ) {
            return new \WP_Error( 'invalid_auth', __( 'Invalid Bearer token format', 'wp-mcp-server' ), array( 'status' => 401 ) );
        }

        if ( strpos( $token, ':' ) !== false ) {
            return self::split_credentials( $token );
        }

        $decoded = base64_decode( $token );
        if ( $decoded !== false && $decoded !== '' && strpos( $decoded, ':' ) !== false ) {
            return self::split_credentials( $decoded );
        }

        return new \WP_Error( 'invalid_auth', __( 'Invalid Bearer token format', 'wp-mcp-server' ), array( 'status' => 401 ) );
    }

    private static function split_credentials( $raw ) {
        list( $username, $password ) = array_pad( explode( ':', $raw, 2 ), 2, '' );
        if ( $username === '' || $password === '' ) {
            return new \WP_Error( 'invalid_auth', __( 'Invalid credentials', 'wp-mcp-server' ), array( 'status' => 401 ) );
        }

        return array( $username, $password );
    }

    private static function authenticate_with_application_password( $username, $password ) {
        if ( ! function_exists( 'wp_authenticate_application_password' ) ) {
            return new \WP_Error( 'no_app_passwords', __( 'Application Passwords not available on this site', 'wp-mcp-server' ), array( 'status' => 500 ) );
        }

        $user = wp_authenticate_application_password( null, $username, $password );
        if ( is_wp_error( $user ) ) {
            return $user;
        }

        if ( ! $user || ! $user->has_cap( 'read' ) ) {
            return new \WP_Error( 'forbidden', __( 'Insufficient capability', 'wp-mcp-server' ), array( 'status' => 403 ) );
        }

        return $user;
    }
}
