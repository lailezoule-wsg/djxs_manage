<?php
declare (strict_types = 1);

namespace app\api\controller;

use app\common\controller\BaseApiController;
use app\api\service\UserService;
use app\api\service\JwtService;
use think\App;

/**
 * 用户控制器
 */
class User extends BaseApiController
{
    protected $userService;
    protected $jwtService;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->userService = new UserService();
        $this->jwtService = new JwtService();
    }

    /**
     * 用户注册
     */
    public function register()
    {
        try {
            $data = $this->request->post();

            $user = $this->userService->register($data);

            // 生成 JWT token
            $token = $this->jwtService->generateToken($user);

            return $this->success(['token' => $token], '注册成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 用户登录
     */
    public function login()
    {
        try {
            $data = $this->request->post();
            $user = $this->userService->login($data);

            // 生成 JWT token
            $token = $this->jwtService->generateToken($user);

            return $this->success(['token' => $token], '登录成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 第三方登录（provider + openid）
     */
    public function oauthLogin()
    {
        try {
            $data = $this->request->post();
            $user = $this->userService->oauthLogin($data);
            $token = $this->jwtService->generateToken($user);

            return $this->success(['token' => $token], '登录成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 获取用户信息
     */
    public function info()
    {
        try {
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $userInfo = $this->userService->info($userId);

            return $this->success($userInfo, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 更新用户信息
     */
    public function updateInfo()
    {
        try {
            $data = $this->request->put();
            
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $userInfo = $this->userService->updateInfo($userId, $data);

            return $this->success($userInfo, '更新成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 修改密码
     */
    public function changePassword()
    {
        try {
            $data = $this->request->put();
            
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $this->userService->changePassword($userId, $data);

            return $this->success([], '密码修改成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 绑定设备
     */
    public function bindDevice()
    {
        try {
            $data = $this->request->post();
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $result = $this->userService->bindDevice((int)$userId, $data);
            return $this->success($result, '绑定成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 设备列表
     */
    public function deviceList()
    {
        try {
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $result = $this->userService->deviceList((int)$userId);
            return $this->success($result, '获取成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }

    /**
     * 解绑设备
     */
    public function unbindDevice(int $id)
    {
        try {
            $userId = $this->getUserId();
            if ($userId instanceof \think\Response) {
                return $userId;
            }

            $this->userService->unbindDevice((int)$userId, $id);
            return $this->success([], '解绑成功');
        } catch (\Throwable $e) {
            return $this->failByException($e);
        }
    }
}
