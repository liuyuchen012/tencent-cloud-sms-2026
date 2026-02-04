<?php
/**
 * 插件核心类
 * 
 * @package TencentCloudSMS
 */

if (!defined('ABSPATH')) {
    exit;
}

class TCSMS_Core {
    
    /**
     * 插件实例
     * 
     * @var TCSMS_Core
     */
    private static $instance = null;
    
    /**
     * 设置类实例
     * 
     * @var TCSMS_Settings
     */
    public $settings;
    
    /**
     * API类实例
     * 
     * @var TCSMS_API
     */
    public $api;
    
    /**
     * 数据库类实例
     * 
     * @var TCSMS_DB
     */
    public $db;
    
    /**
     * 获取插件实例
     * 
     * @return TCSMS_Core
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 私有构造函数
     */
    private function __construct() {
        // 防止外部实例化
    }
    
    /**
     * 初始化插件
     */
    public function init() {
        // 初始化组件
        $this->db = new TCSMS_DB();
        $this->settings = new TCSMS_Settings();
        $this->api = new TCSMS_API();
        
        // 注册钩子
        $this->register_hooks();
        
        // 检查依赖
        $this->check_dependencies();
    }
    
    /**
     * 注册WordPress钩子
     */
    private function register_hooks() {
        // 前端脚本
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        
        // 后台脚本
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // AJAX处理
        add_action('wp_ajax_tcsms_send_verification', [$this->api, 'ajax_send_verification']);
        add_action('wp_ajax_nopriv_tcsms_send_verification', [$this->api, 'ajax_send_verification']);
        add_action('wp_ajax_tcsms_verify_code', [$this->api, 'ajax_verify_code']);
        add_action('wp_ajax_nopriv_tcsms_verify_code', [$this->api, 'ajax_verify_code']);
        
        // 短代码
        add_shortcode('tcsms_form', [$this, 'shortcode_sms_form']);
        
        // 登录集成
        if (get_option('tcsms_enable_login', false)) {
            add_action('login_form', [$this, 'add_sms_to_login_form']);
            add_filter('authenticate', [$this, 'verify_login_sms'], 30, 3);
        }
        
        // 清理过期验证码
        add_action('tcsms_daily_cleanup', [$this->db, 'clean_expired_codes']);
        
        // 插件链接
        add_filter('plugin_action_links_' . TCSMS_PLUGIN_BASENAME, [$this, 'add_plugin_links']);
    }
    
