<?php
/**
 * 腾讯云短信API类
 * 
 * @package TencentCloudSMS
 */

if (!defined('ABSPATH')) {
    exit;
}

class TCSMS_API {
    
    /**
     * 腾讯云短信客户端
     * 
     * @var mixed
     */
    private $client = null;
    
    /**
     * 构造函数
     */
    public function __construct() {
        // 尝试加载腾讯云SDK
        $this->init_client();
    }
    
    /**
     * 初始化腾讯云客户端
     */
    private function init_client() {
        $secret_id = get_option('tcsms_secret_id');
        $secret_key = get_option('tcsms_secret_key');
        $region = get_option('tcsms_region', 'ap-guangzhou');
        
        if (empty($secret_id) || empty($secret_key)) {
            return;
        }
        
        try {
            // 检查腾讯云SDK是否可用
            if (class_exists('TencentCloud\\Common\\Credential')) {
                $cred = new TencentCloud\Common\Credential($secret_id, $secret_key);
                $httpProfile = new TencentCloud\Common\Profile\HttpProfile();
                $httpProfile->setEndpoint("sms.tencentcloudapi.com");
                
                $clientProfile = new TencentCloud\Common\Profile\ClientProfile();
                $clientProfile->setHttpProfile($httpProfile);
                
                $this->client = new TencentCloud\Sms\V20210111\SmsClient($cred, $region, $clientProfile);
            } else {
                // SDK未加载，使用测试模式
                error_log('腾讯云短信：SDK未加载，启用测试模式');
            }
        } catch (Exception $e) {
            error_log('腾讯云SMS初始化失败: ' . $e->getMessage());
        }
    }
    
    /**
     * 发送验证码
     * 
     * @param string $phone 手机号码
     * @param string $code 验证码
     * @return array|WP_Error 发送结果
     */
    public function send_verification_code($phone, $code) {
        global $wpdb;
        
        // 验证手机号格式
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            return new WP_Error('invalid_phone', __('手机号码格式不正确', 'tencent-cloud-sms'));
        }
        
        // 检查发送频率限制
        if (!$this->check_rate_limit($phone)) {
            $limit = get_option('tcsms_rate_limit', 60);
            return new WP_Error('rate_limit', 
                sprintf(__('发送过于频繁，请%s秒后再试', 'tencent-cloud-sms'), $limit));
        }
        
        // 检查最大尝试次数
        if (!$this->check_max_attempts($phone)) {
            return new WP_Error('max_attempts', __('今日发送次数已用完，请明天再试', 'tencent-cloud-sms'));
        }
        
        // 如果没有SDK，返回模拟成功用于测试
        if (!$this->client) {
            // 模拟发送成功，用于测试环境
            $this->save_verification_code($phone, $code);
            return [
                'success' => true,
                'message' => __('验证码发送成功（测试模式）', 'tencent-cloud-sms')
            ];
        }
        
