<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use app\api\service\NovelChapterService;
use think\App;

/**
 * 小说章节控制器
 */
class NovelChapter extends BaseApiController
{
    protected $chapterService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->chapterService = new NovelChapterService();
    }

    /**
     * 获取章节内容
     */
    public function getChapter($id, $chapterNum)
    {
        try {
            $userId = $this->getUserId();
            $chapter = $this->chapterService->getChapter((int)$id, (int)$chapterNum, $userId);

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $chapter,
            ]);
        } catch (\think\exception\ValidateException $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, '登录')) {
                $http = 401;
            } elseif (str_contains($msg, '购买')) {
                $http = 403;
            } else {
                $http = 400;
            }

            return json(['code' => $http, 'msg' => $msg], $http);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * 获取章节列表
     */
    public function getChapterList($id)
    {
        try {
            $userId = $this->getUserId();
            $chapters = $this->chapterService->getChapterList((int)$id, $userId);

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $chapters,
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * 单章购买前商品信息（不含正文）
     */
    public function purchasePreview(int $chapterId)
    {
        try {
            $data = $this->chapterService->getChapterPurchasePreview($chapterId);

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $data,
            ]);
        } catch (\think\exception\ValidateException $e) {
            return json(['code' => 400, 'msg' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }
}
