<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\Drama;
use app\api\model\Novel;

/**
 * 搜索服务层
 */
class SearchService
{
    /**
     * 统一搜索（短剧/小说）
     */
    public function search(array $params = []): array
    {
        $keyword = trim((string)($params['keyword'] ?? ''));
        $type = strtolower(trim((string)($params['type'] ?? 'all')));
        $page = isset($params['page']) ? max(1, (int)$params['page']) : 1;
        $pageSize = isset($params['page_size']) ? (int)$params['page_size'] : (isset($params['limit']) ? (int)$params['limit'] : 10);
        $pageSize = max(1, min($pageSize, 50));

        if ($keyword === '') {
            return [
                'keyword' => $keyword,
                'type' => $type,
                'list' => [],
                'total' => 0,
                'page' => $page,
                'page_size' => $pageSize,
                'has_more' => false,
            ];
        }

        $dramaRows = [];
        $novelRows = [];
        $total = 0;
        $hasMore = false;

        if ($type === 'drama' || $type === 'all') {
            $dramaResult = Drama::where('title', 'like', '%' . $keyword . '%')
                ->where('status', 1)
                ->order('sort', 'asc')
                ->order('id', 'desc')
                ->paginate([
                    'page' => $page,
                    'list_rows' => $pageSize,
                ]);

            $dramaRows = array_map(static function ($item): array {
                $row = $item instanceof \think\Model ? $item->toArray() : (array)$item;
                $row['content_type'] = 'drama';
                return $row;
            }, $dramaResult->items());
            $total += $dramaResult->total();
            $hasMore = $hasMore || ($dramaResult->currentPage() < $dramaResult->lastPage());
        }

        if ($type === 'novel' || $type === 'all') {
            $novelResult = Novel::where('title', 'like', '%' . $keyword . '%')
                ->where('status', 1)
                ->order('sort', 'asc')
                ->order('id', 'desc')
                ->paginate([
                    'page' => $page,
                    'list_rows' => $pageSize,
                ]);

            $novelRows = array_map(static function ($item): array {
                $row = $item instanceof \think\Model ? $item->toArray() : (array)$item;
                $row['content_type'] = 'novel';
                return $row;
            }, $novelResult->items());
            $total += $novelResult->total();
            $hasMore = $hasMore || ($novelResult->currentPage() < $novelResult->lastPage());
        }

        return [
            'keyword' => $keyword,
            'type' => $type,
            'list' => array_values(array_merge($dramaRows, $novelRows)),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'has_more' => $hasMore,
        ];
    }
}
