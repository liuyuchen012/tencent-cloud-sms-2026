=== 腾讯云短信验证码 ===
Contributors: LiuYuchen
Donate link: https://www.615mc.top
Tags: sms, verification, tencent cloud, security, login, authentication
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

集成腾讯云短信服务，支持验证码发送、验证和登录安全增强。

== 描述 ==

**腾讯云短信验证码插件** 是一个功能强大的WordPress插件，帮助您轻松集成腾讯云短信服务到您的网站。

### 主要功能

*   **短信验证码发送**: 轻松发送6位数字验证码到用户手机
*   **验证码验证**: 验证用户输入的验证码是否正确
*   **登录增强**: 在登录表单中添加短信验证，提高安全性
*   **频率限制**: 防止验证码被滥用
*   **短代码支持**: 通过短代码在任何页面显示验证表单
*   **管理界面**: 友好的后台管理界面
*   **多语言支持**: 支持中英文界面
*   **响应式设计**: 在各种设备上都有良好的显示效果

### 使用场景

*   用户注册时的手机验证
*   密码重置时的身份验证
*   登录时的二次验证
*   支付确认验证
*   重要操作的身份验证

== 安装 ==

### 方法一：通过WordPress后台安装

1.  登录WordPress后台
2.  转到"插件" → "安装插件"
3.  在搜索框输入"腾讯云短信"
4.  找到插件并点击"现在安装"
5.  安装完成后点击"启用"

### 方法二：手动安装

1.  下载插件zip文件
2.  解压缩到 `/wp-content/plugins/` 目录
3.  在WordPress后台启用插件

### 方法三：通过Composer安装

`composer require yourname/tencent-cloud-sms`

== 配置 ==

1.  在WordPress后台，转到"设置" → "腾讯云短信"
2.  填写从腾讯云控制台获取的API密钥：
    *   SecretId
    *   SecretKey
    *   SDK AppId
    *   短信签名
    *   模板ID
3.  根据需求配置其他选项：
    *   验证码有效期（分钟）
    *   发送频率限制（秒）
    *   是否启用登录验证
4.  点击"保存设置"

== 使用方法 ==

### 短代码

在文章或页面中使用短代码显示短信验证表单：

`[tcsms_form]`

#### 短代码参数

*   `title`: 表单标题（默认：短信验证）
*   `phone_label`: 手机号标签（默认：手机号码）
*   `code_label`: 验证码标签（默认：验证码）
*   `button_text`: 发送按钮文字（默认：获取验证码）
*   `submit_text`: 验证按钮文字（默认：验证）
*   `class`: 自定义CSS类名

示例：
`[tcsms_form title="手机验证" phone_label="您的手机号" class="custom-form"]`

### PHP代码

在主题文件中直接调用：

```php
<?php if (function_exists('tcsms')): ?>
    <div class="sms-verification">
        <!-- 自定义表单 -->
    </div>
<?php endif; ?>