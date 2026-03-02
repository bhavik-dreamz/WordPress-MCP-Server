<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* @var $this WP_MCP\Admin\Admin_Page */

$site_url = esc_url( get_option( 'wp_mcp_site_url', home_url() ) );
$enabled = get_option( 'wp_mcp_enabled_tools', array() );
$allowed_cpts = get_option( 'wp_mcp_allowed_cpts', array() );
$all_tools = array( 'search_posts', 'search_pages', 'search_post_categories', 'search_tags', 'create_post', 'create_page', 'create_category', 'search_custom_post_types', 'create_custom_post_type', 'search_products', 'create_product', 'search_product_categories', 'create_order', 'get_orders', 'get_order_details', 'create_user', 'recommend_products' );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'WP MCP Server Settings', 'wp-mcp-server' ); ?></h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'wp_mcp_settings' ); ?>
        <?php do_settings_sections( 'wp_mcp_settings' ); ?>

        <h2><?php esc_html_e( 'Connection Info', 'wp-mcp-server' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Site URL', 'wp-mcp-server' ); ?></th>
                <td>
                    <input type="text" name="wp_mcp_site_url" value="<?php echo $site_url; ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'MCP endpoint: {site_url}/wp-json/wp-mcp/v1/mcp', 'wp-mcp-server' ); ?></p>
                    <button type="button" id="wp-mcp-copy-endpoint" class="button"><?php esc_html_e( 'Copy to clipboard', 'wp-mcp-server' ); ?></button>
                    <pre id="wp-mcp-claude-config" style="background:#f7f7f7;padding:10px;margin-top:10px;">{
  "mcpServers": {
    "wordpress-site": {
      "command": "curl",
      "args": [
        "-X", "POST",
        "-H", "Content-Type: application/json",
        "-H", "Authorization: Basic {base64_credentials}",
        "-d", "@-",
        "<?php echo esc_url( rtrim( $site_url, '/' ) . '/wp-json/wp-mcp/v1/mcp' ); ?>"
      ]
    }
  }
}</pre>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Authentication', 'wp-mcp-server' ); ?></h2>
        <p><?php esc_html_e( 'Generate an Application Password at: Users → Profile → Application Passwords → Add New', 'wp-mcp-server' ); ?></p>
        <p class="description"><?php esc_html_e( 'Both Basic auth and Bearer tokens are supported. For Bearer auth, send "username:application-password" (plain or base64 encoded) as the token.', 'wp-mcp-server' ); ?></p>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Test credentials', 'wp-mcp-server' ); ?></th>
                <td>
                    <input type="text" id="wp_mcp_test_username" placeholder="username" />
                    <input type="text" id="wp_mcp_test_app_password" placeholder="application password" />
                    <button type="button" id="wp_mcp_test_button" class="button"><?php esc_html_e( 'Test Connection', 'wp-mcp-server' ); ?></button>
                    <span id="wp_mcp_test_result"></span>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Tool Settings', 'wp-mcp-server' ); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Enable Tools', 'wp-mcp-server' ); ?></th>
                <td>
                    <?php foreach ( $all_tools as $t ) : ?>
                        <label><input type="checkbox" name="wp_mcp_enabled_tools[]" value="<?php echo esc_attr( $t ); ?>" <?php checked( in_array( $t, (array) $enabled, true ) ); ?> /> <?php echo esc_html( $t ); ?></label><br />
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>

        <h2><?php esc_html_e( 'Custom Post Types', 'wp-mcp-server' ); ?></h2>
        <p><?php esc_html_e( 'Whitelist CPTs accessible via search_custom_post_types. Comma-separated or select below.', 'wp-mcp-server' ); ?></p>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e( 'Allowed CPTs', 'wp-mcp-server' ); ?></th>
                <td>
                    <input type="text" name="wp_mcp_allowed_cpts" value="<?php echo esc_attr( implode( ',', (array) $allowed_cpts ) ); ?>" class="regular-text" />
                    <p class="description"><?php esc_html_e( 'Example: product,book', 'wp-mcp-server' ); ?></p>
                </td>
            </tr>
        </table>

        <?php submit_button(); ?>
    </form>
</div>

<script>
document.getElementById('wp-mcp-copy-endpoint').addEventListener('click', function(){
    var text = '<?php echo esc_js( rtrim( $site_url, '/' ) . '/wp-json/wp-mcp/v1/mcp' ); ?>';
    navigator.clipboard && navigator.clipboard.writeText(text);
    alert('Copied: ' + text);
});

document.getElementById('wp_mcp_test_button').addEventListener('click', function(){
    var user = document.getElementById('wp_mcp_test_username').value;
    var pass = document.getElementById('wp_mcp_test_app_password').value;
    var data = new FormData();
    data.append('action','wp_mcp_test_connection');
    data.append('username', user);
    data.append('app_password', pass);
    data.append('nonce', '<?php echo wp_create_nonce( 'wp_mcp_nonce' ); ?>');

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', { method: 'POST', credentials: 'same-origin', body: data })
    .then(r=>r.json()).then(j=>{
        var el = document.getElementById('wp_mcp_test_result');
        if(j.success){ el.innerText = 'OK ('+j.data.code+')'; } else { el.innerText = 'Error: '+j.data; }
    });
});
</script>
