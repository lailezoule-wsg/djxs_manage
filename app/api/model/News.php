<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 资讯模型
 */
class News extends Model
{
    protected $name = 'news';

    protected $pk = 'id';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_time';

    protected $updateTime = 'update_time';

    protected $type = [
        'id' => 'integer',
        'news_type' => 'integer',
        'related_type' => 'integer',
        'related_id' => 'integer',
        'is_top' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
        'view_count' => 'integer',
    ];
}
