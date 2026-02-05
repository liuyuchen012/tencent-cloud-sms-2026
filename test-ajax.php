<?php
/**
 * AJAX测试页面
 */
require_once('../../../wp-load.php');

// 只有管理员可以访问
if (!current_user_can('manage_options')) {
    die('权限不足');
}

// 生成nonce
$nonce = wp_create_nonce('tcsms_ajax_nonce');
?>
<!DOCTYPE html>
<html>
<head>
    <title>腾讯云短信插件AJAX测试</title>
    <script src="<?php echo includes_url('js/jquery/jquery.js'); ?>"></script>
</head>
<body>
    <h1>腾讯云短信插件AJAX测试</h1>
    
    <h2>测试信息</h2>
    <p>站点URL: <?php echo site_url(); ?></p>
    <p>AJAX URL: <?php echo admin_url('admin-ajax.php'); ?></p>
    <p>Nonce: <?php echo $nonce; ?></p>
    <p>Nonce验证: <?php echo wp_verify_nonce($nonce, 'tcsms_ajax_nonce') ? '有效' : '无效'; ?></p>
    
    <h2>发送测试</h2>
    <input type="text" id="test_phone" placeholder="13800138000" value="13800138000">
    <button id="test_send">发送测试短信</button>
    
    <h2>验证测试</h2>
    <input type="text" id="verify_code" placeholder="验证码">
    <button id="test_verify">验证测试</button>
    
    <h2>响应结果</h2>
    <pre id="result"></pre>
    
    <script>
    jQuery(document).ready(function($) {
        var nonce = '<?php echo $nonce; ?>';
        var ajaxurl = '<?php echo admin_url("admin-ajax.php"); ?>';
        
        // 发送测试
        $('#test_send').click(function() {
            var phone = $('#test_phone').val().trim();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tcsms_send_verification',
                    phone: phone,
                    nonce: nonce
                },
                dataType: 'json',
                success: function(response) {
                    $('#result').text(JSON.stringify(response, null, 2));
                },
                error: function(xhr, status, error) {
                    $('#result').text(
                        '状态: ' + status + '\n' +
                        '错误: ' + error + '\n' +
                        '响应: ' + xhr.responseText
                    );
                }
            });
        });
        
        // 验证测试
        $('#test_verify').click(function() {
            var phone = $('#test_phone').val().trim();
            var code = $('#verify_code').val().trim();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'tcsms_verify_code',
                    phone: phone,
                    code: code,
                    nonce: nonce
                },
                dataType: 'json',
                success: function(response) {
                    $('#result').text(JSON.stringify(response, null, 2));
                },
                error: function(xhr, status, error) {
                    $('#result').text(
                        '状态: ' + status + '\n' +
                        '错误: ' + error + '\n' +
                        '响应: ' + xhr.responseText
                    );
                }
            });
        });
    });
    </script>
</body>
</html>