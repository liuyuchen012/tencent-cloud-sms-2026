(function($) {
    'use strict';
    
    var TCSMS = {
        // 初始化
        init: function() {
            this.bindEvents();
        },
        
        // 绑定事件
        bindEvents: function() {
            $(document).on('click', '.tcsms-send-btn', this.sendCode.bind(this));
            $(document).on('click', '.tcsms-verify-btn', this.verifyCode.bind(this));
            
            // 登录页面特定处理
            if ($('#tcsms_login_send').length) {
                $('#tcsms_login_send').on('click', this.sendLoginCode.bind(this));
            }
        },
        
        // 发送验证码
        sendCode: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var $container = $button.closest('.tcsms-form-container');
            var $phoneInput = $container.find('.tcsms-phone-input');
            var phone = $phoneInput.val().trim();
            
            // 验证手机号
            if (!this.validatePhone(phone)) {
                this.showMessage($container, '请输入有效的手机号码', 'error');
                return;
            }
            
            // 检查是否在冷却期
            if ($button.hasClass('disabled')) {
                return;
            }
            
            // 发送请求
            $button.addClass('disabled').text('发送中...');
            
            $.ajax({
                url: window.tcsms_frontend?.ajax_url || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'tcsms_send_verification',
                    phone: phone,
                    nonce: window.tcsms_frontend?.nonce || ''
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        TCSMS.showMessage($container, response.data.message || '发送成功', 'success');
                        TCSMS.startCountdown($button);
                        
                        // 测试环境下显示验证码
                        if (response.data.code) {
                            console.log('测试验证码：', response.data.code);
                        }
                    } else {
                        TCSMS.showMessage($container, response.data.message || '发送失败', 'error');
                        $button.removeClass('disabled').text('获取验证码');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX错误:', status, error);
                    console.error('响应:', xhr.responseText);
                    
                    var errorMsg = '网络错误，请检查控制台查看详情';
                    if (xhr.responseText) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            errorMsg = response.data?.message || errorMsg;
                        } catch(e) {
                            errorMsg = xhr.responseText.substring(0, 100);
                        }
                    }
                    
                    TCSMS.showMessage($container, errorMsg, 'error');
                    $button.removeClass('disabled').text('获取验证码');
                }
            });
        },
        
        // 发送登录验证码
        sendLoginCode: function() {
            var $button = $('#tcsms_login_send');
            var $phoneInput = $('#tcsms_login_phone');
            var phone = $phoneInput.val().trim();
            
            if (!this.validatePhone(phone)) {
                alert('请输入有效的手机号码');
                return;
            }
            
            if ($button.hasClass('disabled')) {
                return;
            }
            
            $button.addClass('disabled').text('发送中...');
            
            $.ajax({
                url: window.tcsms_frontend?.ajax_url || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'tcsms_send_verification',
                    phone: phone,
                    nonce: window.tcsms_frontend?.nonce || ''
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message || '发送成功');
                        TCSMS.startCountdown($button);
                    } else {
                        alert(response.data.message || '发送失败');
                        $button.removeClass('disabled').text('获取验证码');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX错误:', status, error);
                    console.error('响应:', xhr.responseText);
                    alert('网络错误，请检查控制台查看详情');
                    $button.removeClass('disabled').text('获取验证码');
                }
            });
        },
        
        // 验证验证码
        verifyCode: function(e) {
            e.preventDefault();
            
            var $button = $(e.currentTarget);
            var $container = $button.closest('.tcsms-form-container');
            var $phoneInput = $container.find('.tcsms-phone-input');
            var $codeInput = $container.find('.tcsms-code-input');
            
            var phone = $phoneInput.val().trim();
            var code = $codeInput.val().trim();
            
            // 验证输入
            if (!this.validatePhone(phone)) {
                this.showMessage($container, '请输入有效的手机号码', 'error');
                return;
            }
            
            if (code.length !== 6) {
                this.showMessage($container, '请输入6位验证码', 'error');
                return;
            }
            
            // 禁用按钮
            $button.prop('disabled', true).text('验证中...');
            
            // 发送验证请求
            $.ajax({
                url: window.tcsms_frontend?.ajax_url || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'tcsms_verify_code',
                    phone: phone,
                    code: code,
                    nonce: window.tcsms_frontend?.nonce || ''
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        TCSMS.showMessage($container, response.data.message || '验证成功', 'success');
                        
                        // 触发自定义事件，供其他脚本监听
                        $(document).trigger('tcsms_verified', {
                            phone: phone,
                            code: code
                        });
                        
                        // 3秒后重置表单
                        setTimeout(function() {
                            $codeInput.val('');
                            TCSMS.showMessage($container, '', 'success', true);
                            $button.prop('disabled', false).text('验证');
                        }, 3000);
                    } else {
                        TCSMS.showMessage($container, response.data.message || '验证失败', 'error');
                        $button.prop('disabled', false).text('验证');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX错误:', status, error);
                    console.error('响应:', xhr.responseText);
                    TCSMS.showMessage($container, '验证失败，请重试', 'error');
                    $button.prop('disabled', false).text('验证');
                }
            });
        },
        
        // 验证手机号
        validatePhone: function(phone) {
            return /^1[3-9]\d{9}$/.test(phone);
        },
        
        // 显示消息
        showMessage: function($container, message, type, hide) {
            var $message = $container.find('.tcsms-message');
            
            if (!hide) {
                $message
                    .removeClass('success error warning')
                    .addClass(type)
                    .text(message)
                    .show();
            } else {
                $message.hide();
            }
        },
        
        // 开始倒计时
        startCountdown: function($button) {
            var countdown = 60;
            var originalText = $button.text();
            var timer;
            
            var updateButton = function() {
                if (countdown > 0) {
                    $button.text(countdown + '秒后重试');
                    countdown--;
                } else {
                    clearInterval(timer);
                    $button
                        .removeClass('disabled')
                        .text(originalText);
                }
            };
            
            // 立即更新一次
            updateButton();
            
            // 设置定时器
            timer = setInterval(updateButton, 1000);
        }
    };
    
    // 初始化
    $(document).ready(function() {
        TCSMS.init();
    });
    
})(jQuery);