<?php
declare (strict_types = 1);

namespace app\admin\service;

use think\exception\ValidateException;
use think\facade\Db;

/**
 * 管理端内容业务服务（短剧/小说/分类/标签/审核）
 */
class ContentAdminService extends BaseAdminService
{
    /**
     * 重建内容展示统计字段（章节数/字数）
     */
    public function rebuildDisplayStats(int $limit = 500, bool $dryRun = true): array
    {
        $limit = max(1, min(5000, $limit));
        $result = [
            'dry_run' => $dryRun ? 1 : 0,
            'drama_scanned' => 0,
            'drama_total_episodes_fixed' => 0,
            'novel_scanned' => 0,
            'novel_total_chapters_fixed' => 0,
            'novel_word_count_fixed' => 0,
            'chapter_word_count_fixed' => 0,
            'executed_at' => date('Y-m-d H:i:s'),
        ];

        $dramas = Db::name('drama')
            ->field('id,total_episodes')
            ->order('id', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();
        $result['drama_scanned'] = count($dramas);
        foreach ($dramas as $drama) {
            $dramaId = (int)($drama['id'] ?? 0);
            if ($dramaId <= 0) {
                continue;
            }
            $episodes = (int)Db::name('drama_episode')
                ->where('drama_id', $dramaId)
                ->where('status', 1)
                ->count();
            if ($episodes !== (int)($drama['total_episodes'] ?? 0)) {
                $result['drama_total_episodes_fixed']++;
                if (!$dryRun) {
                    Db::name('drama')->where('id', $dramaId)->update(['total_episodes' => $episodes]);
                }
            }
        }

        $novels = Db::name('novel')
            ->field('id,total_chapters,word_count')
            ->order('id', 'asc')
            ->limit($limit)
            ->select()
            ->toArray();
        $result['novel_scanned'] = count($novels);
        foreach ($novels as $novel) {
            $novelId = (int)($novel['id'] ?? 0);
            if ($novelId <= 0) {
                continue;
            }
            $chapters = Db::name('novel_chapter')
                ->where('novel_id', $novelId)
                ->field('id,content,word_count,status')
                ->order('id', 'asc')
                ->select()
                ->toArray();

            $activeChapters = 0;
            $activeWords = 0;
            foreach ($chapters as $chapter) {
                $chapterId = (int)($chapter['id'] ?? 0);
                if ($chapterId <= 0) {
                    continue;
                }
                $wordCount = $this->calcTextLength((string)($chapter['content'] ?? ''));
                if ($wordCount !== (int)($chapter['word_count'] ?? 0)) {
                    $result['chapter_word_count_fixed']++;
                    if (!$dryRun) {
                        Db::name('novel_chapter')->where('id', $chapterId)->update(['word_count' => $wordCount]);
                    }
                }
                if ((int)($chapter['status'] ?? 0) === 1) {
                    $activeChapters++;
                    $activeWords += $wordCount;
                }
            }

            if ($activeChapters !== (int)($novel['total_chapters'] ?? 0)) {
                $result['novel_total_chapters_fixed']++;
            }
            if ($activeWords !== (int)($novel['word_count'] ?? 0)) {
                $result['novel_word_count_fixed']++;
            }
            if (
                !$dryRun &&
                (
                    $activeChapters !== (int)($novel['total_chapters'] ?? 0) ||
                    $activeWords !== (int)($novel['word_count'] ?? 0)
                )
            ) {
                Db::name('novel')->where('id', $novelId)->update([
                    'total_chapters' => $activeChapters,
                    'word_count' => max(0, $activeWords),
                ]);
            }
        }

        return $result;
    }

    /**
     * 通用分页查询
     */
    public function listByTable(string $table, array $params, int $page, int $pageSize, array $fixedWhere = [], string $orderField = 'id'): array
    {
        $title = trim((string)($params['title'] ?? ''));
        $status = $params['status'] ?? '';
        $query = Db::name($table);
        foreach ($fixedWhere as $field => $value) {
            $query->where($field, $value);
        }
        $fields = $this->getTableFields($table);
        if ($title !== '' && isset($fields['title'])) {
            $query->whereLike('title', '%' . $title . '%');
        }
        if ($status !== '' && $status !== null && isset($fields['status'])) {
            $query->where('status', (int)$status);
        }
        if ($orderField !== '' && isset($fields[$orderField])) {
            $query->order($orderField, 'asc');
        }
        // 与首列排序一致：主键升序，列表中 ID 自上而下连续递增（剧集/章节仍以前面的 episode_number/chapter_number 为主序）
        $query->order('id', 'asc');
        return $this->paginateToArray($query, $page, $pageSize);
    }

    /**
     * 通用创建
     */
    public function createByTable(string $table, array $payload): int
    {
        $data = $this->filterPayload($table, $payload, false);
        $this->normalizeDerivedFields($table, $data);
        if ($table === 'drama' || $table === 'novel') {
            unset($data['price']);
        }
        if (empty($data)) {
            throw new ValidateException('缺少可写入字段');
        }
        $data = $this->ensureCreateTimeForInsert($table, $data);
        $this->assertChildContentUniquesOnWrite($table, $data, null);
        if (!$this->isEpisodeOrChapterTable($table)) {
            $this->assertContentPricingRules($table, $data, null);
        }

        $id = (int)Db::name($table)->insertGetId($data);
        $this->afterContentRowInsert($table, $id, $data);

        return $id;
    }

    /**
     * 通用创建（带固定条件作用域）
     */
    public function createByTableScoped(string $table, array $payload, array $fixedWhere): int
    {
        $data = $this->filterPayload($table, $payload, false);
        $this->normalizeDerivedFields($table, $data);
        foreach ($fixedWhere as $field => $value) {
            $data[$field] = $value;
        }
        if ($table === 'drama' || $table === 'novel') {
            unset($data['price']);
        }
        if (empty($data)) {
            throw new ValidateException('缺少可写入字段');
        }
        $data = $this->ensureCreateTimeForInsert($table, $data);
        $this->assertChildContentUniquesOnWrite($table, $data, null);
        if (!$this->isEpisodeOrChapterTable($table)) {
            $this->assertContentPricingRules($table, $data, null);
        }

        $id = (int)Db::name($table)->insertGetId($data);
        $this->afterContentRowInsert($table, $id, $data);

        return $id;
    }

    /**
     * 通用更新
     */
    public function updateByTable(string $table, int $id, array $payload): void
    {
        $this->assertExists($table, $id);
        $data = $this->filterPayload($table, $payload, true);
        $this->normalizeDerivedFields($table, $data);
        if ($table === 'drama' || $table === 'novel') {
            unset($data['price']);
        }
        if (empty($data)) {
            throw new ValidateException('缺少可更新字段');
        }
        $merged = ($table === 'drama_episode' || $table === 'novel_chapter')
            ? $this->mergeRowForChildContentCheck($table, $id, $data)
            : $this->mergeUpdateRow($table, $id, $data);
        $this->assertChildContentUniquesOnWrite($table, $merged, $id);
        if (!$this->isEpisodeOrChapterTable($table)) {
            $this->assertContentPricingRules($table, $merged, $id);
        }
        Db::name($table)->where('id', $id)->update($data);
        $this->afterContentRowUpdate($table, $id, $merged);
    }

    /**
     * 通用更新（带固定条件作用域）
     */
    public function updateByTableScoped(string $table, int $id, array $payload, array $fixedWhere): void
    {
        $query = Db::name($table)->where('id', $id);
        foreach ($fixedWhere as $field => $value) {
            $query->where($field, $value);
        }
        if (!$query->find()) {
            throw new ValidateException('数据不存在');
        }
        $data = $this->filterPayload($table, $payload, true);
        $this->normalizeDerivedFields($table, $data);
        foreach ($fixedWhere as $field => $value) {
            $data[$field] = $value;
        }
        if ($table === 'drama' || $table === 'novel') {
            unset($data['price']);
        }
        if (empty($data)) {
            throw new ValidateException('缺少可更新字段');
        }
        $merged = $this->mergeRowForChildContentCheck($table, $id, $data);
        $this->assertChildContentUniquesOnWrite($table, $merged, $id);
        if (!$this->isEpisodeOrChapterTable($table)) {
            $this->assertContentPricingRules($table, $merged, $id);
        }
        Db::name($table)->where('id', $id)->update($data);
        $this->afterContentRowUpdate($table, $id, $merged);
    }

    /** @return array<string, mixed> */
    private function mergeUpdateRow(string $table, int $id, array $patch): array
    {
        $current = Db::name($table)->where('id', $id)->find();

        return is_array($current) ? array_merge($current, $patch) : $patch;
    }

    /**
     * 短剧/小说：校验「整剧/整本价比例」；整剧价本身由子集标价与比例写回后校验。
     */
    private function assertContentPricingRules(string $table, array $row, ?int $updateId): void
    {
        if ($table === 'drama') {
            $ratio = (float)($row['whole_bundle_ratio'] ?? 1);
            $this->assertValidWholeBundleRatio($ratio);

            return;
        }
        if ($table === 'novel') {
            $ratio = (float)($row['whole_bundle_ratio'] ?? 1);
            $this->assertValidWholeBundleRatio($ratio);
        }
    }

    private function assertValidWholeBundleRatio(float $ratio): void
    {
        if ($ratio < 0.01 || $ratio > 2.0) {
            throw new ValidateException('整剧/整本价相对单集（章）标价合计的比例须在 0.01～2.00 之间。');
        }
    }

    private function isEpisodeOrChapterTable(string $table): bool
    {
        return $table === 'drama_episode' || $table === 'novel_chapter';
    }

    private function normalizeDerivedFields(string $table, array &$data): void
    {
        if ($table === 'novel_chapter' && array_key_exists('content', $data)) {
            $data['word_count'] = $this->calcTextLength((string)$data['content']);
        }
    }

    private function calcTextLength(string $text): int
    {
        if (function_exists('mb_strlen')) {
            return max(0, (int)mb_strlen($text, 'UTF-8'));
        }
        return max(0, strlen($text));
    }

    private function recalcDramaDisplayStats(int $dramaId): void
    {
        if ($dramaId <= 0) {
            return;
        }
        $episodes = (int)Db::name('drama_episode')
            ->where('drama_id', $dramaId)
            ->where('status', 1)
            ->count();
        Db::name('drama')->where('id', $dramaId)->update([
            'total_episodes' => $episodes,
        ]);
    }

    private function recalcNovelDisplayStats(int $novelId): void
    {
        if ($novelId <= 0) {
            return;
        }
        $query = Db::name('novel_chapter')
            ->where('novel_id', $novelId)
            ->where('status', 1);
        $chapters = (int)$query->count();
        $wordRow = Db::name('novel_chapter')
            ->where('novel_id', $novelId)
            ->where('status', 1)
            ->fieldRaw('COALESCE(SUM(CHAR_LENGTH(content)), 0) AS total_words')
            ->find();
        $wordCount = (int)($wordRow['total_words'] ?? 0);
        if ($wordCount <= 0 && $chapters > 0) {
            $wordCount = (int)Db::name('novel_chapter')
                ->where('novel_id', $novelId)
                ->where('status', 1)
                ->sum('word_count');
        }
        Db::name('novel')->where('id', $novelId)->update([
            'total_chapters' => $chapters,
            'word_count' => max(0, $wordCount),
        ]);
    }

    private function recalcDramaWholePrice(int $dramaId): void
    {
        if ($dramaId <= 0) {
            return;
        }
        $row = Db::name('drama')->where('id', $dramaId)->find();
        if (!is_array($row)) {
            return;
        }
        $ratio = (float)($row['whole_bundle_ratio'] ?? 1);
        if ($ratio < 0.01) {
            $ratio = 0.0;
        }
        $sum = (float)Db::name('drama_episode')
            ->where('drama_id', $dramaId)
            ->where('status', 1)
            ->sum('price');
        $raw = $sum * $ratio;
        $whole = round($raw, 2);
        if ($sum > 0 && $ratio > 0 && $whole <= 0) {
            $whole = 0.01;
        }
        if ($sum <= 0 || $ratio <= 0) {
            $whole = 0.0;
        }
        Db::name('drama')->where('id', $dramaId)->update(['price' => $whole]);
    }

    private function recalcNovelWholePrice(int $novelId): void
    {
        if ($novelId <= 0) {
            return;
        }
        $row = Db::name('novel')->where('id', $novelId)->find();
        if (!is_array($row)) {
            return;
        }
        $ratio = (float)($row['whole_bundle_ratio'] ?? 1);
        if ($ratio < 0.01) {
            $ratio = 0.0;
        }
        $sum = (float)Db::name('novel_chapter')
            ->where('novel_id', $novelId)
            ->where('status', 1)
            ->sum('price');
        $raw = $sum * $ratio;
        $whole = round($raw, 2);
        if ($sum > 0 && $ratio > 0 && $whole <= 0) {
            $whole = 0.01;
        }
        if ($sum <= 0 || $ratio <= 0) {
            $whole = 0.0;
        }
        Db::name('novel')->where('id', $novelId)->update(['price' => $whole]);
    }

    private function assertDramaMonetizationConsistent(int $dramaId): void
    {
        if ($dramaId <= 0) {
            return;
        }
        $cnt = Db::name('drama_episode')->where('drama_id', $dramaId)->where('price', '>', 0)->count();
        $price = (float)(Db::name('drama')->where('id', $dramaId)->value('price') ?? 0);
        if ($cnt > 0 && $price <= 0) {
            throw new ValidateException(
                '该短剧已有单集标价（大于0），按当前比例计算出的整剧价须大于 0。请提高「整剧价比例」或增加上架单集的标价后再保存。'
            );
        }
    }

    private function assertNovelMonetizationConsistent(int $novelId): void
    {
        if ($novelId <= 0) {
            return;
        }
        $cnt = Db::name('novel_chapter')->where('novel_id', $novelId)->where('price', '>', 0)->count();
        $price = (float)(Db::name('novel')->where('id', $novelId)->value('price') ?? 0);
        if ($cnt > 0 && $price <= 0) {
            throw new ValidateException(
                '该小说已有章节标价（大于0），按当前比例计算出的整本价须大于 0。请提高「整本价比例」或增加上架章节的标价后再保存。'
            );
        }
    }

    /** @param array<string, mixed> $data 插入行（含 drama_id / novel_id 等） */
    private function afterContentRowInsert(string $table, int $id, array $data): void
    {
        if ($table === 'drama') {
            $this->recalcDramaDisplayStats($id);
            $this->recalcDramaWholePrice($id);

            return;
        }
        if ($table === 'novel') {
            $this->recalcNovelDisplayStats($id);
            $this->recalcNovelWholePrice($id);

            return;
        }
        if ($table === 'drama_episode') {
            $dramaId = (int)($data['drama_id'] ?? 0);
            $this->recalcDramaDisplayStats($dramaId);
            $this->recalcDramaWholePrice($dramaId);
            $this->assertDramaMonetizationConsistent($dramaId);

            return;
        }
        if ($table === 'novel_chapter') {
            $novelId = (int)($data['novel_id'] ?? 0);
            $this->recalcNovelDisplayStats($novelId);
            $this->recalcNovelWholePrice($novelId);
            $this->assertNovelMonetizationConsistent($novelId);
        }
    }

    /** @param array<string, mixed> $merged 合并后的完整行 */
    private function afterContentRowUpdate(string $table, int $id, array $merged): void
    {
        if ($table === 'drama_episode') {
            $dramaId = (int)($merged['drama_id'] ?? 0);
            $this->recalcDramaDisplayStats($dramaId);
            $this->recalcDramaWholePrice($dramaId);
            $this->assertDramaMonetizationConsistent($dramaId);

            return;
        }
        if ($table === 'novel_chapter') {
            $novelId = (int)($merged['novel_id'] ?? 0);
            $this->recalcNovelDisplayStats($novelId);
            $this->recalcNovelWholePrice($novelId);
            $this->assertNovelMonetizationConsistent($novelId);

            return;
        }
        if ($table === 'drama') {
            $this->recalcDramaDisplayStats($id);
            $this->recalcDramaWholePrice($id);
            $this->assertDramaMonetizationConsistent($id);

            return;
        }
        if ($table === 'novel') {
            $this->recalcNovelDisplayStats($id);
            $this->recalcNovelWholePrice($id);
            $this->assertNovelMonetizationConsistent($id);
        }
    }

    /**
     * 短剧剧集 / 小说章节：同一作品下标题不可重复，集数或章节号不可与同作品其他条重复。
     */
    private function assertChildContentUniquesOnWrite(string $table, array $row, ?int $excludeId): void
    {
        if ($table === 'drama_episode') {
            $this->assertDramaEpisodeUniques($row, $excludeId);
        } elseif ($table === 'novel_chapter') {
            $this->assertNovelChapterUniques($row, $excludeId);
        }
    }

    /** @return array<string, mixed> */
    private function mergeRowForChildContentCheck(string $table, int $id, array $patch): array
    {
        if ($table !== 'drama_episode' && $table !== 'novel_chapter') {
            return $patch;
        }
        $current = Db::name($table)->where('id', $id)->find();
        if (!is_array($current)) {
            return $patch;
        }

        return array_merge($current, $patch);
    }

    private function assertDramaEpisodeUniques(array $row, ?int $excludeId): void
    {
        $dramaId = (int)($row['drama_id'] ?? 0);
        if ($dramaId <= 0) {
            throw new ValidateException('缺少短剧ID');
        }
        $title = trim((string)($row['title'] ?? ''));
        if ($title === '') {
            throw new ValidateException('请填写剧集标题');
        }
        $episodeNumber = (int)($row['episode_number'] ?? 0);
        if ($episodeNumber < 1) {
            throw new ValidateException('集数须为不小于 1 的整数');
        }

        $dupTitle = Db::name('drama_episode')->where('drama_id', $dramaId)->where('title', $title);
        if ($excludeId !== null) {
            $dupTitle->where('id', '<>', $excludeId);
        }
        if ($dupTitle->find()) {
            throw new ValidateException('同一短剧下已存在相同标题的剧集');
        }

        $dupNum = Db::name('drama_episode')->where('drama_id', $dramaId)->where('episode_number', $episodeNumber);
        if ($excludeId !== null) {
            $dupNum->where('id', '<>', $excludeId);
        }
        if ($dupNum->find()) {
            throw new ValidateException('同一短剧下集数不可重复');
        }
    }

    private function assertNovelChapterUniques(array $row, ?int $excludeId): void
    {
        $novelId = (int)($row['novel_id'] ?? 0);
        if ($novelId <= 0) {
            throw new ValidateException('缺少小说ID');
        }
        $title = trim((string)($row['title'] ?? ''));
        if ($title === '') {
            throw new ValidateException('请填写章节标题');
        }
        $chapterNumber = (int)($row['chapter_number'] ?? 0);
        if ($chapterNumber < 1) {
            throw new ValidateException('章节号须为不小于 1 的整数');
        }

        $dupTitle = Db::name('novel_chapter')->where('novel_id', $novelId)->where('title', $title);
        if ($excludeId !== null) {
            $dupTitle->where('id', '<>', $excludeId);
        }
        if ($dupTitle->find()) {
            throw new ValidateException('同一小说下已存在相同标题的章节');
        }

        $dupNum = Db::name('novel_chapter')->where('novel_id', $novelId)->where('chapter_number', $chapterNumber);
        if ($excludeId !== null) {
            $dupNum->where('id', '<>', $excludeId);
        }
        if ($dupNum->find()) {
            throw new ValidateException('同一小说下章节号不可重复');
        }
    }

    /**
     * 通用删除
     */
    public function deleteByTable(string $table, int $id): void
    {
        $this->assertExists($table, $id);
        $pre = Db::name($table)->where('id', $id)->find();
        Db::name($table)->where('id', $id)->delete();
        if (!is_array($pre)) {
            return;
        }
        if ($table === 'drama_episode') {
            $dramaId = (int)($pre['drama_id'] ?? 0);
            $this->recalcDramaDisplayStats($dramaId);
            $this->recalcDramaWholePrice($dramaId);

            return;
        }
        if ($table === 'novel_chapter') {
            $novelId = (int)($pre['novel_id'] ?? 0);
            $this->recalcNovelDisplayStats($novelId);
            $this->recalcNovelWholePrice($novelId);
        }
    }

    /**
     * 通用删除（带固定条件作用域）
     */
    public function deleteByTableScoped(string $table, int $id, array $fixedWhere): void
    {
        $query = Db::name($table)->where('id', $id);
        foreach ($fixedWhere as $field => $value) {
            $query->where($field, $value);
        }
        $pre = $query->find();
        if (!$pre) {
            throw new ValidateException('数据不存在');
        }
        $del = Db::name($table)->where('id', $id);
        foreach ($fixedWhere as $field => $value) {
            $del->where($field, $value);
        }
        $del->delete();
        if (!is_array($pre)) {
            return;
        }
        if ($table === 'drama_episode') {
            $dramaId = (int)($pre['drama_id'] ?? 0);
            $this->recalcDramaDisplayStats($dramaId);
            $this->recalcDramaWholePrice($dramaId);

            return;
        }
        if ($table === 'novel_chapter') {
            $novelId = (int)($pre['novel_id'] ?? 0);
            $this->recalcNovelDisplayStats($novelId);
            $this->recalcNovelWholePrice($novelId);
        }
    }

    /**
     * 通用内容审核
     */
    public function audit(array $payload): void
    {
        $tableMap = ['drama' => 'drama', 'novel' => 'novel'];
        $contentType = strtolower((string)($payload['content_type'] ?? ''));
        $contentId = (int)($payload['content_id'] ?? 0);
        $status = (int)($payload['status'] ?? 1);
        $remark = (string)($payload['remark'] ?? '');

        if (!isset($tableMap[$contentType]) || $contentId <= 0) {
            throw new ValidateException('参数错误');
        }
        $table = $tableMap[$contentType];
        $this->assertExists($table, $contentId, '内容不存在');
        $fields = $this->getTableFields($table);
        $data = [];
        if (isset($fields['status'])) {
            $data['status'] = $status;
        }
        if (isset($fields['audit_status'])) {
            $data['audit_status'] = $status;
        }
        if (isset($fields['audit_remark'])) {
            $data['audit_remark'] = $remark;
        }
        if (empty($data)) {
            throw new ValidateException('当前内容表未配置审核字段');
        }
        Db::name($table)->where('id', $contentId)->update($data);
    }

    /**
     * 按内容类型执行审核
     */
    public function auditByType(string $contentType, array $payload): void
    {
        $payload['content_type'] = $contentType;
        $this->audit($payload);
    }
}
