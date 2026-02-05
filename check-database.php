<?php
/**
 * 数据库检查工具
 */
require_once('../../../wp-load.php');

// 只有管理员可以访问
if (!current_user_can('manage_options')) {
    die('权限不足');
}

global $wpdb;
$table_name = $wpdb->prefix . 'tcsms_codes';

echo "<h1>验证码数据库检查</h1>";

// 检查表是否存在
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

if (!$table_exists) {
    echo "<p style='color:red;'>数据库表不存在！</p>";
    exit;
}

echo "<p style='color:green;'>✓ 数据库表存在</p>";

// 显示表结构
echo "<h2>表结构</h2>";
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

// 显示最近的验证码记录
echo "<h2>最近的验证码记录（最近10条）</h2>";

$records = $wpdb->get_results(
    "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10"
);

if ($records) {
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr>
            <th>ID</th>
            <th>手机号</th>
            <th>验证码</th>
            <th>IP地址</th>
            <th>已验证</th>
            <th>创建时间</th>
            <th>过期时间</th>
            <th>状态</th>
          </tr>";
    
    $current_time = current_time('mysql');
    
    foreach ($records as $record) {
        $status = '未知';
        $style = '';
        
        if ($record->verified == 1) {
            $status = '已验证';
            $style = 'background-color: #d4edda;';
        } elseif ($record->expires_at < $current_time) {
            $status = '已过期';
            $style = 'background-color: #f8d7da;';
        } else {
            $status = '有效';
            $style = 'background-color: #fff3cd;';
        }
        
        echo "<tr style='$style'>";
        echo "<td>{$record->id}</td>";
        echo "<td>{$record->phone}</td>";
        echo "<td>{$record->code}</td>";
        echo "<td>{$record->ip_address}</td>";
        echo "<td>" . ($record->verified ? '是' : '否') . "</td>";
        echo "<td>{$record->created_at}</td>";
        echo "<td>{$record->expires_at}</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p>当前服务器时间：" . date('Y-m-d H:i:s') . "</p>";
    echo "<p>WordPress当前时间：" . $current_time . "</p>";
    echo "<p>时区设置：" . wp_timezone_string() . "</p>";
} else {
    echo "<p>没有验证码记录</p>";
}

// 显示统计信息
echo "<h2>统计信息</h2>";

$total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
$verified = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE verified = 1");
$expired = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE expires_at < '$current_time' AND verified = 0");
$active = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE expires_at > '$current_time' AND verified = 0");

echo "<ul>";
echo "<li>总记录数：$total</li>";
echo "<li>已验证：$verified</li>";
echo "<li>已过期：$expired</li>";
echo "<li>有效未验证：$active</li>";
echo "</ul>";

// 测试查询
echo "<h2>测试查询</h2>";

if (isset($_GET['test_phone']) && isset($_GET['test_code'])) {
    $test_phone = sanitize_text_field($_GET['test_phone']);
    $test_code = sanitize_text_field($_GET['test_code']);
    
    echo "<h3>测试验证码：$test_code（手机号：$test_phone）</h3>";
    
    // 直接执行SQL查询
    $sql = $wpdb->prepare(
        "SELECT * FROM $table_name 
         WHERE phone = %s AND code = %s AND verified = 0 AND expires_at > %s
         ORDER BY created_at DESC LIMIT 1",
        $test_phone, $test_code, $current_time
    );
    
    echo "<p>执行的SQL：<code>" . esc_html($sql) . "</code></p>";
    
    $result = $wpdb->get_row($sql);
    
    if ($result) {
        echo "<p style='color:green;'>✓ 找到匹配的记录</p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    } else {
        echo "<p style='color:red;'>✗ 没有找到匹配的记录</p>";
        
        // 显示该手机号的所有验证码
        $all_codes = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE phone = %s ORDER BY created_at DESC",
            $test_phone
        ));
        
        if ($all_codes) {
            echo "<h4>该手机号的所有验证码：</h4>";
            foreach ($all_codes as $code) {
                echo "验证码：{$code->code}，创建：{$code->created_at}，过期：{$code->expires_at}，已验证：" . ($code->verified ? '是' : '否') . "<br>";
            }
        }
    }
}

// 测试表单
echo "<h3>手动测试验证</h3>";
echo "<form method='get'>
    <input type='hidden' name='page' value='check-database'>
    <p>手机号：<input type='text' name='test_phone' value='13800138000'></p>
    <p>验证码：<input type='text' name='test_code'></p>
    <p><input type='submit' value='测试验证'></p>
</form>";

// 清理过期数据
echo "<h2>数据库维护</h2>";
echo "<p><a href='?clean=1'>清理过期验证码（过期超过1天的）</a></p>";

if (isset($_GET['clean'])) {
    $deleted = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$table_name} WHERE expires_at < DATE_SUB(%s, INTERVAL 1 DAY)",
            $current_time
        )
    );
    
    echo "<p>清理完成，删除了 $deleted 条过期记录</p>";
}
?>