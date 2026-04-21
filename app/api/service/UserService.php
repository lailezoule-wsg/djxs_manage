<?php
declare (strict_types = 1);

namespace app\api\service;

use app\api\model\Device;
use app\api\model\Distribution;
use app\api\model\User;
use app\api\validate\User as UserValidate;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 用户服务层
 */
class UserService
{
    /**
     * 第三方登录（简化版）
     * 说明：当前未引入单独 oauth 绑定表，使用 provider+openid 生成稳定账号标识。
     */
    public function oauthLogin(array $data)
    {
        $provider = strtolower(trim((string)($data['provider'] ?? '')));
        $openid = trim((string)($data['openid'] ?? ''));
        $nickname = trim((string)($data['nickname'] ?? ''));
        $avatar = trim((string)($data['avatar'] ?? ''));
        $mobile = trim((string)($data['mobile'] ?? ''));

        if ($provider === '' || $openid === '') {
            throw new ValidateException('provider和openid不能为空');
        }

        if ($mobile === '') {
            $mobile = $this->buildVirtualMobile($provider, $openid);
        }

        $user = User::where('mobile', $mobile)->find();
        if ($user) {
            if ($nickname !== '') {
                $user->nickname = $nickname;
            }
            if ($avatar !== '') {
                $user->avatar = $avatar;
            }
            $user->last_login_time = date('Y-m-d H:i:s');
            $user->save();
            return $user;
        }

        $usernameSeed = substr(md5($provider . ':' . $openid), 0, 10);
        $username = 'oauth_' . $usernameSeed;
        if (User::where('username', $username)->find()) {
            $username .= '_' . substr((string)microtime(true), -4);
        }

        $user = Db::transaction(function () use ($mobile, $username, $nickname, $avatar) {
            $created = User::create([
                'mobile' => $mobile,
                'username' => $username,
                'password' => bin2hex(random_bytes(8)),
                'nickname' => $nickname !== '' ? $nickname : '第三方用户',
                'avatar' => $avatar,
                'last_login_time' => date('Y-m-d H:i:s'),
            ]);
            $this->ensureDistributionProfile((int)$created->id, 0);
            return $created;
        });

        return $user;
    }

    /**
     * 用户注册
     */
    public function register($data)
    {
        // 验证数据
        $validate = new UserValidate();
        if (!$validate->scene('register')->check($data)) {
            throw new ValidateException($validate->getError());
        }

        // 检查手机号是否已注册
        $user = User::where('mobile', $data['mobile'])->find();
        if ($user) {
            throw new ValidateException('该手机号已注册');
        }

        $promotionCode = trim((string)($data['promotion_code'] ?? ''));
        $parentUserId = 0;
        if ($promotionCode !== '') {
            $parentUserId = (int)Distribution::where('promotion_code', $promotionCode)->value('user_id');
            if ($parentUserId <= 0) {
                throw new ValidateException('推广码无效');
            }
        }

        $user = Db::transaction(function () use ($data, $parentUserId) {
            $created = User::create([
                'mobile'   => $data['mobile'],
                'password' => $data['password'],
                'username' => $data['username'],
                'nickname' => $data['nickname']
            ]);

            // 新用户注册后立即建分销档案，若带推广码则绑定上级
            $this->ensureDistributionProfile((int)$created->id, $parentUserId);
            return $created;
        });

        return $user;
    }

    /**
     * 用户登录
     */
    public function login($data)
    {
        // 验证数据
        $validate = new UserValidate();
        if (!$validate->scene('login')->check($data)) {
            throw new ValidateException($validate->getError());
        }

        // 查找用户
        $user = User::where('mobile', $data['mobile'])->find();
        if (!$user) {
            throw new ValidateException('手机号或密码错误');
        }

        // 验证密码
        if (!$user->checkPassword($data['password'])) {
            throw new ValidateException('手机号或密码错误');
        }

        // 更新最后登录时间
        $user->last_login_time = date('Y-m-d H:i:s');
        $user->save();

        return $user;
    }

    /**
     * 获取用户信息
     */
    public function info($userId)
    {
        $user = User::find($userId);
        if (!$user) {
            throw new ValidateException('用户不存在');
        }

        return $user->toArray();
    }

