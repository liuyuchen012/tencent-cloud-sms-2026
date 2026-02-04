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

// 防止插件被重复加载
if (class_exists('TCSMS_Core')) {
    wp_die('腾讯云短信插件已被加载，请检查插件冲突。');
}

// 定义插件常量
define('TCSMS_VERSION', '1.0.0');
define('TCSMS_PLUGIN_FILE', __FILE__);
define('TCSMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TCSMS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TCSMS_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * 类自动加载器
 * 支持：TCSMS_Core, TCSMS_Settings, TCSMS_API, TCSMS_DB
 */
if (!function_exists('tcsms_autoloader')) {
    function tcsms_autoloader($class_name) {
        // 只加载TCSMS_开头的类
        if (strpos($class_name, 'TCSMS_') === 0) {
            $class_file = str_replace('_', '-', strtolower(substr($class_name, 6)));
            $file_path = TCSMS_PLUGIN_DIR . 'includes/class-' . $class_file . '.php';
            
            if (file_exists($file_path)) {
                require_once $file_path;
            } else {
                // 调试信息
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('腾讯云短信插件：未找到类文件: ' . $file_path);
                }
            }
        }
    }
}

/**
 * 获取插件实例
 * 
 * @return TCSMS_Core 插件核心实例
 */
if (!function_exists('tcsms')) {
    function tcsms() {
        return TCSMS_Core::get_instance();
    }
}

/**
 * 初始化插件
 */
if (!function_exists('tcsms_init')) {
    function tcsms_init() {
        // 检查Composer依赖是否已安装
        $composer_autoload = TCSMS_PLUGIN_DIR . 'vendor/autoload.php';
        
        if (!file_exists($composer_autoload)) {
            // 显示警告但继续加载
            if (is_admin() && current_user_can('manage_options')) {
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
        
        // 确保核心类文件存在
        $core_file = TCSMS_PLUGIN_DIR . 'includes/class-core.php';
        if (!file_exists($core_file)) {
            if (is_admin()) {
                add_action('admin_notices', function() {
                    ?>
                    <div class="notice notice-error">
                        <p><strong>腾讯云短信插件错误：</strong> 核心类文件不存在，请重新安装插件。</p>
                    </div>
                    <?php
                });
            }
            return;
        }
        
        // 加载核心类
        require_once $core_file;
        
        // 检查核心类是否已加载
        if (!class_exists('TCSMS_Core')) {
            if (is_admin()) {
                add_action('admin_notices', function() {
                    ?>
                    <div class="notice notice-error">
                        <p><strong>腾讯云短信插件错误：</strong> 核心类加载失败。</p>
                    </div>
                    <?php
                });
            }
            return;
        }
        
        // 加载文本域
        load_plugin_textdomain('tencent-cloud-sms', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        try {
            // 获取插件实例并初始化
            $tcsms = tcsms();
            $tcsms->init();
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('腾讯云短信插件初始化失败: ' . $e->getMessage());
            }
            
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

/**
 * 短代码表单辅助函数
 * 
 * @param array $atts 短代码属性
 * @return string HTML表单
 */
if (!function_exists('tcsms_shortcode_form')) {
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

// 注册激活和停用钩子
register_activation_hook(__FILE__, function() {
    // 在激活时检查核心类
    if (!class_exists('TCSMS_Core')) {
        $core_file = TCSMS_PLUGIN_DIR . 'includes/class-core.php';
        if (file_exists($core_file)) {
            require_once $core_file;
        }
    }
    
    if (class_exists('TCSMS_Core')) {
        TCSMS_Core::activate();
    }
});

register_deactivation_hook(__FILE__, function() {
    if (class_exists('TCSMS_Core')) {
        TCSMS_Core::deactivate();
    }
});

// 注册短代码
add_shortcode('tcsms_form', 'tcsms_shortcode_form');

// 注册自动加载器
spl_autoload_register('tcsms_autoloader');

// 初始化插件
add_action('plugins_loaded', 'tcsms_init', 10);