        try {
            $sdk_app_id = get_option('tcsms_sdk_app_id');
            $sign_name = get_option('tcsms_sign_name');
            $template_id = get_option('tcsms_template_id');
            $expiry = get_option('tcsms_code_expiry', 5);
            
            if (empty($sdk_app_id) || empty($sign_name) || empty($template_id)) {
                return new WP_Error('config_incomplete', __('短信配置不完整', 'tencent-cloud-sms'));
            }
            
            $req = new TencentCloud\Sms\V20210111\Models\SendSmsRequest();
            $req->setSmsSdkAppId($sdk_app_id);
            $req->setSignName($sign_name);
            $req->setTemplateId($template_id);
            $req->setPhoneNumberSet(["+86{$phone}"]);
            $req->setTemplateParamSet([$code, $expiry]);
            
            $resp = $this->client->SendSms($req);
            $response = $resp->serialize();
            
            if (isset($response['SendStatusSet'][0]['Code']) && 
                $response['SendStatusSet'][0]['Code'] == 'Ok') {
                
                // 保存验证码到数据库
                $this->save_verification_code($phone, $code);
                
                return [
                    'success' => true,
                    'message' => __('验证码发送成功', 'tencent-cloud-sms'),
                    'data' => $response
                ];
            } else {
                $error_msg = $response['SendStatusSet'][0]['Message'] ?? __('未知错误', 'tencent-cloud-sms');
                return new WP_Error('send_failed', $error_msg);
            }
        } catch (Exception $e) {
            error_log('腾讯云短信发送失败: ' . $e->getMessage());
            return new WP_Error('send_exception', __('短信发送失败，请稍后重试', 'tencent-cloud-sms'));
        }
    }
    
    /**
     * 检查发送频率限制
     * 
     * @param string $phone 手机号码
     * @return bool 是否允许发送
     */
    private function check_rate_limit($phone) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tcsms_codes';
        $rate_limit = get_option('tcsms_rate_limit', 60);
        
        $recent_sent = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE phone = %s AND created_at > DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $phone, $rate_limit
        ));
        
        return $recent_sent == 0;
    }
    
    /**
     * 检查最大尝试次数
     * 
     * @param string $phone 手机号码
     * @return bool 是否允许发送
     */
    private function check_max_attempts($phone) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tcsms_codes';
        $max_attempts = get_option('tcsms_max_attempts', 10);
        
        $today_attempts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE phone = %s AND DATE(created_at) = CURDATE()",
            $phone
        ));
        
        return $today_attempts < $max_attempts;
    }
    
    /**
     * 保存验证码到数据库
     * 
     * @param string $phone 手机号码
     * @param string $code 验证码
     */
    private function save_verification_code($phone, $code) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tcsms_codes';
        $expiry = get_option('tcsms_code_expiry', 5);
        
        $data = [
            'phone' => $phone,
            'code' => $code,
            'ip_address' => $this->get_client_ip(),
            'expires_at' => date('Y-m-d H:i:s', time() + $expiry * 60),
            'verified' => 0,
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert($table_name, $data);
    }
    
    /**
     * 验证验证码
     * 
     * @param string $phone 手机号码
     * @param string $code 验证码
     * @return bool 是否验证成功
     */
    public function verify_code($phone, $code) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tcsms_codes';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table_name} 
             WHERE phone = %s AND code = %s AND verified = 0 AND expires_at > NOW()
             ORDER BY created_at DESC LIMIT 1",
            $phone, $code
        ));
        
        return !empty($result);
    }
    
    /**
     * AJAX发送验证码
     */
    public function ajax_send_verification() {
        check_ajax_referer('tcsms_nonce', 'nonce');
        
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        
        if (empty($phone)) {
            wp_send_json_error(['message' => __('请输入手机号码', 'tencent-cloud-sms')]);
        }
        
        // 生成6位随机验证码
        $code = sprintf('%06d', mt_rand(0, 999999));
        
        $result = $this->send_verification_code($phone, $code);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        $response_data = [
            'message' => $result['message'],
        ];
        
        // 测试环境下返回验证码（无SDK时）
        if (!$this->client && isset($result['message']) && strpos($result['message'], '测试模式') !== false) {
            $response_data['code'] = $code;
        }
        
        wp_send_json_success($response_data);
    }
    
    /**
     * AJAX验证验证码
     */
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
    
    /**
     * 获取客户端IP地址
     * 
     * @return string IP地址
     */
    private function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        // 处理多个IP的情况
        if (strpos($ip, ',') !== false) {
            $ips = explode(',', $ip);
            $ip = trim($ips[0]);
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * AJAX清理过期数据
     */
    public function ajax_clean_expired() {
        check_ajax_referer('tcsms_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('权限不足', 'tencent-cloud-sms')]);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tcsms_codes';
        
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE expires_at < %s OR created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)",
                current_time('mysql')
            )
        );
        
        if ($deleted !== false) {
            wp_send_json_success([
                'message' => __('清理完成', 'tencent-cloud-sms'),
                'deleted' => $deleted
            ]);
        } else {
            wp_send_json_error(['message' => __('清理失败', 'tencent-cloud-sms')]);
        }
    }
}