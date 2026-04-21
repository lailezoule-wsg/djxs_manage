<?php
declare (strict_types = 1);

namespace app\admin\validate;

use think\Validate;

class DistributionWithdrawAuditValidate extends Validate
{
    protected $rule = [
        'status' => 'require|in:1,2',
        'remark' => 'max:255',
    ];

    protected $message = [
        'status.require' => 'status 必填',
        'status.in' => 'status 仅支持 1(通过)/2(拒绝)',
        'remark.max' => 'remark 长度不能超过255',
    ];
}
