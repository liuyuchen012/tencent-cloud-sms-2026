<?php
/**
 * 插件设置类
 * 
 * @package TencentCloudSMS
 */

if (!defined('ABSPATH')) {
    exit;
}

class TCSMS_Settings {
    
    /**
     * 设置页面slug
     * 
     * @var string
     */
    private $page_slug = 'tencent-cloud-sms';
    
    /**
     * 构造函数
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_notices', [$this, 'show_admin_notices']);
    }
    
    /**
     * 添加管理菜单
     */
    public function add_admin_menu() {
        add_options_page(
            __('腾讯云短信设置', 'tencent-cloud-sms'),
            __('腾讯云短信', 'tencent-cloud-sms'),
            'manage_options',
            $this->page_slug,
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * 注册设置
     */
    public function register_settings() {
        // 注册设置组
        register_setting('tcsms_settings_group', 'tcsms_secret_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting('tcsms_settings_group', 'tcsms_secret_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting('tcsms_settings_group', 'tcsms_sdk_app_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting('tcsms_settings_group', 'tcsms_sign_name', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting('tcsms_settings_group', 'tcsms_template_id', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        
        register_setting('tcsms_settings_group', 'tcsms_region', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'ap-guangzhou'
        ]);
        
        register_setting('tcsms_settings_group', 'tcsms_enable_login', [
            'type' => 'boolean',
            'default' => false
        ]);
        
        register_setting('tcsms_settings_group', 'tcsms_code_expiry', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 5
        ]);
        
        register_setting('tcsms_settings_group', 'tcsms_rate_limit', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 60
        ]);
        
        // 添加设置部分
        $this->add_settings_sections();
        
        // 添加设置字段
        $this->add_settings_fields();
    }
    
    /**
     * 添加设置部分
     */
    private function add_settings_sections() {
        // API配置部分
        add_settings_section(
            'tcsms_api_section',
            __('腾讯云API配置', 'tencent-cloud-sms'),
            [$this, 'render_api_section_desc'],
            $this->page_slug
        );
        
        // 短信配置部分
        add_settings_section(
            'tcsms_sms_section',
            __('短信配置', 'tencent-cloud-sms'),
            [$this, 'render_sms_section_desc'],
            $this->page_slug
        );
        
        // 功能配置部分
        add_settings_section(
            'tcsms_feature_section',
            __('功能配置', 'tencent-cloud-sms'),
            [$this, 'render_feature_section_desc'],
            $this->page_slug
        );
    }
    
    /**
     * 添加设置字段
     */
    private function add_settings_fields() {
        // API配置字段
        add_settings_field(
            'tcsms_secret_id',
            __('SecretId', 'tencent-cloud-sms'),
            [$this, 'render_secret_id_field'],
            $this->page_slug,
            'tcsms_api_section'
        );
        
        add_settings_field(
            'tcsms_secret_key',
            __('SecretKey', 'tencent-cloud-sms'),
            [$this, 'render_secret_key_field'],
            $this->page_slug,
            'tcsms_api_section'
        );
        
        add_settings_field(
            'tcsms_region',
            __('区域', 'tencent-cloud-sms'),
            [$this, 'render_region_field'],
            $this->page_slug,
            'tcsms_api_section'
        );
        
        // 短信配置字段
        add_settings_field(
            'tcsms_sdk_app_id',
            __('SDK AppId', 'tencent-cloud-sms'),
            [$this, 'render_sdk_app_id_field'],
            $this->page_slug,
            'tcsms_sms_section'
        );
        
        add_settings_field(
            'tcsms_sign_name',
            __('短信签名', 'tencent-cloud-sms'),
            [$this, 'render_sign_name_field'],
            $this->page_slug,
            'tcsms_sms_section'
        );
        
        add_settings_field(
            'tcsms_template_id',
            __('模板ID', 'tencent-cloud-sms'),
            [$this, 'render_template_id_field'],
            $this->page_slug,
            'tcsms_sms_section'
        );
        
        // 功能配置字段
        add_settings_field(
            'tcsms_enable_login',
            __('登录验证', 'tencent-cloud-sms'),
            [$this, 'render_enable_login_field'],
            $this->page_slug,
            'tcsms_feature_section'
        );
        
        add_settings_field(
            'tcsms_code_expiry',
            __('验证码有效期', 'tencent-cloud-sms'),
            [$this, 'render_code_expiry_field'],
            $this->page_slug,
            'tcsms_feature_section'
        );
        
        add_settings_field(
            'tcsms_rate_limit',
            __('发送频率限制', 'tencent-cloud-sms'),
            [$this, 'render_rate_limit_field'],
            $this->page_slug,
            'tcsms_feature_section'
        );
    }
    
    /**
     * 渲染设置页面
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('您没有权限访问此页面。', 'tencent-cloud-sms'));
        }
         // 加载模板文件
        $template_path = TCSMS_PLUGIN_DIR . 'templates/admin-settings.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // 如果模板文件不存在，显示错误信息
            echo '<div class="error"><p>';
            _e('设置模板文件不存在，请重新安装插件。', 'tencent-cloud-sms');
            echo '</p></div>';
        }
        ?>
        <div class="wrap tcsms-settings">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <?php if (empty(get_option('tcsms_secret_id')) || empty(get_option('tcsms_secret_key'))): ?>
                <div class="notice notice-warning">
                    <p><?php _e('请先配置腾讯云API密钥，插件才能正常工作。', 'tencent-cloud-sms'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="tcsms-settings-container">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('tcsms_settings_group');
                    do_settings_sections($this->page_slug);
                    submit_button(__('保存设置', 'tencent-cloud-sms'));
                    ?>
                </form>
                
                <div class="tcsms-settings-sidebar">
                    <div class="tcsms-test-box">
                        <h3><?php _e('测试短信发送', 'tencent-cloud-sms'); ?></h3>
                        <p><?php _e('配置完成后，可以在此测试短信发送功能。', 'tencent-cloud-sms'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('测试手机号', 'tencent-cloud-sms'); ?></th>
                                <td>
                                    <input type="text" 
                                           id="tcsms_test_phone" 
                                           class="regular-text" 
                                           placeholder="13800138000"
                                           pattern="1[3-9]\d{9}">
                                </td>
                            </tr>
                        </table>
                        
                        <button type="button" id="tcsms_test_send" class="button button-primary">
                            <?php _e('发送测试短信', 'tencent-cloud-sms'); ?>
                        </button>
                        
                        <div id="tcsms_test_result" style="margin-top: 10px;"></div>
                    </div>
                    
                    <div class="tcsms-info-box">
                        <h3><?php _e('使用说明', 'tencent-cloud-sms'); ?></h3>
                        <ol>
                            <li><?php _e('在腾讯云控制台获取API密钥', 'tencent-cloud-sms'); ?></li>
                            <li><?php _e('创建短信应用，获取SDK AppId', 'tencent-cloud-sms'); ?></li>
                            <li><?php _e('申请短信签名和模板', 'tencent-cloud-sms'); ?></li>
                            <li><?php _e('填写下方配置信息', 'tencent-cloud-sms'); ?></li>
                            <li><?php _e('使用短代码 [tcsms_form] 显示验证表单', 'tencent-cloud-sms'); ?></li>
                        </ol>
                        
                        <h4><?php _e('短代码示例', 'tencent-cloud-sms'); ?></h4>
                        <code>[tcsms_form title="手机验证" phone_label="您的手机号"]</code>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 渲染API配置部分描述
     */
    public function render_api_section_desc() {
        echo '<p>' . __('填写从腾讯云控制台获取的API密钥信息。', 'tencent-cloud-sms') . '</p>';
    }
    
    /**
     * 渲染短信配置部分描述
     */
    public function render_sms_section_desc() {
        echo '<p>' . __('填写短信应用、签名和模板信息。', 'tencent-cloud-sms') . '</p>';
    }
    
    /**
     * 渲染功能配置部分描述
     */
    public function render_feature_section_desc() {
        echo '<p>' . __('配置插件功能和验证码行为。', 'tencent-cloud-sms') . '</p>';
    }
    
    /**
     * 渲染SecretId字段
     */
    public function render_secret_id_field() {
        $value = get_option('tcsms_secret_id', '');
        ?>
        <input type="text" 
               name="tcsms_secret_id" 
               id="tcsms_secret_id" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" 
               required>
        <p class="description">
            <?php _e('腾讯云API密钥ID，可在腾讯云控制台获取。', 'tencent-cloud-sms'); ?>
        </p>
        <?php
    }
    
    /**
     * 渲染SecretKey字段
     */
    public function render_secret_key_field() {
        $value = get_option('tcsms_secret_key', '');
        ?>
        <input type="password" 
               name="tcsms_secret_key" 
               id="tcsms_secret_key" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" 
               required>
        <p class="description">
            <?php _e('腾讯云API密钥，请妥善保管。', 'tencent-cloud-sms'); ?>
        </p>
        <?php
    }
    
    /**
     * 渲染区域字段
     */
    public function render_region_field() {
        $value = get_option('tcsms_region', 'ap-guangzhou');
        $regions = [
            'ap-beijing' => '北京',
            'ap-shanghai' => '上海',
            'ap-guangzhou' => '广州',
            'ap-chengdu' => '成都',
            'ap-chongqing' => '重庆',
            'ap-hongkong' => '香港'
        ];
        ?>
        <select name="tcsms_region" id="tcsms_region" class="regular-text">
            <?php foreach ($regions as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php _e('选择最近的区域以获得更好的性能。', 'tencent-cloud-sms'); ?>
        </p>
        <?php
    }
    
    /**
     * 渲染SDK AppId字段
     */
    public function render_sdk_app_id_field() {
        $value = get_option('tcsms_sdk_app_id', '');
        ?>
        <input type="text" 
               name="tcsms_sdk_app_id" 
               id="tcsms_sdk_app_id" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" 
               required>
        <p class="description">
            <?php _e('短信应用的SDK AppId。', 'tencent-cloud-sms'); ?>
        </p>
        <?php
    }
    
    /**
     * 渲染短信签名字段
     */
    public function render_sign_name_field() {
        $value = get_option('tcsms_sign_name', '');
        ?>
        <input type="text" 
               name="tcsms_sign_name" 
               id="tcsms_sign_name" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" 
               required>
        <p class="description">
            <?php _e('审核通过的短信签名。', 'tencent-cloud-sms'); ?>
        </p>
        <?php
    }
    
    /**
     * 渲染模板ID字段
     */
    public function render_template_id_field() {
        $value = get_option('tcsms_template_id', '');
        ?>
        <input type="text" 
               name="tcsms_template_id" 
               id="tcsms_template_id" 
               value="<?php echo esc_attr($value); ?>" 
               class="regular-text" 
               required>
        <p class="description">
            <?php _e('审核通过的模板ID，模板内容需包含 {1} 和 {2} 参数。', 'tencent-cloud-sms'); ?>
        </p>
        <?php
    }
    
    /**
     * 渲染启用登录验证字段
     */
    public function render_enable_login_field() {
        $value = get_option('tcsms_enable_login', false);
        ?>
        <label>
            <input type="checkbox" 
                   name="tcsms_enable_login" 
                   id="tcsms_enable_login" 
                   value="1" 
                   <?php checked($value, 1); ?>>
            <?php _e('在登录表单中启用短信验证', 'tencent-cloud-sms'); ?>
        </label>
        <p class="description">
            <?php _e('启用后，用户登录时需要验证手机短信验证码。', 'tencent-cloud-sms'); ?>
        </p>
        <?php
    }
    
    /**
     * 渲染验证码有效期字段
     */
    public function render_code_expiry_field() {
        $value = get_option('tcsms_code_expiry', 5);
        ?>
        <input type="number" 
               name="tcsms_code_expiry" 
               id="tcsms_code_expiry" 
               value="<?php echo esc_attr($value); ?>" 
               min="1" max="30" 
               class="small-text">
        <span><?php _e('分钟', 'tencent-cloud-sms'); ?></span>
        <p class="description">
            <?php _e('验证码的有效时间，建议设置为5-10分钟。', 'tencent-cloud-sms'); ?>
        </p>
        <?php
    }
    
    /**
     * 渲染发送频率限制字段
     */
    public function render_rate_limit_field() {
        $value = get_option('tcsms_rate_limit', 60);
        ?>
        <input type="number" 
               name="tcsms_rate_limit" 
               id="tcsms_rate_limit" 
               value="<?php echo esc_attr($value); ?>" 
               min="30" max="300" 
               class="small-text">
        <span><?php _e('秒', 'tencent-cloud-sms'); ?></span>
        <p class="description">
            <?php _e('同一手机号发送验证码的最小时间间隔。', 'tencent-cloud-sms'); ?>
        </p>
        <?php
    }
    
    /**
     * 显示管理通知
     */
    public function show_admin_notices() {
        $screen = get_current_screen();
        
        if ($screen && $screen->id === 'settings_page_tencent-cloud-sms') {
            // 检查配置是否完整
            if (empty(get_option('tcsms_secret_id')) || empty(get_option('tcsms_secret_key'))) {
                ?>
                <div class="notice notice-warning">
                    <p><?php _e('请先配置腾讯云API密钥，否则插件无法正常工作。', 'tencent-cloud-sms'); ?></p>
                </div>
                <?php
            }
        }
    }
}