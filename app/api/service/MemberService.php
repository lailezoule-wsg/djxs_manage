<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\Member;
use app\api\model\MemberLevel;
use think\exception\ValidateException;

class MemberService
{
    const PRIVILEGE_FREE_WATCH = 'free_watch';
    const PRIVILEGE_NO_ADS = 'no_ads';
    const PRIVILEGE_DISCOUNT = 'discount';
    const PRIVILEGE_PRIORITY_SUPPORT = 'priority_support';
    const PRIVILEGE_VIP_SUPPORT = 'vip_support';
    const PRIVILEGE_DISTRIBUTION_SHARE = 'distribution_share';

    private $privilegeMap = [
        'free_watch' => [1, 2, 3, 4],
        'no_ads' => [2, 3, 4],
        'discount' => [1, 2, 3, 4],
        'priority_support' => [3, 4],
        'vip_support' => [4],
        'distribution_share' => [1, 2, 3, 4],
    ];

    /**
     * 获取会员等级列表
     */
    public function levelList()
    {
        return MemberLevel::where('status', 1)
            ->order('price', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 购买会员（创建订单并返回支付参数，不直接开通会员）
     */
    public function buy($userId, $data)
    {
        $levelId = (int)($data['level_id'] ?? 0);
        $payType = strtolower(trim((string)($data['pay_type'] ?? 'wechat')));

        $level = MemberLevel::find($levelId);
        if (!$level || (int)$level->status !== 1) {
            throw new ValidateException('会员等级不存在');
        }
        if (!in_array($payType, ['wechat', 'alipay'], true)) {
            throw new ValidateException('支付方式不支持');
        }
        if ((float)$level->price <= 0) {
            throw new ValidateException('会员价格配置异常，请联系管理员');
        }

        $orderService = new OrderService();
        $order = $orderService->create($userId, [
            'goods_type' => 3,
            'goods_id' => $levelId,
        ]);
        $order->pay_type = $payType === 'alipay' ? 2 : 1;
        $order->save();
        $payData = $orderService->pay($userId, (int)$order->id, $payType);

        return [
            'order_id' => (int)$order->id,
            'order_sn' => (string)$order->order_sn,
            'pay_amount' => (float)$order->pay_amount,
            'pay_type' => (string)($payData['pay_type'] ?? $payType),
            'pay_url' => (string)($payData['pay_url'] ?? ''),
        ];
    }

    /**
     * 获取会员信息
     */
    public function info($userId)
    {
        $member = Member::where('user_id', $userId)->find();

        if (!$member || strtotime($member->end_time) <= time()) {
            return [
                'is_member' => false,
                'level_id' => 0,
                'level_name' => '',
                'discount' => 1,
                'start_time' => '',
                'end_time' => '',
                'days_remain' => 0,
                'privileges' => [],
            ];
        }

        $level = MemberLevel::find($member->member_level_id);

        return [
            'is_member' => true,
            'level_id' => $member->member_level_id,
            'level_name' => $level ? $level->name : '',
            'discount' => $level ? floatval($level->discount) : 1,
            'start_time' => $member->start_time,
            'end_time' => $member->end_time,
            'days_remain' => floor((strtotime($member->end_time) - time()) / 86400),
            'privileges' => $this->getMemberPrivileges($member->member_level_id),
        ];
    }

    /**
     * 获取会员特权列表
     */
    public function getMemberPrivileges($levelId)
    {
        $privileges = [];
        
        foreach ($this->privilegeMap as $privilege => $levels) {
            if (in_array($levelId, $levels)) {
                $privileges[] = $privilege;
            }
        }
        
        return $privileges;
    }

    /**
     * 检查会员是否有效
     */
    public function isValidMember($userId)
    {
        $member = Member::where('user_id', $userId)
            ->where('status', 1)
            ->where('end_time', '>', date('Y-m-d H:i:s'))
            ->find();
            
        return $member ? true : false;
    }

    /**
     * 检查用户是否享有某权益
     */
    public function checkPrivilege($userId, $privilege)
    {
        $memberInfo = $this->info($userId);
        
        if (!$memberInfo['is_member']) {
            return false;
        }
        
        $requiredLevels = $this->privilegeMap[$privilege] ?? [];
        return in_array($memberInfo['level_id'], $requiredLevels);
    }

    /**
     * 计算会员折扣价格
     */
    public function calculateDiscountedPrice($userId, $originalPrice)
    {
        $memberInfo = $this->info($userId);
        
        if (!$memberInfo['is_member']) {
            return $originalPrice;
        }
        
        if (!$this->checkPrivilege($userId, self::PRIVILEGE_DISCOUNT)) {
            return $originalPrice;
        }
        
        $discount = $memberInfo['discount'] ?? 1;
        return round($originalPrice * $discount, 2);
    }

    /**
     * 检查会员是否可以免费观看
     */
    public function canWatchForFree($userId)
    {
        return $this->checkPrivilege($userId, self::PRIVILEGE_FREE_WATCH);
    }

    /**
     * 检查用户是否拥有某内容（会员免费看也返回true）
     */
    public function checkPurchased($userId, $goodsType, $goodsId)
    {
        if ($this->canWatchForFree($userId)) {
            return true;
        }
        
        return false;
    }
}
