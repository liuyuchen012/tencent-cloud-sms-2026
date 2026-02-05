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
        
        // 登录页面脚本
        add_action('login_enqueue_scripts', [$this, 'enqueue_login_scripts']);
        
        // AJAX处理
        add_action('wp_ajax_tcsms_send_verification', [$this->api, 'ajax_send_verification']);
        add_action('wp_ajax_nopriv_tcsms_send_verification', [$this->api, 'ajax_send_verification']);
        add_action('wp_ajax_tcsms_verify_code', [$this->api, 'ajax_verify_code']);
        add_action('wp_ajax_nopriv_tcsms_verify_code', [$this->api, 'ajax_verify_code']);
        add_action('wp_ajax_tcsms_bind_phone', [$this, 'ajax_bind_phone']);
        
        // 短代码
        add_shortcode('tcsms_form', [$this, 'shortcode_sms_form']);
        
        // 登录集成 - 总是显示登录选项
        add_action('login_form', [$this, 'add_sms_to_login_form']);
        
        // 只有当启用登录验证时才添加验证钩子
        if (get_option('tcsms_enable_login', false)) {
            add_filter('authenticate', [$this, 'verify_login_sms'], 30, 3);
        }
        
        // 清理过期验证码
        add_action('tcsms_daily_cleanup', [$this->db, 'clean_expired_codes']);
        
        // 插件链接
        add_filter('plugin_action_links_' . TCSMS_PLUGIN_BASENAME, [$this, 'add_plugin_links']);
        
        // 用户个人中心手机号绑定
        add_action('show_user_profile', [$this, 'add_phone_field_to_profile']);
        add_action('edit_user_profile', [$this, 'add_phone_field_to_profile']);
        add_action('personal_options_update', [$this, 'save_phone_field']);
        add_action('edit_user_profile_update', [$this, 'save_phone_field']);
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
            'nonce' => wp_create_nonce('tcsms_ajax_nonce'),
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
     * 加载登录页面脚本
     */
    public function enqueue_login_scripts() {
        // 确保jQuery被加载
        wp_enqueue_script('jquery');
        
        // 加载登录页面专用的脚本
        wp_enqueue_script(
            'tcsms-login',
            TCSMS_PLUGIN_URL . 'assets/js/login.js',
            ['jquery'],
            TCSMS_VERSION,
            true
        );
        
        // 传递变量到JavaScript
        wp_localize_script('tcsms-login', 'tcsms_login', [
            'ajax_url' => is_ssl() ? str_replace('http://', 'https://', admin_url('admin-ajax.php')) : admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tcsms_ajax_nonce'),
            'texts' => [
                'sending' => __('发送中...', 'tencent-cloud-sms'),
                'countdown' => __('秒后重试', 'tencent-cloud-sms'),
                'invalid_phone' => __('请输入有效的手机号码', 'tencent-cloud-sms')
            ]
        ]);
        
        // 加载登录页面样式
        wp_enqueue_style(
            'tcsms-login',
            TCSMS_PLUGIN_URL . 'assets/css/login.css',
            [],
            TCSMS_VERSION
        );
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
            <div class="tcsms-login-section">
                
                <div id="tcsms_sms_login" style="display: none;">
                    <p>
                        <label for="tcsms_login_phone"><?php _e('手机号码', 'tencent-cloud-sms'); ?><br>
                            <input type="tel" name="tcsms_login_phone" id="tcsms_login_phone" class="input" 
                                   pattern="1[3-9]\d{9}" maxlength="11">
                        </label>
                    </p>
                    <p>
                        <label for="tcsms_login_code"><?php _e('短信验证码', 'tencent-cloud-sms'); ?></label>
                        <div class="tcsms-code-group">
                            <input type="text" name="tcsms_login_code" id="tcsms_login_code" class="input" 
                                   size="6" maxlength="6" style="width: 100px;">
                            <button type="button" id="tcsms_login_send" class="button button-secondary">
                                <?php _e('获取验证码', 'tencent-cloud-sms'); ?>
                            </button>
                        </div>
                    </p>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * 验证登录短信
     */
    public function verify_login_sms($user, $username, $password) {
        // 如果用户选择了短信登录方式
        if (isset($_POST['tcsms_login_method']) && $_POST['tcsms_login_method'] === 'sms') {
            // 即使没有启用登录验证，也允许尝试
            if (!empty($_POST['tcsms_login_phone']) && !empty($_POST['tcsms_login_code'])) {
                $phone = sanitize_text_field($_POST['tcsms_login_phone']);
                $code = sanitize_text_field($_POST['tcsms_login_code']);
                
                // 验证短信验证码
                if (!$this->api->verify_code($phone, $code)) {
                    return new WP_Error('sms_verification_failed', 
                        __('短信验证码错误或已过期', 'tencent-cloud-sms'));
                }
                
                // 根据手机号查找用户
                $user = get_users([
                    'meta_key' => 'tcsms_phone',
                    'meta_value' => $phone,
                    'number' => 1
                ]);
                
                if (empty($user)) {
                    return new WP_Error('invalid_phone', 
                        __('该手机号未绑定任何账户', 'tencent-cloud-sms'));
                }
                
                $user = $user[0];
                
                // 标记验证码为已验证
                $this->api->mark_code_verified($phone, $code);
                
                return $user;
            } else {
                return new WP_Error('sms_required', 
                    __('请输入手机号和验证码', 'tencent-cloud-sms'));
            }
        }
        
        // 如果启用了强制验证，检查是否需要进行验证
        if (get_option('tcsms_enable_login', false)) {
            // 这里可以添加强制验证的逻辑
            // 例如：某些用户组需要短信验证等
        }
        
        return $user;
    }
    
    /**
     * 在用户资料页面添加手机号字段
     */
    public function add_phone_field_to_profile($user) {
        // 检查是否是用户自己的资料页
        $is_own_profile = ($user->ID == get_current_user_id());
        
        if ($is_own_profile) {
            include TCSMS_PLUGIN_DIR . 'templates/profile-template.php';
        } else {
            // 管理员查看其他用户
            $user_phone = get_user_meta($user->ID, 'tcsms_phone', true);
            ?>
            <h3><?php _e('腾讯云短信', 'tencent-cloud-sms'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label for="tcsms_phone"><?php _e('绑定手机号', 'tencent-cloud-sms'); ?></label></th>
                    <td>
                        <input type="tel" 
                               name="tcsms_phone" 
                               id="tcsms_phone" 
                               value="<?php echo esc_attr($user_phone); ?>" 
                               class="regular-text" 
                               pattern="1[3-9]\d{9}" 
                               maxlength="11">
                        <p class="description"><?php _e('用户的绑定手机号', 'tencent-cloud-sms'); ?></p>
                    </td>
                </tr>
            </table>
            <?php
        }
    }
    
    /**
     * 保存用户手机号字段
     */
    public function save_phone_field($user_id) {
        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }
        
        if (isset($_POST['tcsms_phone'])) {
            $phone = sanitize_text_field($_POST['tcsms_phone']);
            
            if (!empty($phone)) {
                if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
                    add_action('user_profile_update_errors', function($errors) {
                        $errors->add('tcsms_phone_error', __('手机号码格式不正确', 'tencent-cloud-sms'));
                    });
                    return;
                }
            }
            
            update_user_meta($user_id, 'tcsms_phone', $phone);
        }
    }
    
    /**
     * AJAX绑定手机号
     */
    public function ajax_bind_phone() {
        // 调试日志
        error_log('腾讯云短信：开始处理绑定手机号请求');
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : get_current_user_id();
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $code = sanitize_text_field($_POST['code'] ?? '');
        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        
        error_log('腾讯云短信：绑定手机号参数 - 用户ID: ' . $user_id . ', 手机号: ' . $phone . ', 验证码: ' . $code);
        
        // 验证nonce - 使用动态生成的nonce，包含用户ID
        if (!wp_verify_nonce($nonce, 'tcsms_bind_phone_' . $user_id)) {
            error_log('腾讯云短信：绑定手机号nonce验证失败，用户ID: ' . $user_id);
            wp_send_json_error(['message' => __('安全验证失败，请刷新页面重试', 'tencent-cloud-sms')]);
            wp_die();
        }
        
        // 验证用户权限
        if ($user_id != get_current_user_id() && !current_user_can('edit_user', $user_id)) {
            error_log('腾讯云短信：用户权限不足，当前用户ID: ' . get_current_user_id() . ', 目标用户ID: ' . $user_id);
            wp_send_json_error(['message' => __('权限不足', 'tencent-cloud-sms')]);
            wp_die();
        }
        
        if (empty($phone) || empty($code)) {
            error_log('腾讯云短信：手机号或验证码为空');
            wp_send_json_error(['message' => __('请输入手机号和验证码', 'tencent-cloud-sms')]);
            wp_die();
        }
        
        // 验证手机号格式
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            error_log('腾讯云短信：手机号格式不正确：' . $phone);
            wp_send_json_error(['message' => __('手机号码格式不正确', 'tencent-cloud-sms')]);
            wp_die();
        }
        
        // 验证验证码
        if (!$this->api->verify_code($phone, $code)) {
            error_log('腾讯云短信：验证码验证失败');
            wp_send_json_error(['message' => __('验证码错误或已过期', 'tencent-cloud-sms')]);
            wp_die();
        }
        
        // 检查手机号是否已被其他用户绑定
        $existing_user = get_users([
            'meta_key' => 'tcsms_phone',
            'meta_value' => $phone,
            'exclude' => [$user_id],
            'number' => 1,
            'fields' => 'ID'
        ]);
        
        if (!empty($existing_user)) {
            error_log('腾讯云短信：手机号已被其他用户绑定，用户ID：' . $existing_user[0]);
            wp_send_json_error(['message' => __('该手机号已被其他用户绑定', 'tencent-cloud-sms')]);
            wp_die();
        }
        
        // 绑定手机号到用户元数据
        $result = update_user_meta($user_id, 'tcsms_phone', $phone);
        
        if ($result !== false) {
            error_log('腾讯云短信：手机号绑定成功，用户ID：' . $user_id . '，手机号：' . $phone);
            
            // 标记验证码为已验证
            $this->api->mark_code_verified($phone, $code);
            
            // 创建绑定记录到数据库
            $this->save_phone_bind_record($user_id, $phone);
            
            wp_send_json_success(['message' => __('手机号绑定成功', 'tencent-cloud-sms')]);
        } else {
            error_log('腾讯云短信：手机号绑定失败');
            wp_send_json_error(['message' => __('绑定失败，请重试', 'tencent-cloud-sms')]);
        }
        
        wp_die();
    }
    
    /**
     * 保存手机号绑定记录到数据库
     * 
     * @param int $user_id 用户ID
     * @param string $phone 手机号码
     */
    private function save_phone_bind_record($user_id, $phone) {
        global $wpdb;
        
        // 创建绑定记录表（如果不存在）
        $table_name = $wpdb->prefix . 'tcsms_phone_bindings';
        
        // 检查表是否存在
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                phone varchar(20) NOT NULL,
                ip_address varchar(45) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY idx_user_id (user_id),
                UNIQUE KEY idx_phone (phone),
                KEY idx_created_at (created_at)
            ) {$charset_collate};";
            
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
            
            error_log('腾讯云短信：创建手机号绑定记录表成功');
        }
        
        // 获取客户端IP
        $ip_address = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip_address = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }
        
        $current_time = current_time('mysql');
        
        // 检查是否已存在记录
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE user_id = %d OR phone = %s",
            $user_id, $phone
        ));
        
        if ($existing) {
            // 先删除可能存在的重复记录
            $wpdb->delete(
                $table_name,
                ['phone' => $phone],
                ['%s']
            );
            
            // 更新用户绑定记录
            $result = $wpdb->update(
                $table_name,
                [
                    'phone' => $phone,
                    'ip_address' => sanitize_text_field($ip_address),
                    'updated_at' => $current_time
                ],
                ['user_id' => $user_id],
                ['%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // 插入新记录
            $result = $wpdb->insert(
                $table_name,
                [
                    'user_id' => $user_id,
                    'phone' => $phone,
                    'ip_address' => sanitize_text_field($ip_address),
                    'created_at' => $current_time
                ],
                ['%d', '%s', '%s', '%s']
            );
        }
        
        if ($result === false) {
            error_log('腾讯云短信：保存绑定记录失败：' . $wpdb->last_error);
        } else {
            error_log('腾讯云短信：保存绑定记录成功，记录ID：' . ($wpdb->insert_id ?: '更新成功'));
        }
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