    /**
     * 检查依赖
     */
    private function check_dependencies() {
        // 检查PHP版本
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php 
                        printf(
                            __('腾讯云短信插件需要 PHP 7.4 或更高版本。当前版本：%s', 'tencent-cloud-sms'),
                            PHP_VERSION
                        );
                    ?></p>
                </div>
                <?php
            });
        }
        
        // 检查cURL扩展
        if (!extension_loaded('curl')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('腾讯云短信插件需要 cURL 扩展支持。', 'tencent-cloud-sms'); ?></p>
                </div>
                <?php
            });
        }
        
        // 检查OpenSSL扩展
        if (!extension_loaded('openssl')) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php _e('腾讯云短信插件需要 OpenSSL 扩展支持。', 'tencent-cloud-sms'); ?></p>
                </div>
                <?php
            });
        }
    }
    
    /**
     * 加载前端脚本
     */
    public function enqueue_frontend_scripts() {
        // 样式
        wp_enqueue_style(
            'tcsms-frontend',
            TCSMS_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            TCSMS_VERSION
        );
        
        // 脚本
        wp_enqueue_script(
            'tcsms-frontend',
            TCSMS_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            TCSMS_VERSION,
            true
        );
        
        // 本地化脚本
        wp_localize_script('tcsms-frontend', 'tcsms_frontend', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tcsms_nonce'),
            'texts' => [
                'sending' => __('发送中...', 'tencent-cloud-sms'),
                'resend' => __('重新发送', 'tencent-cloud-sms'),
                'countdown' => __('秒后重试', 'tencent-cloud-sms'),
                'invalid_phone' => __('请输入有效的手机号码', 'tencent-cloud-sms'),
                'success' => __('发送成功', 'tencent-cloud-sms'),
                'failed' => __('发送失败', 'tencent-cloud-sms')
            ]
        ]);
    }
    
    /**
     * 加载后台脚本
     */
    public function enqueue_admin_scripts($hook) {
        if ('settings_page_tencent-cloud-sms' !== $hook) {
            return;
        }
        
        // 样式
        wp_enqueue_style(
            'tcsms-admin',
            TCSMS_PLUGIN_URL . 'assets/css/admin.css',
            [],
            TCSMS_VERSION
        );
        
        // 脚本
        wp_enqueue_script(
            'tcsms-admin',
            TCSMS_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            TCSMS_VERSION,
            true
        );
        
        // 本地化脚本
        wp_localize_script('tcsms-admin', 'tcsms_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tcsms_admin_nonce'),
            'texts' => [
                'test_sending' => __('测试发送中...', 'tencent-cloud-sms'),
                'test_success' => __('测试短信发送成功', 'tencent-cloud-sms'),
                'test_failed' => __('测试发送失败', 'tencent-cloud-sms')
            ]
        ]);
    }
    
    /**
     * 短信表单短代码
     */
    public function shortcode_sms_form($atts) {
        $atts = shortcode_atts([
            'title' => __('短信验证', 'tencent-cloud-sms'),
            'phone_label' => __('手机号码', 'tencent-cloud-sms'),
            'code_label' => __('验证码', 'tencent-cloud-sms'),
            'button_text' => __('获取验证码', 'tencent-cloud-sms'),
            'submit_text' => __('验证', 'tencent-cloud-sms'),
            'class' => 'tcsms-form'
        ], $atts, 'tcsms_form');
        
        ob_start();
        include TCSMS_PLUGIN_DIR . 'templates/shortcode-form.php';
        return ob_get_clean();
    }
    
    /**
     * 在登录表单中添加短信验证
     */
    public function add_sms_to_login_form() {
        if (!isset($_GET['action']) || $_GET['action'] !== 'register') {
            ?>
            <p>
                <label for="tcsms_login_phone"><?php _e('手机号码', 'tencent-cloud-sms'); ?><br>
                    <input type="tel" name="tcsms_login_phone" id="tcsms_login_phone" class="input" 
                           pattern="1[3-9]\d{9}" maxlength="11" required>
                </label>
            </p>
            <p>
                <label for="tcsms_login_code"><?php _e('短信验证码', 'tencent-cloud-sms'); ?><br>
                    <input type="text" name="tcsms_login_code" id="tcsms_login_code" class="input" size="6" maxlength="6" required>
                    <button type="button" id="tcsms_login_send" class="button button-secondary" 
                            style="margin-top: 5px;">
                        <?php _e('获取验证码', 'tencent-cloud-sms'); ?>
                    </button>
                </label>
            </p>
            <?php
        }
    }
    
    /**
     * 验证登录短信
     */
    public function verify_login_sms($user, $username, $password) {
        if (!empty($_POST['tcsms_login_phone']) && !empty($_POST['tcsms_login_code'])) {
            $phone = sanitize_text_field($_POST['tcsms_login_phone']);
            $code = sanitize_text_field($_POST['tcsms_login_code']);
            
            if (!$this->api->verify_code($phone, $code)) {
                return new WP_Error('sms_verification_failed', 
                    __('短信验证码错误或已过期', 'tencent-cloud-sms'));
            }
            
            // 验证成功后清除验证码记录
            $this->db->mark_code_used($phone, $code);
        }
        
        return $user;
    }
    
    /**
     * 添加插件设置链接
     */
    public function add_plugin_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=tencent-cloud-sms'),
            __('设置', 'tencent-cloud-sms')
        );
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * 插件激活时执行
     */
    public static function activate() {
        // 创建数据库表
        TCSMS_DB::create_tables();
        
        // 设置默认选项
        self::set_default_options();
        
        // 添加清理任务
        if (!wp_next_scheduled('tcsms_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'tcsms_daily_cleanup');
        }
        
        // 设置版本号
        update_option('tcsms_version', TCSMS_VERSION);
    }
    
    /**
     * 插件停用时执行
     */
    public static function deactivate() {
        // 清理定时任务
        wp_clear_scheduled_hook('tcsms_daily_cleanup');
    }
    
    /**
     * 设置默认选项
     */
    private static function set_default_options() {
        $defaults = [
            'tcsms_code_expiry' => 5, // 5分钟
            'tcsms_region' => 'ap-guangzhou',
            'tcsms_enable_login' => 0,
            'tcsms_rate_limit' => 60, // 60秒
            'tcsms_max_attempts' => 3 // 最大尝试次数
        ];
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}