<?php
/**
 * 数据库操作类
 * 
 * @package TencentCloudSMS
 */

if (!defined('ABSPATH')) {
    exit;
}

class TCSMS_DB {
    
    /**
     * 表名
     * 
     * @var string
     */
    private $table_name;
    
    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'tcsms_codes';
    }
    
    /**
     * 创建数据库表
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'tcsms_codes';
        
        error_log('腾讯云短信：开始创建数据库表...');
        
        // 检查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('腾讯云短信：表不存在，正在创建...');
            
            $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                phone varchar(20) NOT NULL,
                code varchar(10) NOT NULL,
                ip_address varchar(45) DEFAULT NULL,
                verified tinyint(1) DEFAULT 0,
                expires_at datetime NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_phone (phone),
                KEY idx_expires_at (expires_at),
                KEY idx_created_at (created_at),
                KEY idx_phone_verified (phone, verified)
            ) {$charset_collate};";
            
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $result = dbDelta($sql);
            
            if (is_wp_error($result)) {
                error_log('腾讯云短信：创建表失败，错误：' . $result->get_error_message());
            } else {
                error_log('腾讯云短信：表创建成功或已存在');
            }
        } else {
            error_log('腾讯云短信：表已存在');
        }
        
        // 添加统计表
        self::create_stats_table();
        
        // 添加手机号绑定记录表
        self::create_phone_binding_table();
    }
    
    /**
     * 创建手机号绑定记录表
     */
    private static function create_phone_binding_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'tcsms_phone_bindings';
        
        // 检查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('腾讯云短信：创建手机号绑定记录表...');
            
            $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                phone varchar(20) NOT NULL,
                ip_address varchar(45) DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY idx_user_id (user_id),
                UNIQUE KEY idx_phone (phone),
                KEY idx_created_at (created_at)
            ) {$charset_collate};";
            
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $result = dbDelta($sql);
            
            if (is_wp_error($result)) {
                error_log('腾讯云短信：创建手机号绑定记录表失败，错误：' . $result->get_error_message());
            } else {
                error_log('腾讯云短信：手机号绑定记录表创建成功');
            }
        }
    }
    
    /**
     * 创建统计表
     */
    private static function create_stats_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'tcsms_stats';
        
        // 检查表是否存在
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            error_log('腾讯云短信：创建统计表...');
            
            $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                date date NOT NULL,
                sent_count int(11) DEFAULT 0,
                verified_count int(11) DEFAULT 0,
                failed_count int(11) DEFAULT 0,
                PRIMARY KEY (id),
                UNIQUE KEY idx_date (date)
            ) {$charset_collate};";
            
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $result = dbDelta($sql);
            
            if (is_wp_error($result)) {
                error_log('腾讯云短信：创建统计表失败，错误：' . $result->get_error_message());
            } else {
                error_log('腾讯云短信：统计表创建成功');
            }
        }
    }
    
    /**
     * 标记验证码为已使用
     * 
     * @param string $phone 手机号码
     * @param string $code 验证码
     * @return bool 是否成功
     */
    public function mark_code_used($phone, $code) {
        global $wpdb;
        
        return $wpdb->update(
            $this->table_name,
            ['verified' => 1],
            [
                'phone' => $phone,
                'code' => $code,
                'verified' => 0,
                'expires_at >' => current_time('mysql')
            ],
            ['%d'],
            ['%s', '%s', '%d', '%s']
        );
    }
    

    /**
     * 清理过期验证码
     */
    public function clean_expired_codes() {
        global $wpdb;
        
        // 修复：使用 WordPress 时间
        $current_time = current_time('mysql');
        
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE expires_at < %s",
                $current_time
            )
        );
        
        error_log('腾讯云短信：清理了 ' . $deleted . ' 条过期验证码记录，当前时间：' . $current_time);
    }
}