<?php

use think\facade\Route;

Route::post('login', 'Auth/login');
Route::get('health', function () {
    return json(['status' => 'ok', 'time' => date('Y-m-d H:i:s')]);
});
Route::post('channel-distribution/callback/:channel', 'ChannelDistributionCallback/receive');

Route::group('', function () {
    Route::get('dashboard/overview', 'Dashboard/overview');
    Route::get('auth/profile', 'Auth/profile');
    Route::get('auth/permissions', 'Auth/permissions');
    Route::get('auth/menus', 'Auth/menus');

    Route::group('system/permission', function () {
        Route::get('list', 'system.Permission/list');
        Route::post('create', 'system.Permission/create');
        Route::put('update/:id', 'system.Permission/update');
        Route::delete('delete/:id', 'system.Permission/delete');
    });

    Route::group('system/menu', function () {
        Route::get('list', 'system.Menu/list');
        Route::post('create', 'system.Menu/create');
        Route::put('update/:id', 'system.Menu/update');
        Route::delete('delete/:id', 'system.Menu/delete');
    });

    Route::group('system/role', function () {
        Route::get('list', 'system.Role/list');
        Route::post('create', 'system.Role/create');
        Route::put('update/:id', 'system.Role/update');
        Route::delete('delete/:id', 'system.Role/delete');
        Route::get(':id/permissions', 'system.Role/permissions');
        Route::put(':id/permissions', 'system.Role/savePermissions');
    });

    Route::group('system/admin-user', function () {
        Route::get('list', 'system.AdminUser/list');
        Route::post('create', 'system.AdminUser/create');
        Route::put('update/:id', 'system.AdminUser/update');
        Route::delete('delete/:id', 'system.AdminUser/delete');
        Route::get(':id/roles', 'system.AdminUser/roles');
        Route::put(':id/roles', 'system.AdminUser/saveRoles');
    });

    Route::group('system/job', function () {
        Route::get('timeout-status', 'Order/timeoutJobStatus');
    });

    Route::group('upload', function () {
        Route::post('image', 'Upload/image');
    });

    Route::group('user', function () {
        Route::get('list', 'User/list');
        Route::get('detail/:id', 'User/detail');
        Route::put('status/:id', 'User/updateStatus');
    });

    Route::group('device', function () {
        Route::get('list', 'User/deviceList');
        Route::delete(':id', 'User/deviceDelete');
    });

    Route::group('drama', function () {
        Route::get('list', 'Content/dramaList');
        Route::post('create', 'Content/dramaCreate');
        Route::put('update/:id', 'Content/dramaUpdate');
        Route::delete(':id', 'Content/dramaDelete');
    });

    Route::group('episode', function () {
        Route::get('list/:drama_id', 'Content/episodeList');
        Route::post('create', 'Content/episodeCreate');
        Route::put('update/:id', 'Content/episodeUpdate');
        Route::delete(':id', 'Content/episodeDelete');
    });

    Route::group('novel', function () {
        Route::get('list', 'Content/novelList');
        Route::post('create', 'Content/novelCreate');
        Route::put('update/:id', 'Content/novelUpdate');
        Route::delete(':id', 'Content/novelDelete');
    });

    Route::group('chapter', function () {
        Route::get('list/:novel_id', 'Content/chapterList');
        Route::post('create', 'Content/chapterCreate');
        Route::put('update/:id', 'Content/chapterUpdate');
        Route::delete(':id', 'Content/chapterDelete');
    });

    Route::group('category', function () {
        Route::get('list', 'Content/categoryList');
        Route::post('create', 'Content/categoryCreate');
        Route::put('update/:id', 'Content/categoryUpdate');
        Route::delete(':id', 'Content/categoryDelete');
    });

    Route::group('tag', function () {
        Route::get('list', 'Content/tagList');
        Route::post('create', 'Content/tagCreate');
        Route::put('update/:id', 'Content/tagUpdate');
        Route::delete(':id', 'Content/tagDelete');
    });

    Route::group('drama/category', function () {
        Route::get('list', 'Content/dramaCategoryList');
        Route::post('create', 'Content/dramaCategoryCreate');
        Route::put('update/:id', 'Content/dramaCategoryUpdate');
        Route::delete(':id', 'Content/dramaCategoryDelete');
    });

    Route::group('drama/tag', function () {
        Route::get('list', 'Content/dramaTagList');
        Route::post('create', 'Content/dramaTagCreate');
        Route::put('update/:id', 'Content/dramaTagUpdate');
        Route::delete(':id', 'Content/dramaTagDelete');
    });

    Route::group('novel/category', function () {
        Route::get('list', 'Content/novelCategoryList');
        Route::post('create', 'Content/novelCategoryCreate');
        Route::put('update/:id', 'Content/novelCategoryUpdate');
        Route::delete(':id', 'Content/novelCategoryDelete');
    });

    Route::group('novel/tag', function () {
        Route::get('list', 'Content/novelTagList');
        Route::post('create', 'Content/novelTagCreate');
        Route::put('update/:id', 'Content/novelTagUpdate');
        Route::delete(':id', 'Content/novelTagDelete');
    });

    Route::post('content/audit', 'Content/audit');
    Route::post('drama/audit', 'Content/dramaAudit');
    Route::post('novel/audit', 'Content/novelAudit');

    Route::group('order', function () {
        Route::get('list', 'Order/list');
        Route::get('detail/:id', 'Order/detail');
        Route::post('refund/:id', 'Order/refund');
        Route::get('statistics', 'Order/statistics');
    });

    Route::group('member', function () {
        Route::get('level/list', 'Member/levelList');
        Route::post('level/create', 'Member/levelCreate');
        Route::put('level/update/:id', 'Member/levelUpdate');
        Route::delete('level/:id', 'Member/levelDelete');
        Route::get('user/list', 'Member/userList');
    });

    Route::group('distribution', function () {
        Route::get('record/list', 'Distribution/recordList');
        Route::get('withdraw/list', 'Distribution/withdrawList');
        Route::post('withdraw/audit/:id', 'Distribution/withdrawAudit');
        Route::get('config', 'Distribution/configGet');
        Route::put('config', 'Distribution/configSet');
    });

    Route::group('ad/position', function () {
        Route::get('list', 'Ad/positionList');
        Route::post('create', 'Ad/positionCreate');
        Route::put('update/:id', 'Ad/positionUpdate');
        Route::delete(':id', 'Ad/positionDelete');
    });

    Route::group('ad', function () {
        Route::get('list', 'Ad/list');
        Route::post('create', 'Ad/create');
        Route::put('update/:id', 'Ad/update');
        Route::delete(':id', 'Ad/delete');
        Route::get('statistics/:id', 'Ad/statistics');
    });

    Route::group('news', function () {
        Route::get('list', 'News/list');
        Route::post('create', 'News/create');
        Route::put('update/:id', 'News/update');
        Route::delete(':id', 'News/delete');
    });

    Route::group('flash-sale', function () {
        Route::get('activity/list', 'FlashSale/activityList');
        Route::post('activity/create', 'FlashSale/activityCreate');
        Route::put('activity/update/:id', 'FlashSale/activityUpdate');
        Route::post('activity/publish/:id', 'FlashSale/activityPublish');
        Route::post('activity/close/:id', 'FlashSale/activityClose');
        Route::post('activity/copy/:id', 'FlashSale/activityCopy');
        Route::post('activity/batch-copy', 'FlashSale/activityBatchCopy');
        Route::post('activity/batch-status', 'FlashSale/activityBatchStatus');
        Route::get('item/list/:activityId', 'FlashSale/itemList');
        Route::post('item/create', 'FlashSale/itemCreate');
        Route::put('item/update/:id', 'FlashSale/itemUpdate');
        Route::delete('item/:id', 'FlashSale/itemDelete');
        Route::get('order/list', 'FlashSale/orderList');
        Route::post('order/export/task', 'FlashSale/orderExportTaskCreate');
        Route::get('order/export/task/list', 'FlashSale/orderExportTaskList');
        Route::get('order/export/task/:taskId', 'FlashSale/orderExportTaskStatus');
        Route::post('order/export/task/retry/:taskId', 'FlashSale/orderExportTaskRetry');
        Route::delete('order/export/task/:taskId', 'FlashSale/orderExportTaskDelete');
        Route::get('statistics/:activityId', 'FlashSale/statistics');
        Route::get('risk/log/list', 'FlashSale/riskLogList');
        Route::get('risk/summary', 'FlashSale/riskSummary');
        Route::get('risk/health-history', 'FlashSale/riskHealthHistory');
        Route::get('risk/health-threshold', 'FlashSale/riskHealthThresholdGet');
        Route::put('risk/health-threshold', 'FlashSale/riskHealthThresholdUpdate');
        Route::get('risk/blacklist/list', 'FlashSale/blacklistList');
        Route::post('risk/blacklist/create', 'FlashSale/blacklistCreate');
        Route::put('risk/blacklist/update/:id', 'FlashSale/blacklistUpdate');
        Route::delete('risk/blacklist/:id', 'FlashSale/blacklistDelete');
    });

    Route::group('channel-distribution', function () {
        Route::get('channel/list', 'ChannelDistribution/channelList');
        Route::get('channel/options', 'ChannelDistribution/channelOptions');
        Route::post('channel/create', 'ChannelDistribution/channelCreate');
        Route::put('channel/update/:id', 'ChannelDistribution/channelUpdate');
        Route::post('channel/toggle/:id', 'ChannelDistribution/channelToggle');
        Route::get('account/list', 'ChannelDistribution/accountList');
        Route::post('account/create', 'ChannelDistribution/accountCreate');
        Route::put('account/update/:id', 'ChannelDistribution/accountUpdate');
        Route::post('account/toggle/:id', 'ChannelDistribution/accountToggle');
        Route::post('account/test/:id', 'ChannelDistribution/accountTest');
        Route::get('task/list', 'ChannelDistribution/taskList');
        Route::post('task/create', 'ChannelDistribution/taskCreate');
        Route::get('task/:taskNo', 'ChannelDistribution/taskDetail');
        Route::post('task/:taskNo/resubmit', 'ChannelDistribution/taskResubmit');
        Route::post('task/:taskNo/audit', 'ChannelDistribution/taskAudit');
        Route::post('task/:taskNo/retry', 'ChannelDistribution/taskRetry');
        Route::get('task/:taskNo/logs', 'ChannelDistribution/taskLogs');
        Route::get('callback/list', 'ChannelDistribution/callbackList');
        Route::get('callback/:id', 'ChannelDistribution/callbackDetail');
    });

    Route::group('statistics', function () {
        Route::get('overview', 'Statistics/overview');
        Route::get('user', 'Statistics/user');
        Route::get('content', 'Statistics/content');
        Route::get('payment', 'Statistics/payment');
    });

    Route::group('config', function () {
        Route::get('list', 'Config/list');
        Route::put('update', 'Config/update');
    });
})->middleware(['admin_auth', 'admin_rbac']);
