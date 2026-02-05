<?php
/**
 * 手动激活脚本
 */
require_once('../../../wp-load.php');

// 只有管理员可以访问
if (!current_user_can('manage_options')) {
    die('权限不足');
}

echo "<h1>腾讯云短信插件手动激活</h1>";

// 定义插件路径
define('TCSMS_PLUGIN_DIR', dirname(__FILE__) . '/');
define('TCSMS_VERSION', '1.0.0');

// 包含必要的文件
$core_file = TCSMS_PLUGIN_DIR . 'includes/class-core.php';
$db_file = TCSMS_PLUGIN_DIR . 'includes/class-db.php';

if (!file_exists($core_file) || !file_exists($db_file)) {
    die("<p style='color:red;'>错误：找不到必要的类文件</p>");
}

require_once $core_file;
require_once $db_file;

echo "<h2>步骤1：检查数据库表</h2>";
global $wpdb;

// 检查表是否存在
$table_name = $wpdb->prefix . 'tcsms_codes';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

if ($table_exists) {
    echo "<p style='color:green;'>✓ 验证码表已存在</p>";
} else {
    echo "<p style='color:orange;'>⚠ 验证码表不存在，正在创建...</p>";
    
    // 创建表
    TCSMS_DB::create_tables();
    
    // 再次检查
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    
    if ($table_exists) {
        echo "<p style='color:green;'>✓ 验证码表创建成功</p>";
    } else {
        echo "<p style='color:red;'>✗ 验证码表创建失败</p>";
    }
}

// 检查统计表
$stats_table = $wpdb->prefix . 'tcsms_stats';
$stats_exists = $wpdb->get_var("SHOW TABLES LIKE '$stats_table'") == $stats_table;

if ($stats_exists) {
    echo "<p style='color:green;'>✓ 统计表已存在</p>";
} else {
    echo "<p style='color:orange;'>⚠ 统计表不存在</p>";
}

echo "<h2>步骤2：检查默认设置</h2>";

$defaults = [
    'tcsms_code_expiry' => 5,
    'tcsms_region' => 'ap-guangzhou',
    'tcsms_enable_login' => 0,
    'tcsms_rate_limit' => 60,
    'tcsms_max_attempts' => 10,
    'tcsms_version' => TCSMS_VERSION
];

foreach ($defaults as $key => $value) {
    $current_value = get_option($key);
    
    if ($current_value === false) {
        echo "<p style='color:orange;'>⚠ {$key} 未设置，正在设置默认值：{$value}</p>";
        add_option($key, $value);
    } else {
        echo "<p style='color:green;'>✓ {$key} 已设置，当前值：{$current_value}</p>";
    }
}

echo "<h2>步骤3：检查定时任务</h2>";

// 检查清理任务
$timestamp = wp_next_scheduled('tcsms_daily_cleanup');
if ($timestamp) {
    echo "<p style='color:green;'>✓ 每日清理任务已安排，下次执行：" . date('Y-m-d H:i:s', $timestamp) . "</p>";
} else {
    echo "<p style='color:orange;'>⚠ 每日清理任务未安排，正在安排...</p>";
    wp_schedule_event(time(), 'daily', 'tcsms_daily_cleanup');
    echo "<p style='color:green;'>✓ 每日清理任务已安排</p>";
}

echo "<h2>步骤4：数据库表结构</h2>";

if ($table_exists) {
    $columns = $wpdb->get_results("DESCRIBE $table_name");
    
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>字段</th><th>类型</th><th>允许NULL</th><th>键</th><th>默认值</th><th>额外</th></tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col->Field}</td>";
        echo "<td>{$col->Type}</td>";
        echo "<td>{$col->Null}</td>";
        echo "<td>{$col->Key}</td>";
        echo "<td>{$col->Default}</td>";
        echo "<td>{$col->Extra}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<h2>步骤5：测试数据库操作</h2>";

// 测试插入数据
$test_data = [
    'phone' => '13800138000',
    'code' => '123456',
    'ip_address' => '127.0.0.1',
    'expires_at' => date('Y-m-d H:i:s', time() + 300),
    'verified' => 0,
    'created_at' => current_time('mysql')
];

$insert_result = $wpdb->insert($table_name, $test_data);

if ($insert_result) {
    echo "<p style='color:green;'>✓ 测试数据插入成功，ID：{$wpdb->insert_id}</p>";
    
    // 测试查询
    $test_record = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $wpdb->insert_id
    ));
    
    if ($test_record) {
        echo "<p style='color:green;'>✓ 测试数据查询成功</p>";
        
        // 清理测试数据
        $delete_result = $wpdb->delete($table_name, ['id' => $wpdb->insert_id]);
        if ($delete_result) {
            echo "<p style='color:green;'>✓ 测试数据清理成功</p>";
        }
    }
} else {
    echo "<p style='color:red;'>✗ 测试数据插入失败，错误：{$wpdb->last_error}</p>";
}

echo "<h2>完成</h2>";
echo "<p><a href='javascript:location.reload()'>刷新页面重新检查</a></p>";
echo "<p><a href='" . admin_url('plugins.php') . "'>返回插件页面</a></p>";
?>