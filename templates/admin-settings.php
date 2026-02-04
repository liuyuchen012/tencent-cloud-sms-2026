<?php
/**
 * 插件设置页面模板
 * 
 * @package TencentCloudSMS
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 获取当前标签页
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
$tabs = [
    'general' => __('常规设置', 'tencent-cloud-sms'),
    'sms' => __('短信设置', 'tencent-cloud-sms'),
    'security' => __('安全设置', 'tencent-cloud-sms'),
    'advanced' => __('高级设置', 'tencent-cloud-sms'),
    'logs' => __('发送日志', 'tencent-cloud-sms'),
    'help' => __('帮助', 'tencent-cloud-sms')
];
?>

<div class="wrap tcsms-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if (empty(get_option('tcsms_secret_id')) || empty(get_option('tcsms_secret_key'))): ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e('请先配置腾讯云API密钥，插件才能正常工作。', 'tencent-cloud-sms'); ?></p>
        </div>
    <?php endif; ?>
    
    <?php 
    // 检查腾讯云SDK是否可用
    $sdk_available = class_exists('TencentCloud\Common\Credential');
    if (!$sdk_available): ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php _e('腾讯云SDK未加载，请通过Composer安装依赖：', 'tencent-cloud-sms'); ?>
                <code>composer require tencentcloud/tencentcloud-sdk-php</code>
            </p>
        </div>
    <?php endif; ?>
    
    <h2 class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_key => $tab_label): ?>
            <a href="?page=tencent-cloud-sms&tab=<?php echo esc_attr($tab_key); ?>" 
               class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </h2>
    
    <div class="tcsms-tab-content">
        <?php switch ($current_tab):
            case 'general': ?>
                <div class="tcsms-settings-container">
                    <form method="post" action="options.php" class="tcsms-settings-form">
                        <?php
                        settings_fields('tcsms_general_group');
                        do_settings_sections('tcsms_general_page');
                        submit_button(__('保存常规设置', 'tencent-cloud-sms'));
                        ?>
                    </form>
                    
                    <div class="tcsms-settings-sidebar">
                        <div class="postbox">
                            <h3 class="hndle"><?php _e('API配置状态', 'tencent-cloud-sms'); ?></h3>
                            <div class="inside">
                                <table class="tcsms-status-table">
                                    <tr>
                                        <th><?php _e('SecretId', 'tencent-cloud-sms'); ?></th>
                                        <td>
                                            <span class="tcsms-status-indicator <?php echo empty(get_option('tcsms_secret_id')) ? 'status-error' : 'status-success'; ?>">
                                                <?php echo empty(get_option('tcsms_secret_id')) ? '未配置' : '已配置'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?php _e('SecretKey', 'tencent-cloud-sms'); ?></th>
                                        <td>
                                            <span class="tcsms-status-indicator <?php echo empty(get_option('tcsms_secret_key')) ? 'status-error' : 'status-success'; ?>">
                                                <?php echo empty(get_option('tcsms_secret_key')) ? '未配置' : '已配置'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th><?php _e('腾讯云SDK', 'tencent-cloud-sms'); ?></th>
                                        <td>
                                            <span class="tcsms-status-indicator <?php echo $sdk_available ? 'status-success' : 'status-error'; ?>">
                                                <?php echo $sdk_available ? '已加载' : '未加载'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="postbox">
                            <h3 class="hndle"><?php _e('快速开始', 'tencent-cloud-sms'); ?></h3>
                            <div class="inside">
                                <ol>
                                    <li><?php _e('在腾讯云控制台创建API密钥', 'tencent-cloud-sms'); ?></li>
                                    <li><?php _e('申请短信签名（需备案）', 'tencent-cloud-sms'); ?></li>
                                    <li><?php _e('创建短信模板', 'tencent-cloud-sms'); ?></li>
                                    <li><?php _e('填写下方的配置信息', 'tencent-cloud-sms'); ?></li>
                                    <li><?php _e('使用短代码或PHP函数调用', 'tencent-cloud-sms'); ?></li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                <?php break;
                
            case 'sms': ?>
                <form method="post" action="options.php" class="tcsms-settings-form">
                    <?php
                    settings_fields('tcsms_sms_group');
                    do_settings_sections('tcsms_sms_page');
                    submit_button(__('保存短信设置', 'tencent-cloud-sms'));
                    ?>
                </form>
                
                <div class="tcsms-test-section postbox" style="margin-top: 20px;">
                    <h3 class="hndle"><?php _e('短信发送测试', 'tencent-cloud-sms'); ?></h3>
                    <div class="inside">
                        <p><?php _e('配置完成后，可以在此测试短信发送功能是否正常。', 'tencent-cloud-sms'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('测试手机号', 'tencent-cloud-sms'); ?></th>
                                <td>
                                    <input type="text" 
                                           id="tcsms_test_phone" 
                                           class="regular-text" 
                                           placeholder="13800138000"
                                           pattern="1[3-9]\d{9}"
                                           required>
                                    <p class="description"><?php _e('请输入接收测试短信的手机号码', 'tencent-cloud-sms'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <button type="button" id="tcsms_test_send" class="button button-primary">
                            <?php _e('发送测试短信', 'tencent-cloud-sms'); ?>
                        </button>
                        
                        <div id="tcsms_test_result" style="margin-top: 10px; padding: 10px; display: none;"></div>
                    </div>
                </div>
                <?php break;
                
            case 'security': ?>
                <form method="post" action="options.php" class="tcsms-settings-form">
                    <?php
                    settings_fields('tcsms_security_group');
                    do_settings_sections('tcsms_security_page');
                    submit_button(__('保存安全设置', 'tencent-cloud-sms'));
                    ?>
                </form>
                
                <div class="postbox" style="margin-top: 20px;">
                    <h3 class="hndle"><?php _e('安全建议', 'tencent-cloud-sms'); ?></h3>
                    <div class="inside">
                        <ul>
                            <li><?php _e('验证码有效期不宜过长，建议5-10分钟', 'tencent-cloud-sms'); ?></li>
                            <li><?php _e('发送频率限制可防止恶意请求', 'tencent-cloud-sms'); ?></li>
                            <li><?php _e('每日发送限制可防止短信轰炸', 'tencent-cloud-sms'); ?></li>
                            <li><?php _e('IP白名单功能适用于内网环境', 'tencent-cloud-sms'); ?></li>
                            <li><?php _e('定期检查发送日志，发现异常行为', 'tencent-cloud-sms'); ?></li>
                        </ul>
                    </div>
                </div>
                <?php break;
                
            case 'advanced': ?>
                <form method="post" action="options.php" class="tcsms-settings-form">
                    <?php
                    settings_fields('tcsms_advanced_group');
                    do_settings_sections('tcsms_advanced_page');
                    submit_button(__('保存高级设置', 'tencent-cloud-sms'));
                    ?>
                </form>
                
                <div class="postbox" style="margin-top: 20px;">
                    <h3 class="hndle"><?php _e('数据库维护', 'tencent-cloud-sms'); ?></h3>
                    <div class="inside">
                        <p><?php _e('清理过期的验证码记录，释放数据库空间。', 'tencent-cloud-sms'); ?></p>
                        <button type="button" id="tcsms_clean_db" class="button">
                            <?php _e('清理过期数据', 'tencent-cloud-sms'); ?>
                        </button>
                        <span id="tcsms_clean_result" style="margin-left: 10px;"></span>
                    </div>
                </div>
                <?php break;
                
            case 'logs': ?>
                <div class="tcsms-logs-container">
                    <h3><?php _e('短信发送日志', 'tencent-cloud-sms'); ?></h3>
                    
                    <?php
                    // 获取日志数据
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'tcsms_codes';
                    $logs = $wpdb->get_results(
                        "SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 50"
                    );
                    
                    if ($logs): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('ID', 'tencent-cloud-sms'); ?></th>
                                    <th><?php _e('手机号', 'tencent-cloud-sms'); ?></th>
                                    <th><?php _e('验证码', 'tencent-cloud-sms'); ?></th>
                                    <th><?php _e('IP地址', 'tencent-cloud-sms'); ?></th>
                                    <th><?php _e('发送时间', 'tencent-cloud-sms'); ?></th>
                                    <th><?php _e('过期时间', 'tencent-cloud-sms'); ?></th>
                                    <th><?php _e('状态', 'tencent-cloud-sms'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo esc_html($log->id); ?></td>
                                        <td><?php echo esc_html($log->phone); ?></td>
                                        <td><?php echo esc_html($log->code); ?></td>
                                        <td><?php echo esc_html($log->ip_address); ?></td>
                                        <td><?php echo esc_html($log->created_at); ?></td>
                                        <td><?php echo esc_html($log->expires_at); ?></td>
                                        <td>
                                            <span class="tcsms-status-indicator <?php echo $log->verified ? 'status-success' : 'status-warning'; ?>">
                                                <?php echo $log->verified ? '已验证' : '未验证'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="tablenav bottom">
                            <div class="alignleft">
                                <p><?php 
                                    $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
                                    printf(__('共 %d 条记录', 'tencent-cloud-sms'), $total); 
                                ?></p>
                            </div>
                        </div>
                    <?php else: ?>
                        <p><?php _e('暂无发送记录。', 'tencent-cloud-sms'); ?></p>
                    <?php endif; ?>
                </div>
                <?php break;
                
            case 'help': ?>
                <div class="postbox-container" style="width: 100%;">
                    <div class="postbox">
                        <h3 class="hndle"><?php _e('使用说明', 'tencent-cloud-sms'); ?></h3>
                        <div class="inside">
                            <h4><?php _e('短代码使用', 'tencent-cloud-sms'); ?></h4>
                            <p><?php _e('在文章或页面中插入以下短代码显示短信验证表单：', 'tencent-cloud-sms'); ?></p>
                            <pre><code>[tcsms_form]</code></pre>
                            
                            <h4><?php _e('短代码参数', 'tencent-cloud-sms'); ?></h4>
                            <table class="wp-list-table widefat fixed">
                                <thead>
                                    <tr>
                                        <th><?php _e('参数', 'tencent-cloud-sms'); ?></th>
                                        <th><?php _e('说明', 'tencent-cloud-sms'); ?></th>
                                        <th><?php _e('默认值', 'tencent-cloud-sms'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>title</td>
                                        <td><?php _e('表单标题', 'tencent-cloud-sms'); ?></td>
                                        <td><?php _e('短信验证', 'tencent-cloud-sms'); ?></td>
                                    </tr>
                                    <tr>
                                        <td>phone_label</td>
                                        <td><?php _e('手机号标签', 'tencent-cloud-sms'); ?></td>
                                        <td><?php _e('手机号码', 'tencent-cloud-sms'); ?></td>
                                    </tr>
                                    <tr>
                                        <td>code_label</td>
                                        <td><?php _e('验证码标签', 'tencent-cloud-sms'); ?></td>
                                        <td><?php _e('验证码', 'tencent-cloud-sms'); ?></td>
                                    </tr>
                                    <tr>
                                        <td>button_text</td>
                                        <td><?php _e('发送按钮文字', 'tencent-cloud-sms'); ?></td>
                                        <td><?php _e('获取验证码', 'tencent-cloud-sms'); ?></td>
                                    </tr>
                                    <tr>
                                        <td>submit_text</td>
                                        <td><?php _e('验证按钮文字', 'tencent-cloud-sms'); ?></td>
                                        <td><?php _e('验证', 'tencent-cloud-sms'); ?></td>
                                    </tr>
                                    <tr>
                                        <td>class</td>
                                        <td><?php _e('自定义CSS类名', 'tencent-cloud-sms'); ?></td>
                                        <td>tcsms-form</td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <h4><?php _e('示例', 'tencent-cloud-sms'); ?></h4>
                            <pre><code>[tcsms_form title="手机验证" phone_label="您的手机号" class="custom-form"]</code></pre>
                            
                            <h4><?php _e('PHP调用', 'tencent-cloud-sms'); ?></h4>
                            <pre><code>&lt;?php 
if (function_exists('tcsms_shortcode_form')) {
    echo tcsms_shortcode_form([
        'title' => '自定义标题',
        'phone_label' => '输入手机号'
    ]);
}
?&gt;</code></pre>
                        </div>
                    </div>
                    
                    <div class="postbox" style="margin-top: 20px;">
                        <h3 class="hndle"><?php _e('常见问题', 'tencent-cloud-sms'); ?></h3>
                        <div class="inside">
                            <h4><?php _e('1. 如何获取腾讯云API密钥？', 'tencent-cloud-sms'); ?></h4>
                            <ol>
                                <li><?php _e('访问腾讯云控制台', 'tencent-cloud-sms'); ?></li>
                                <li><?php _e('进入"访问管理" → "访问密钥" → "API密钥管理"', 'tencent-cloud-sms'); ?></li>
                                <li><?php _e('创建新的API密钥', 'tencent-cloud-sms'); ?></li>
                            </ol>
                            
                            <h4><?php _e('2. 如何申请短信签名和模板？', 'tencent-cloud-sms'); ?></h4>
                            <ol>
                                <li><?php _e('进入"短信"服务', 'tencent-cloud-sms'); ?></li>
                                <li><?php _e('申请短信签名（需要企业或网站备案）', 'tencent-cloud-sms'); ?></li>
                                <li><?php _e('创建短信模板，内容需包含 {1} 和 {2} 参数', 'tencent-cloud-sms'); ?></li>
                            </ol>
                            
                            <h4><?php _e('3. 为什么收不到短信？', 'tencent-cloud-sms'); ?></h4>
                            <ul>
                                <li><?php _e('检查手机号格式是否正确', 'tencent-cloud-sms'); ?></li>
                                <li><?php _e('检查短信签名和模板是否已审核通过', 'tencent-cloud-sms'); ?></li>
                                <li><?php _e('检查账户余额是否充足', 'tencent-cloud-sms'); ?></li>
                                <li><?php _e('查看腾讯云短信控制台的状态报告', 'tencent-cloud-sms'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <?php break;
        endswitch; ?>
    </div>
</div>

<style>
.tcsms-settings-container {
    display: flex;
    gap: 20px;
    margin-top: 20px;
}

.tcsms-settings-form {
    flex: 2;
    min-width: 0;
}

.tcsms-settings-sidebar {
    flex: 1;
    min-width: 300px;
}

.tcsms-status-table {
    width: 100%;
    border-collapse: collapse;
}

.tcsms-status-table th,
.tcsms-status-table td {
    padding: 8px 0;
    border-bottom: 1px solid #eee;
    text-align: left;
}

.tcsms-status-indicator {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.tcsms-status-indicator.status-success {
    background: #d4edda;
    color: #155724;
}

.tcsms-status-indicator.status-warning {
    background: #fff3cd;
    color: #856404;
}

.tcsms-status-indicator.status-error {
    background: #f8d7da;
    color: #721c24;
}

.tcsms-test-section {
    margin-top: 20px;
}

.tcsms-logs-container {
    margin-top: 20px;
}

@media (max-width: 1024px) {
    .tcsms-settings-container {
        flex-direction: column;
    }
    
    .tcsms-settings-sidebar {
        min-width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // 测试短信发送
    $('#tcsms_test_send').on('click', function() {
        var $button = $(this);
        var $result = $('#tcsms_test_result');
        var phone = $('#tcsms_test_phone').val().trim();
        
        // 验证手机号
        if (!/^1[3-9]\d{9}$/.test(phone)) {
            $result
                .removeClass('success')
                .addClass('error')
                .text('请输入有效的手机号码')
                .show();
            return;
        }
        
        // 禁用按钮
        $button.prop('disabled', true).text('发送中...');
        
        // 发送测试请求
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tcsms_send_verification',
                phone: phone,
                nonce: '<?php echo wp_create_nonce("tcsms_admin_nonce"); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $result
                        .removeClass('error')
                        .addClass('success')
                        .text('发送成功！' + (response.data.code ? ' 测试验证码：' + response.data.code : ''))
                        .show();
                } else {
                    $result
                        .removeClass('success')
                        .addClass('error')
                        .text('发送失败：' + (response.data.message || '未知错误'))
                        .show();
                }
                $button.prop('disabled', false).text('发送测试短信');
            },
            error: function() {
                $result
                    .removeClass('success')
                    .addClass('error')
                    .text('网络错误，请重试')
                    .show();
                $button.prop('disabled', false).text('发送测试短信');
            }
        });
    });
    
    // 清理数据库
    $('#tcsms_clean_db').on('click', function() {
        var $button = $(this);
        var $result = $('#tcsms_clean_result');
        
        $button.prop('disabled', true).text('清理中...');
        $result.text('');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tcsms_clean_expired',
                nonce: '<?php echo wp_create_nonce("tcsms_admin_nonce"); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $result
                        .css('color', 'green')
                        .text('清理完成，删除了 ' + response.data.deleted + ' 条记录');
                } else {
                    $result
                        .css('color', 'red')
                        .text('清理失败：' + (response.data.message || '未知错误'));
                }
                $button.prop('disabled', false).text('清理过期数据');
            },
            error: function() {
                $result
                    .css('color', 'red')
                    .text('网络错误，请重试');
                $button.prop('disabled', false).text('清理过期数据');
            }
        });
    });
    
    // 显示/隐藏SecretKey
    $('#tcsms_secret_key').after(
        '<button type="button" class="button button-small" id="toggle_secret_key" style="margin-left: 10px;">显示</button>'
    );
    
    $('#toggle_secret_key').on('click', function() {
        var $input = $('#tcsms_secret_key');
        var $button = $(this);
        
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $button.text('隐藏');
        } else {
            $input.attr('type', 'password');
            $button.text('显示');
        }
    });
    
    // 表单验证
    $('form').on('submit', function(e) {
        var secretId = $('#tcsms_secret_id').val().trim();
        var secretKey = $('#tcsms_secret_key').val().trim();
        var sdkAppId = $('#tcsms_sdk_app_id').val().trim();
        var signName = $('#tcsms_sign_name').val().trim();
        var templateId = $('#tcsms_template_id').val().trim();
        
        var missingFields = [];
        
        if (!secretId) missingFields.push('SecretId');
        if (!secretKey) missingFields.push('SecretKey');
        if (!sdkAppId) missingFields.push('SDK AppId');
        if (!signName) missingFields.push('短信签名');
        if (!templateId) missingFields.push('模板ID');
        
        if (missingFields.length > 0) {
            e.preventDefault();
            alert('请填写以下必填字段：\n' + missingFields.join('\n'));
            return false;
        }
    });
});
</script>