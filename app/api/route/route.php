<?php
// +----------------------------------------------------------------------
// | API 模块路由配置
// +----------------------------------------------------------------------

use think\facade\Route;

// 注意：在多应用模式下，URL 前缀 /api 已经被解析为应用名
// 所以这里不需要再定义 api 分组，直接定义路由即可

// ==================== 用户相关 ====================
Route::group('user', function () {
    // 用户注册
    Route::post('register', 'User/register');
    // 用户登录
    Route::post('login', 'User/login');
    // 第三方登录
    Route::post('oauth-login', 'User/oauthLogin');
    // 获取用户信息（需要登录）
    Route::get('info', 'User/info')->middleware('auth');
    // 更新用户信息
    Route::put('info', 'User/updateInfo')->middleware('auth');
    // 修改密码
    Route::put('password', 'User/changePassword')->middleware('auth');
    // 绑定设备
    Route::post('device/bind', 'User/bindDevice')->middleware('auth');
    // 设备列表
    Route::get('device/list', 'User/deviceList')->middleware('auth');
    // 解绑设备
    Route::delete('device/:id', 'User/unbindDevice')->middleware('auth');
});

// ==================== 文件上传 ====================
Route::group('upload', function () {
    // 图片上传
    Route::post('image', 'Upload/image')->middleware('auth');
});

// ==================== 内容相关 - 短剧 ====================
Route::group('drama', function () {
    // 短剧列表
    Route::get('list', 'Drama/list');
    // 短剧详情（需要登录）
    Route::get('detail/:id', 'Drama/detail')->middleware('auth');
    // 短剧分类列表
    Route::get('category', 'Drama/category');
});

// 短剧剧集（独立路由，避免冲突）
Route::group('drama-episode', function () {
    // 获取剧集列表
    Route::get('list/:id', 'DramaEpisode/getEpisodeList')->middleware('auth');
    // 单集下单前展示
    Route::get('purchase-preview/:episodeId', 'DramaEpisode/purchasePreview')->middleware('auth');
    // 获取剧集内容
    Route::get(':id/:episodeNum', 'DramaEpisode/getEpisode')->middleware('auth');
});

// ==================== 内容相关 - 小说 ====================
Route::group('novel', function () {
    // 小说列表
    Route::get('list', 'Novel/list');
    // 小说详情（需要登录）
    Route::get('detail/:id', 'Novel/detail')->middleware('auth');
    // 小说分类列表
    Route::get('category', 'Novel/category');
});

// 小说章节（独立路由，避免冲突）
Route::group('novel-chapter', function () {
    // 获取章节列表
    Route::get('list/:id', 'NovelChapter/getChapterList')->middleware('auth');
    // 单章下单前展示（不含正文）
    Route::get('purchase-preview/:chapterId', 'NovelChapter/purchasePreview')->middleware('auth');
    // 获取章节内容
    Route::get(':id/:chapterNum', 'NovelChapter/getChapter')->middleware('auth');
});

// ==================== 分类与标签 ====================
Route::group('category', function () {
    // 获取分类列表（通用）
    Route::get('list', 'Category/list');
});

Route::group('tag', function () {
    // 获取标签列表
    Route::get('list', 'Tag/list');
});

// ==================== 支付相关 ====================
Route::group('order', function () {
    // 创建订单
    Route::post('create', 'Order/create')->middleware('auth');
    // 订单列表
    Route::get('list', 'Order/list')->middleware('auth');
    // 订单详情
    Route::get('detail/:id', 'Order/detail')->middleware('auth');
    // 支付订单
    Route::post('pay', 'Order/pay')->middleware('auth');
    // 取消订单
    Route::post('cancel', 'Order/cancel')->middleware('auth');
    // 检查购买状态
    Route::get('check-purchased', 'Order/checkPurchased')->middleware('auth');
    // 检查会员权限
    Route::get('check-member-access', 'Order/checkMemberAccess')->middleware('auth');
    // 支付回调
    Route::post('notify', 'Order/notify');
    // 支付宝同步回跳确认（前端主动带参数确认）
    Route::get('alipay-return', 'Order/alipayReturn');
    // 旧接口迁移提示（已迁移到 admin 域）
    Route::get('timeout-job-status', function () {
        return json([
            'code' => 410,
            'biz_code' => 40401,
            'msg' => '接口已迁移，请使用 /admin/system/job/timeout-status',
            'data' => [
                'new_endpoint' => '/admin/system/job/timeout-status',
            ],
        ], 410);
    });
});

// ==================== 会员相关 ====================
Route::group('member', function () {
    // 会员等级列表
    Route::get('level', 'Member/levelList');
    // 购买会员
    Route::post('buy', 'Member/buy')->middleware('auth');
    // 会员信息
    Route::get('info', 'Member/info')->middleware('auth');
});

// ==================== 分销相关 ====================
Route::group('distribution', function () {
    // 获取推广码
    Route::get('code', 'Distribution/getCode')->middleware('auth');
    // 分销记录
    Route::get('record', 'Distribution/record')->middleware('auth');
    // 佣金提现
    Route::post('withdraw', 'Distribution/withdraw')->middleware('auth');
    // 我的下级
    Route::get('team', 'Distribution/team')->middleware('auth');
});

// ==================== 搜索 ====================
Route::get('search', 'Search/index');

// ==================== 公共配置 ====================
Route::group('config', function () {
    // 公共配置（白名单）
    Route::get('public', 'Config/publicConfig');
});

// ==================== 广告相关 ====================
Route::group('ad', function () {
    // 获取广告列表
    Route::get('list', 'Ad/list');
    // 广告点击统计
    Route::post('click', 'Ad/click');
});

// ==================== 资讯相关 ====================
Route::group('news', function () {
    // 资讯列表（短剧/小说）
    Route::get('list', 'News/list');
    // 资讯详情
    Route::get('detail/:id', 'News/detail');
});

// ==================== 秒杀活动 ====================
Route::group('flash-sale', function () {
    Route::get('list', 'FlashSale/list');
    Route::get('detail/:activityId', 'FlashSale/detail');
    Route::get('stream', 'FlashSale/stream');
    Route::post('token', 'FlashSale/token')->middleware('auth');
    Route::post('order/precheck', 'FlashSale/orderPrecheck')->middleware('auth');
    Route::post('order/create', 'FlashSale/createOrder')->middleware('auth');
    Route::get('order/result/:requestId', 'FlashSale/orderResult')->middleware('auth');
});

// ==================== 用户行为记录 - 观看 ====================
Route::group('watch', function () {
    // 记录观看进度
    Route::post('record', 'Watch/record')->middleware('auth');
    // 获取观看历史
    Route::get('history', 'Watch/history')->middleware('auth');
});

// ==================== 用户行为记录 - 阅读 ====================
Route::group('read', function () {
    // 记录阅读进度
    Route::post('record', 'Read/record')->middleware('auth');
    // 获取阅读历史
    Route::get('history', 'Read/history')->middleware('auth');
});

// 健康检查路由
Route::get('health', function () {
    return json(['status' => 'ok', 'time' => date('Y-m-d H:i:s')]);
});
