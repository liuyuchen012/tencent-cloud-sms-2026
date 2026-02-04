<?php
namespace TCSMS;

require_once TCSMS_PLUGIN_DIR . 'vendor/autoload.php';

use TencentCloud\Common\Credential;
use TencentCloud\Common\Profile\ClientProfile;
use TencentCloud\Common\Profile\HttpProfile;
use TencentCloud\Sms\V20210111\SmsClient;
use TencentCloud\Sms\V20210111\Models\SendSmsRequest;

class SMS_API {
    private $client;
    
    public function __construct() {
        $this->init_client();
    }
    
    private function init_client() {
        $secret_id = get_option('tcsms_secret_id');
        $secret_key = get_option('tcsms_secret_key');
        $region = get_option('tcsms_region', 'ap-guangzhou');
        
        if (empty($secret_id) || empty($secret_key)) {
            return;
        }
        
        try {
            $cred = new Credential($secret_id, $secret_key);
            $httpProfile = new HttpProfile();
            $httpProfile->setEndpoint("sms.tencentcloudapi.com");
            
            $clientProfile = new ClientProfile();
            $clientProfile->setHttpProfile($httpProfile);
            
            $this->client = new SmsClient($cred, $region, $clientProfile);
        } catch (\Exception $e) {
            error_log('腾讯云SMS初始化失败: ' . $e->getMessage());
        }
    }
    
    public function send_verification_code($phone, $code) {
        if (!$this->client) {
            return new \WP_Error('client_not_initialized', __('短信服务未配置', 'tencent-cloud-sms'));
        }
        
        // 检查发送频率
        if (!$this->check_rate_limit($phone)) {
            return new \WP_Error('rate_limit', __('发送过于频繁，请稍后再试', 'tencent-cloud-sms'));
        }
        
        try {
            $req = new SendSmsRequest();
            $req->setSmsSdkAppId(get_option('tcsms_sdk_app_id'));
            $req->setSignName(get_option('tcsms_sign_name'));
            $req->setTemplateId(get_option('tcsms_template_id'));
            $req->setPhoneNumberSet(["+86" . $phone]);
            $req->setTemplateParamSet([$code, get_option('tcsms_code_expiry', 5)]); // 验证码和有效期(分钟)
            
            $resp = $this->client->SendSms($req);
            $result = $resp->serialize();
            
            // 保存验证码到数据库
            $this->save_verification_code($phone, $code);
            
            return [
                'success' => true,
                'message' => __('验证码发送成功', 'tencent-cloud-sms')
            ];
        } catch (\Exception $e) {
            error_log('短信发送失败: ' . $e->getMessage());
            return new \WP_Error('send_failed', $e->getMessage());
        }
    }
    
    private function check_rate_limit($phone) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tcsms_codes';
        
        // 检查60秒内是否已经发送过
        $recent = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name 
             WHERE phone = %s AND created_at > DATE_SUB(NOW(), INTERVAL 60 SECOND)",
            $phone
        ));
        
        return $recent == 0;
    }
    
    private function save_verification_code($phone, $code) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tcsms_codes';
        
        $expiry_minutes = get_option('tcsms_code_expiry', 5);
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiry_minutes} minutes"));
        
        $wpdb->insert($table_name, [
            'phone' => $phone,
            'code' => $code,
            'ip_address' => $this->get_client_ip(),
            'expires_at' => $expires_at,
            'verified' => 0
        ]);
    }
    
    public function verify_code($phone, $code) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tcsms_codes';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE phone = %s AND code = %s AND verified = 0 AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1",
            $phone, $code
        ));
        
        if ($result) {
            // 标记为已验证
            $wpdb->update($table_name, ['verified' => 1], ['id' => $result->id]);
            return true;
        }
        
        return false;
    }
    
    public function ajax_send_code() {
        check_ajax_referer('tcsms_nonce', 'nonce');
        
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            wp_send_json_error(['message' => __('手机号码格式不正确', 'tencent-cloud-sms')]);
        }
        
        // 生成6位验证码
        $code = sprintf('%06d', mt_rand(0, 999999));
        
        $result = $this->send_verification_code($phone, $code);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => __('验证码发送成功', 'tencent-cloud-sms')]);
    }
    
    public function ajax_verify_code() {
        check_ajax_referer('tcsms_nonce', 'nonce');
        
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $code = sanitize_text_field($_POST['code'] ?? '');
        
        if (empty($phone) || empty($code)) {
            wp_send_json_error(['message' => __('请输入手机号和验证码', 'tencent-cloud-sms')]);
        }
        
        if ($this->verify_code($phone, $code)) {
            wp_send_json_success(['message' => __('验证成功', 'tencent-cloud-sms')]);
        } else {
            wp_send_json_error(['message' => __('验证码错误或已过期', 'tencent-cloud-sms')]);
        }
    }
    
    private function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        return $ip;
    }
}