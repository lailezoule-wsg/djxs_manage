<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\Distribution;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 分销服务层
 */
class DistributionService
{
    /**
     * 获取推广码
     */
    public function getCode($userId)
    {
        $distribution = Distribution::where('user_id', $userId)->find();

        if (!$distribution) {
            $promotionCode = $this->generatePromotionCode();

            $distribution = Distribution::create([
                'user_id'         => $userId,
                'promotion_code'  => $promotionCode,
                'total_commission' => 0,
                'available_commission' => 0,
                'status' => 1,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
        }

        $promotionUrl = $this->buildPromotionUrl((string)$distribution->promotion_code);

        return [
            'promotion_code' => $distribution->promotion_code,
            'promotion_url'  => $promotionUrl,
        ];
    }

    /**
     * 获取分销记录
     */
    public function record($userId)
    {
        $config = $this->getDistributionConfig();
        $minWithdraw = round((float)($config['min_withdraw'] ?? 0), 2);
        $distribution = Distribution::where('user_id', $userId)->find();
        $totalCommission = $distribution ? (float)$distribution->total_commission : 0.0;
        $availableCommission = $distribution ? (float)$distribution->available_commission : 0.0;
        $list = Db::name('commission_record')
            ->alias('c')
            ->leftJoin('djxs_order o', 'o.id = c.order_id')
            ->leftJoin('djxs_user bu', 'bu.id = o.user_id')
            ->field('c.*,o.user_id as source_user_id,bu.username as source_username,bu.mobile as source_mobile,bu.nickname as source_nickname')
            ->where('c.user_id', $userId)
            ->order('c.id', 'desc')
            ->limit(20)
            ->select()
            ->toArray();

        foreach ($list as &$row) {
            $type = (int)($row['type'] ?? 0);
            $status = (int)($row['status'] ?? 0);
            $row['type_text'] = $type === 1 ? '佣金入账' : ($type === 2 ? '提现申请' : '其他');
            $row['status_text'] = $status === 1 ? '已完成' : ($status === 2 ? '已拒绝' : '待处理');
            $row['source_account'] = '-';
            if ($type === 1) {
                $sourceUsername = trim((string)($row['source_username'] ?? ''));
                $sourceMobile = trim((string)($row['source_mobile'] ?? ''));
                $sourceUserId = (int)($row['source_user_id'] ?? 0);
                if ($sourceUsername !== '') {
                    $row['source_account'] = $sourceUsername;
                } elseif ($sourceMobile !== '') {
                    $row['source_account'] = $sourceMobile;
                } elseif ($sourceUserId > 0) {
                    $row['source_account'] = '用户#' . $sourceUserId;
                }
            }
        }
        unset($row);

        return [
            'total_commission'     => round($totalCommission, 2),
            'available_commission' => round($availableCommission, 2),
            'min_withdraw'         => $minWithdraw,
            'list'                 => $list,
        ];
    }

    /**
     * 佣金提现
     */
    public function withdraw($userId, $data)
    {
        $amount = round((float)($data['amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw new ValidateException('提现金额必须大于0');
        }

        $config = $this->getDistributionConfig();
        $minWithdraw = round((float)($config['min_withdraw'] ?? 0), 2);
        if ($minWithdraw > 0 && $amount < $minWithdraw) {
            throw new ValidateException('提现金额不能低于最低提现：' . $minWithdraw);
        }

        Db::transaction(function () use ($userId, $amount) {
            $distribution = Db::name('distribution')
                ->alias('d')
                ->where('d.user_id', $userId)
                ->lock(true)
                ->find();

            if (!$distribution) {
                $promotionCode = $this->generatePromotionCode();
                Db::name('distribution')->insert([
                    'user_id' => $userId,
                    'parent_id' => 0,
                    'promotion_code' => $promotionCode,
                    'total_commission' => 0,
                    'available_commission' => 0,
                    'status' => 1,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);
                $distribution = Db::name('distribution')
                    ->alias('d')
                    ->where('d.user_id', $userId)
                    ->lock(true)
                    ->find();
            }

            $available = round((float)($distribution['available_commission'] ?? 0), 2);
            if ($available < $amount) {
                throw new ValidateException('可用佣金不足');
            }

            Db::name('distribution')
                ->where('id', $distribution['id'])
                ->update(['available_commission' => $available - $amount]);

            Db::name('commission_record')->insert([
                'user_id' => $userId,
                'order_id' => null,
                'amount' => $amount,
                'type' => 2,
                'status' => 0,
                'create_time' => date('Y-m-d H:i:s'),
            ]);
        });

        return true;
    }

    /**
     * 获取我的下级
     */
    public function team(int $userId, array $params = []): array
    {
        $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
        $pageSize = isset($params['page_size']) ? (int)$params['page_size'] : (isset($params['limit']) ? (int)$params['limit'] : 10);
        $pageSize = max(1, min($pageSize, 100));

        $query = Distribution::alias('d')
            ->join('user u', 'u.id = d.user_id')
            ->where('d.parent_id', $userId)
            ->field('d.user_id,d.create_time,u.mobile,u.nickname,u.avatar');

        $result = $query->order('d.id', 'desc')->paginate([
            'page' => $page,
            'list_rows' => $pageSize,
        ]);

        return [
            'list' => $result->items(),
            'total' => $result->total(),
            'page' => $result->currentPage(),
            'page_size' => $result->listRows(),
            'has_more' => $result->currentPage() < $result->lastPage(),
        ];
    }

    /**
     * 生成推广码
     */
    private function generatePromotionCode()
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $length = strlen($chars) - 1;
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, $length)];
            }
            $exists = Distribution::where('promotion_code', $code)->count() > 0;
        } while ($exists);

        return $code;
    }

    private function buildPromotionUrl(string $code): string
    {
        $base = rtrim((string)config('app.distribution_register_url', ''), '/');
        if ($base !== '') {
            return $base . '?code=' . urlencode($code);
        }

        return '/register?code=' . urlencode($code);
    }

    private function getDistributionConfig(): array
    {
        $row = Db::name('system_config')->whereRaw('`key` = ?', ['distribution_config'])->find();
        if (!$row || !isset($row['value'])) {
            return [];
        }
        $decoded = json_decode((string)$row['value'], true);
        return is_array($decoded) ? $decoded : [];
    }
}
