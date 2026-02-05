<?php
/**
 * Plugin Name: 腾讯云短信验证码
 * Plugin URI: https://github.com/liuyuchen012/tencent-cloud-sms-2026?tab=readme-ov-file
 * Description: 集成腾讯云短信服务，支持验证码发送、验证和登录安全增强。适用于最新版WordPress。
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://github.com/liuyuchen012/
 * License: gpl-3.0
 * License URI: https://gnu.ac.cn/licenses/gpl-3.0.html
 * Text Domain: tencent-cloud-sms
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * 
 * @package TencentCloudSMS
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// ==================== 关键修复：防止重复加载 ====================
// 如果插件已经加载过，直接退出
if (defined('TCSMS_PLUGIN_FILE')) {
    return; // 不执行任何代码，直接退出
}
// ===============================================================

// 定义插件常量
define('TCSMS_VERSION', '1.0.0');
define('TCSMS_PLUGIN_FILE', __FILE__);
define('TCSMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TCSMS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TCSMS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// ==================== 插件激活和停用函数 ====================
if (!function_exists('tcsms_activate')) {
    /**
     * 插件激活函数
     */
    function tcsms_activate() {
        error_log('腾讯云短信插件：正在激活...');
        
        // 确保必要的类文件已加载
        $core_file = TCSMS_PLUGIN_DIR . 'includes/class-core.php';
        $db_file = TCSMS_PLUGIN_DIR . 'includes/class-db.php';
        
        if (file_exists($core_file) && file_exists($db_file)) {
            require_once $core_file;
            require_once $db_file;
            
            // 创建数据库表
            TCSMS_DB::create_tables();
            
            // 设置默认选项
            $defaults = [
                'tcsms_code_expiry' => 5,
                'tcsms_region' => 'ap-guangzhou',
                'tcsms_enable_login' => 0,
                'tcsms_rate_limit' => 60,
                'tcsms_max_attempts' => 10
            ];
            
            foreach ($defaults as $key => $value) {
                if (get_option($key) === false) {
                    add_option($key, $value);
                }
            }
            
            // 设置版本号
            update_option('tcsms_version', TCSMS_VERSION);
            
            // 添加清理任务 - 使用WordPress时间
            if (!wp_next_scheduled('tcsms_daily_cleanup')) {
                // 使用UTC时间午夜，避免时区问题
                $utc_now = current_time('timestamp', 1); // 获取UTC时间戳
                $utc_midnight = strtotime('tomorrow midnight', $utc_now);
                wp_schedule_event($utc_midnight, 'daily', 'tcsms_daily_cleanup');
            }
            
            error_log('腾讯云短信插件：激活完成');
        } else {
            error_log('腾讯云短信插件：激活失败，类文件不存在');
        }
    }
}

if (!function_exists('tcsms_deactivate')) {
    /**
     * 插件停用函数
     */
    function tcsms_deactivate() {
        error_log('腾讯云短信插件：正在停用...');
        
        // 清理定时任务
        wp_clear_scheduled_hook('tcsms_daily_cleanup');
        
        error_log('腾讯云短信插件：停用完成');
    }
}

// 注册激活和停用钩子
register_activation_hook(__FILE__, 'tcsms_activate');
register_deactivation_hook(__FILE__, 'tcsms_deactivate');
// ===============================================================

// 检查Composer依赖是否已安装
add_action('plugins_loaded', function() {
    $composer_autoload = TCSMS_PLUGIN_DIR . 'vendor/autoload.php';
    
    if (!file_exists($composer_autoload)) {
        // 显示错误提示但允许插件继续加载（测试模式可用）
        if (is_admin()) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-warning">
                    <p><strong>腾讯云短信插件：</strong> 缺少Composer依赖包。如需完整功能，请在插件目录运行：<code>composer install</code></p>
                </div>
                <?php
            });
        }
    } else {
        // 加载Composer自动加载器
        require_once $composer_autoload;
    }
}, 5);

// 类自动加载器
if (!function_exists('tcsms_autoloader')) {
    /**
     * 类自动加载器
     * 支持：TCSMS_Core, TCSMS_Settings, TCSMS_API, TCSMS_DB
     */
    function tcsms_autoloader($class_name) {
        // 只加载TCSMS_开头的类
        if (strpos($class_name, 'TCSMS_') === 0) {
            $class_file = str_replace('_', '-', strtolower(substr($class_name, 6)));
            $file_path = TCSMS_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';
            
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                // 调试信息
                error_log('腾讯云短信插件：未找到类文件: ' . $file_path);
            }
        }
    }
    
    // 注册自动加载器
    spl_autoload_register('tcsms_autoloader');
}

// 强制在登录页面使用HTTPS的AJAX URL
add_filter('admin_url', function($url, $path, $blog_id) {
    if (strpos($path, 'admin-ajax.php') !== false && is_ssl()) {
        $url = str_replace('http://', 'https://', $url);
    }
    return $url;
}, 10, 3);

// 获取插件实例函数
if (!function_exists('tcsms')) {
    /**
     * 获取插件实例
     * 
     * @return TCSMS_Core 插件核心实例
     */
    function tcsms() {
        return TCSMS_Core::get_instance();
    }
}

