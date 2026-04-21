<?php
declare (strict_types = 1);

namespace app\admin\service;

use think\facade\Db;

class StatisticsAdminService extends BaseAdminService
{
    public function overview(): array
    {
        return [
            'user_total' => (int)Db::name('user')->count(),
            'drama_total' => (int)Db::name('drama')->count(),
            'novel_total' => (int)Db::name('novel')->count(),
            'paid_order_total' => (int)Db::name('order')->where('status', 1)->count(),
            'paid_amount_total' => (float)Db::name('order')->where('status', 1)->sum('pay_amount'),
        ];
    }

    public function userTrend(int $days = 7): array
    {
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} day"));
            $next = date('Y-m-d', strtotime($day . ' +1 day'));
            $data[] = [
                'date' => $day,
                'register_count' => (int)Db::name('user')->whereBetweenTime('reg_time', $day, $next)->count(),
            ];
        }
        return $data;
    }

    public function content(): array
    {
        return [
            'drama' => [
                'total' => (int)Db::name('drama')->count(),
                'online' => (int)Db::name('drama')->where('status', 1)->count(),
            ],
            'novel' => [
                'total' => (int)Db::name('novel')->count(),
                'online' => (int)Db::name('novel')->where('status', 1)->count(),
            ],
        ];
    }

    public function paymentTrend(int $days = 7): array
    {
        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} day"));
            $next = date('Y-m-d', strtotime($day . ' +1 day'));
            $query = Db::name('order')->where('status', 1)->whereBetweenTime('pay_time', $day, $next);
            $data[] = [
                'date' => $day,
                'paid_count' => (int)$query->count(),
                'paid_amount' => (float)$query->sum('pay_amount'),
            ];
        }
        return $data;
    }
}
