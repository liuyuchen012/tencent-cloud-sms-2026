// [file name]: admin.js (替换原文件内容)
(function($) {
    'use strict';
    
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
        
        // 发送测试请求 - 使用 admin-settings.php 中定义的 nonce
        var nonce = tcsms_admin?.nonce || $('#tcsms_admin_nonce').val() || '';
        
        $.ajax({
            url: ajaxurl, // 使用WordPress提供的ajaxurl
            type: 'POST',
            data: {
                action: 'tcsms_send_verification',
                phone: phone,
                nonce: nonce
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
            error: function(xhr, status, error) {
                console.error('AJAX错误:', status, error);
                console.error('响应:', xhr.responseText);
                $result
                    .removeClass('success')
                    .addClass('error')
                    .text('网络错误：' + error + '，请检查控制台查看详情')
                    .show();
                $button.prop('disabled', false).text('发送测试短信');
            }
        });
    });
    
    // 输入框实时验证
    $('#tcsms_test_phone').on('input', function() {
        var phone = $(this).val().trim();
        var $result = $('#tcsms_test_result');
        
        if (phone && !/^1[3-9]\d{9}$/.test(phone)) {
            $result
                .removeClass('success')
                .addClass('error')
                .text('手机号码格式不正确')
                .show();
        } else {
            $result.hide();
        }
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
    
    // 配置验证
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
    
})(jQuery);