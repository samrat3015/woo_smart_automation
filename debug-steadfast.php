<?php
/**
 * SteadFast Fraud Check - Debug & Test Script
 * 
 * Place this file in wp-content/plugins/woo-smart-automation/
 * Then access: yourdomain.com/wp-content/plugins/woo-smart-automation/debug-steadfast.php
 */

// Load WordPress
require_once(__DIR__ . '/../../../wp-load.php');

// Must be admin
if (!current_user_can('manage_options')) {
    die('Access denied. Admin only.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>SteadFast Debug - Woo Smart Shield</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #2271b1; border-bottom: 3px solid #2271b1; padding-bottom: 10px; }
        h2 { color: #135e96; margin-top: 30px; }
        .section { background: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #2271b1; }
        .success { color: #00a32a; font-weight: bold; }
        .error { color: #d63638; font-weight: bold; }
        .warning { color: #dba617; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #2271b1; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .code { background: #282c34; color: #abb2bf; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .btn { background: #2271b1; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #135e96; }
        .info-box { background: #e7f3ff; border-left: 4px solid #2271b1; padding: 12px; margin: 10px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 SteadFast Fraud Check - Debug Panel</h1>
    
    <?php
    // ============================================
    // 1. CHECK DATABASE TABLE
    // ============================================
    echo '<h2>1. Database Table Check</h2>';
    echo '<div class="section">';
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'woo_smart_courier_scores';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if ($table_exists) {
        echo '<span class="success">✓ Table exists: ' . $table_name . '</span><br><br>';
        
        // Count records
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo '<strong>Total Records:</strong> ' . $count . '<br><br>';
        
        // Show recent records
        if ($count > 0) {
            $records = $wpdb->get_results("SELECT * FROM $table_name ORDER BY last_checked DESC LIMIT 10");
            echo '<table>';
            echo '<tr><th>ID</th><th>Phone</th><th>Parcels</th><th>Delivered</th><th>Cancelled</th><th>Success Rate</th><th>Source</th><th>Last Checked</th></tr>';
            foreach ($records as $record) {
                echo '<tr>';
                echo '<td>' . $record->id . '</td>';
                echo '<td>' . $record->phone . '</td>';
                echo '<td>' . $record->total_parcels . '</td>';
                echo '<td>' . $record->total_delivered . '</td>';
                echo '<td>' . $record->total_cancelled . '</td>';
                echo '<td>' . $record->success_rate . '%</td>';
                echo '<td>' . $record->data_source . '</td>';
                echo '<td>' . $record->last_checked . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
    } else {
        echo '<span class="error">✗ Table NOT found: ' . $table_name . '</span><br>';
        echo '<div class="info-box">';
        echo '<strong>FIX:</strong> Deactivate and reactivate the plugin to create the table.<br>';
        echo 'Or run this SQL manually:';
        echo '<div class="code">';
        echo htmlspecialchars("CREATE TABLE {$table_name} (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    phone varchar(50) NOT NULL,
    total_parcels int(11) DEFAULT 0,
    total_delivered int(11) DEFAULT 0,
    total_cancelled int(11) DEFAULT 0,
    success_rate decimal(5,2) DEFAULT 0,
    data_source varchar(20) DEFAULT 'api',
    last_checked datetime DEFAULT CURRENT_TIMESTAMP,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY phone (phone),
    KEY last_checked (last_checked)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        echo '</div></div>';
    }
    echo '</div>';
    
    // ============================================
    // 2. CHECK SETTINGS
    // ============================================
    echo '<h2>2. Plugin Settings</h2>';
    echo '<div class="section">';
    echo '<table>';
    echo '<tr><th>Setting</th><th>Value</th><th>Status</th></tr>';
    
    $settings = [
        'wsa_steadfast_fraud_check_enabled' => get_option('wsa_steadfast_fraud_check_enabled', false),
        'wsa_steadfast_api_key' => get_option('wsa_steadfast_api_key', ''),
        'wsa_steadfast_secret_key' => get_option('wsa_steadfast_secret_key', ''),
        'wsa_steadfast_minimum_order_amount' => get_option('wsa_steadfast_minimum_order_amount', 1000),
        'wsa_steadfast_skip_repeat_customers' => get_option('wsa_steadfast_skip_repeat_customers', 1),
        'wsa_steadfast_web_scraping_enabled' => get_option('wsa_steadfast_web_scraping_enabled', 0),
        'wsa_steadfast_login_email' => get_option('wsa_steadfast_login_email', ''),
        'wsa_steadfast_login_password' => get_option('wsa_steadfast_login_password', ''),
    ];
    
    foreach ($settings as $key => $value) {
        echo '<tr>';
        echo '<td><strong>' . $key . '</strong></td>';
        
        if (strpos($key, 'password') !== false || strpos($key, 'secret') !== false) {
            $display = $value ? str_repeat('*', strlen($value)) : '<em>not set</em>';
        } else {
            $display = $value ? $value : '<em>not set</em>';
        }
        echo '<td>' . $display . '</td>';
        
        // Status
        if ($key === 'wsa_steadfast_fraud_check_enabled') {
            echo '<td>' . ($value ? '<span class="success">✓ Enabled</span>' : '<span class="error">✗ Disabled</span>') . '</td>';
        } elseif (strpos($key, '_enabled') !== false) {
            echo '<td>' . ($value ? '<span class="success">✓ ON</span>' : '<span class="warning">OFF</span>') . '</td>';
        } elseif ($value) {
            echo '<td><span class="success">✓ Set</span></td>';
        } else {
            echo '<td><span class="warning">Not set</span></td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    echo '</div>';
    
    // ============================================
    // 3. TEST API CONNECTION
    // ============================================
    echo '<h2>3. API Connection Test</h2>';
    echo '<div class="section">';
    
    if (isset($_GET['test_api'])) {
        require_once(WSA_PATH . 'includes/Modules/Courier/SteadfastAPIService.php');
        $api_service = new \WooSmartAutomation\Modules\Courier\SteadfastAPIService();
        
        echo '<strong>Testing with phone: 01700000000</strong><br><br>';
        $result = $api_service->get_customer_courier_score('01700000000', true);
        
        if ($result) {
            echo '<span class="success">✓ API Test Successful!</span><br><br>';
            echo '<pre style="background: #f9f9f9; padding: 10px; border-radius: 4px;">';
            print_r($result);
            echo '</pre>';
        } else {
            echo '<span class="error">✗ API Test Failed</span><br>';
            echo '<div class="info-box">';
            echo '<strong>Possible Reasons:</strong><br>';
            echo '1. API is disabled in settings<br>';
            echo '2. Invalid API credentials<br>';
            echo '3. Network error<br>';
            echo '4. Order amount below minimum (if testing from order)<br>';
            echo '5. API daily limit reached<br>';
            echo '</div>';
        }
    } else {
        echo '<a href="?test_api=1" class="btn">Run API Test</a>';
        echo '<p>This will test with phone number: 01700000000</p>';
    }
    echo '</div>';
    
    // ============================================
    // 4. CHECK RECENT ORDERS
    // ============================================
    echo '<h2>4. Recent WooCommerce Orders with Courier Data</h2>';
    echo '<div class="section">';
    
    $orders = wc_get_orders([
        'limit' => 10,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
    
    if ($orders) {
        echo '<table>';
        echo '<tr><th>Order ID</th><th>Phone</th><th>Total</th><th>Courier Data</th><th>Status</th></tr>';
        foreach ($orders as $order) {
            echo '<tr>';
            echo '<td>#' . $order->get_id() . '</td>';
            echo '<td>' . $order->get_billing_phone() . '</td>';
            echo '<td>' . $order->get_total() . ' ' . $order->get_currency() . '</td>';
            
            $courier_total = get_post_meta($order->get_id(), '_wsa_courier_total_orders', true);
            $courier_delivered = get_post_meta($order->get_id(), '_wsa_courier_delivered', true);
            $courier_success = get_post_meta($order->get_id(), '_wsa_courier_success_rate', true);
            $courier_source = get_post_meta($order->get_id(), '_wsa_courier_data_source', true);
            
            if ($courier_total !== '') {
                echo '<td>';
                echo 'Total: ' . $courier_total . '<br>';
                echo 'Delivered: ' . $courier_delivered . '<br>';
                echo 'Success: ' . $courier_success . '%<br>';
                echo 'Source: ' . ($courier_source ?: 'api');
                echo '</td>';
                echo '<td><span class="success">✓ Checked</span></td>';
            } else {
                echo '<td><em>No data</em></td>';
                echo '<td><span class="warning">Not checked</span></td>';
            }
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>No orders found.</p>';
    }
    echo '</div>';
    
    // ============================================
    // 5. DIAGNOSTICS
    // ============================================
    echo '<h2>5. System Diagnostics</h2>';
    echo '<div class="section">';
    echo '<table>';
    echo '<tr><th>Check</th><th>Status</th></tr>';
    
    // WordPress version
    echo '<tr><td>WordPress Version</td><td>' . get_bloginfo('version') . '</td></tr>';
    
    // WooCommerce
    echo '<tr><td>WooCommerce Active</td><td>' . (class_exists('WooCommerce') ? '<span class="success">✓ Yes</span>' : '<span class="error">✗ No</span>') . '</td></tr>';
    
    // Plugin version
    echo '<tr><td>Plugin Version</td><td>' . WSA_VERSION . '</td></tr>';
    
    // DB version
    $db_version = get_option('wsa_db_version');
    echo '<tr><td>Database Version</td><td>' . ($db_version ?: '<span class="warning">Not set</span>') . '</td></tr>';
    
    // PHP version
    echo '<tr><td>PHP Version</td><td>' . PHP_VERSION . '</td></tr>';
    
    // Cron jobs
    $next_cleanup = wp_next_scheduled('wsa_cleanup_old_orders');
    echo '<tr><td>Cleanup Cron</td><td>' . ($next_cleanup ? '<span class="success">✓ Scheduled for ' . date('Y-m-d H:i:s', $next_cleanup) . '</span>' : '<span class="warning">Not scheduled</span>') . '</td></tr>';
    
    echo '</table>';
    echo '</div>';
    
    // ============================================
    // 6. QUICK ACTIONS
    // ============================================
    echo '<h2>6. Quick Actions</h2>';
    echo '<div class="section">';
    
    if (isset($_GET['create_table'])) {
        require_once(WSA_PATH . 'includes/Core/Database.php');
        \WooSmartAutomation\Core\Database::activate();
        echo '<div class="info-box"><span class="success">✓ Table creation attempted. Refresh page to verify.</span></div>';
    }
    
    if (isset($_GET['clear_cache'])) {
        global $wpdb;
        $deleted = $wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '%wsa_stdf_score_%'");
        echo '<div class="info-box"><span class="success">✓ Cleared ' . $deleted . ' cached entries.</span></div>';
    }
    
    echo '<a href="?create_table=1" class="btn">Create Database Table</a>';
    echo '<a href="?test_api=1" class="btn">Test API</a>';
    echo '<a href="?clear_cache=1" class="btn">Clear Cache</a>';
    echo '<a href="' . admin_url('admin.php?page=wsa-courier-settings') . '" class="btn">Settings Page</a>';
    echo '</div>';
    
    ?>
    
    <h2>7. Debug Information</h2>
    <div class="section">
        <div class="code">
Plugin Path: <?php echo WSA_PATH; ?>

Database Table: <?php echo $table_name; ?>

Settings URL: <?php echo admin_url('admin.php?page=wsa-courier-settings'); ?>

Debug Log: wp-content/debug.log
        </div>
    </div>
    
    <div class="info-box" style="margin-top: 30px;">
        <strong>💡 Troubleshooting Tips:</strong><br>
        1. If table doesn't exist: Click "Create Database Table" button above<br>
        2. If API fails: Check API credentials in Settings page<br>
        3. If no courier data on orders: Check minimum order amount (default 1000 BDT)<br>
        4. Enable WP_DEBUG and check wp-content/debug.log for detailed errors<br>
    </div>
</div>
</body>
</html>
