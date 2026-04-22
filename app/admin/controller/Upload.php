<?php
declare (strict_types = 1);

namespace app\admin\controller;

use think\facade\Filesystem;

/**
 * 管理端文件上传接口
 */
class Upload extends BaseAdminController
{
    /**
     * 上传新闻内容图片
     */
    public function image()
    {
        try {
            $file = $this->request->file('file');
            if (!$file) {
                return $this->error('请选择要上传的图片', 400, self::BIZ_INVALID_PARAMS);
            }

            $rule = [
                'file' => 'fileSize:5242880|fileExt:jpg,jpeg,png,webp,gif',
            ];
            $msg = [
                'file.fileSize' => '文件大小不能超过5MB',
                'file.fileExt' => '文件类型不允许（仅支持 jpg/jpeg/png/webp/gif）',
            ];
            validate($rule, $msg)->check(['file' => $file]);

            $savename = Filesystem::disk('public')->putFile('news', $file, function () {
                return md5(time() . mt_rand(1000, 9999) . uniqid());
            });
            if (!$savename) {
                return $this->error('上传失败', 400, self::BIZ_INVALID_PARAMS);
            }

            return $this->success([
                'url' => '/storage/' . $savename,
                'filename' => $savename,
            ], '上传成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
