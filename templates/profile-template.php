<?php
/**
 * 用户个人中心绑定手机号模板
 * 
 * @package TencentCloudSMS
 */

if (!defined('ABSPATH')) {
    exit;
}

// 获取当前用户
$current_user = wp_get_current_user();
$user_id = $current_user->ID;
$user_phone = get_user_meta($user_id, 'tcsms_phone', true);

if (!empty($user_phone)) {
    // 隐藏部分手机号
    $hidden_phone = substr_replace($user_phone, '****', 3, 4);
}
?>

<div class="tcsms-profile-container">
    <h3><?php _e('手机号绑定', 'tencent-cloud-sms'); ?></h3>
    
    <?php if (!empty($user_phone)): ?>
        <div class="notice notice-success" style="margin-bottom: 15px;">
            <p><?php printf(__('当前绑定的手机号：%s', 'tencent-cloud-sms'), $hidden_phone); ?></p>
        </div>
        
        <button type="button" id="tcsms_change_phone" class="button">
            <?php _e('更换手机号', 'tencent-cloud-sms'); ?>
        </button>
    <?php endif; ?>
    
    <div id="tcsms_phone_form" style="<?php echo empty($user_phone) ? '' : 'display: none;'; ?> margin-top: 15px;">
        <div id="tcsms_phone_bind_form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="tcsms_profile_phone"><?php _e('手机号码', 'tencent-cloud-sms'); ?></label>
                    </th>
                    <td>
                        <input type="tel" 
                               id="tcsms_profile_phone" 
                               class="regular-text" 
                               pattern="1[3-9]\d{9}" 
                               maxlength="11" 
                               value="<?php echo !empty($user_phone) ? esc_attr($user_phone) : ''; ?>"
                               <?php echo !empty($user_phone) ? 'readonly' : ''; ?>>
                        <p class="description"><?php _e('请输入您的手机号码', 'tencent-cloud-sms'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="tcsms_profile_code"><?php _e('验证码', 'tencent-cloud-sms'); ?></label>
                    </th>
                    <td>
                        <div style="display: flex; gap: 10px; max-width: 300px;">
                            <input type="text" 
                                   id="tcsms_profile_code" 
                                   class="regular-text" 
                                   maxlength="6" 
                                   placeholder="<?php esc_attr_e('请输入验证码', 'tencent-cloud-sms'); ?>">
                            <button type="button" 
                                    id="tcsms_profile_send" 
                                    class="button">
                                <?php _e('获取验证码', 'tencent-cloud-sms'); ?>
                            </button>
                        </div>
                        <p class="description"><?php _e('验证码将发送到您的手机', 'tencent-cloud-sms'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="button" 
                        id="tcsms_bind_submit" 
                        class="button button-primary">
                    <?php echo empty($user_phone) ? __('绑定手机号', 'tencent-cloud-sms') : __('更新手机号', 'tencent-cloud-sms'); ?>
                </button>
                <?php if (!empty($user_phone)): ?>
                    <button type="button" 
                            id="tcsms_cancel_change" 
                            class="button">
                        <?php _e('取消', 'tencent-cloud-sms'); ?>
                    </button>
                <?php endif; ?>
            </p>
        </div>
        
        <div id="tcsms_profile_message" style="margin-top: 10px;"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var countdown = 0;
    var countdownTimer;
    
    // 切换更换手机号表单
    $('#tcsms_change_phone').on('click', function() {
        $('#tcsms_phone_form').show();
        $('#tcsms_profile_phone').prop('readonly', false).val('');
        $(this).hide();
    });
    
    $('#tcsms_cancel_change').on('click', function() {
        $('#tcsms_phone_form').hide();
        $('#tcsms_change_phone').show();
        $('#tcsms_profile_phone').prop('readonly', true).val('<?php echo esc_js($user_phone); ?>');
        $('#tcsms_profile_code').val('');
    });
    
    // 发送验证码
    $('#tcsms_profile_send').on('click', function() {
        var $button = $(this);
        var phone = $('#tcsms_profile_phone').val().trim();
        
        if (!/^1[3-9]\d{9}$/.test(phone)) {
            showMessage('请输入有效的手机号码', 'error');
            return false;
        }
        
        if (countdown > 0) {
            return false;
        }
        
        $button.prop('disabled', true).text('发送中...');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'tcsms_send_verification',
                phone: phone,
                nonce: '<?php echo wp_create_nonce("tcsms_ajax_nonce"); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage('验证码发送成功', 'success');
                    startCountdown($button);
                } else {
                    showMessage('发送失败：' + (response.data?.message || '未知错误'), 'error');
                    $button.prop('disabled', false).text('获取验证码');
                }
            },
            error: function(xhr, status, error) {
                console.error('发送验证码错误:', status, error);
                showMessage('网络错误，请重试', 'error');
                $button.prop('disabled', false).text('获取验证码');
            }
        });
        
        return false;
    });
    
    // 绑定手机号
    $('#tcsms_bind_submit').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $button = $(this);
        var phone = $('#tcsms_profile_phone').val().trim();
        var code = $('#tcsms_profile_code').val().trim();
        
        if (!/^1[3-9]\d{9}$/.test(phone)) {
            showMessage('请输入有效的手机号码', 'error');
            return false;
        }
        
        if (code.length !== 6) {
            showMessage('请输入6位验证码', 'error');
            return false;
        }
        
        var originalText = $button.text();
        $button.prop('disabled', true).text('处理中...');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'tcsms_bind_phone',
                phone: phone,
                code: code,
                user_id: <?php echo $user_id; ?>,
                nonce: '<?php echo wp_create_nonce("tcsms_bind_phone_" . $user_id); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showMessage(response.data?.message || '绑定成功', 'success');
                    
                    // 绑定成功后刷新页面
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage(response.data?.message || '绑定失败', 'error');
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('绑定手机号错误:', status, error);
                console.error('响应:', xhr.responseText);
                showMessage('网络错误，请重试', 'error');
                $button.prop('disabled', false).text(originalText);
            }
        });
        
        return false;
    });
    
    function startCountdown($btn) {
        countdown = 60;
        clearInterval(countdownTimer);
        
        countdownTimer = setInterval(function() {
            if (countdown > 0) {
                $btn.text(countdown + '秒后重试');
                countdown--;
            } else {
                clearInterval(countdownTimer);
                $btn.prop('disabled', false).text('获取验证码');
            }
        }, 1000);
    }
    
    function showMessage(message, type) {
        var $msg = $('#tcsms_profile_message');
        var classType = '';
        
        switch(type) {
            case 'success':
                classType = 'notice-success';
                break;
            case 'error':
                classType = 'notice-error';
                break;
            default:
                classType = 'notice-info';
        }
        
        $msg.removeClass('notice-success notice-error notice-info')
            .addClass('notice ' + classType)
            .html('<p>' + message + '</p>')
            .show();
        
        setTimeout(function() {
            $msg.fadeOut();
        }, 5000);
    }
});
</script>

<style>
.tcsms-profile-container {
    margin: 20px 0;
    padding: 20px;
    background: #fff;
    border: 1px solid #e5e5e5;
    border-radius: 4px;
}

.tcsms-profile-container h3 {
    margin-top: 0;
    border-bottom: 1px solid #e5e5e5;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

#tcsms_profile_message {
    padding: 10px 15px;
    border-radius: 3px;
    display: none;
    border-left: 4px solid #00a0d2;
}

#tcsms_profile_message.notice-success {
    background: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
}

#tcsms_profile_message.notice-error {
    background: #f8d7da;
    color: #721c24;
    border-color: #f5c6cb;
}

#tcsms_profile_message p {
    margin: 0;
}
</style>