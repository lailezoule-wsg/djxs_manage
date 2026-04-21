<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use think\exception\ValidateException;
use think\facade\Filesystem;

class Upload extends BaseApiController
{
    public function image()
    {
        try {
            $file = $this->request->file('file');

            if (!$file) {
                return json(['code' => 400, 'msg' => '请选择要上传的图片'], 400);
            }

            $rule = [
                'file' => 'fileSize:5242880|fileExt:jpg,jpeg,png'
            ];
            
            $msg = [
                'file.fileSize' => '文件大小不能超过5MB',
                'file.fileExt' => '文件类型不允许'
            ];
            
            try {
                validate($rule, $msg)->check(['file' => $file]);
            } catch (\Exception $e) {
                return json(['code' => 400, 'msg' => $e->getMessage()], 400);
            }

            $savename = Filesystem::disk('public')->putFile('avatar', $file, function () {
                return md5(time() . mt_rand(1000, 9999) . uniqid());
            });

            if ($savename) {
                $url = '/storage/' . $savename;
                return json([
                    'code' => 200,
                    'msg'  => '上传成功',
                    'data' => [
                        'url' => $url,
                        'filename' => $savename
                    ]
                ]);
            }

            return json(['code' => 400, 'msg' => '上传失败'], 400);

        } catch (ValidateException $e) {
            return json(['code' => 400, 'msg' => $e->getError()], 400);
        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => $e->getMessage()], 500);
        }
    }
}
