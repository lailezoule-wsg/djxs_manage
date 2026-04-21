<?php
declare (strict_types = 1);

namespace app\api\validate;

use think\Validate;

/**
 * 订单验证规则
 */
class Order extends Validate
{
    /**
     * 定义验证规则
     */
    protected $rule = [
        'goods_type' => 'require|in:1,2,3,10,20',
        'goods_id'   => 'require|number',
        'quantity'   => 'number|min:1',
    ];

    /**
     * 定义错误提示
     */
    protected $message = [
        'goods_type.require' => '商品类型不能为空',
        'goods_type.in'      => '商品类型不正确',
        'goods_id.require'   => '商品ID不能为空',
        'goods_id.number'    => '商品ID格式不正确',
        'quantity.number'    => '购买数量必须为数字',
        'quantity.min'       => '购买数量不能少于1',
    ];

    /**
     * 定义验证场景
     */
    protected $scene = [
        'create' => ['goods_type', 'goods_id'],
    ];
}
