<?php
namespace WP_MCP\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Logiscape\MCP\Server as MCPServer;
use Logiscape\MCP\Handlers\CallToolResult;
use Logiscape\MCP\Types\TextContent;

class MCP_Server {
    protected $server;
    protected $user;

    /**
     * Identifiers for tools that depend on WooCommerce APIs.
     *
     * @var string[]
     */
    protected static $woocommerce_tools = array(
        'search_products',
        'create_product',
        'search_product_categories',
        'create_order',
        'get_orders',
        'get_order_details',
        'recommend_products',
    );

    public function __construct( $wp_user ) {
        $this->user = $wp_user;
        // Instantiate SDK server if available, otherwise provide minimal fallback
        if ( class_exists( '\\Logiscape\\MCP\\Server' ) ) {
            $this->server = new MCPServer();
        } else {
            $this->server = null;
        }

        $this->register_handlers();
    }

    protected function register_handlers() {
        // If SDK available, use registerHandler calls; if not, we'll implement simple routing
        if ( $this->server ) {
            $this->server->registerHandler( 'tools/list', array( $this, 'handle_tools_list' ) );
            $this->server->registerHandler( 'tools/call', array( $this, 'handle_tools_call' ) );
        }
    }

    /**
     * Handle raw JSON-RPC payload and return JSON response. Uses SDK if present.
     */
    public function handle_raw( $raw ) {
        if ( $this->server ) {
            return $this->server->handle( $raw );
        }

        // Fallback: parse as JSON-RPC with method and params
        $data = json_decode( $raw, true );
        if ( json_last_error() !== JSON_ERROR_NONE || empty( $data['method'] ) ) {
            throw new \Exception( 'Invalid JSON-RPC payload' );
        }

        switch ( $data['method'] ) {
            case 'tools/list':
                return $this->handle_tools_list( $data['params'] ?? array() );
            case 'tools/call':
                return $this->handle_tools_call( $data['params'] ?? array() );
            default:
                throw new \Exception( 'Method not found Tool' );
        }
    }

    public function handle_tools_list( $params = array() ) {
        return array( 'tools' => self::get_available_tools() );
    }

    public static function get_available_tools() {
        $enabled = get_option( 'wp_mcp_enabled_tools', array() );
        $tools = array();

        foreach ( self::get_all_tools() as $key => $info ) {
            if ( ! self::tool_is_enabled( $key, $enabled ) ) {
                continue;
            }

            if ( ! self::tool_dependencies_available( $key ) ) {
                continue;
            }

            $tools[] = $info;
        }

        return $tools;
    }

    public static function get_available_tool_names() {
        $names = array();

        foreach ( self::get_available_tools() as $tool ) {
            if ( isset( $tool['name'] ) ) {
                $names[] = $tool['name'];
            }
        }

        return $names;
    }

    protected static function tool_is_enabled( $key, $enabled ) {
        if ( empty( $enabled ) ) {
            return true;
        }

        return in_array( $key, (array) $enabled, true );
    }

    protected static function tool_dependencies_available( $key ) {
        if ( in_array( $key, self::$woocommerce_tools, true ) && ! class_exists( 'WooCommerce' ) ) {
            return false;
        }

        return true;
    }