// 初始化插件函数
if (!function_exists('tcsms_init')) {
    /**
     * 初始化插件
     */
    function tcsms_init() {
        // 确保核心类已加载
        if (!class_exists('TCSMS_Core')) {
            $core_file = TCSMS_PLUGIN_DIR . 'includes/class-core.php';
            if (file_exists($core_file)) {
                require_once $core_file;
            } else {
                error_log('腾讯云短信插件：核心类文件不存在');
                return;
            }
        }
        
        // 加载文本域
        load_plugin_textdomain('tencent-cloud-sms', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // 获取插件实例并初始化
        try {
            $tcsms = tcsms();
            $tcsms->init();
        } catch (Exception $e) {
            error_log('腾讯云短信插件初始化失败: ' . $e->getMessage());
            
            if (is_admin()) {
                add_action('admin_notices', function() use ($e) {
                    ?>
                    <div class="notice notice-error">
                        <p><strong>腾讯云短信插件错误：</strong> <?php echo esc_html($e->getMessage()); ?></p>
                    </div>
                    <?php
                });
            }
        }
    }
}

// 短代码表单辅助函数
if (!function_exists('tcsms_shortcode_form')) {
    /**
     * 短代码表单辅助函数
     * 
     * @param array $atts 短代码属性
     * @return string HTML表单
     */
    function tcsms_shortcode_form($atts = []) {
        $atts = shortcode_atts([
            'title' => __('短信验证', 'tencent-cloud-sms'),
            'phone_label' => __('手机号码', 'tencent-cloud-sms'),
            'code_label' => __('验证码', 'tencent-cloud-sms'),
            'button_text' => __('获取验证码', 'tencent-cloud-sms'),
            'submit_text' => __('验证', 'tencent-cloud-sms'),
            'class' => 'tcsms-form'
        ], $atts, 'tcsms_form');
        
        ob_start();
        ?>
        <div class="tcsms-form-container <?php echo esc_attr($atts['class']); ?>">
            <?php if (!empty($atts['title'])): ?>
                <h3 class="tcsms-form-title"><?php echo esc_html($atts['title']); ?></h3>
            <?php endif; ?>
            
            <div class="tcsms-form-group">
                <label for="tcsms_phone_<?php echo uniqid(); ?>">
                    <?php echo esc_html($atts['phone_label']); ?>
                </label>
                <input type="tel" 
                       id="tcsms_phone_<?php echo uniqid(); ?>" 
                       class="tcsms-phone-input" 
                       pattern="1[3-9]\d{9}" 
                       maxlength="11" 
                       placeholder="<?php esc_attr_e('请输入手机号码', 'tencent-cloud-sms'); ?>" 
                       required>
            </div>
            
            <div class="tcsms-form-group">
                <label for="tcsms_code_<?php echo uniqid(); ?>">
                    <?php echo esc_html($atts['code_label']); ?>
                </label>
                <div class="tcsms-code-container">
                    <input type="text" 
                           id="tcsms_code_<?php echo uniqid(); ?>" 
                           class="tcsms-code-input" 
                           maxlength="6" 
                           placeholder="<?php esc_attr_e('请输入验证码', 'tencent-cloud-sms'); ?>" 
                           required>
                    <button type="button" 
                            class="tcsms-send-btn button">
                        <?php echo esc_html($atts['button_text']); ?>
                    </button>
                </div>
            </div>
            
            <div class="tcsms-form-group">
                <button type="button" 
                        class="tcsms-verify-btn button button-primary">
                    <?php echo esc_html($atts['submit_text']); ?>
                </button>
            </div>
            
            <div class="tcsms-message" style="display: none;"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// 初始化插件
add_action('plugins_loaded', 'tcsms_init', 10);

// 注册短代码
add_shortcode('tcsms_form', 'tcsms_shortcode_form');

// ==================== 时间辅助函数 ====================
if (!function_exists('tcsms_get_current_time')) {
    /**
     * 获取当前时间（统一使用WordPress时间）
     * 
     * @param string $format 时间格式
     * @return string 格式化后的时间
     */
    function tcsms_get_current_time($format = 'Y-m-d H:i:s') {
        // 使用WordPress的时间函数，它会正确处理时区
        return current_time($format);
    }
}

if (!function_exists('tcsms_get_current_timestamp')) {
    /**
     * 获取当前时间戳（统一使用WordPress时间）
     * 
     * @return int 时间戳
     */
    function tcsms_get_current_timestamp() {
        // 使用WordPress的时间函数，它会正确处理时区
        return current_time('timestamp');
    }
}

if (!function_exists('tcsms_calculate_expiry_time')) {
    /**
     * 计算过期时间
     * 
     * @param int $minutes 分钟数
     * @return string 过期时间
     */
    function tcsms_calculate_expiry_time($minutes = 5) {
        // 获取当前时间戳
        $current_timestamp = tcsms_get_current_timestamp();
        
        // 计算过期时间戳（当前时间 + 分钟数 * 60秒）
        $expiry_timestamp = $current_timestamp + ($minutes * 60);
        
        // 返回格式化的时间
        return date('Y-m-d H:i:s', $expiry_timestamp);
    }
}