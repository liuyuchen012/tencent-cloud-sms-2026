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
        dbDelta($sql);
        
        // 添加统计表
        self::create_stats_table();
    }
    
    /**
     * 创建统计表
     */
    private static function create_stats_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'tcsms_stats';
        
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
        dbDelta($sql);
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
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE expires_at < %s",
                date('Y-m-d H:i:s', strtotime('-1 day'))
            )
        );
    }
}