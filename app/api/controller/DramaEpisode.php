<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use app\api\service\DramaEpisodeService;
use think\App;

/**
 * 短剧剧集控制器
 */
class DramaEpisode extends BaseApiController
{
    protected $episodeService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->episodeService = new DramaEpisodeService();
    }

    /**
     * 获取剧集内容
     */
    public function getEpisode($id, $episodeNum)
    {
        try {
            $userId = $this->getUserId();
            $episode = $this->episodeService->getEpisode((int)$id, (int)$episodeNum, $userId);

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $episode,
            ]);
        } catch (\think\exception\ValidateException $e) {
            $msg = $e->getMessage();
            // 与 axios 约定：401 仅表示登录态问题；未购买/无观看权限用 403，避免被全局 401 拦截清 token
            $http = str_contains($msg, '登录') ? 401 : 403;

            return json(['code' => $http, 'msg' => $msg], $http);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * 获取剧集列表
     */
    public function getEpisodeList($id)
    {
        try {
            $userId = $this->getUserId();
            $episodes = $this->episodeService->getEpisodeList((int)$id, $userId);

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $episodes,
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * 单集购买前商品信息（不含视频地址）
     */
    public function purchasePreview(int $episodeId)
    {
        try {
            $data = $this->episodeService->getEpisodePurchasePreview($episodeId);

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
