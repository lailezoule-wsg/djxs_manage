<?php
declare (strict_types = 1);

namespace app\admin\controller;

use app\common\controller\BaseApiController;
use think\exception\ValidateException;

class BaseAdminController extends BaseApiController
{
    protected function pageParams(): array
    {
        $raw = [
            'page' => (int)$this->request->param('page', 1),
            'page_size' => (int)$this->request->param('page_size', $this->request->param('limit', 20)),
        ];
        $this->validateOrFail(\app\admin\validate\PaginationValidate::class, $raw);
        $page = max(1, $raw['page']);
        $pageSize = max(1, min($raw['page_size'], 100));
        return [$page, $pageSize];
    }

    protected function requestPayload(): array
    {
        $payload = $this->request->put();
        if (empty($payload)) {
            $payload = $this->request->post();
        }
        return is_array($payload) ? $payload : [];
    }

    protected function validateOrFail(string $validatorClass, array $data, string $scene = ''): void
    {
        $validator = new $validatorClass();
        $ok = $scene === '' ? $validator->check($data) : $validator->scene($scene)->check($data);
        if (!$ok) {
            throw new ValidateException($validator->getError());
        }
    }
}
