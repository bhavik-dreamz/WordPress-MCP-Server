<?php
namespace WP_MCP\Tests\Unit;

use Brain\Monkey\Functions;
use WP_MCP\Core\Auth;
use WP_MCP\Tests\TestCase;

/**
 * Tests for WP_MCP\Core\Auth
 */
class AuthTest extends TestCase {

    // ------------------------------------------------------------------
    // Missing / malformed Authorization header
    // ------------------------------------------------------------------

    public function test_missing_authorization_header_returns_wp_error(): void {
        $request = new \WP_REST_Request();
        // No Authorization header set.

        $result = Auth::authenticate_request( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'no_auth', $result->get_error_code() );
    }

    public function test_unsupported_scheme_returns_wp_error(): void {
        $request = new \WP_REST_Request();
        $request->set_header( 'authorization', 'Digest some-token' );

        $result = Auth::authenticate_request( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_auth_scheme', $result->get_error_code() );
    }

    public function test_invalid_base64_returns_wp_error(): void {
        $request = new \WP_REST_Request();
        // base64_decode of an empty string returns an empty string → triggers guard.
        $request->set_header( 'authorization', 'Basic ' );

        $result = Auth::authenticate_request( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_missing_password_returns_wp_error(): void {
        $request = new \WP_REST_Request();
        // Only a username, no colon-separated password.
        $request->set_header( 'authorization', 'Basic ' . base64_encode( 'user' ) );

        $result = Auth::authenticate_request( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_auth', $result->get_error_code() );
    }

    // ------------------------------------------------------------------
    // Application Passwords not available
    // ------------------------------------------------------------------

    public function test_returns_error_when_app_passwords_unavailable(): void {
        // wp_authenticate_application_password does NOT exist in the stub env.
        $request = new \WP_REST_Request();
        $request->set_header( 'authorization', 'Basic ' . base64_encode( 'user:password' ) );

        $result = Auth::authenticate_request( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'no_app_passwords', $result->get_error_code() );
    }

    // ------------------------------------------------------------------
    // Correct wp_authenticate_application_password call signature
    // ------------------------------------------------------------------

    public function test_app_password_called_with_null_first_arg(): void {
        // Verify the fixed call passes null as the first argument so
        // wp_authenticate_application_password receives the correct three-arg
        // signature expected by WordPress (input_user, username, password).
        $spy = null;
        Functions\when( 'wp_authenticate_application_password' )
            ->alias( function () use ( &$spy ) {
                $spy = func_get_args();
                return new \WP_User();
            } );

        $request = new \WP_REST_Request();
        $request->set_header( 'authorization', 'Basic ' . base64_encode( 'admin:secret' ) );

        Auth::authenticate_request( $request );

        $this->assertIsArray( $spy );
        $this->assertCount( 3, $spy );
        $this->assertNull( $spy[0], 'First argument must be null (input_user)' );
        $this->assertSame( 'admin', $spy[1] );
        $this->assertSame( 'secret', $spy[2] );
    }


    public function test_valid_credentials_return_wp_user(): void {
        $user = new \WP_User();

        Functions\when( 'wp_authenticate_application_password' )->justReturn( $user );

        $request = new \WP_REST_Request();
        $request->set_header( 'authorization', 'Basic ' . base64_encode( 'admin:app-password' ) );

        $result = Auth::authenticate_request( $request );

        $this->assertInstanceOf( \WP_User::class, $result );
    }

    // ------------------------------------------------------------------
    // Authentication failure from WordPress itself
    // ------------------------------------------------------------------

    public function test_wrong_password_returns_wp_error(): void {
        $error = new \WP_Error( 'invalid_username', 'Unknown username' );

        Functions\when( 'wp_authenticate_application_password' )->justReturn( $error );

        $request = new \WP_REST_Request();
        $request->set_header( 'authorization', 'Basic ' . base64_encode( 'nobody:wrong' ) );

        $result = Auth::authenticate_request( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
    }

    public function test_bearer_plain_credentials_return_user(): void {
        $user = new \WP_User();
        Functions\when( 'wp_authenticate_application_password' )->justReturn( $user );

        $request = new \WP_REST_Request();
        $request->set_header( 'authorization', 'Bearer admin:secret' );

        $result = Auth::authenticate_request( $request );

        $this->assertInstanceOf( \WP_User::class, $result );
    }

    public function test_bearer_base64_credentials_return_user(): void {
        $user = new \WP_User();
        Functions\when( 'wp_authenticate_application_password' )->justReturn( $user );

        $request = new \WP_REST_Request();
        $request->set_header( 'authorization', 'Bearer ' . base64_encode( 'admin:secret' ) );

        $result = Auth::authenticate_request( $request );

        $this->assertInstanceOf( \WP_User::class, $result );
    }

    public function test_bearer_invalid_token_returns_error(): void {
        $request = new \WP_REST_Request();
        $request->set_header( 'authorization', 'Bearer invalid' );

        $result = Auth::authenticate_request( $request );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'invalid_auth', $result->get_error_code() );
    }
}
