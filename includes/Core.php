<?php
namespace TCSMS;

class Core {
    private static $instance = null;
    private $settings;
    private $api;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // 私有构造函数
    }
    
    public function init() {
        // 初始化设置
        $this->settings = new Settings();
        
        // 初始化API
        $this->api = new SMS_API();
        
        // 加载文本域
        load_plugin_textdomain('tencent-cloud-sms', false, dirname(plugin_basename(__FILE__)) . '/../languages');
        
        // 初始化功能
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // 前端脚本和样式
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // AJAX处理
        add_action('wp_ajax_tcsms_send_code', [$this->api, 'ajax_send_code']);
        add_action('wp_ajax_nopriv_tcsms_send_code', [$this->api, 'ajax_send_code']);
        add_action('wp_ajax_tcsms_verify_code', [$this->api, 'ajax_verify_code']);
        add_action('wp_ajax_nopriv_tcsms_verify_code', [$this->api, 'ajax_verify_code']);
        
        // 短代码
        add_shortcode('tcsms_verify_button', [$this, 'shortcode_verify_button']);
        
        // 登录表单集成
        $enable_login = get_option('tcsms_enable_login', false);
        if ($enable_login) {
            add_action('login_form', [$this, 'add_login_sms_field']);
            add_filter('authenticate', [$this, 'sms_login_authenticate'], 30, 3);
        }
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style(
            'tcsms-frontend',
            TCSMS_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            TCSMS_VERSION
        );
        
        wp_enqueue_script(
            'tcsms-frontend',
            TCSMS_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            TCSMS_VERSION,
            true
        );
        
        wp_localize_script('tcsms-frontend', 'tcsms_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tcsms_nonce'),
            'sending' => __('发送中...', 'tencent-cloud-sms'),
            'resend' => __('重新发送', 'tencent-cloud-sms'),
            'countdown' => __('秒后重试', 'tencent-cloud-sms')
        ]);
    }
    
    public function shortcode_verify_button($atts) {
        $atts = shortcode_atts([
            'phone' => '',
            'label' => __('获取验证码', 'tencent-cloud-sms'),
            'button_class' => 'tcsms-verify-btn',
            'input_class' => 'tcsms-code-input'
        ], $atts);
        
        ob_start();
        ?>
        <div class="tcsms-verify-container">
            <input type="text" 
                   class="<?php echo esc_attr($atts['input_class']); ?>" 
                   placeholder="<?php esc_attr_e('请输入验证码', 'tencent-cloud-sms'); ?>">
            <button type="button" 
                    class="<?php echo esc_attr($atts['button_class']); ?>" 
                    data-phone="<?php echo esc_attr($atts['phone']); ?>">
                <?php echo esc_html($atts['label']); ?>
            </button>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function add_login_sms_field() {
        ?>
        <p>
            <label for="sms_code"><?php _e('短信验证码', 'tencent-cloud-sms'); ?><br>
                <input type="text" name="sms_code" id="sms_code" class="input" size="20">
                <button type="button" id="send_sms_code" class="button">
                    <?php _e('获取验证码', 'tencent-cloud-sms'); ?>
                </button>
            </label>
        </p>
        <input type="hidden" name="sms_phone" id="sms_phone" value="">
        <?php
    }
    
    public function sms_login_authenticate($user, $username, $password) {
        if (!empty($_POST['sms_code']) && !empty($_POST['sms_phone'])) {
            // 验证短信验证码逻辑
            $phone = sanitize_text_field($_POST['sms_phone']);
            $code = sanitize_text_field($_POST['sms_code']);
            
            if (!$this->api->verify_code($phone, $code)) {
                return new \WP_Error('sms_verification_failed', __('短信验证码错误', 'tencent-cloud-sms'));
            }
        }
        
        return $user;
    }
    
    public static function activate() {
        // 创建数据库表
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'tcsms_codes';
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            phone varchar(20) NOT NULL,
            code varchar(10) NOT NULL,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            verified tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY phone (phone),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // 添加默认选项
        add_option('tcsms_version', TCSMS_VERSION);
    }
    
    public static function deactivate() {
        // 清理定时任务
        wp_clear_scheduled_hook('tcsms_clean_expired_codes');
    }
    
    public static function uninstall() {
        global $wpdb;
        
        // 删除数据库表
        $table_name = $wpdb->prefix . 'tcsms_codes';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // 删除所有选项
        delete_option('tcsms_secret_id');
        delete_option('tcsms_secret_key');
        delete_option('tcsms_sdk_app_id');
        delete_option('tcsms_sign_name');
        delete_option('tcsms_template_id');
        delete_option('tcsms_enable_login');
        delete_option('tcsms_code_expiry');
        delete_option('tcsms_version');
    }
}