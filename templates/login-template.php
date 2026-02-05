<?php
/**
 * 登录页面增强模板
 * 
 * @package TencentCloudSMS
 */

if (!defined('ABSPATH')) {
    exit;
}

// 防止重复输出
global $tcsms_template_loaded;
if (isset($tcsms_template_loaded) && $tcsms_template_loaded) {
    return;
}
$tcsms_template_loaded = true;

?>
<style>
.tcsms-login-method {
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}

.tcsms-login-method label {
    display: inline-block;
    margin-right: 20px;
}

#tcsms_sms_login {
    margin-top: 15px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
    border: 1px solid #e5e5e5;
}

#tcsms_sms_login label {
    display: block;
    margin-bottom: 10px;
    font-weight: 600;
}

#tcsms_login_phone {
    width: 100%;
    padding: 8px 10px;
    margin-bottom: 10px;
    font-size: 14px;
}

.tcsms-code-group {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.tcsms-code-group input {
    flex: 1;
}

#tcsms_login_send {
    flex-shrink: 0;
}

#tcsms_login_send.disabled {
    background: #ccc !important;
    cursor: not-allowed;
}
</style>

<script>
jQuery(document).ready(function($) {
    // 防止重复执行 - 检查是否已经存在登录方式选择
    if ($('.tcsms-login-method').length > 0) {
        return; // 如果已经存在，不再重复执行
    }
    
    // 添加登录方式选择
    $('#loginform').prepend(`
        <p class="tcsms-login-method">
            <label>
                <input type="radio" name="tcsms_login_method" value="password" checked> <?php _e('密码登录', 'tencent-cloud-sms'); ?>
            </label>
            <label style="margin-left: 20px;">
                <input type="radio" name="tcsms_login_method" value="sms"> <?php _e('验证码登录', 'tencent-cloud-sms'); ?>
            </label>
        </p>
    `);
    
    // 隐藏短信验证区域，默认显示
    $('#tcsms_sms_login').hide();
    
    // 切换登录方式
    $('input[name="tcsms_login_method"]').on('change', function() {
        var method = $(this).val();
        
        if (method === 'sms') {
            // 显示短信验证，隐藏密码输入
            $('#tcsms_sms_login').show();
            $('#loginform .user-pass-wrap').hide();
            $('#user_pass').prop('required', false);
            $('#tcsms_login_phone').prop('required', true);
            $('#tcsms_login_code').prop('required', true);
        } else {
            // 显示密码输入，隐藏短信验证
            $('#tcsms_sms_login').hide();
            $('#loginform .user-pass-wrap').show();
            $('#user_pass').prop('required', true);
            $('#tcsms_login_phone').prop('required', false);
            $('#tcsms_login_code').prop('required', false);
        }
    });
    
    // 发送登录验证码
    $('#tcsms_login_send').on('click', function() {
        var $button = $(this);
        var phone = $('#tcsms_login_phone').val().trim();
        
        if (!/^1[3-9]\d{9}$/.test(phone)) {
            alert('请输入有效的手机号码');
            return;
        }
        
        if ($button.hasClass('disabled')) {
            return;
        }
        
        $button.addClass('disabled').text('发送中...');
        
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
                    alert('验证码发送成功！');
                    startCountdown($button);
                } else {
                    alert('发送失败：' + (response.data?.message || '未知错误'));
                    $button.removeClass('disabled').text('获取验证码');
                }
            },
            error: function() {
                alert('网络错误，请重试');
                $button.removeClass('disabled').text('获取验证码');
            }
        });
        
        function startCountdown($btn) {
            var countdown = 60;
            var timer = setInterval(function() {
                if (countdown > 0) {
                    $btn.text(countdown + '秒后重试');
                    countdown--;
                } else {
                    clearInterval(timer);
                    $btn.removeClass('disabled').text('获取验证码');
                }
            }, 1000);
        }
    });
});
</script>