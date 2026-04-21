<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use app\api\service\DistributionService;
use think\App;

/**
 * 分销控制器
 */
class Distribution extends BaseApiController
{
    protected $distributionService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->distributionService = new DistributionService();
    }

    /**
     * 获取推广码
     */
    public function getCode()
    {
        try {
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $code = $this->distributionService->getCode($userId);

            return $this->success($code, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 分销记录
     */
    public function record()
    {
        try {
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $record = $this->distributionService->record($userId);

            return $this->success($record, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 佣金提现
     */
    public function withdraw()
    {
        try {
            $data = $this->request->post();
            
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $this->distributionService->withdraw($userId, $data);

            return $this->success([], '提现申请已提交');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 我的下级
     */
    public function team()
    {
        try {
            $params = $this->request->get();
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $result = $this->distributionService->team((int)$userId, $params);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
