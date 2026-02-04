<?php
/**
 * 短信验证表单模板
 * 
 * @package TencentCloudSMS
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="tcsms-form-container <?php echo esc_attr($atts['class']); ?>">
    <?php if (!empty($atts['title'])): ?>
        <h3 class="tcsms-form-title"><?php echo esc_html($atts['title']); ?></h3>
    <?php endif; ?>
    
    <div class="tcsms-form-group">
        <label for="tcsms_phone_<?php echo uniqid(); ?>">
            <?php echo esc_html($atts['phone_label']); ?>
        </label>
        <input type="tel" 
               id="tcsms_phone_<?php echo uniqid(); ?>" 
               class="tcsms-phone-input" 
               pattern="1[3-9]\d{9}" 
               maxlength="11" 
               placeholder="<?php esc_attr_e('请输入手机号码', 'tencent-cloud-sms'); ?>" 
               required>
    </div>
    
    <div class="tcsms-form-group">
        <label for="tcsms_code_<?php echo uniqid(); ?>">
            <?php echo esc_html($atts['code_label']); ?>
        </label>
        <div class="tcsms-code-container">
            <input type="text" 
                   id="tcsms_code_<?php echo uniqid(); ?>" 
                   class="tcsms-code-input" 
                   maxlength="6" 
                   placeholder="<?php esc_attr_e('请输入验证码', 'tencent-cloud-sms'); ?>" 
                   required>
            <button type="button" 
                    class="tcsms-send-btn button">
                <?php echo esc_html($atts['button_text']); ?>
            </button>
        </div>
    </div>
    
    <div class="tcsms-form-group">
        <button type="button" 
                class="tcsms-verify-btn button button-primary">
            <?php echo esc_html($atts['submit_text']); ?>
        </button>
    </div>
    
    <div class="tcsms-message" style="display: none;"></div>
</div>