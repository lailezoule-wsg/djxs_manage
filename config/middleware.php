<?php
// 中间件配置
return [
    // 别名或分组
    'alias'    => [
        'auth' => app\api\middleware\Auth::class,
        'admin_auth' => app\admin\middleware\Auth::class,
        'admin_rbac' => app\admin\middleware\AdminRbac::class,
        'admin_csrf' => app\admin\middleware\Csrf::class,
        'admin_security' => app\admin\middleware\SecurityHeaders::class,
    ],
    // 优先级设置，此数组中的中间件会按照数组中的顺序优先执行
    // Auth 必须在 Csrf 之前执行，以便 Csrf 中间件可以获取当前登录用户信息
    'priority' => [
        app\admin\middleware\SecurityHeaders::class,
        app\admin\middleware\Auth::class,
        app\admin\middleware\Csrf::class,
        app\admin\middleware\AdminRbac::class,
    ],
];