    protected static function get_all_tools() {
        return array(
            'search_posts' => array(
                'name' => 'search_posts',
                'description' => 'Search WordPress posts by keyword with optional pagination.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'query'     => array( 'type' => 'string',  'description' => 'Search keyword' ),
                        'post_type' => array( 'type' => 'string',  'description' => 'Post type (default: post)' ),
                        'per_page'  => array( 'type' => 'integer', 'description' => 'Results per page (default: 10)' ),
                        'page'      => array( 'type' => 'integer', 'description' => 'Page number (default: 1)' ),
                    ),
                ),
            ),
            'search_pages' => array(
                'name' => 'search_pages',
                'description' => 'Search WordPress pages by keyword.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'query'    => array( 'type' => 'string',  'description' => 'Search keyword' ),
                        'per_page' => array( 'type' => 'integer', 'description' => 'Results per page (default: 10)' ),
                        'page'     => array( 'type' => 'integer', 'description' => 'Page number (default: 1)' ),
                    ),
                ),
            ),
            'search_post_categories' => array(
                'name' => 'search_post_categories',
                'description' => 'Search WordPress post categories.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'query'     => array( 'type' => 'string',  'description' => 'Search keyword' ),
                        'parent_id' => array( 'type' => 'integer', 'description' => 'Filter by parent category ID' ),
                        'per_page'  => array( 'type' => 'integer', 'description' => 'Results per page (default: 20)' ),
                    ),
                ),
            ),
            'search_tags' => array(
                'name' => 'search_tags',
                'description' => 'Search WordPress post tags.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'query'    => array( 'type' => 'string',  'description' => 'Search keyword' ),
                        'per_page' => array( 'type' => 'integer', 'description' => 'Results per page (default: 20)' ),
                    ),
                ),
            ),
            'create_post' => array(
                'name' => 'create_post',
                'description' => 'Create a WordPress post.',
                'inputSchema' => array(
                    'type' => 'object',
                    'required' => array( 'title' ),
                    'properties' => array(
                        'title' => array( 'type' => 'string', 'description' => 'Post title (required)' ),
                        'content' => array( 'type' => 'string', 'description' => 'Post content' ),
                        'excerpt' => array( 'type' => 'string', 'description' => 'Post excerpt' ),
                        'status' => array( 'type' => 'string', 'description' => 'draft/publish/pending/private (default: draft)' ),
                        'slug' => array( 'type' => 'string', 'description' => 'Post slug' ),
                        'author_id' => array( 'type' => 'integer', 'description' => 'Author user ID' ),
                    ),
                ),
            ),
            'create_page' => array(
                'name' => 'create_page',
                'description' => 'Create a WordPress page.',
                'inputSchema' => array(
                    'type' => 'object',
                    'required' => array( 'title' ),
                    'properties' => array(
                        'title' => array( 'type' => 'string', 'description' => 'Page title (required)' ),
                        'content' => array( 'type' => 'string', 'description' => 'Page content' ),
                        'excerpt' => array( 'type' => 'string', 'description' => 'Page excerpt' ),
                        'status' => array( 'type' => 'string', 'description' => 'draft/publish/pending/private (default: draft)' ),
                        'slug' => array( 'type' => 'string', 'description' => 'Page slug' ),
                        'author_id' => array( 'type' => 'integer', 'description' => 'Author user ID' ),
                    ),
                ),
            ),
            'create_category' => array(
                'name' => 'create_category',
                'description' => 'Create a WordPress category.',
                'inputSchema' => array(
                    'type' => 'object',
                    'required' => array( 'name' ),
                    'properties' => array(
                        'name' => array( 'type' => 'string', 'description' => 'Category name (required)' ),
                        'slug' => array( 'type' => 'string', 'description' => 'Category slug' ),
                        'parent_id' => array( 'type' => 'integer', 'description' => 'Parent category term ID' ),
                        'description' => array( 'type' => 'string', 'description' => 'Category description' ),
                    ),
                ),
            ),
            'search_custom_post_types' => array(
                'name' => 'search_custom_post_types',
                'description' => 'Search allowed custom post types with optional meta filters.',
                'inputSchema' => array(
                    'type' => 'object',
                    'required' => array( 'post_type' ),
                    'properties' => array(
                        'post_type'    => array( 'type' => 'string',  'description' => 'Custom post type slug (required)' ),
                        'query'        => array( 'type' => 'string',  'description' => 'Search keyword' ),
                        'meta_filters' => array( 'type' => 'array',   'description' => 'Array of {key, value} meta filter objects' ),
                        'per_page'     => array( 'type' => 'integer', 'description' => 'Results per page (default: 10)' ),
                        'page'         => array( 'type' => 'integer', 'description' => 'Page number (default: 1)' ),
                    ),
                ),
            ),
            'create_custom_post_type' => array(
                'name' => 'create_custom_post_type',
                'description' => 'Create a custom post type entry (must be whitelisted in settings).',
                'inputSchema' => array(
                    'type' => 'object',
                    'required' => array( 'post_type', 'title' ),
                    'properties' => array(
                        'post_type' => array( 'type' => 'string', 'description' => 'Custom post type slug (required)' ),
                        'title' => array( 'type' => 'string', 'description' => 'Entry title (required)' ),
                        'content' => array( 'type' => 'string', 'description' => 'Entry content' ),
                        'excerpt' => array( 'type' => 'string', 'description' => 'Entry excerpt' ),
                        'status' => array( 'type' => 'string', 'description' => 'draft/publish/pending/private (default: draft)' ),
                        'slug' => array( 'type' => 'string', 'description' => 'Entry slug' ),
                        'author_id' => array( 'type' => 'integer', 'description' => 'Author user ID' ),
                    ),
                ),
            ),
            'search_products' => array(
                'name' => 'search_products',
                'description' => 'Search WooCommerce products with optional price and stock filters.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'query'       => array( 'type' => 'string',  'description' => 'Search keyword' ),
                        'category_id' => array( 'type' => 'integer', 'description' => 'Filter by product category ID' ),
                        'min_price'   => array( 'type' => 'number',  'description' => 'Minimum price filter' ),
                        'max_price'   => array( 'type' => 'number',  'description' => 'Maximum price filter' ),
                        'in_stock'    => array( 'type' => 'boolean', 'description' => 'Only return in-stock products' ),
                        'per_page'    => array( 'type' => 'integer', 'description' => 'Results per page (default: 10)' ),
                        'page'        => array( 'type' => 'integer', 'description' => 'Page number (default: 1)' ),
                    ),
                ),
            ),
            'create_product' => array(
                'name' => 'create_product',
                'description' => 'Create a WooCommerce product.',
                'inputSchema' => array(
                    'type' => 'object',
                    'required' => array( 'name' ),
                    'properties' => array(
                        'name' => array( 'type' => 'string', 'description' => 'Product name (required)' ),
                        'description' => array( 'type' => 'string', 'description' => 'Product description' ),
                        'short_description' => array( 'type' => 'string', 'description' => 'Short description' ),
                        'status' => array( 'type' => 'string', 'description' => 'draft/publish/pending/private (default: draft)' ),
                        'regular_price' => array( 'type' => 'number', 'description' => 'Regular price' ),
                        'sale_price' => array( 'type' => 'number', 'description' => 'Sale price' ),
                        'sku' => array( 'type' => 'string', 'description' => 'Product SKU' ),
                        'manage_stock' => array( 'type' => 'boolean', 'description' => 'Enable stock management' ),
                        'stock_quantity' => array( 'type' => 'integer', 'description' => 'Stock quantity if manage_stock=true' ),
                        'category_ids' => array( 'type' => 'array', 'description' => 'Array of product_cat term IDs' ),
                    ),
                ),
            ),
            'search_product_categories' => array(
                'name' => 'search_product_categories',
                'description' => 'List or search WooCommerce product categories.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'query'     => array( 'type' => 'string',  'description' => 'Search keyword' ),
                        'parent_id' => array( 'type' => 'integer', 'description' => 'Filter by parent category ID' ),
                        'per_page'  => array( 'type' => 'integer', 'description' => 'Results per page (default: 20)' ),
                    ),
                ),
            ),
            'create_order' => array(
                'name' => 'create_order',
                'description' => 'Create a WooCommerce order with line items.',
                'inputSchema' => array(
                    'type' => 'object',
                    'required' => array( 'line_items' ),
                    'properties' => array(
                        'customer_id' => array( 'type' => 'integer', 'description' => 'Customer user ID' ),
                        'line_items' => array( 'type' => 'array', 'description' => 'Array of {product_id, quantity} objects' ),
                        'billing' => array( 'type' => 'object', 'description' => 'Billing address fields' ),
                        'shipping' => array( 'type' => 'object', 'description' => 'Shipping address fields' ),
                        'status' => array( 'type' => 'string', 'description' => 'Order status (e.g. pending, processing)' ),
                    ),
                ),
            ),
            'get_orders' => array(
                'name' => 'get_orders',
                'description' => 'List WooCommerce orders with optional status, customer and date filters.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'status'      => array( 'type' => 'string',  'description' => 'Order status (e.g. processing, completed)' ),
                        'customer_id' => array( 'type' => 'integer', 'description' => 'Filter by customer user ID' ),
                        'date_from'   => array( 'type' => 'string',  'description' => 'Start date (YYYY-MM-DD)' ),
                        'date_to'     => array( 'type' => 'string',  'description' => 'End date (YYYY-MM-DD)' ),
                        'per_page'    => array( 'type' => 'integer', 'description' => 'Results per page (default: 10)' ),
                        'page'        => array( 'type' => 'integer', 'description' => 'Page number (default: 1)' ),
                    ),
                ),
            ),
            'get_order_details' => array(
                'name' => 'get_order_details',
                'description' => 'Get full details (line items, billing, shipping, notes) of a WooCommerce order.',
                'inputSchema' => array(
                    'type' => 'object',
                    'required' => array( 'order_id' ),
                    'properties' => array(
                        'order_id' => array( 'type' => 'integer', 'description' => 'WooCommerce order ID (required)' ),
                    ),
                ),
            ),
            'create_user' => array(
                'name' => 'create_user',
                'description' => 'Create a WordPress user account.',
                'inputSchema' => array(
                    'type' => 'object',
                    'required' => array( 'username', 'email' ),
                    'properties' => array(
                        'username' => array( 'type' => 'string', 'description' => 'Username (required)' ),
                        'email' => array( 'type' => 'string', 'description' => 'Email (required)' ),
                        'password' => array( 'type' => 'string', 'description' => 'Password (optional; auto-generated if omitted)' ),
                        'display_name' => array( 'type' => 'string', 'description' => 'Display name' ),
                        'role' => array( 'type' => 'string', 'description' => 'User role (default: subscriber)' ),
                    ),
                ),
            ),
            'recommend_products' => array(
                'name' => 'recommend_products',
                'description' => 'Recommend WooCommerce products using related, upsell, crosssell, bestseller, or new_arrivals strategies.',
                'inputSchema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'strategy'    => array( 'type' => 'string',  'description' => 'Recommendation strategy: related, upsell, crosssell, bestseller, new_arrivals (default: related)' ),
                        'product_id'  => array( 'type' => 'integer', 'description' => 'Source product ID (required for related, upsell, crosssell)' ),
                        'category_id' => array( 'type' => 'integer', 'description' => 'Filter by category ID' ),
                        'limit'       => array( 'type' => 'integer', 'description' => 'Maximum number of results (default: 5)' ),
                    ),
                ),
            ),
        );
    }

    public function handle_tools_call( $params = array() ) {
        if ( empty( $params['name'] ) ) {
            throw new \Exception( 'name parameter required' );
        }
        $tool = sanitize_text_field( $params['name'] );
        $args = $params['arguments'] ?? array();

        $mapping = array(
            'search_posts' => '\\WP_MCP\\Tools\\Tool_Posts',
            'search_pages' => '\\WP_MCP\\Tools\\Tool_Posts',
            'search_post_categories' => '\\WP_MCP\\Tools\\Tool_Taxonomies',
            'search_tags' => '\\WP_MCP\\Tools\\Tool_Taxonomies',
            'create_post' => '\\WP_MCP\\Tools\\Tool_Create_Content',
            'create_page' => '\\WP_MCP\\Tools\\Tool_Create_Content',
            'create_category' => '\\WP_MCP\\Tools\\Tool_Create_Category',
            'search_custom_post_types' => '\\WP_MCP\\Tools\\Tool_CPT',
            'create_custom_post_type' => '\\WP_MCP\\Tools\\Tool_Create_Content',
            'search_products' => '\\WP_MCP\\Tools\\Tool_Products',
            'create_product' => '\\WP_MCP\\Tools\\Tool_Create_Product',
            'search_product_categories' => '\\WP_MCP\\Tools\\Tool_Categories',
            'create_order' => '\\WP_MCP\\Tools\\Tool_Create_Order',
            'get_orders' => '\\WP_MCP\\Tools\\Tool_Orders',
            'get_order_details' => '\\WP_MCP\\Tools\\Tool_Order_Details',
            'create_user' => '\\WP_MCP\\Tools\\Tool_Create_User',
            'recommend_products' => '\\WP_MCP\\Tools\\Tool_Recommendations',
        );

        if ( ! isset( $mapping[ $tool ] ) ) {
            throw new \Exception( 'Unknown tool' );
        }

        $class = $mapping[ $tool ];
        if ( ! class_exists( $class ) ) {
            throw new \Exception( 'Tool class not found: ' . $class );
        }

        if ( ! is_callable( array( $class, 'call' ) ) ) {
            throw new \Exception( 'Tool does not implement call()' );
        }

        if ( $tool === 'search_pages' && empty( $args['post_type'] ) ) {
            $args['post_type'] = 'page';
        }

        if ( $tool === 'search_post_categories' ) {
            $args['taxonomy'] = 'category';
        }

        if ( $tool === 'search_tags' ) {
            $args['taxonomy'] = 'post_tag';
        }

        if ( $tool === 'create_post' ) {
            $args['post_type'] = 'post';
        }

        if ( $tool === 'create_page' ) {
            $args['post_type'] = 'page';
        }

        // Call the tool with sanitized args and current user
        return call_user_func( array( $class, 'call' ), $args, $this->user );
    }
}
