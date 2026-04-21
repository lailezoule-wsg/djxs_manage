<?php
// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

return [
    // 应用的命名空间
    'app_namespace'    => '',
    // 是否启用路由
    'with_route'       => true,
    // 默认应用
    'default_app'      => 'index',
    // 默认时区
    'default_timezone' => 'Asia/Shanghai',

    // 应用映射（自动多应用模式有效）
    'app_map'          => [
        'api' => 'api',
        'admin' => 'admin'
    ],
    // 域名绑定（自动多应用模式有效）
    'domain_bind'      => [],
    // 禁止URL访问的应用列表（自动多应用模式有效）
    'deny_app_list'    => [],

    // 异常页面的模板文件
    'exception_tmpl'   => app()->getThinkPath() . 'tpl/think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'    => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'   => true,
    /**
     * HS256 算法：必须提供至少 32 字节（256位）的密钥。
     * HS512 算法：必须提供至少 64 字节（512位）的密钥。
     * 使用加密安全的随机数生成器生成密钥
     */
    'jwt_secret' => 'djxs_jwt_token_asdadsasdadsadadadadadasdasdada',
    /** 管理端 JWT（与用户端隔离，建议 JWT_SECRET_ADMIN 环境变量注入） */
    'jwt_aud_admin' => env('JWT_AUD_ADMIN', 'djxs_admin'),
    'jwt_secret_admin' => env('JWT_SECRET_ADMIN', ''),
    // 支付回调签名密钥（务必通过环境变量配置强随机值）
    'pay_notify_secret' => env('PAY_NOTIFY_SECRET', ''),
    // 秒杀订单导出任务文件保留天数（<=0 表示不自动清理）
    'flash_sale_export_retention_days' => env('FLASH_SALE_EXPORT_RETENTION_DAYS', 7),
    // 秒杀风控命中日志保留天数（<=0 表示不自动清理）
    'flash_sale_risk_log_retention_days' => env('FLASH_SALE_RISK_LOG_RETENTION_DAYS', 30),
];
