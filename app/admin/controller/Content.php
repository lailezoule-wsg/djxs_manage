<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\ContentAdminService;
use think\exception\ValidateException;

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

    public function dramaList()
    {
        return $this->listByTable('drama');
    }

    public function dramaCreate()
    {
        return $this->createByTable('drama');
    }

    public function dramaUpdate(int $id)
    {
        return $this->updateByTable('drama', $id);
    }

    public function dramaDelete(int $id)
    {
        return $this->deleteByTable('drama', $id);
    }

    public function episodeList(int $drama_id)
    {
        return $this->listByTable('drama_episode', ['drama_id' => $drama_id], 'episode_number');
    }

    public function episodeCreate()
    {
        return $this->createByTable('drama_episode');
    }

    public function episodeUpdate(int $id)
    {
        return $this->updateByTable('drama_episode', $id);
    }

    public function episodeDelete(int $id)
    {
        return $this->deleteByTable('drama_episode', $id);
    }

    public function novelList()
    {
        return $this->listByTable('novel');
    }

    public function novelCreate()
    {
        return $this->createByTable('novel');
    }

    public function novelUpdate(int $id)
    {
        return $this->updateByTable('novel', $id);
    }

    public function novelDelete(int $id)
    {
        return $this->deleteByTable('novel', $id);
    }

    public function chapterList(int $novel_id)
    {
        return $this->listByTable('novel_chapter', ['novel_id' => $novel_id], 'chapter_number');
    }

    public function chapterCreate()
    {
        return $this->createByTable('novel_chapter');
    }

    public function chapterUpdate(int $id)
    {
        return $this->updateByTable('novel_chapter', $id);
    }

    public function chapterDelete(int $id)
    {
        return $this->deleteByTable('novel_chapter', $id);
    }

    public function categoryList()
    {
        return $this->listByTable('category');
    }

    public function dramaCategoryList()
    {
        return $this->listByTable('category', ['type' => self::TYPE_DRAMA]);
    }

    public function novelCategoryList()
    {
        return $this->listByTable('category', ['type' => self::TYPE_NOVEL]);
    }

    public function categoryCreate()
    {
        return $this->createByTable('category');
    }

    public function dramaCategoryCreate()
    {
        return $this->createByTableScoped('category', ['type' => self::TYPE_DRAMA]);
    }

    public function novelCategoryCreate()
    {
        return $this->createByTableScoped('category', ['type' => self::TYPE_NOVEL]);
    }

    public function categoryUpdate(int $id)
    {
        return $this->updateByTable('category', $id);
    }

    public function dramaCategoryUpdate(int $id)
    {
        return $this->updateByTableScoped('category', $id, ['type' => self::TYPE_DRAMA]);
    }

    public function novelCategoryUpdate(int $id)
    {
        return $this->updateByTableScoped('category', $id, ['type' => self::TYPE_NOVEL]);
    }

    public function categoryDelete(int $id)
    {
        return $this->deleteByTable('category', $id);
    }

    public function dramaCategoryDelete(int $id)
    {
        return $this->deleteByTableScoped('category', $id, ['type' => self::TYPE_DRAMA]);
    }

    public function novelCategoryDelete(int $id)
    {
        return $this->deleteByTableScoped('category', $id, ['type' => self::TYPE_NOVEL]);
    }

    public function tagList()
    {
        return $this->listByTable('tag');
    }

    public function dramaTagList()
    {
        return $this->listByTable('tag', ['type' => self::TYPE_DRAMA]);
    }

    public function novelTagList()
    {
        return $this->listByTable('tag', ['type' => self::TYPE_NOVEL]);
    }

    public function tagCreate()
    {
        return $this->createByTable('tag');
    }

    public function dramaTagCreate()
    {
        return $this->createByTableScoped('tag', ['type' => self::TYPE_DRAMA]);
    }

    public function novelTagCreate()
    {
        return $this->createByTableScoped('tag', ['type' => self::TYPE_NOVEL]);
    }

    public function tagUpdate(int $id)
    {
        return $this->updateByTable('tag', $id);
    }

    public function dramaTagUpdate(int $id)
    {
        return $this->updateByTableScoped('tag', $id, ['type' => self::TYPE_DRAMA]);
    }

    public function novelTagUpdate(int $id)
    {
        return $this->updateByTableScoped('tag', $id, ['type' => self::TYPE_NOVEL]);
    }

    public function tagDelete(int $id)
    {
        return $this->deleteByTable('tag', $id);
    }

    public function dramaTagDelete(int $id)
    {
        return $this->deleteByTableScoped('tag', $id, ['type' => self::TYPE_DRAMA]);
    }

    public function novelTagDelete(int $id)
    {
        return $this->deleteByTableScoped('tag', $id, ['type' => self::TYPE_NOVEL]);
    }

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

    public function dramaAudit()
    {
        return $this->auditByType(self::CONTENT_DRAMA);
    }

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
