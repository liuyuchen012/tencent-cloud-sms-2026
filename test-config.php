<?php
/**
 * 配置测试页面
 */
require_once('../../../wp-load.php');

// 只有管理员可以访问
if (!current_user_can('manage_options')) {
    die('权限不足');
}

// 检查腾讯云SDK是否可用
$sdk_available = class_exists('TencentCloud\\Common\\Credential');
?>
<!DOCTYPE html>
<html>
<head>
    <title>腾讯云短信配置测试</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
        .section { margin-bottom: 30px; border: 1px solid #ddd; padding: 20px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>腾讯云短信配置测试</h1>
    
    <div class="section">
        <h2>1. 配置状态</h2>
        <?php
        $configs = [
            'SecretId' => get_option('tcsms_secret_id'),
            'SecretKey' => get_option('tcsms_secret_key'),
            'SDK AppId' => get_option('tcsms_sdk_app_id'),
            '短信签名' => get_option('tcsms_sign_name'),
            '模板ID' => get_option('tcsms_template_id'),
            '区域' => get_option('tcsms_region', 'ap-guangzhou'),
        ];
        
        foreach ($configs as $name => $value) {
            $status = !empty($value) ? '✓' : '✗';
            $class = !empty($value) ? 'success' : 'error';
            echo "<p class='$class'>$status $name: " . ($value ?: '<em>未设置</em>') . "</p>";
        }
        ?>
    </div>
    
    <div class="section">
        <h2>2. SDK状态</h2>
        <?php
        if ($sdk_available) {
            echo '<p class="success">✓ TencentCloud SDK 已加载</p>';
            
            // 测试创建凭证
            try {
                $secret_id = get_option('tcsms_secret_id');
                $secret_key = get_option('tcsms_secret_key');
                
                if (!empty($secret_id) && !empty($secret_key)) {
                    $cred = new TencentCloud\Common\Credential($secret_id, $secret_key);
                    echo '<p class="success">✓ 凭证创建成功</p>';
                    
                    // 测试创建客户端
                    $region = get_option('tcsms_region', 'ap-guangzhou');
                    $httpProfile = new TencentCloud\Common\Profile\HttpProfile();
                    $httpProfile->setEndpoint("sms.tencentcloudapi.com");
                    
                    $clientProfile = new TencentCloud\Common\Profile\ClientProfile();
                    $clientProfile->setHttpProfile($httpProfile);
                    
                    $client = new TencentCloud\Sms\V20210111\SmsClient($cred, $region, $clientProfile);
                    echo '<p class="success">✓ SMS客户端创建成功</p>';
                } else {
                    echo '<p class="warning">⚠ 无法创建凭证（API密钥未设置）</p>';
                }
            } catch (Exception $e) {
                echo '<p class="error">✗ 创建客户端失败: ' . $e->getMessage() . '</p>';
            }
        } else {
            echo '<p class="error">✗ TencentCloud SDK 未加载</p>';
            
            // 检查可能的路径
            echo '<h3>SDK文件检查</h3>';
            $possible_paths = [
                TCSMS_PLUGIN_DIR . 'vendor/autoload.php',
                TCSMS_PLUGIN_DIR . 'vendor/tencentcloud/tencentcloud-sdk-php/src/TencentCloud/Common/Credential.php',
                ABSPATH . 'vendor/autoload.php',
            ];
            
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    echo "<p class='success'>✓ 文件存在: $path</p>";
                } else {
                    echo "<p class='warning'>⚠ 文件不存在: $path</p>";
                }
            }
        }
        ?>
    </div>
    
    <div class="section">
        <h2>3. Composer状态</h2>
        <?php
        $composer_path = TCSMS_PLUGIN_DIR . 'vendor/autoload.php';
        if (file_exists($composer_path)) {
            echo '<p class="success">✓ Composer autoloader 存在</p>';
            
            // 检查vendor/tencentcloud目录
            $tencentcloud_path = TCSMS_PLUGIN_DIR . 'vendor/tencentcloud';
            if (is_dir($tencentcloud_path)) {
                echo '<p class="success">✓ tencentcloud目录存在</p>';
                
                // 列出vendor/tencentcloud目录内容
                $files = scandir($tencentcloud_path);
                echo '<p>目录内容: ' . implode(', ', $files) . '</p>';
            } else {
                echo '<p class="error">✗ tencentcloud目录不存在</p>';
            }
        } else {
            echo '<p class="error">✗ Composer autoloader 不存在</p>';
            echo '<p>请运行: <code>composer require tencentcloud/tencentcloud-sdk-php</code></p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>4. 数据库状态</h2>
        <?php
        global $wpdb;
        $table_name = $wpdb->prefix . 'tcsms_codes';
        
        // 检查表是否存在
        $result = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if ($result == $table_name) {
            echo '<p class="success">✓ 数据库表存在</p>';
            
            // 检查表结构
            $columns = $wpdb->get_results("DESCRIBE $table_name");
            echo '<p>表结构：</p><pre>';
            foreach ($columns as $column) {
                echo $column->Field . ' | ' . $column->Type . ' | ' . $column->Null . ' | ' . $column->Key . ' | ' . $column->Default . ' | ' . $column->Extra . "\n";
            }
            echo '</pre>';
        } else {
            echo '<p class="error">✗ 数据库表不存在</p>';
            echo '<p>需要激活插件来创建表</p>';
        }
        ?>
    </div>
    
    <div class="section">
        <h2>5. 测试模式</h2>
        <p>当前模式: <?php echo $sdk_available ? '生产模式（使用腾讯云SDK）' : '测试模式（模拟发送）'; ?></p>
        
        <?php if (!$sdk_available): ?>
        <p class="warning">⚠ 由于SDK未加载，插件将使用测试模式</p>
        <p>在测试模式下，验证码会生成并保存到数据库，但不会实际发送短信</p>
        <p>测试验证码会在浏览器控制台显示</p>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>6. PHP扩展检查</h2>
        <?php
        $extensions = [
            'curl' => 'cURL扩展（用于HTTP请求）',
            'openssl' => 'OpenSSL扩展（用于加密）',
            'json' => 'JSON扩展（用于数据处理）',
            'mbstring' => 'mbstring扩展（用于字符串处理）',
        ];
        
        foreach ($extensions as $ext => $desc) {
            if (extension_loaded($ext)) {
                echo "<p class='success'>✓ $desc</p>";
            } else {
                echo "<p class='error'>✗ $desc</p>";
            }
        }
        ?>
    </div>
    
    <div class="section">
        <h2>7. 服务器环境</h2>
        <pre>
PHP版本: <?php echo phpversion(); ?>

WordPress版本: <?php echo $GLOBALS['wp_version']; ?>

Web服务器: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? '未知'; ?>

最大执行时间: <?php echo ini_get('max_execution_time'); ?>秒

内存限制: <?php echo ini_get('memory_limit'); ?>

cURL版本: <?php echo function_exists('curl_version') ? curl_version()['version'] : '不可用'; ?>

OpenSSL版本: <?php echo OPENSSL_VERSION_TEXT; ?>
        </pre>
    </div>
</body>
</html>