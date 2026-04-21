<?php
declare (strict_types = 1);

namespace app\admin\controller;

use think\facade\Db;

class Dashboard extends BaseAdminController
{
    public function overview()
    {
        try {
            $today = date('Y-m-d');
            $tomorrow = date('Y-m-d', strtotime('+1 day'));

            $data = [
                'user_total' => (int)Db::name('user')->count(),
                'user_new_today' => (int)Db::name('user')->whereBetweenTime('reg_time', $today, $tomorrow)->count(),
                'order_total' => (int)Db::name('order')->count(),
                'order_paid_total' => (int)Db::name('order')->where('status', 1)->count(),
                'order_paid_amount' => (float)Db::name('order')->where('status', 1)->sum('pay_amount'),
                'drama_total' => (int)Db::name('drama')->count(),
                'novel_total' => (int)Db::name('novel')->count(),
            ];
            return $this->success($data, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
