<?php
declare (strict_types = 1);

namespace app\common\controller;

use app\BaseController;
use app\common\exception\BizException;
use think\exception\ValidateException;
use Throwable;

/**
 * 公共API基础控制器
 */
class BaseApiController extends BaseController
{
    /**
     * 业务码定义
     */
    public const BIZ_OK = 0;
    public const BIZ_INVALID_PARAMS = 40001;
    public const BIZ_UNAUTHORIZED = 40101;
    public const BIZ_NOT_FOUND = 40401;
    public const BIZ_CONFLICT = 40901;
    public const BIZ_SERVER_ERROR = 50001;

    /**
     * 统一成功响应
     */
    protected function success($data = [], string $msg = '操作成功', int $bizCode = self::BIZ_OK)
    {
        return json([
            'code' => 200,
            'biz_code' => $bizCode,
            'msg'  => $msg,
            'data' => $data,
        ]);
    }

    /**
     * 统一错误响应
     */
    protected function error(string $msg = '操作失败', int $httpCode = 500, int $bizCode = self::BIZ_SERVER_ERROR, array $data = [])
    {
        return json([
            'code' => $httpCode,
            'biz_code' => $bizCode,
            'msg'  => $msg,
            'data' => $data,
        ], $httpCode);
    }

    /**
     * 将异常映射为统一错误响应
     */
    protected function failByException(Throwable $e)
    {
        if ($e instanceof BizException) {
            return $this->error($e->getMessage(), $e->getHttpCode(), $e->getBizCode(), $e->getData());
        }
        if ($e instanceof ValidateException) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        }
        return $this->error($e->getMessage(), 500, self::BIZ_SERVER_ERROR);
    }

    /**
     * 获取当前登录用户ID
     */
    protected function getUserId()
    {
        $userId = $this->request->user['id'] ?? $this->request->userId ?? null;

        if (!$userId) {
            return $this->error('用户未登录或登录已过期', 401, self::BIZ_UNAUTHORIZED);
        }

        return $userId;
    }
}
