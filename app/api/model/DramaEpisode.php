<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 短剧剧集模型
 */
class DramaEpisode extends Model
{
    // 表名
    protected $table = 'djxs_drama_episode';
    
    // 自动时间戳
    protected $autoWriteTimestamp = 'datetime';
    
    protected $createTime = 'create_time';
    protected $updateTime = false;
    
    protected $type = [
        'id'             => 'integer',
        'drama_id'       => 'integer',
        'episode_number' => 'integer',
        'duration'       => 'integer',
        'price'          => 'decimal:2',
        'status'         => 'integer',
    ];
}
