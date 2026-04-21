<?php
declare (strict_types = 1);

namespace app\admin\service;

use think\exception\ValidateException;
use think\facade\Db;

class NewsAdminService extends BaseAdminService
{
    public function list(array $params, int $page, int $pageSize): array
    {
        $query = Db::name('news');
        $keyword = trim((string) ($params['keyword'] ?? ''));
        $newsType = (int) ($params['news_type'] ?? 0);
        $statusRaw = $params['status'] ?? '';

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('title', "%{$keyword}%")
                    ->whereOrLike('summary', "%{$keyword}%");
            });
        }
        if (in_array($newsType, [1, 2], true)) {
            $query->where('news_type', $newsType);
        }
        if ($statusRaw !== '' && in_array((int) $statusRaw, [0, 1], true)) {
            $query->where('status', (int) $statusRaw);
        }

        $query->order('is_top', 'desc')
            ->order('sort', 'desc')
            ->order('id', 'desc');

        return $this->paginateToArray($query, $page, $pageSize);
    }

    public function create(array $payload): int
    {
        $data = $this->normalizePayload($payload, false);
        $data = $this->ensureCreateTimeForInsert('news', $data);
        $id = Db::name('news')->insertGetId($data);

        return (int) $id;
    }

    public function update(int $id, array $payload): void
    {
        $this->assertExists('news', $id, '资讯不存在');
        $data = $this->normalizePayload($payload, true);
        if (!empty($data)) {
            Db::name('news')->where('id', $id)->update($data);
        }
    }

    public function delete(int $id): void
    {
        $this->assertExists('news', $id, '资讯不存在');
        Db::name('news')->where('id', $id)->delete();
    }

    private function normalizePayload(array $payload, bool $isUpdate): array
    {
        $hasTitle = array_key_exists('title', $payload);
        $hasCover = array_key_exists('cover', $payload);
        $hasSummary = array_key_exists('summary', $payload);
        $hasContent = array_key_exists('content', $payload);
        $hasType = array_key_exists('news_type', $payload);
        $hasRelatedType = array_key_exists('related_type', $payload);
        $hasRelatedId = array_key_exists('related_id', $payload);
        $hasIsTop = array_key_exists('is_top', $payload);
        $hasSort = array_key_exists('sort', $payload);
        $hasStatus = array_key_exists('status', $payload);
        $hasPublishTime = array_key_exists('publish_time', $payload);

        $title = trim((string) ($payload['title'] ?? ''));
        $cover = trim((string) ($payload['cover'] ?? ''));
        $summary = trim((string) ($payload['summary'] ?? ''));
        $content = trim((string) ($payload['content'] ?? ''));
        $newsType = (int) ($payload['news_type'] ?? 1);
        $relatedType = (int) ($payload['related_type'] ?? 0);
        $relatedId = (int) ($payload['related_id'] ?? 0);
        $isTop = (int) ($payload['is_top'] ?? 0);
        $sort = (int) ($payload['sort'] ?? 0);
        $status = (int) ($payload['status'] ?? 0);
        $publishTime = isset($payload['publish_time']) ? trim((string) $payload['publish_time']) : '';

        if (!$isUpdate) {
            if ($title === '') {
                throw new ValidateException('资讯标题不能为空');
            }
            if ($summary === '') {
                throw new ValidateException('资讯摘要不能为空');
            }
            if ($content === '') {
                throw new ValidateException('资讯正文不能为空');
            }
            if (!in_array($newsType, [1, 2], true)) {
                throw new ValidateException('资讯类型不合法');
            }
            if (!in_array($relatedType, [0, 1, 2], true)) {
                throw new ValidateException('关联内容类型不合法');
            }
            if ($relatedType === 0) {
                $relatedId = 0;
            } elseif ($relatedId < 1) {
                throw new ValidateException('请选择有效的关联内容ID');
            }
            if (!in_array($status, [0, 1], true)) {
                throw new ValidateException('状态不合法');
            }
            if ($status === 1 && $publishTime === '') {
                $publishTime = date('Y-m-d H:i:s');
            }
            if ($publishTime !== '' && strtotime($publishTime) === false) {
                throw new ValidateException('发布时间格式不正确');
            }

            return [
                'title' => $title,
                'cover' => $cover,
                'summary' => $summary,
                'content' => $content,
                'news_type' => $newsType,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'is_top' => $isTop === 1 ? 1 : 0,
                'sort' => $sort,
                'status' => $status,
                'publish_time' => $publishTime === '' ? null : $publishTime,
                'update_time' => date('Y-m-d H:i:s'),
            ];
        }

        $data = [];
        if ($hasTitle) {
            if ($title === '') {
                throw new ValidateException('资讯标题不能为空');
            }
            $data['title'] = $title;
        }
        if ($hasCover) {
            $data['cover'] = $cover;
        }
        if ($hasSummary) {
            if ($summary === '') {
                throw new ValidateException('资讯摘要不能为空');
            }
            $data['summary'] = $summary;
        }
        if ($hasContent) {
            if ($content === '') {
                throw new ValidateException('资讯正文不能为空');
            }
            $data['content'] = $content;
        }
        if ($hasType) {
            if (!in_array($newsType, [1, 2], true)) {
                throw new ValidateException('资讯类型不合法');
            }
            $data['news_type'] = $newsType;
        }
        if ($hasRelatedType) {
            if (!in_array($relatedType, [0, 1, 2], true)) {
                throw new ValidateException('关联内容类型不合法');
            }
            $data['related_type'] = $relatedType;
            if ($relatedType === 0) {
                $data['related_id'] = 0;
            }
        }
        if ($hasRelatedId) {
            if ($relatedId < 0) {
                throw new ValidateException('关联内容ID不合法');
            }
            $data['related_id'] = $relatedId;
        }
        if (
            (array_key_exists('related_type', $data) && (int) $data['related_type'] !== 0)
            || (!array_key_exists('related_type', $data) && $hasRelatedId && $relatedId > 0)
        ) {
            $typeForCheck = (int) ($data['related_type'] ?? ($payload['related_type'] ?? 0));
            $idForCheck = (int) ($data['related_id'] ?? ($payload['related_id'] ?? 0));
            if ($typeForCheck !== 0 && $idForCheck < 1) {
                throw new ValidateException('请选择有效的关联内容ID');
            }
        }
        if ($hasIsTop) {
            $data['is_top'] = $isTop === 1 ? 1 : 0;
        }
        if ($hasSort) {
            $data['sort'] = $sort;
        }
        if ($hasStatus) {
            if (!in_array($status, [0, 1], true)) {
                throw new ValidateException('状态不合法');
            }
            $data['status'] = $status;
            if ($status === 1 && !$hasPublishTime) {
                $data['publish_time'] = date('Y-m-d H:i:s');
            }
        }
        if ($hasPublishTime) {
            if ($publishTime !== '' && strtotime($publishTime) === false) {
                throw new ValidateException('发布时间格式不正确');
            }
            $data['publish_time'] = $publishTime === '' ? null : $publishTime;
        }
        if (!empty($data)) {
            $data['update_time'] = date('Y-m-d H:i:s');
        }

        return $data;
    }
}
