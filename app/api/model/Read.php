<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 阅读记录模型
 */
class Read extends Model
{
    // 表名
    protected $table = 'djxs_user_read';

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

    // 关联小说
    public function novel()
    {
        return $this->belongsTo(Novel::class, 'novel_id', 'id');
    }

    // 关联章节
    public function chapter()
    {
        return $this->belongsTo(NovelChapter::class, 'chapter_id', 'id');
    }
}
