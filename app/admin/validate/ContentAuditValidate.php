<?php
declare (strict_types = 1);

namespace app\admin\validate;

use think\Validate;

class ContentAuditValidate extends Validate
{
    protected $rule = [
        'content_type' => 'require|in:drama,novel',
        'content_id' => 'require|integer|egt:1',
        'status' => 'require|in:0,1',
        'remark' => 'max:255',
    ];

    protected $message = [
        'content_type.require' => 'content_type 必填',
        'content_type.in' => 'content_type 仅支持 drama/novel',
        'content_id.require' => 'content_id 必填',
        'content_id.integer' => 'content_id 必须为整数',
        'content_id.egt' => 'content_id 必须大于0',
        'status.require' => 'status 必填',
        'status.in' => 'status 仅支持 0/1',
        'remark.max' => 'remark 长度不能超过255',
    ];
}
