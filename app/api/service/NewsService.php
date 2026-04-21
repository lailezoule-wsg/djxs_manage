<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\News;
use think\exception\ValidateException;

class NewsService
{
    public function list(array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = (int) ($params['limit'] ?? ($params['page_size'] ?? 10));
        $limit = max(1, min(50, $limit));
        $newsType = (int) ($params['news_type'] ?? 0);

        $query = News::where('status', 1)
            ->where(function ($q) {
                $q->whereNull('publish_time')
                    ->whereOr('publish_time', '<=', date('Y-m-d H:i:s'));
            });

        if (in_array($newsType, [1, 2], true)) {
            $query->where('news_type', $newsType);
        }

        $result = $query->field('id,title,cover,summary,news_type,related_type,related_id,is_top,sort,publish_time,view_count,create_time')
            ->order('is_top', 'desc')
            ->order('sort', 'desc')
            ->order('publish_time', 'desc')
            ->order('id', 'desc')
            ->paginate([
                'page' => $page,
                'list_rows' => $limit,
            ]);

        return [
            'list' => $result->items(),
            'total' => $result->total(),
            'page' => $result->currentPage(),
            'limit' => $result->listRows(),
            'has_more' => $result->currentPage() < $result->lastPage(),
        ];
    }

    public function detail(int $id): array
    {
        if ($id < 1) {
            throw new ValidateException('资讯不存在');
        }

        $row = News::where('id', $id)
            ->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('publish_time')
                    ->whereOr('publish_time', '<=', date('Y-m-d H:i:s'));
            })
            ->find();

        if (!$row) {
            throw new ValidateException('资讯不存在或未发布');
        }

        $detail = $row->toArray();
        $currentViewCount = (int) ($detail['view_count'] ?? 0);
        $row->setInc('view_count', 1);
        $detail['view_count'] = $currentViewCount + 1;
        [$prevNews, $nextNews] = $this->resolveAdjacentNews((int)($detail['id'] ?? 0), (int)($detail['news_type'] ?? 0));
        $detail['prev_news'] = $prevNews;
        $detail['next_news'] = $nextNews;

        return $detail;
    }

    /**
     * 获取同类型资讯的上一篇/下一篇（按列表排序口径）。
     *
     * @return array{0:array{id:int,title:string}|null,1:array{id:int,title:string}|null}
     */
    private function resolveAdjacentNews(int $currentId, int $newsType): array
    {
        if ($currentId <= 0 || !in_array($newsType, [1, 2], true)) {
            return [null, null];
        }
        $rows = News::where('status', 1)
            ->where('news_type', $newsType)
            ->where(function ($q) {
                $q->whereNull('publish_time')
                    ->whereOr('publish_time', '<=', date('Y-m-d H:i:s'));
            })
            ->field('id,title')
            ->order('is_top', 'desc')
            ->order('sort', 'desc')
            ->order('publish_time', 'desc')
            ->order('id', 'desc')
            ->select()
            ->toArray();

        $currentIndex = -1;
        foreach ($rows as $idx => $item) {
            if ((int)($item['id'] ?? 0) === $currentId) {
                $currentIndex = $idx;
                break;
            }
        }
        if ($currentIndex < 0) {
            return [null, null];
        }

        $prevRaw = $rows[$currentIndex - 1] ?? null;
        $nextRaw = $rows[$currentIndex + 1] ?? null;
        $prev = is_array($prevRaw) ? [
            'id' => (int)($prevRaw['id'] ?? 0),
            'title' => (string)($prevRaw['title'] ?? ''),
        ] : null;
        $next = is_array($nextRaw) ? [
            'id' => (int)($nextRaw['id'] ?? 0),
            'title' => (string)($nextRaw['title'] ?? ''),
        ] : null;

        return [$prev, $next];
    }
}
