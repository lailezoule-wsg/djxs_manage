# 秒杀限流参数说明

本文档用于说明秒杀相关限流参数的含义、推荐值与调参顺序。

## 1. 下单接口限流（`/api/flash-sale/order/create`）

- `FLASH_SALE_CREATE_USER_LIMIT_PER_MIN`：单用户每分钟允许提交下单次数
- `FLASH_SALE_CREATE_DEVICE_LIMIT_PER_MIN`：单设备每分钟允许提交下单次数
- `FLASH_SALE_CREATE_IP_LIMIT_PER_MIN`：单 IP 每分钟允许提交下单次数

### 推荐思路

- 用户维度限制最严格（防账号脚本）
- 设备维度次之（防多账号同设备轮询）
- IP 维度最宽（避免 NAT 网络误伤）

## 2. 令牌接口限流（`/api/flash-sale/token`）

- `FLASH_SALE_TOKEN_USER_LIMIT_PER_MIN`：单用户每分钟允许领取 token 次数
- `FLASH_SALE_TOKEN_DEVICE_LIMIT_PER_MIN`：单设备每分钟允许领取 token 次数
- `FLASH_SALE_TOKEN_IP_LIMIT_PER_MIN`：单 IP 每分钟允许领取 token 次数

### 推荐思路

- token 限流应整体高于 create 限流（通常 1.1~1.5 倍）
- 若 token 接口命中限流明显高于 create，说明存在“只领 token 不下单”的异常流量

## 3. 调参顺序（线上建议）

1. 先观察风险日志 `flash_sale_risk_log` 中的 `*_rate_limit` 命中比例
2. 先调整 `IP` 限制，避免大面积误伤
3. 再调整 `DEVICE` 限制，控制机器刷量
4. 最后调整 `USER` 限制，平衡真实用户重试体验

## 4. 与请求参数的配套要求

- `request_id` 必须满足：
  - 长度 8~64
  - 字符集仅允许 `A-Za-z0-9_-`
- 建议前端统一使用 UUID（去掉 `-` 或保留 `-` 均可）

## 5. 默认值参考（当前仓库）

- `CREATE_*_LIMIT_PER_MIN = 100000`
- `TOKEN_*_LIMIT_PER_MIN = 120000`

> 注意：以上默认值偏“放开”，更适合联调/压测环境。正式生产建议结合活动峰值与历史风控数据下调。

## 6. 生产三档建议值（可直接落 `.env`）

以下三档以“活动峰值流量 + 风险容忍度”做经验分层，单位均为每分钟请求数。

| 档位 | 适用规模 | CREATE_USER | CREATE_DEVICE | CREATE_IP | TOKEN_USER | TOKEN_DEVICE | TOKEN_IP |
|---|---|---:|---:|---:|---:|---:|---:|
| S（约 1k QPS） | 小型活动/单场压测 | 30 | 60 | 180 | 45 | 90 | 270 |
| M（约 5k QPS） | 常规大促/多渠道导流 | 50 | 100 | 300 | 75 | 150 | 450 |
| L（约 10k QPS） | 头部活动/短时爆发 | 80 | 160 | 480 | 120 | 240 | 720 |

### 6.1 S 档（约 1k QPS）配置块

```env
# flash sale create limits
FLASH_SALE_CREATE_USER_LIMIT_PER_MIN = 30
FLASH_SALE_CREATE_DEVICE_LIMIT_PER_MIN = 60
FLASH_SALE_CREATE_IP_LIMIT_PER_MIN = 180

# flash sale token limits
FLASH_SALE_TOKEN_USER_LIMIT_PER_MIN = 45
FLASH_SALE_TOKEN_DEVICE_LIMIT_PER_MIN = 90
FLASH_SALE_TOKEN_IP_LIMIT_PER_MIN = 270
```

### 6.2 M 档（约 5k QPS）配置块

```env
# flash sale create limits
FLASH_SALE_CREATE_USER_LIMIT_PER_MIN = 50
FLASH_SALE_CREATE_DEVICE_LIMIT_PER_MIN = 100
FLASH_SALE_CREATE_IP_LIMIT_PER_MIN = 300

# flash sale token limits
FLASH_SALE_TOKEN_USER_LIMIT_PER_MIN = 75
FLASH_SALE_TOKEN_DEVICE_LIMIT_PER_MIN = 150
FLASH_SALE_TOKEN_IP_LIMIT_PER_MIN = 450
```

### 6.3 L 档（约 10k QPS）配置块

```env
# flash sale create limits
FLASH_SALE_CREATE_USER_LIMIT_PER_MIN = 80
FLASH_SALE_CREATE_DEVICE_LIMIT_PER_MIN = 160
FLASH_SALE_CREATE_IP_LIMIT_PER_MIN = 480

# flash sale token limits
FLASH_SALE_TOKEN_USER_LIMIT_PER_MIN = 120
FLASH_SALE_TOKEN_DEVICE_LIMIT_PER_MIN = 240
FLASH_SALE_TOKEN_IP_LIMIT_PER_MIN = 720
```

## 7. 灰度上线建议

- 首次上线建议从 **S 档** 起步，观察 15~30 分钟风控命中率后再放大。
- 若 `token_*_rate_limit` 命中显著高于 `create_*_rate_limit`，优先下调 token 档位。
- 若大量正常用户被误伤，先放宽 `IP`，再放宽 `DEVICE`，最后才放宽 `USER`。
- 活动前 1 小时不要同时大幅调高三类阈值，建议每次仅调一类并观测 5 分钟。

## 8. 风控日志采样建议

- `FLASH_SALE_RISK_LOG_SAMPLE_PERCENT`：风控日志采样率（0~100）
- `100` 表示全量记录，`20` 表示约 20% 采样记录
- 黑名单拦截（`blacklist_*`）默认始终全量记录，不受采样影响
- 压测或攻击流量期间可临时调低到 `10~30`，待稳定后恢复

## 9. request_id 强校验建议

- `FLASH_SALE_REQUEST_ID_STRICT`：是否开启严格校验（建议生产保持 `1`）
- `FLASH_SALE_REQUEST_ID_MIN_LENGTH`：严格模式下最小长度（建议 `12~16`）
- `FLASH_SALE_REQUEST_ID_MAX_AGE_SECONDS`：request_id 有效期（建议 `900~1800` 秒）
- `FLASH_SALE_REQUEST_LOCK_TTL_SECONDS`：request_id 互斥锁 TTL（建议 `60~180` 秒）
- `FLASH_SALE_USER_ITEM_LOCK_TTL_SECONDS`：用户商品互斥锁 TTL（建议 `40~90` 秒）

### 当前服务端校验规则

- 基础规则：长度 `8~64`，仅允许 `A-Za-z0-9_-`
- 严格模式附加：
  - 长度需达到 `FLASH_SALE_REQUEST_ID_MIN_LENGTH`
  - 至少包含 2 类字符（大写/小写/数字/`_-`）
  - 去重后字符数至少 6
- 新建请求会记录首次出现时间，超过 `FLASH_SALE_REQUEST_ID_MAX_AGE_SECONDS` 后拒绝受理（防重放）
