<?php
declare (strict_types = 1);

namespace app\admin\service;

use think\exception\ValidateException;
use think\facade\Db;

/**
 * 管理端广告业务服务
 */
class AdAdminService extends BaseAdminService
{
    /**
     * 分页查询广告位
     */
    public function positionList(array $params, int $page, int $pageSize): array
    {
        $query = Db::name('ad_position')->alias('p');
        $status = isset($params['status']) && $params['status'] !== '' ? (int)$params['status'] : null;
        $keyword = trim((string)($params['keyword'] ?? ''));

        if ($status !== null) {
            $query->where('p.status', $status);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('p.name', "%{$keyword}%")
                    ->whereOrLike('p.position', "%{$keyword}%");
            });
        }

        $query->order('p.id', 'desc');
        return $this->paginateToArray($query, $page, $pageSize);
    }

    /**
     * 创建广告位
     */
    public function positionCreate(array $payload): int
    {
        $data = $this->normalizePositionPayload($payload, false);
        return (int)Db::name('ad_position')->insertGetId($data);
    }

    /**
     * 更新广告位
     */
    public function positionUpdate(int $id, array $payload): void
    {
        $this->assertExists('ad_position', $id, '广告位不存在');
        $data = $this->normalizePositionPayload($payload, true);
        if (!empty($data)) {
            Db::name('ad_position')->where('id', $id)->update($data);
        }
    }

    /**
     * 删除广告位
     */
    public function positionDelete(int $id): void
    {
        $this->assertExists('ad_position', $id, '广告位不存在');
        Db::name('ad_position')->where('id', $id)->delete();
    }

    /**
     * 分页查询广告
     */
    public function list(array $params, int $page, int $pageSize): array
    {
        $positionId = (int)($params['position_id'] ?? 0);
        $status = isset($params['status']) && $params['status'] !== '' ? (int)$params['status'] : null;
        $title = trim((string)($params['title'] ?? ''));
        $keyword = trim((string)($params['keyword'] ?? ''));
        $query = Db::name('ad')->alias('a')
            ->leftJoin('djxs_ad_position p', 'p.id = a.position_id')
            ->field('a.*,p.name as position_name');
        if ($positionId > 0) {
            $query->where('a.position_id', $positionId);
        }
        if ($status !== null) {
            $query->where('a.status', $status);
        }
        if ($title !== '') {
            $query->whereLike('a.title', "%{$title}%");
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('a.title', "%{$keyword}%")
                    ->whereOrLike('a.link_url', "%{$keyword}%")
                    ->whereOrLike('p.name', "%{$keyword}%")
                    ->whereOrLike('p.position', "%{$keyword}%");
            });
        }
        return $this->paginateToArray($query->order('a.id', 'desc'), $page, $pageSize);
    }

    /**
     * 创建广告
     */
    public function create(array $payload): int
    {
        $data = $this->normalizeAdPayload($payload, false);
        return (int)Db::name('ad')->insertGetId($data);
    }

    /**
     * 更新广告
     */
    public function update(int $id, array $payload): void
    {
        $this->assertExists('ad', $id, '广告不存在');
        $data = $this->normalizeAdPayload($payload, true);
        if (!empty($data)) {
            Db::name('ad')->where('id', $id)->update($data);
        }
    }

    /**
     * 删除广告
     */
    public function delete(int $id): void
    {
        $this->assertExists('ad', $id, '广告不存在');
        Db::name('ad')->where('id', $id)->delete();
    }

    /**
     * 获取广告统计
     */
    public function statistics(int $id): array
    {
        $row = Db::name('ad')->where('id', $id)->find();
        if (!$row) {
            throw new ValidateException('广告不存在');
        }
        return [
            'id' => $id,
            'click_count' => (int)($row['click_count'] ?? 0),
        ];
    }

    /**
     * 兼容旧前端字段：
     * - code -> position
     * - sort 为历史冗余字段，直接忽略
     */
    private function normalizePositionPayload(array $payload, bool $isUpdate): array
    {
        $hasName = array_key_exists('name', $payload);
        $hasPosition = array_key_exists('position', $payload) || array_key_exists('code', $payload);
        $hasWidth = array_key_exists('width', $payload);
        $hasHeight = array_key_exists('height', $payload);
        $hasStatus = array_key_exists('status', $payload);

        $name = trim((string)($payload['name'] ?? ''));
        $position = trim((string)($payload['position'] ?? ($payload['code'] ?? '')));
        $width = $hasWidth ? (int)$payload['width'] : null;
        $height = $hasHeight ? (int)$payload['height'] : null;
        $status = $hasStatus ? (int)$payload['status'] : null;

        if (!$isUpdate) {
            if ($name === '') {
                throw new ValidateException('广告位名称不能为空');
            }
            if ($position === '') {
                throw new ValidateException('广告位标识不能为空');
            }
            if (!$hasWidth || $width <= 0) {
                $width = 1200;
            }
            if (!$hasHeight || $height <= 0) {
                $height = 100;
            }
            if (!$hasStatus) {
                $status = 1;
            }
            return [
                'name' => $name,
                'position' => $position,
                'width' => $width,
                'height' => $height,
                'status' => $status === 1 ? 1 : 0,
            ];
        }

        $data = [];
        if ($hasName) {
            if ($name === '') {
                throw new ValidateException('广告位名称不能为空');
            }
            $data['name'] = $name;
        }
        if ($hasPosition) {
            if ($position === '') {
                throw new ValidateException('广告位标识不能为空');
            }
            $data['position'] = $position;
        }
        if ($hasWidth) {
            if ($width <= 0) {
                throw new ValidateException('广告位宽度必须大于0');
            }
            $data['width'] = $width;
        }
        if ($hasHeight) {
            if ($height <= 0) {
                throw new ValidateException('广告位高度必须大于0');
            }
            $data['height'] = $height;
        }
        if ($hasStatus) {
            $data['status'] = $status === 1 ? 1 : 0;
        }
        return $data;
    }

    /**
     * 兼容旧前端字段：
     * - link -> link_url
     * - image -> image_url
     */
    private function normalizeAdPayload(array $payload, bool $isUpdate): array
    {
        $hasPositionId = array_key_exists('position_id', $payload);
        $hasTitle = array_key_exists('title', $payload);
        $hasImageUrl = array_key_exists('image_url', $payload) || array_key_exists('image', $payload);
        $hasLinkUrl = array_key_exists('link_url', $payload) || array_key_exists('link', $payload);
        $hasStartTime = array_key_exists('start_time', $payload);
        $hasEndTime = array_key_exists('end_time', $payload);
        $hasStatus = array_key_exists('status', $payload);

        $positionId = (int)($payload['position_id'] ?? 0);
        $title = trim((string)($payload['title'] ?? ''));
        $imageUrl = trim((string)($payload['image_url'] ?? ($payload['image'] ?? '')));
        $linkUrl = trim((string)($payload['link_url'] ?? ($payload['link'] ?? '')));
        $startTime = trim((string)($payload['start_time'] ?? ''));
        $endTime = trim((string)($payload['end_time'] ?? ''));
        $status = (int)($payload['status'] ?? 1);

        if (!$isUpdate) {
            if ($positionId <= 0) {
                throw new ValidateException('请选择广告位');
            }
            if ($title === '') {
                throw new ValidateException('广告标题不能为空');
            }
            if ($imageUrl === '') {
                throw new ValidateException('广告图片地址不能为空');
            }
            if ($linkUrl === '') {
                throw new ValidateException('广告跳转链接不能为空');
            }
            if ($startTime === '' || strtotime($startTime) === false) {
                throw new ValidateException('投放开始时间格式不正确');
            }
            if ($endTime === '' || strtotime($endTime) === false) {
                throw new ValidateException('投放结束时间格式不正确');
            }
            if (strtotime($startTime) >= strtotime($endTime)) {
                throw new ValidateException('投放结束时间必须晚于开始时间');
            }
            return [
                'position_id' => $positionId,
                'title' => $title,
                'image_url' => $imageUrl,
                'link_url' => $linkUrl,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $status === 1 ? 1 : 0,
            ];
        }

        $data = [];
        if ($hasPositionId) {
            if ($positionId <= 0) {
                throw new ValidateException('请选择广告位');
            }
            $data['position_id'] = $positionId;
        }
        if ($hasTitle) {
            if ($title === '') {
                throw new ValidateException('广告标题不能为空');
            }
            $data['title'] = $title;
        }
        if ($hasImageUrl) {
            if ($imageUrl === '') {
                throw new ValidateException('广告图片地址不能为空');
            }
            $data['image_url'] = $imageUrl;
        }
        if ($hasLinkUrl) {
            if ($linkUrl === '') {
                throw new ValidateException('广告跳转链接不能为空');
            }
            $data['link_url'] = $linkUrl;
        }
        if ($hasStartTime) {
            if ($startTime === '' || strtotime($startTime) === false) {
                throw new ValidateException('投放开始时间格式不正确');
            }
            $data['start_time'] = $startTime;
        }
        if ($hasEndTime) {
            if ($endTime === '' || strtotime($endTime) === false) {
                throw new ValidateException('投放结束时间格式不正确');
            }
            $data['end_time'] = $endTime;
        }
        if (isset($data['start_time']) && isset($data['end_time']) && strtotime($data['start_time']) >= strtotime($data['end_time'])) {
            throw new ValidateException('投放结束时间必须晚于开始时间');
        }
        if ($hasStatus) {
            $data['status'] = $status === 1 ? 1 : 0;
        }
        return $data;
    }
}
