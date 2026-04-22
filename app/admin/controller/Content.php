<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\ContentAdminService;
use think\exception\ValidateException;

/**
 * 管理端内容管理接口（短剧/小说/分类/标签/审核）
 */
class Content extends BaseAdminController
{
    private const CONTENT_DRAMA = 'drama';
    private const CONTENT_NOVEL = 'novel';
    private const TYPE_DRAMA = 1;
    private const TYPE_NOVEL = 2;

    protected ContentAdminService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new ContentAdminService();
    }

    /**
     * 分页查询短剧列表
     */
    public function dramaList()
    {
        return $this->listByTable('drama');
    }

    /**
     * 新增短剧
     */
    public function dramaCreate()
    {
        return $this->createByTable('drama');
    }

    /**
     * 更新短剧
     */
    public function dramaUpdate(int $id)
    {
        return $this->updateByTable('drama', $id);
    }

    /**
     * 删除短剧
     */
    public function dramaDelete(int $id)
    {
        return $this->deleteByTable('drama', $id);
    }

    /**
     * 分页查询短剧剧集列表
     */
    public function episodeList(int $drama_id)
    {
        return $this->listByTable('drama_episode', ['drama_id' => $drama_id], 'episode_number');
    }

    /**
     * 新增短剧剧集
     */
    public function episodeCreate()
    {
        return $this->createByTable('drama_episode');
    }

    /**
     * 更新短剧剧集
     */
    public function episodeUpdate(int $id)
    {
        return $this->updateByTable('drama_episode', $id);
    }

    /**
     * 删除短剧剧集
     */
    public function episodeDelete(int $id)
    {
        return $this->deleteByTable('drama_episode', $id);
    }

    /**
     * 分页查询小说列表
     */
    public function novelList()
    {
        return $this->listByTable('novel');
    }

    /**
     * 新增小说
     */
    public function novelCreate()
    {
        return $this->createByTable('novel');
    }

    /**
     * 更新小说
     */
    public function novelUpdate(int $id)
    {
        return $this->updateByTable('novel', $id);
    }

    /**
     * 删除小说
     */
    public function novelDelete(int $id)
    {
        return $this->deleteByTable('novel', $id);
    }

    /**
     * 分页查询小说章节列表
     */
    public function chapterList(int $novel_id)
    {
        return $this->listByTable('novel_chapter', ['novel_id' => $novel_id], 'chapter_number');
    }

    /**
     * 新增小说章节
     */
    public function chapterCreate()
    {
        return $this->createByTable('novel_chapter');
    }

    /**
     * 更新小说章节
     */
    public function chapterUpdate(int $id)
    {
        return $this->updateByTable('novel_chapter', $id);
    }

    /**
     * 删除小说章节
     */
    public function chapterDelete(int $id)
    {
        return $this->deleteByTable('novel_chapter', $id);
    }

    /**
     * 分页查询分类列表
     */
    public function categoryList()
    {
        return $this->listByTable('category');
    }

    /**
     * 分页查询短剧分类列表
     */
    public function dramaCategoryList()
    {
        return $this->listByTable('category', ['type' => self::TYPE_DRAMA]);
    }

    /**
     * 分页查询小说分类列表
     */
    public function novelCategoryList()
    {
        return $this->listByTable('category', ['type' => self::TYPE_NOVEL]);
    }

    /**
     * 新增分类
     */
    public function categoryCreate()
    {
        return $this->createByTable('category');
    }

    /**
     * 新增短剧分类
     */
    public function dramaCategoryCreate()
    {
        return $this->createByTableScoped('category', ['type' => self::TYPE_DRAMA]);
    }

    /**
     * 新增小说分类
     */
    public function novelCategoryCreate()
    {
        return $this->createByTableScoped('category', ['type' => self::TYPE_NOVEL]);
    }

    /**
     * 更新分类
     */
    public function categoryUpdate(int $id)
    {
        return $this->updateByTable('category', $id);
    }

    /**
     * 更新短剧分类
     */
    public function dramaCategoryUpdate(int $id)
    {
        return $this->updateByTableScoped('category', $id, ['type' => self::TYPE_DRAMA]);
    }

    /**
     * 更新小说分类
     */
    public function novelCategoryUpdate(int $id)
    {
        return $this->updateByTableScoped('category', $id, ['type' => self::TYPE_NOVEL]);
    }

    /**
     * 删除分类
     */
    public function categoryDelete(int $id)
    {
        return $this->deleteByTable('category', $id);
    }

    /**
     * 删除短剧分类
     */
    public function dramaCategoryDelete(int $id)
    {
        return $this->deleteByTableScoped('category', $id, ['type' => self::TYPE_DRAMA]);
    }

    /**
     * 删除小说分类
     */
    public function novelCategoryDelete(int $id)
    {
        return $this->deleteByTableScoped('category', $id, ['type' => self::TYPE_NOVEL]);
    }

    /**
     * 分页查询标签列表
     */
    public function tagList()
    {
        return $this->listByTable('tag');
    }

    /**
     * 分页查询短剧标签列表
     */
    public function dramaTagList()
    {
        return $this->listByTable('tag', ['type' => self::TYPE_DRAMA]);
    }

    /**
     * 分页查询小说标签列表
     */
    public function novelTagList()
    {
        return $this->listByTable('tag', ['type' => self::TYPE_NOVEL]);
    }

    /**
     * 新增标签
     */
    public function tagCreate()
    {
        return $this->createByTable('tag');
    }

    /**
     * 新增短剧标签
     */
    public function dramaTagCreate()
    {
        return $this->createByTableScoped('tag', ['type' => self::TYPE_DRAMA]);
    }

    /**
     * 新增小说标签
     */
    public function novelTagCreate()
    {
        return $this->createByTableScoped('tag', ['type' => self::TYPE_NOVEL]);
    }

    /**
     * 更新标签
     */
    public function tagUpdate(int $id)
    {
        return $this->updateByTable('tag', $id);
    }

    /**
     * 更新短剧标签
     */
    public function dramaTagUpdate(int $id)
    {
        return $this->updateByTableScoped('tag', $id, ['type' => self::TYPE_DRAMA]);
    }

    /**
     * 更新小说标签
     */
    public function novelTagUpdate(int $id)
    {
        return $this->updateByTableScoped('tag', $id, ['type' => self::TYPE_NOVEL]);
    }

    /**
     * 删除标签
     */
    public function tagDelete(int $id)
    {
        return $this->deleteByTable('tag', $id);
    }

    /**
     * 删除短剧标签
     */
    public function dramaTagDelete(int $id)
    {
        return $this->deleteByTableScoped('tag', $id, ['type' => self::TYPE_DRAMA]);
    }

    /**
     * 删除小说标签
     */
    public function novelTagDelete(int $id)
    {
        return $this->deleteByTableScoped('tag', $id, ['type' => self::TYPE_NOVEL]);
    }

    /**
     * 通用内容审核入口
     */
    public function audit()
    {
        try {
            $payload = $this->request->post();
            $this->validateOrFail(\app\admin\validate\ContentAuditValidate::class, $payload);
            $this->service->audit($payload);
            return $this->success([], '审核成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 短剧审核接口
     */
    public function dramaAudit()
    {
        return $this->auditByType(self::CONTENT_DRAMA);
    }

    /**
     * 小说审核接口
     */
    public function novelAudit()
    {
        return $this->auditByType(self::CONTENT_NOVEL);
    }

    private function listByTable(string $table, array $fixedWhere = [], string $orderField = 'id')
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->listByTable($table, $this->request->param(), $page, $pageSize, $fixedWhere, $orderField);
            return $this->success($result, '获取成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    private function createByTable(string $table)
    {
        try {
            $id = $this->service->createByTable($table, $this->request->post());
            return $this->success(['id' => (int)$id], '创建成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    private function createByTableScoped(string $table, array $fixedWhere)
    {
        try {
            $id = $this->service->createByTableScoped($table, $this->request->post(), $fixedWhere);
            return $this->success(['id' => (int)$id], '创建成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    private function updateByTable(string $table, int $id)
    {
        try {
            $this->service->updateByTable($table, $id, $this->requestPayload());
            return $this->success([], '更新成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    private function updateByTableScoped(string $table, int $id, array $fixedWhere)
    {
        try {
            $this->service->updateByTableScoped($table, $id, $this->requestPayload(), $fixedWhere);
            return $this->success([], '更新成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    private function deleteByTable(string $table, int $id)
    {
        try {
            $this->service->deleteByTable($table, $id);
            return $this->success([], '删除成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 404, self::BIZ_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    private function deleteByTableScoped(string $table, int $id, array $fixedWhere)
    {
        try {
            $this->service->deleteByTableScoped($table, $id, $fixedWhere);
            return $this->success([], '删除成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 404, self::BIZ_NOT_FOUND);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    private function auditByType(string $contentType)
    {
        try {
            $payload = $this->request->post();
            $payload['content_type'] = $contentType;
            $this->validateOrFail(\app\admin\validate\ContentAuditValidate::class, $payload);
            $this->service->auditByType($contentType, $payload);
            return $this->success([], '审核成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
