# 腾讯云短信验证码插件2026

**最后更新**: 2026年2月5日  
**版本**: 1.0.0  
**作者**: LiuYuchen  / 刘宇晨
**联系方式**: liuyuchen032901@outlook.com  
**项目地址**: https://github.com/liuyuchen012/tencent-cloud-sms-2026

> [配置步骤请查看wiki](https://github.com/liuyuchen012/tencent-cloud-sms-2026/wiki)

## 📄 许可证

本插件采用 **GPL v3.0** 许可证发布。


### 完整许可证
请查看 `LICENSE` 文件或访问：
[https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html)

---





## 实际应用示例（部分已由插件自动导入）

**用户注册页面**
```html
<div class="register-form">
    <h2>用户注册</h2>
    
    <!-- 基本表单字段 -->
    <input type="text" name="username" placeholder="用户名">
    <input type="email" name="email" placeholder="邮箱">
    
    <!-- 短信验证部分 -->
    [tcsms_form 
        title="手机验证"
        phone_label="注册手机号"
        button_text="发送验证码"
        class="register-sms-form"
    ]
    
    <!-- 密码字段 -->
    <input type="password" name="password" placeholder="密码">
    
    <button type="submit">注册</button>
</div>
```

**密码重置页面**
```html
<div class="password-reset">
    <h2>找回密码</h2>
    
    <p>请输入您的手机号，我们将发送验证码到您的手机</p>
    
    [tcsms_form 
        title="身份验证"
        phone_label="绑定手机号"
        submit_text="验证身份"
        class="reset-sms-form"
    ]
    
    <!-- 新密码输入框 -->
    <div class="new-password" style="display:none;">
        <input type="password" name="new_password" placeholder="新密码">
        <input type="password" name="confirm_password" placeholder="确认密码">
        <button type="submit">重置密码</button>
    </div>
</div>
```

### 2. PHP代码调用

#### 直接调用短代码函数
```php
<?php
// 在主题模板中调用
if (function_exists('tcsms_shortcode_form')) {
    echo tcsms_shortcode_form([
        'title' => '短信验证',
        'phone_label' => '手机号码',
        'code_label' => '验证码'
    ]);
}
?>
```

#### 完整示例：自定义注册表单
```php
<?php
/**
 * 自定义用户注册页面模板
 */
get_header(); ?>

<div class="container">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="registration-form">
                <h1 class="text-center">用户注册</h1>
                
                <form id="user-registration" method="post" action="<?php echo admin_url('admin-ajax.php'); ?>">
                    <!-- 基本信息 -->
                    <div class="form-group">
                        <label for="username">用户名 *</label>
                        <input type="text" id="username" name="username" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">邮箱地址 *</label>
                        <input type="email" id="email" name="email" class="form-control" required>
                    </div>
                    
                    <!-- 短信验证部分 -->
                    <div class="form-group">
                        <h3>手机验证</h3>
                        <?php
                        // 调用短信验证表单
                        if (function_exists('tcsms_shortcode_form')) {
                            echo tcsms_shortcode_form([
                                'title' => '',
                                'phone_label' => '手机号码',
                                'code_label' => '验证码',
                                'button_text' => '发送验证码',
                                'submit_text' => '验证',
                                'class' => 'registration-sms'
                            ]);
                        }
                        ?>
                    </div>
                    
                    <!-- 密码 -->
                    <div class="form-group">
                        <label for="password">密码 *</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <!-- 提交按钮 -->
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-block">注册账号</button>
                    </div>
                    
                    <!-- 隐藏字段 -->
                    <input type="hidden" name="action" value="custom_user_registration">
                    <?php wp_nonce_field('custom_registration_nonce', 'registration_nonce'); ?>
                </form>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
```

#### AJAX验证处理示例
```php
<?php
/**
 * AJAX处理用户注册
 */
add_action('wp_ajax_custom_user_registration', 'handle_custom_registration');
add_action('wp_ajax_nopriv_custom_user_registration', 'handle_custom_registration');

function handle_custom_registration() {
    // 验证nonce
    if (!wp_verify_nonce($_POST['registration_nonce'], 'custom_registration_nonce')) {
        wp_send_json_error(['message' => '安全验证失败']);
    }
    
    // 获取数据
    $username = sanitize_user($_POST['username']);
    $email = sanitize_email($_POST['email']);
    $password = $_POST['password'];
    $phone = sanitize_text_field($_POST['phone']);
    $sms_code = sanitize_text_field($_POST['sms_code']);
    
    // 验证短信验证码
    if (class_exists('TCSMS_API')) {
        $sms_api = new TCSMS_API();
        
        if (!$sms_api->verify_code($phone, $sms_code)) {
            wp_send_json_error(['message' => '短信验证码错误或已过期']);
        }
        
        // 标记验证码为已使用
        $sms_api->mark_code_verified($phone, $sms_code);
    }
    
    // 创建用户
    $user_id = wp_create_user($username, $password, $email);
    
    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => $user_id->get_error_message()]);
    }
    
    // 保存手机号到用户元数据
    update_user_meta($user_id, 'tcsms_phone', $phone);
    
    // 自动登录
    wp_set_auth_cookie($user_id);
    
    wp_send_json_success([
        'message' => '注册成功',
        'redirect' => home_url('/dashboard')
    ]);
}
?>
```

### 3. WooCommerce集成

#### 结账页面添加手机验证
```php
<?php
/**
 * 在WooCommerce结账页面添加手机验证
 */
add_action('woocommerce_after_checkout_billing_form', 'add_sms_verification_to_checkout');

function add_sms_verification_to_checkout() {
    // 检查是否已登录
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $phone = get_user_meta($user_id, 'tcsms_phone', true);
        
        if ($phone) {
            // 用户已绑定手机号，显示验证码输入
            ?>
            <div class="sms-verification-checkout">
                <h3>订单验证</h3>
                <p>为了保障您的账户安全，请完成短信验证</p>
                
                <div class="form-row">
                    <label>手机号码</label>
                    <input type="tel" 
                           name="sms_phone" 
                           value="<?php echo esc_attr($phone); ?>" 
                           readonly 
                           class="input-text">
                </div>
                
                <div class="form-row">
                    <label>短信验证码</label>
                    <div class="sms-code-container">
                        <input type="text" 
                               name="sms_code" 
                               placeholder="请输入验证码" 
                               maxlength="6" 
                               class="input-text">
                        <button type="button" 
                                class="button send-sms-code"
                                data-phone="<?php echo esc_attr($phone); ?>">
                            获取验证码
                        </button>
                    </div>
                </div>
            </div>
            <?php
        } else {
            // 用户未绑定手机号，显示绑定表单
            ?>
            <div class="sms-bind-checkout">
                <h3>绑定手机号</h3>
                <p>请绑定手机号以接收订单通知</p>
                
                <?php
                if (function_exists('tcsms_shortcode_form')) {
                    echo tcsms_shortcode_form([
                        'title' => '',
                        'phone_label' => '手机号码',
                        'class' => 'checkout-sms-form'
                    ]);
                }
                ?>
            </div>
            <?php
        }
    } else {
        // 游客结账，显示手机验证
        ?>
        <div class="guest-sms-verification">
            <h3>短信验证</h3>
            <p>请输入手机号接收订单验证码</p>
            
            <?php
            if (function_exists('tcsms_shortcode_form')) {
                echo tcsms_shortcode_form([
                    'title' => '',
                    'phone_label' => '订单接收手机号',
                    'class' => 'guest-sms-form'
                ]);
            }
            ?>
        </div>
        <?php
    }
}

/**
 * 验证结账时的短信验证码
 */
add_action('woocommerce_checkout_process', 'verify_checkout_sms_code');

function verify_checkout_sms_code() {
    if (isset($_POST['sms_code']) && isset($_POST['sms_phone'])) {
        $code = sanitize_text_field($_POST['sms_code']);
        $phone = sanitize_text_field($_POST['sms_phone']);
        
        if (class_exists('TCSMS_API')) {
            $sms_api = new TCSMS_API();
            
            if (!$sms_api->verify_code($phone, $code)) {
                wc_add_notice('短信验证码错误或已过期，请重新获取', 'error');
            }
        }
    }
}
?>
```

---

## 🔌 API接口

### AJAX接口

#### 发送验证码
```javascript
// JavaScript调用示例
jQuery.ajax({
    url: '/wp-admin/admin-ajax.php',
    type: 'POST',
    data: {
        action: 'tcsms_send_verification',
        phone: '13800138000',
        nonce: '您的Nonce令牌'
    },
    success: function(response) {
        if (response.success) {
            console.log('发送成功');
        } else {
            console.log('发送失败:', response.data.message);
        }
    }
});
```

#### 验证验证码
```javascript
// JavaScript调用示例
jQuery.ajax({
    url: '/wp-admin/admin-ajax.php',
    type: 'POST',
    data: {
        action: 'tcsms_verify_code',
        phone: '13800138000',
        code: '123456',
        nonce: '您的Nonce令牌'
    },
    success: function(response) {
        if (response.success) {
            console.log('验证成功');
        } else {
            console.log('验证失败:', response.data.message);
        }
    }
});
```

### REST API接口

插件还提供了REST API接口，可以在外部应用中使用：

#### 获取API状态
```http
GET /wp-json/tcsms/v1/status
```

#### 发送验证码
```http
POST /wp-json/tcsms/v1/send
Content-Type: application/json

{
    "phone": "13800138000",
    "api_key": "您的API密钥"
}
```

#### 验证验证码
```http
POST /wp-json/tcsms/v1/verify
Content-Type: application/json

{
    "phone": "13800138000",
    "code": "123456",
    "api_key": "您的API密钥"
}
```

### Webhook回调

插件支持腾讯云短信状态回调：

#### 配置回调URL
```
https://您的域名/wp-json/tcsms/v1/callback
```

#### 回调数据格式
```json
{
    "ActionStatus": "OK",
    "ErrorCode": 0,
    "ErrorInfo": "",
    "ReplyToken": "令牌",
    "ReplyContent": "回复内容"
}
```

---

## 🗄️ 数据库结构

### 主要数据表（按道理会自动创建）

#### 验证码表 (wp_tcsms_codes)
```sql
CREATE TABLE wp_tcsms_codes (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    phone VARCHAR(20) NOT NULL,
    code VARCHAR(10) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    verified TINYINT(1) DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_phone (phone),
    KEY idx_expires_at (expires_at),
    KEY idx_created_at (created_at),
    KEY idx_phone_verified (phone, verified)
);
```

#### 统计表 (wp_tcsms_stats)
```sql
CREATE TABLE wp_tcsms_stats (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    date DATE NOT NULL,
    sent_count INT(11) DEFAULT 0,
    verified_count INT(11) DEFAULT 0,
    failed_count INT(11) DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY idx_date (date)
);
```

#### 手机绑定表 (wp_tcsms_phone_bindings)
```sql
CREATE TABLE wp_tcsms_phone_bindings (
    id BIGINT(20) NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY idx_user_id (user_id),
    UNIQUE KEY idx_phone (phone),
    KEY idx_created_at (created_at)
);
```

### 数据清理策略

插件自动清理过期数据：

1. **每日清理**: 每天凌晨清理过期验证码
2. **保留期限**: 验证码记录保留30天
3. **统计保留**: 统计数据永久保留

手动清理命令：
```sql
-- 清理过期验证码
DELETE FROM wp_tcsms_codes 
WHERE expires_at < NOW() - INTERVAL 1 DAY;

-- 清理30天前的统计
DELETE FROM wp_tcsms_stats 
WHERE date < NOW() - INTERVAL 30 DAY;
```

---

## 🔧 故障排除

### 常见问题

#### 1. 短信发送失败
**可能原因及解决方案：**

| 问题现象 | 可能原因 | 解决方案 |
|----------|----------|----------|
| 提示"签名未审核" | 短信签名未通过审核 | 等待审核通过或联系客服 |
| 提示"模板未审核" | 短信模板未通过审核 | 检查模板内容，重新提交审核 |
| 提示"余额不足" | 账户余额不足 | 充值腾讯云账户 |
| 提示"手机号格式错误" | 手机号格式不正确 | 使用11位中国大陆手机号 |
| 提示"发送频率限制" | 发送过于频繁 | 等待60秒后重试 |

#### 2. 验证码验证失败
**排查步骤：**
1. 检查数据库连接是否正常
2. 验证时区设置是否正确
3. 检查验证码是否已过期
4. 确认验证码未被使用过

#### 3. 插件无法激活
**解决方法：**
```php
// 检查PHP版本
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    echo "需要PHP 7.4或更高版本";
}

// 检查扩展
if (!extension_loaded('curl')) {
    echo "需要cURL扩展";
}

// 检查权限
if (!is_writable(WP_CONTENT_DIR)) {
    echo "wp-content目录不可写";
}
```

#### 4. Composer依赖问题
**安装依赖失败：**
```bash
# 更新Composer
composer self-update

# 清除缓存
composer clear-cache

# 重新安装
composer install --no-dev --optimize-autoloader

# 如果遇到内存限制
COMPOSER_MEMORY_LIMIT=-1 composer install
```

### 测试工具

插件提供了多个测试工具：

1. **配置测试页面**: `/wp-content/plugins/tencent-cloud-sms-2026/test-config.php`
2. **AJAX测试页面**: `/wp-content/plugins/tencent-cloud-sms-2026/test-ajax.php`
3. **数据库检查工具**: `/wp-content/plugins/tencent-cloud-sms-2026/check-database.php`
4. **手动激活脚本**: `/wp-content/plugins/tencent-cloud-sms-2026/manual-activate.php`

**使用示例：**
```php
// 访问测试页面
// 需要管理员权限
// https://您的域名/wp-content/plugins/tencent-cloud-sms-2026/test-config.php
```


---

## 📝 更新日志

### 版本 1.0.0 (2026-02-05)
**新增功能：**
- ✅ 腾讯云短信SDK集成
- ✅ 验证码发送和验证功能
- ✅ 用户登录短信验证
- ✅ 短代码支持
- ✅ 用户个人中心手机绑定
- ✅ 后台管理界面
- ✅ 多语言支持
- ✅ 数据库自动清理

**改进：**
- 🔄 优化了错误处理机制
- 🔄 改进了手机号验证逻辑
- 🔄 增强了安全性

**修复：**
- 🐛 修复了时区问题
- 🐛 修复了数据库表创建问题
- 🐛 修复了AJAX请求问题

### 版本 0.9.0 (2026-01-20)
**测试版本：**
- 基本功能测试完成
- 安全测试通过
- 性能测试通过

