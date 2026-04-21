<?php
declare (strict_types = 1);

namespace app\admin\validate;

use think\Validate;

class PaginationValidate extends Validate
{
    protected $rule = [
        'page' => 'require|integer|egt:1',
        'page_size' => 'require|integer|between:1,100',
    ];

    protected $message = [
        'page.require' => 'page 必填',
        'page.integer' => 'page 必须为整数',
        'page.egt' => 'page 必须大于等于 1',
        'page_size.require' => 'page_size 必填',
        'page_size.integer' => 'page_size 必须为整数',
        'page_size.between' => 'page_size 必须在 1-100 之间',
    ];
}
