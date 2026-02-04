<?php
namespace TCSMS;

class Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('腾讯云短信设置', 'tencent-cloud-sms'),
            __('腾讯云短信', 'tencent-cloud-sms'),
            'manage_options',
            'tencent-cloud-sms',
            [$this, 'render_settings_page']
        );
    }
    
    public function register_settings() {
        // 注册设置
        register_setting('tcsms_settings', 'tcsms_secret_id');
        register_setting('tcsms_settings', 'tcsms_secret_key');
        register_setting('tcsms_settings', 'tcsms_sdk_app_id');
        register_setting('tcsms_settings', 'tcsms_sign_name');
        register_setting('tcsms_settings', 'tcsms_template_id');
        register_setting('tcsms_settings', 'tcsms_region');
        register_setting('tcsms_settings', 'tcsms_enable_login');
        register_setting('tcsms_settings', 'tcsms_code_expiry');
        
        // 添加设置部分
        add_settings_section(
            'tcsms_main_section',
            __('腾讯云短信配置', 'tencent-cloud-sms'),
            null,
            'tcsms_settings'
        );
        
        // 添加字段
        $fields = [
            'tcsms_secret_id' => __('SecretId', 'tencent-cloud-sms'),
            'tcsms_secret_key' => __('SecretKey', 'tencent-cloud-sms'),
            'tcsms_sdk_app_id' => __('SDK AppId', 'tencent-cloud-sms'),
            'tcsms_sign_name' => __('短信签名', 'tencent-cloud-sms'),
            'tcsms_template_id' => __('模板ID', 'tencent-cloud-sms'),
            'tcsms_region' => __('区域', 'tencent-cloud-sms'),
            'tcsms_enable_login' => __('启用登录验证', 'tencent-cloud-sms'),
            'tcsms_code_expiry' => __('验证码有效期(分钟)', 'tencent-cloud-sms')
        ];
        
        foreach ($fields as $field => $label) {
            add_settings_field(
                $field,
                $label,
                [$this, 'render_field'],
                'tcsms_settings',
                'tcsms_main_section',
                ['field' => $field]
            );
        }
    }
    
    public function render_field($args) {
        $field = $args['field'];
        $value = get_option($field, '');
        
        switch ($field) {
            case 'tcsms_secret_key':
                echo '<input type="password" name="' . esc_attr($field) . '" value="' . esc_attr($value) . '" class="regular-text">';
                break;
            case 'tcsms_enable_login':
                echo '<input type="checkbox" name="' . esc_attr($field) . '" value="1" ' . checked(1, $value, false) . '>';
                echo '<p class="description">' . __('在登录表单中添加短信验证码验证', 'tencent-cloud-sms') . '</p>';
                break;
            case 'tcsms_region':
                echo '<select name="' . esc_attr($field) . '" class="regular-text">';
                $regions = [
                    'ap-guangzhou' => '广州',
                    'ap-shanghai' => '上海',
                    'ap-beijing' => '北京',
                    'ap-chengdu' => '成都',
                    'ap-chongqing' => '重庆'
                ];
                foreach ($regions as $key => $name) {
                    echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($name) . '</option>';
                }
                echo '</select>';
                break;
            default:
                echo '<input type="text" name="' . esc_attr($field) . '" value="' . esc_attr($value) . '" class="regular-text">';
        }
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('tcsms_settings');
                do_settings_sections('tcsms_settings');
                submit_button();
                ?>
            </form>
            
            <div class="tcsms-test-section" style="margin-top: 30px; padding: 20px; background: #f5f5f5; border-radius: 5px;">
                <h2><?php _e('测试短信发送', 'tencent-cloud-sms'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('测试手机号', 'tencent-cloud-sms'); ?></th>
                        <td>
                            <input type="text" id="tcsms_test_phone" class="regular-text" placeholder="13800138000">
                            <button type="button" id="tcsms_test_send" class="button">
                                <?php _e('发送测试短信', 'tencent-cloud-sms'); ?>
                            </button>
                            <p class="description"><?php _e('请输入要接收测试短信的手机号码', 'tencent-cloud-sms'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#tcsms_test_send').on('click', function() {
                var phone = $('#tcsms_test_phone').val();
                var $button = $(this);
                
                if (!/^1[3-9]\d{9}$/.test(phone)) {
                    alert('请输入正确的手机号码');
                    return;
                }
                
                $button.prop('disabled', true).text('发送中...');
                
                $.post(ajaxurl, {
                    action: 'tcsms_send_code',
                    phone: phone,
                    nonce: '<?php echo wp_create_nonce('tcsms_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('发送成功！请查看手机');
                    } else {
                        alert('发送失败：' + response.data.message);
                    }
                    $button.prop('disabled', false).text('发送测试短信');
                }).fail(function() {
                    alert('请求失败，请检查网络');
                    $button.prop('disabled', false).text('发送测试短信');
                });
            });
        });
        </script>
        <?php
    }
}