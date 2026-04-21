<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use app\api\service\MemberService;
use think\App;

/**
 * 会员控制器
 */
class Member extends BaseApiController
{
    protected $memberService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->memberService = new MemberService();
    }

    /**
     * 会员等级列表
     */
    public function levelList()
    {
        try {
            $levels = $this->memberService->levelList();

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $levels,
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }

    /**
     * 购买会员
     */
    public function buy()
    {
        try {
            $data = $this->request->post();
            
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $result = $this->memberService->buy($userId, $data);
            return $this->success($result, '订单创建成功，请完成支付');
        } catch (\Exception $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 会员信息
     */
    public function info()
    {
        try {
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $info = $this->memberService->info($userId);

            return json([
                'code' => 200,
                'msg'  => '获取成功',
                'data' => $info,
            ]);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }
}
