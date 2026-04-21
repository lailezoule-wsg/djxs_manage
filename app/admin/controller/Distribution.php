<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\admin\service\DistributionAdminService;
use think\exception\ValidateException;

class Distribution extends BaseAdminController
{
    protected DistributionAdminService $service;

    public function __construct(\think\App $app)
    {
        parent::__construct($app);
        $this->service = new DistributionAdminService();
    }

    public function recordList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->recordList($page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function withdrawList()
    {
        try {
            [$page, $pageSize] = $this->pageParams();
            $result = $this->service->withdrawList($this->request->param(), $page, $pageSize);
            return $this->success($result, '获取成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function withdrawAudit(int $id)
    {
        try {
            $payload = $this->request->post();
            $this->validateOrFail(\app\admin\validate\DistributionWithdrawAuditValidate::class, $payload);
            $status = (int)($payload['status'] ?? 1);
            $remark = (string)($payload['remark'] ?? '');
            $this->service->withdrawAudit($id, $status, $remark);
            return $this->success([], '审核成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function configGet()
    {
        try {
            $value = $this->service->configGet();
            return $this->success($value, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    public function configSet()
    {
        try {
            $data = $this->requestPayload();
            $this->service->configSet($data);
            return $this->success([], '保存成功');
        } catch (ValidateException $e) {
            return $this->error($e->getMessage(), 400, self::BIZ_INVALID_PARAMS);
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
