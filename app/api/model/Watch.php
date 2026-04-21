<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 观看记录模型
 */
class Watch extends Model
{
    // 表名
    protected $table = 'djxs_user_watch';

    // 主键
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'create_time';
    protected $updateTime = 'update_time';

    // 关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // 关联短剧
    public function drama()
    {
        return $this->belongsTo(Drama::class, 'drama_id', 'id');
    }

    // 关联剧集
    public function episode()
    {
        return $this->belongsTo(DramaEpisode::class, 'episode_id', 'id');
    }
}
