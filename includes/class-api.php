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
        
        error_log('腾讯云短信：开始初始化客户端');
        error_log('腾讯云短信：SecretId=' . (!empty($secret_id) ? '已设置' : '未设置'));
        error_log('腾讯云短信：SecretKey=' . (!empty($secret_key) ? '已设置' : '未设置'));
        error_log('腾讯云短信：Region=' . $region);
        
        if (empty($secret_id) || empty($secret_key)) {
            error_log('腾讯云短信：API密钥未配置，启用测试模式');
            return;
        }
        
        try {
            // 检查腾讯云SDK是否可用
            if (!class_exists('TencentCloud\\Common\\Credential')) {
                error_log('腾讯云短信：TencentCloud SDK类未找到');
                // 尝试直接包含SDK文件
                $sdk_path = TCSMS_PLUGIN_DIR . 'vendor/tencentcloud/tencentcloud-sdk-php/src/TencentCloud/Common/Credential.php';
                if (file_exists($sdk_path)) {
                    require_once $sdk_path;
                } else {
                    error_log('腾讯云短信：SDK文件不存在于：' . $sdk_path);
                }
            }
            
            if (class_exists('TencentCloud\\Common\\Credential')) {
                error_log('腾讯云短信：正在创建凭证');
                $cred = new TencentCloud\Common\Credential($secret_id, $secret_key);
                $httpProfile = new TencentCloud\Common\Profile\HttpProfile();
                $httpProfile->setEndpoint("sms.tencentcloudapi.com");
                
                $clientProfile = new TencentCloud\Common\Profile\ClientProfile();
                $clientProfile->setHttpProfile($httpProfile);
                
                error_log('腾讯云短信：正在创建SMS客户端');
                $this->client = new TencentCloud\Sms\V20210111\SmsClient($cred, $region, $clientProfile);
                error_log('腾讯云短信：客户端初始化成功');
            } else {
                // SDK未加载，使用测试模式
                error_log('腾讯云短信：SDK未加载，启用测试模式');
            }
        } catch (Exception $e) {
            error_log('腾讯云SMS初始化失败: ' . $e->getMessage());
            error_log('异常追踪：' . $e->getTraceAsString());
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
        
        error_log('腾讯云短信：开始发送验证码，手机号：' . $phone);
        
        // 验证手机号格式
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            error_log('腾讯云短信：手机号格式无效：' . $phone);
            return new WP_Error('invalid_phone', __('手机号码格式不正确', 'tencent-cloud-sms'));
        }
        
        // 检查发送频率限制
        if (!$this->check_rate_limit($phone)) {
            $limit = get_option('tcsms_rate_limit', 60);
            error_log('腾讯云短信：发送频率限制，手机号：' . $phone);
            return new WP_Error('rate_limit', 
                sprintf(__('发送过于频繁，请%s秒后再试', 'tencent-cloud-sms'), $limit));
        }
        
        // 检查最大尝试次数
        if (!$this->check_max_attempts($phone)) {
            error_log('腾讯云短信：超过最大尝试次数，手机号：' . $phone);
            return new WP_Error('max_attempts', __('今日发送次数已用完，请明天再试', 'tencent-cloud-sms'));
        }
        
        // 如果没有SDK，返回模拟成功用于测试
        if (!$this->client) {
            error_log('腾讯云短信：SDK未加载，进入测试模式，手机号：' . $phone . '，验证码：' . $code);
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
            
            error_log('腾讯云短信：开始调用SDK发送短信');
            error_log('腾讯云短信：SDK AppId=' . $sdk_app_id);
            error_log('腾讯云短信：签名=' . $sign_name);
            error_log('腾讯云短信：模板ID=' . $template_id);
            
            if (empty($sdk_app_id) || empty($sign_name) || empty($template_id)) {
                error_log('腾讯云短信：短信配置不完整');
                return new WP_Error('config_incomplete', __('短信配置不完整', 'tencent-cloud-sms'));
            }
            
            $req = new TencentCloud\Sms\V20210111\Models\SendSmsRequest();
            $req->setSmsSdkAppId($sdk_app_id);
            $req->setSignName($sign_name);
            $req->setTemplateId($template_id);
            $req->setPhoneNumberSet(["+86{$phone}"]);
            $req->setTemplateParamSet([(string)$code]);
            
            error_log('腾讯云短信：准备发送请求');
            $resp = $this->client->SendSms($req);
            $response = $resp->serialize();
            error_log('腾讯云短信：响应：' . json_encode($response));
            
            if (isset($response['SendStatusSet'][0]['Code']) && 
                $response['SendStatusSet'][0]['Code'] == 'Ok') {
                
                error_log('腾讯云短信：发送成功，手机号：' . $phone);
                // 保存验证码到数据库
                $this->save_verification_code($phone, $code);
                
                return [
                    'success' => true,
                    'message' => __('验证码发送成功', 'tencent-cloud-sms'),
                    'data' => $response
                ];
            } else {
                $error_code = $response['SendStatusSet'][0]['Code'] ?? 'Unknown';
                $error_msg = $response['SendStatusSet'][0]['Message'] ?? __('未知错误', 'tencent-cloud-sms');
                error_log('腾讯云短信：发送失败，错误码：' . $error_code . '，错误信息：' . $error_msg);
                return new WP_Error('send_failed', $error_msg);
            }
        } catch (Exception $e) {
            error_log('腾讯云短信发送异常：' . $e->getMessage());
            error_log('异常追踪：' . $e->getTraceAsString());
            return new WP_Error('send_exception', __('短信发送失败：' . $e->getMessage(), 'tencent-cloud-sms'));
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
        
        // 使用 WordPress 时间
        $current_time = current_time('mysql');
        
        $recent_sent = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE phone = %s AND created_at > DATE_SUB(%s, INTERVAL %d SECOND)",
            $phone, $current_time, $rate_limit
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
        
        // 使用 WordPress 时间的当天开始时间
        $today_start = date('Y-m-d 00:00:00', current_time('timestamp'));
        
        $today_attempts = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE phone = %s AND created_at >= %s",
            $phone, $today_start
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
        
        // 统一使用 WordPress 时间函数
        $current_time = current_time('mysql');
        $current_timestamp = current_time('timestamp');
        
        // 计算过期时间：使用 WordPress 时间戳
        $expires_at = date('Y-m-d H:i:s', $current_timestamp + $expiry * 60);
        
        $data = [
            'phone' => $phone,
            'code' => $code,
            'ip_address' => $this->get_client_ip(),
            'expires_at' => $expires_at,
            'verified' => 0,
            'created_at' => $current_time
        ];
        
        error_log('腾讯云短信：保存验证码到数据库');
        error_log('手机号：' . $phone);
        error_log('验证码：' . $code);
        error_log('当前时间：' . $current_time);
        error_log('过期时间：' . $expires_at);
        error_log('当前时间戳：' . $current_timestamp);
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            error_log('腾讯云短信：保存验证码失败，错误：' . $wpdb->last_error);
        } else {
            error_log('腾讯云短信：保存验证码成功，ID：' . $wpdb->insert_id);
        }
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
        
        $current_time = current_time('mysql');
        
        error_log('腾讯云短信：开始验证验证码');
        error_log('手机号：' . $phone);
        error_log('输入的验证码：' . $code);
        error_log('当前时间：' . $current_time);
        
        // 先查询所有未验证的验证码用于调试
        $all_codes = $wpdb->get_results($wpdb->prepare(
            "SELECT id, phone, code, expires_at, created_at, verified 
             FROM {$table_name} 
             WHERE phone = %s AND verified = 0 
             ORDER BY created_at DESC",
            $phone
        ));
        
        if ($all_codes) {
            error_log('腾讯云短信：找到 ' . count($all_codes) . ' 条未验证记录');
            foreach ($all_codes as $record) {
                error_log('记录ID：' . $record->id . 
                         '，验证码：' . $record->code . 
                         '，创建时间：' . $record->created_at . 
                         '，过期时间：' . $record->expires_at . 
                         '，是否过期：' . ($record->expires_at < $current_time ? '是' : '否'));
            }
        } else {
            error_log('腾讯云短信：未找到该手机号的验证码记录');
        }
        
        // 正式查询（只查询未过期且未验证的）
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT id, phone, code, expires_at, created_at FROM {$table_name} 
             WHERE phone = %s AND code = %s AND verified = 0 AND expires_at > %s
             ORDER BY created_at DESC LIMIT 1",
            $phone, $code, $current_time
        ));
        
        if (!empty($result)) {
            error_log('腾讯云短信：验证成功，找到匹配的记录，ID：' . $result->id);
            error_log('创建时间：' . $result->created_at);
            error_log('过期时间：' . $result->expires_at);
            error_log('时间差：' . (strtotime($result->expires_at) - strtotime($current_time)) . '秒');
            return true;
        } else {
            error_log('腾讯云短信：验证失败，没有找到匹配的记录');
            return false;
        }
    }
    
    /**
     * AJAX发送验证码
     */
    public function ajax_send_verification() {
        // 调试日志
        error_log('腾讯云短信：收到AJAX发送验证码请求');
        
        $nonce = $_POST['nonce'] ?? '';
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        
        // 验证nonce - 支持前台和后台两种nonce
        $valid_nonce = false;
        
        if (wp_verify_nonce($nonce, 'tcsms_admin_nonce')) {
            // 后台管理员nonce验证
            error_log('腾讯云短信：使用tcsms_admin_nonce验证成功');
            $valid_nonce = true;
        } elseif (wp_verify_nonce($nonce, 'tcsms_ajax_nonce')) {
            // 前台ajax nonce验证
            error_log('腾讯云短信：使用tcsms_ajax_nonce验证成功');
            $valid_nonce = true;
        } elseif (wp_verify_nonce($nonce, 'tcsms_bind_phone_action')) {
            // 个人中心绑定手机号nonce验证
            error_log('腾讯云短信：使用tcsms_bind_phone_action验证成功');
            $valid_nonce = true;
        }
        
        if (!$valid_nonce) {
            error_log('腾讯云短信：所有nonce验证失败，收到的nonce：' . $nonce);
            wp_send_json_error(['message' => __('安全验证失败，请刷新页面重试', 'tencent-cloud-sms')]);
            wp_die();
        }
        
        if (empty($phone)) {
            wp_send_json_error(['message' => __('请输入手机号码', 'tencent-cloud-sms')]);
            wp_die();
        }
        
        // 验证手机号格式
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            wp_send_json_error(['message' => __('手机号码格式不正确', 'tencent-cloud-sms')]);
            wp_die();
        }
        
        // 生成6位随机验证码
        $code = sprintf('%06d', mt_rand(0, 999999));
        
        $result = $this->send_verification_code($phone, $code);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            wp_die();
        }
        
        $response_data = [
            'message' => $result['message'] ?? __('发送成功', 'tencent-cloud-sms'),
        ];
        
        // 测试环境下返回验证码（无SDK时）
        if (!$this->client && isset($result['message']) && strpos($result['message'], '测试模式') !== false) {
            $response_data['code'] = $code;
        }
        
        wp_send_json_success($response_data);
        wp_die(); // 必须调用wp_die()来结束请求
    }
    
    /**
     * AJAX验证验证码
     */
    public function ajax_verify_code() {
        // 验证nonce - 支持前台和后台两种nonce
        $nonce = $_POST['nonce'] ?? '';
        $valid_nonce = false;
        
        if (wp_verify_nonce($nonce, 'tcsms_admin_nonce')) {
            $valid_nonce = true;
        } elseif (wp_verify_nonce($nonce, 'tcsms_ajax_nonce')) {
            $valid_nonce = true;
        } elseif (wp_verify_nonce($nonce, 'tcsms_bind_phone_action')) {
            $valid_nonce = true;
        }
        
        if (!$valid_nonce) {
            wp_send_json_error(['message' => __('安全验证失败，请刷新页面重试', 'tencent-cloud-sms')]);
            wp_die();
        }
        
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $code = sanitize_text_field($_POST['code'] ?? '');
        
        if (empty($phone) || empty($code)) {
            wp_send_json_error(['message' => __('请输入手机号和验证码', 'tencent-cloud-sms')]);
            wp_die();
        }
        
        if ($this->verify_code($phone, $code)) {
            wp_send_json_success(['message' => __('验证成功', 'tencent-cloud-sms')]);
        } else {
            wp_send_json_error(['message' => __('验证码错误或已过期', 'tencent-cloud-sms')]);
        }
        
        wp_die(); // 必须调用wp_die()来结束请求
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
        
        $current_time = current_time('mysql');
        
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE expires_at < %s OR created_at < DATE_SUB(%s, INTERVAL 30 DAY)",
                $current_time, $current_time
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
    
    /**
     * 标记验证码为已验证
     * 
     * @param string $phone 手机号码
     * @param string $code 验证码
     * @return bool 是否成功
     */
    public function mark_code_verified($phone, $code) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tcsms_codes';
        
        return $wpdb->update(
            $table_name,
            ['verified' => 1],
            [
                'phone' => $phone,
                'code' => $code,
                'verified' => 0
            ],
            ['%d'],
            ['%s', '%s', '%d']
        );
    }
}