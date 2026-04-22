<?php
declare (strict_types = 1);

namespace app\api\model;

use think\Model;

/**
 * 小说章节模型
 */
class NovelChapter extends Model
{
    // 表名
    protected $name = 'novel_chapter';

    // 主键
    protected $pk = 'id';

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    /**
     * 关联小说
     */
    public function novel()
    {
        return $this->belongsTo(Novel::class, 'novel_id', 'id');
    }
}
