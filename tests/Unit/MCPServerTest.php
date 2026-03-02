<?php
namespace WP_MCP\Tests\Unit;

use Brain\Monkey\Functions;
use WP_MCP\Core\MCP_Server;
use WP_MCP\Tests\TestCase;

/**
 * Tests for WP_MCP\Core\MCP_Server (fallback mode – no SDK present).
 */
class MCPServerTest extends TestCase {

    private \WP_User $user;

    protected function setUp(): void {
        parent::setUp();
        $this->user = new \WP_User();
        // Stub WordPress option functions used by handle_tools_list.
        Functions\when( 'get_option' )->justReturn( array() );
    }

    // ------------------------------------------------------------------
    // handle_raw – JSON-RPC routing
    // ------------------------------------------------------------------

    public function test_invalid_json_throws_exception(): void {
        $server = new MCP_Server( $this->user );
        $this->expectException( \Exception::class );
        $server->handle_raw( 'not json' );
    }

    public function test_missing_method_throws_exception(): void {
        $server = new MCP_Server( $this->user );
        $this->expectException( \Exception::class );
        $server->handle_raw( '{"id":1}' );
    }

    public function test_unknown_method_throws_exception(): void {
        $server = new MCP_Server( $this->user );
        $this->expectException( \Exception::class );
        $server->handle_raw( '{"method":"unknown/method","params":{}}' );
    }

    public function test_tools_list_method_returns_tools_key(): void {
        // WooCommerce classes are absent in the test environment, so WC tools
        // are excluded automatically without needing to mock class_exists.
        $server = new MCP_Server( $this->user );
        $result = $server->handle_raw( '{"method":"tools/list","params":{}}' );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'tools', $result );
    }

    public function test_tools_call_without_tool_param_throws_exception(): void {
        $server = new MCP_Server( $this->user );
        $this->expectException( \Exception::class );
        // MCP spec uses 'name', not 'tool'; missing 'name' must throw.
        $server->handle_raw( '{"method":"tools/call","params":{}}' );
    }

    public function test_tools_call_unknown_tool_throws_exception(): void {
        Functions\when( 'sanitize_text_field' )->returnArg();

        $server = new MCP_Server( $this->user );
        $this->expectException( \Exception::class );
        // 'name' is the MCP-compliant parameter name.
        $server->handle_raw( '{"method":"tools/call","params":{"name":"nonexistent"}}' );
    }

    // ------------------------------------------------------------------
    // handle_tools_list – enabled / disabled tools
    // ------------------------------------------------------------------

    public function test_all_non_woo_tools_included_when_no_enabled_option(): void {
        // WooCommerce is absent; WC tools excluded naturally.
        $server = new MCP_Server( $this->user );
        $result = $server->handle_tools_list();

        $names = array_column( $result['tools'], 'name' );
        $this->assertContains( 'search_posts', $names );
        $this->assertContains( 'search_pages', $names );
        $this->assertContains( 'search_post_categories', $names );
        $this->assertContains( 'search_tags', $names );
        $this->assertContains( 'search_custom_post_types', $names );
        // WooCommerce tools must be absent.
        $this->assertNotContains( 'search_products', $names );
        $this->assertNotContains( 'get_orders', $names );
    }

    public function test_enabled_option_filters_tools(): void {
        Functions\when( 'get_option' )->alias( function ( $option ) {
            if ( $option === 'wp_mcp_enabled_tools' ) {
                return array( 'search_posts' );
            }
            return array();
        } );

        $server = new MCP_Server( $this->user );
        $result = $server->handle_tools_list();

        $names = array_column( $result['tools'], 'name' );
        $this->assertContains( 'search_posts', $names );
        $this->assertNotContains( 'search_pages', $names );
    }

    public function test_available_tool_names_exclude_woocommerce_when_missing(): void {
        $names = MCP_Server::get_available_tool_names();

        $this->assertContains( 'search_posts', $names );
        $this->assertNotContains( 'search_products', $names );
    }
}