    /**
     * 更新用户信息
     */
    public function updateInfo($userId, $data)
    {
        // 查找用户
        $user = User::find($userId);
        if (!$user) {
            throw new ValidateException('用户不存在');
        }

        // 如果要修改手机号，检查唯一性
        if (isset($data['mobile']) && $data['mobile'] !== $user->mobile) {
            $existingUser = User::where('mobile', $data['mobile'])->find();
            if ($existingUser) {
                throw new ValidateException('该手机号已被其他用户使用');
            }
        }

        // 更新允许的字段（不允许修改username）
        $allowedFields = ['nickname', 'avatar', 'gender', 'birthday', 'mobile'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $user->$field = $data[$field];
            }
        }

        $user->save();

        return $user->toArray();
    }

    /**
     * 修改密码
     */
    public function changePassword($userId, $data)
    {
        // 验证数据
        $validate = new UserValidate();
        if (!$validate->scene('change_password')->check($data)) {
            throw new ValidateException($validate->getError());
        }

        // 查找用户
        $user = User::find($userId);
        if (!$user) {
            throw new ValidateException('用户不存在');
        }

        // 验证旧密码
        if (!$user->checkPassword($data['old_password'])) {
            throw new ValidateException('旧密码错误');
        }

        // 更新密码
        $user->password = $data['new_password'];
        $user->save();

        return true;
    }

    /**
     * 绑定设备
     */
    public function bindDevice(int $userId, array $data): array
    {
        $deviceId = trim((string)($data['device_id'] ?? ''));
        if ($deviceId === '') {
            throw new ValidateException('device_id不能为空');
        }

        $deviceType = trim((string)($data['device_type'] ?? 'unknown'));
        $deviceModel = trim((string)($data['device_model'] ?? ''));
        $osVersion = trim((string)($data['os_version'] ?? ''));

        $device = Device::where('device_id', $deviceId)->find();
        if ($device) {
            if ((int)$device->user_id !== $userId) {
                throw new ValidateException('该设备已绑定其他账号');
            }

            $device->device_type = $deviceType;
            $device->device_model = $deviceModel;
            $device->os_version = $osVersion;
            $device->bind_time = date('Y-m-d H:i:s');
            $device->save();
        } else {
            $device = Device::create([
                'user_id' => $userId,
                'device_id' => $deviceId,
                'device_type' => $deviceType,
                'device_model' => $deviceModel,
                'os_version' => $osVersion,
                'bind_time' => date('Y-m-d H:i:s'),
            ]);
        }

        return $device->toArray();
    }

    /**
     * 设备列表
     */
    public function deviceList(int $userId): array
    {
        return Device::where('user_id', $userId)
            ->order('id', 'desc')
            ->select()
            ->toArray();
    }

    /**
     * 解绑设备
     */
    public function unbindDevice(int $userId, int $id): void
    {
        $device = Device::where('id', $id)->where('user_id', $userId)->find();
        if (!$device) {
            throw new ValidateException('设备不存在或无权限解绑');
        }

        $device->delete();
    }

    /**
     * 构造稳定虚拟手机号（11位，仅用于第三方登录占位）
     */
    private function buildVirtualMobile(string $provider, string $openid): string
    {
        $hash = sprintf('%u', crc32($provider . ':' . $openid));
        $tail = str_pad(substr($hash, -10), 10, '0', STR_PAD_LEFT);
        return '9' . $tail;
    }

    private function ensureDistributionProfile(int $userId, int $parentUserId = 0): void
    {
        if ($userId <= 0) {
            return;
        }

        $exists = Distribution::where('user_id', $userId)->find();
        if ($exists) {
            return;
        }

        $parent = $parentUserId > 0 && $parentUserId !== $userId ? $parentUserId : 0;
        Distribution::create([
            'user_id' => $userId,
            'parent_id' => $parent,
            'promotion_code' => $this->generateUniquePromotionCode(),
            'total_commission' => 0,
            'available_commission' => 0,
            'status' => 1,
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }

    private function generateUniquePromotionCode(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max = strlen($chars) - 1;
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, $max)];
            }
            $used = Distribution::where('promotion_code', $code)->count() > 0;
        } while ($used);

        return $code;
    }
}
