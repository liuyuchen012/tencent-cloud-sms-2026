(function($) {
    'use strict';
    
    $(document).ready(function() {
        // 确保登录表单存在
        if ($('#loginform').length) {
            // 添加登录方式选择
            $('#loginform').prepend(`
                <p class="tcsms-login-method">
                    <label>
                        <input type="radio" name="tcsms_login_method" value="password" checked> 密码登录
                    </label>
                    <label style="margin-left: 20px;">
                        <input type="radio" name="tcsms_login_method" value="sms"> 验证码登录
                    </label>
                </p>
            `);
            
            // 隐藏短信验证区域
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
                    url: tcsms_login?.ajax_url || '/wp-admin/admin-ajax.php',
                    type: 'POST',
                    data: {
                        action: 'tcsms_send_verification',
                        phone: phone,
                        nonce: tcsms_login?.nonce || ''
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
                    error: function(xhr, status, error) {
                        console.error('AJAX错误:', status, error);
                        alert('网络错误，请重试');
                        $button.removeClass('disabled').text('获取验证码');
                    }
                });
            });
            
            function startCountdown($btn) {
                var countdown = 60;
                var originalText = $btn.text();
                var timer;
                
                var updateButton = function() {
                    if (countdown > 0) {
                        $btn.text(countdown + '秒后重试');
                        countdown--;
                    } else {
                        clearInterval(timer);
                        $btn
                            .removeClass('disabled')
                            .text(originalText);
                    }
                };
                
                // 立即更新一次
                updateButton();
                
                // 设置定时器
                timer = setInterval(updateButton, 1000);
            }
        }
    });
    
})(jQuery);