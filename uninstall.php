<?php
/**
 * 插件卸载脚本
 * 
 * 当用户删除插件时执行
 * 
 * @package TencentCloudSMS
 */

// 如果未通过WordPress卸载，直接退出
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 删除数据库表
global $wpdb;

$tables = [
    $wpdb->prefix . 'tcsms_codes',
    $wpdb->prefix . 'tcsms_stats'
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// 删除选项
$options = [
    'tcsms_secret_id',
    'tcsms_secret_key',
    'tcsms_sdk_app_id',
    'tcsms_sign_name',
    'tcsms_template_id',
    'tcsms_region',
    'tcsms_enable_login',
    'tcsms_code_expiry',
    'tcsms_rate_limit',
    'tcsms_max_attempts',
    'tcsms_version'
];

foreach ($options as $option) {
    delete_option($option);
    delete_site_option($option); // 多站点支持
}

// 清理定时任务
$timestamp = wp_next_scheduled('tcsms_daily_cleanup');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'tcsms_daily_cleanup');
}