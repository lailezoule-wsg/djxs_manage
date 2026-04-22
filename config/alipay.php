<?php

return [
    // 是否启用沙箱环境
    'sandbox' => true,

    // 沙箱 APPID（来自 zfbsx.MD）
    'app_id' => '9021000162674452',

    // 网关地址（沙箱）
    'gateway' => 'https://openapi-sandbox.dl.alipaydev.com/gateway.do',

    // 通知地址与同步返回地址（按你的域名调整）
    'notify_url' => env('ALIPAY_NOTIFY_URL', 'http://localhost:8082/api/order/notify'),
    'return_url' => env('ALIPAY_RETURN_URL', 'http://localhost:3000/orders'),
    // 商户号（可选；配置后将用于回调 seller_id 校验）
    'seller_id' => env('ALIPAY_SELLER_ID', ''),

    // 签名类型/编码
    'sign_type' => 'RSA2',
    'charset' => 'utf-8',
    'format' => 'JSON',
    'version' => '1.0',

    /**
     * 密钥读取优先级（避免引用断裂）：
     * 1. *_key_path（推荐，读取本地 PEM 文件）
     * 2. *_key（兼容旧配置，直接读取字符串）
     */
    'app_private_key_path' => env('ALIPAY_APP_PRIVATE_KEY_PATH', ''),
    'alipay_public_key_path' => env('ALIPAY_PUBLIC_KEY_PATH', ''),
    'app_private_key' => env('ALIPAY_APP_PRIVATE_KEY', ''),
    'alipay_public_key' => env('ALIPAY_PUBLIC_KEY', ''),
];

