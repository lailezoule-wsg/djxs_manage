# DJXS Manage (ThinkPHP 8)

`djxs_manage` 是短剧/小说平台的后端核心服务，负责 C 端 API、管理端 API、订单支付、秒杀活动、分销与异步消费。

## 1. 项目概览

- 技术栈：`PHP 8` + `ThinkPHP 8` + `MySQL 8` + `Redis 7` + `RabbitMQ 3`
- 架构模式：ThinkPHP 多应用（`api`、`admin`）
- 关键能力：
  - 用户认证（JWT）
  - 内容浏览与购买（短剧/小说）
  - 普通订单 + 支付宝支付回调
  - 秒杀令牌、排队下单、异步创建订单
  - 分销、广告、资讯、统计看板
  - 管理端 RBAC（权限/菜单/角色/管理员）

## 2. 目录结构（核心）

```text
app/djxs_manage
├── app/
│   ├── api/                 # C 端接口（/api/*）
│   │   ├── controller/
│   │   ├── service/
│   │   ├── middleware/
│   │   └── route/route.php
│   ├── admin/               # 管理端接口（/admin/*）
│   │   ├── controller/
│   │   ├── service/
│   │   ├── middleware/
│   │   └── route/route.php
│   ├── command/             # think 控制台命令
│   └── job/                 # 异步任务/消费任务
├── config/                  # 框架与业务配置
├── route/                   # 根路由（多应用入口）
├── runtime/                 # 运行日志与缓存
├── public/                  # 入口文件
└── .env                     # 环境变量
```

## 3. 接口入口与路由

### C 端 API（`/api/*`）

路由文件：`app/api/route/route.php`

- 用户：`/api/user/*`
- 内容：`/api/drama/*`、`/api/novel/*`
- 订单：`/api/order/*`
- 秒杀：`/api/flash-sale/*`
- 行为记录：`/api/watch/*`、`/api/read/*`

秒杀关键接口：

- `POST /api/flash-sale/token`
- `POST /api/flash-sale/order/precheck`
- `POST /api/flash-sale/order/create`
- `GET /api/flash-sale/order/result/:requestId`
- `GET /api/flash-sale/stream`（SSE）

### 管理端 API（`/admin/*`）

路由文件：`app/admin/route/route.php`

- 登录：`POST /admin/login`
- 权限系统：`/admin/system/*`
- 内容管理：`/admin/drama/*`、`/admin/novel/*`
- 秒杀管理：`/admin/flash-sale/*`
- 渠道分发：`/admin/channel-distribution/*`
- 统计分析：`/admin/statistics/*`

## 4. 秒杀链路说明（当前实现）

秒杀下单采用「令牌 + 幂等请求 + 分布式锁 + MQ 异步创建订单」模式：

1. 前端先 `precheck` 与 `token`
2. 调用 `order/create` 时校验 `request_id`
3. 服务端持有请求锁与用户-商品锁，消费一次性令牌
4. 发布消息到 `djxs.flash_sale.order_create.s{0..3}` 分片队列
5. 消费者异步创建订单并回写结果状态
6. 前端通过 `order/result` 轮询或 SSE 获取结果

补充机制：

- 超时预留库存释放：独立命令 + Job 执行
- 非核心链路可做节流兜底触发
- 队列支持 DLQ（死信队列）
- token/发布失败有结构化 `warning` 日志可追踪 `reason`

## 5. 环境要求

- PHP `>=8.0`（项目当前镜像为 `8.3-fpm-alpine`）
- MySQL `8.0`
- Redis `7`
- RabbitMQ `3-management`
- Composer `2.x`

## 6. 环境变量（重点）

配置文件：`app/djxs_manage/.env`

- 数据库：`DB_*`
- 缓存：`CACHE_DRIVER`、`REDIS_*`
- 队列：`RABBITMQ_*`
- 秒杀：
  - `FLASH_SALE_TOKEN_TTL_SECONDS`
  - `FLASH_SALE_TOKEN_CONSUME_RETRY_TIMES`
  - `FLASH_SALE_TOKEN_ISSUE_STOCK_FACTOR`
  - `FLASH_SALE_STOCK_NOT_ENOUGH_LOG_SAMPLE_RATE`
  - `FLASH_SALE_RISK_LOG_ASYNC`
  - `FLASH_SALE_RISK_LOG_QUEUE_MAX_LEN`
  - `FLASH_SALE_RISK_LOG_CONSUME_BATCH_SIZE`
  - `FLASH_SALE_RISK_LOG_CONSUME_IDLE_SLEEP_MS`
  - `FLASH_SALE_QUEUE_PUBLISH_RETRY_TIMES`
  - `FLASH_SALE_REQUEST_LOCK_TTL_SECONDS`
  - `FLASH_SALE_USER_ITEM_LOCK_TTL_SECONDS`
  - `FLASH_SALE_RATE_LIMIT_FAIL_OPEN`
  - `FLASH_SALE_RELEASE_FALLBACK_*`

建议：

- 生产环境关闭 `APP_DEBUG`
- 不要将真实密钥、数据库密码、支付证书提交到仓库
- `FLASH_SALE_RATE_LIMIT_FAIL_OPEN` 默认建议 `0`（限流组件异常时 fail-close，返回“系统繁忙，请稍后重试”）
- 仅在应急排障窗口可短时设为 `1`（fail-open），并配合流量保护；窗口结束后务必恢复 `0`
- `FLASH_SALE_TOKEN_ISSUE_STOCK_FACTOR` 高并发压测可从 `1` 起步；值越小越保守（更少 token 超发），值越大越激进（可能增加 create 阶段失败）
- `FLASH_SALE_STOCK_NOT_ENOUGH_LOG_SAMPLE_RATE` 默认建议 `0.02`（2%）；可在压测期临时调低以减少 warning 噪音
- `FLASH_SALE_RISK_LOG_ASYNC` 建议灰度开启（先 `0` 后 `1`）；异步写失败会自动回退同步落库
- `FLASH_SALE_RISK_LOG_QUEUE_MAX_LEN` 建议 >= `200000`，避免洪峰时队列无限增长

## 7. 本地启动（推荐 Docker Compose）

在仓库根目录 `web_project` 执行：

```bash
docker compose up -d nginx-djxs php-djxs mysql-djxs redis rabbitmq supervisor
```

常用访问地址：

- API 网关：`http://localhost:8082`
- C 端前端：`http://localhost:3000`
- 管理端前端：`http://localhost:3001`
- Supervisor 面板：`http://localhost:9001`
- RabbitMQ 管理台：`http://localhost:15672`

健康检查：

- `GET /api/health`
- `GET /admin/health`

## 8. 仅后端开发启动（不依赖 Docker）

在 `app/djxs_manage` 目录：

```bash
composer install
php think run
```

默认地址：`http://127.0.0.1:8000`

> 此模式需自行准备 MySQL/Redis/RabbitMQ，并在 `.env` 配置连接。

## 9. 常用命令（think）

在 `app/djxs_manage` 目录执行：

```bash
php think list
```

关键命令示例：

- 订单
  - `php think order:cancel-timeout`
  - `php think order:contract-check`
- 秒杀
  - `php think flash-sale:order-consume`
  - `php think flash-sale:risk-log-consume`
  - `php think flash-sale:release-reserve`
  - `php think flash-sale:cleanup-cache`
  - `php think flash-sale:cleanup-risk`
  - `php think flash-sale:reconcile`
  - `php think flash-sale:audit-items --dry-run`
  - `php think flash-sale:audit-items --fix`
- 统计/渠道
  - `php think content-stat:consume`
  - `php think content:rebuild-stats`
  - `php think channel-distribution:consume`

## 10. 日志与排障

日志目录：`runtime/api/log/YYYYMM/`

重点观察：

- 秒杀 token 问题：
  - `flash-sale token consume failed | reason=...`
- 队列发布问题：
  - `FlashSaleOrderQueueService publish failed | reason=...`

排障建议：

1. 先看 `warning` 日志中的 `reason`
2. 再检查 RabbitMQ 队列状态与消费者进程
3. 最后核对 Redis key 前缀、TTL 与请求频率限制

## 11. 相关子项目

- 用户端前端：`app/djxs_user_web`
- 管理端前端：`app/djxs_manage_web`
- 秒杀压测脚本：`perf/flash-sale`

---

如需新增业务模块，优先按现有分层规范扩展：

- `controller` 只做参数接入和响应
- `service` 承担业务编排
- `command/job` 承担离线和异步处理

## 12. 发布上线 Checklist（建议）

### 发布前（Pre-check）

- 配置校验
  - [ ] `.env` 中 `DB_*`、`REDIS_*`、`RABBITMQ_*` 指向目标环境
  - [ ] 生产环境 `APP_DEBUG=false`
  - [ ] `REDIS_PREFIX` 与历史环境保持一致（避免 token/key 命名冲突）
- 依赖与代码
  - [ ] `composer install --no-dev` 已执行完成
  - [ ] `php think list` 正常，关键命令可见
- 基础服务
  - [ ] MySQL/Redis/RabbitMQ 全部可连通
  - [ ] RabbitMQ 中秒杀队列和 DLQ 参数一致（无历史冲突）
- 秒杀活动数据
  - [ ] 活动时间窗、库存、限购规则确认
  - [ ] 执行 `php think flash-sale:audit-items --dry-run`，无异常商品

### 发布中（Deploy）

- 镜像/容器
  - [ ] `php-djxs`、`nginx-djxs`、`supervisor` 按顺序更新
  - [ ] 配置变更后执行容器重启，确保新环境变量生效
- 消费者进程
  - [ ] `flash-sale-order-consume` 处于 RUNNING
  - [ ] 开启 `FLASH_SALE_RISK_LOG_ASYNC=1` 时，`flash-sale-risk-log-consume` 处于 RUNNING
  - [ ] `content-stat-consume`、`channel-distribution-consume` 处于 RUNNING
- 热点链路冒烟
  - [ ] `GET /api/health` 返回正常
  - [ ] 秒杀链路完成一次：`token -> create -> result` 成功闭环

### 发布后（Post-check）

- 业务与日志
  - [ ] `runtime/api/log/YYYYMM/*_warning.log` 无持续新增异常
  - [ ] 无大量 `flash-sale token consume failed | reason=...`
  - [ ] 无大量 `FlashSaleOrderQueueService publish failed | reason=...`
- 队列与消费
  - [ ] 秒杀分片队列无持续积压（Ready/Unacked 可回落）
  - [ ] DLQ 无异常增长
- 订单结果
  - [ ] 秒杀订单可正常创建，结果查询状态可终态收敛
  - [ ] 支付回调链路可用（如本次包含支付变更）

### 回滚策略（Rollback）

- 应用回滚
  - [ ] 回滚到上一个稳定镜像/代码版本
  - [ ] 重启 `php-djxs` 与 `supervisor`，确认进程恢复
- 配置回滚
  - [ ] 恢复上一个稳定 `.env`（尤其秒杀重试、锁 TTL、队列参数）
- 数据与队列保护
  - [ ] 不直接清理生产队列消息，先评估积压与消费状态
  - [ ] 若遇队列声明冲突，先比对参数，再按计划重建（保留 DLQ）

### 常用巡检命令（示例）

在 `app/djxs_manage` 目录：

```bash
php think flash-sale:reconcile
php think flash-sale:release-reserve
php think flash-sale:audit-items --dry-run
```

在仓库根目录：

```bash
docker compose ps
docker compose logs --tail=200 supervisor
docker compose logs --tail=200 php-djxs
```

## 13. 值班排障速查表（5 分钟定位）

### 场景 A：提示「抢购令牌无效或已过期」

1. 先看告警日志是否有结构化原因：
   - 关注 `flash-sale token consume failed | reason=...`
2. 按 `reason` 判断：
   - `token_mismatch`：前端参数与令牌绑定不一致（活动/商品/user 不匹配）
   - `token_reused`：同令牌重复提交
   - `token_expired_or_missing`：令牌过期或 Redis key 不存在
   - `token_consume_conflict`：令牌消费阶段冲突（重点查 key 前缀、并发、重试）
3. 快速处置：
   - 核对 `FLASH_SALE_TOKEN_TTL_SECONDS`
   - 核对 `REDIS_PREFIX` 与线上历史是否一致
   - 确认前端是否重复触发 create（按钮防抖、轮询 stop 条件）

### 场景 B：提示「秒杀排队服务繁忙，请稍后重试」

1. 查队列发布失败日志：
   - `FlashSaleOrderQueueService publish failed | reason=...`
2. 优先排查：
   - RabbitMQ 连接是否可用（网络/账号/vhost）
   - 队列声明参数是否冲突（特别是 DLX/DLQ 相关）
   - `supervisor` 中消费者是否存活
3. 常见根因：
   - `queue_declare_mismatch`：队列历史参数不一致
   - `connection_failed`：MQ 实例不可达或认证失败

### 场景 C：创建成功但结果长时间不结束

1. 看队列积压（Ready/Unacked）是否持续增长
2. 看消费者进程是否异常重启或退出
3. 查消费日志里是否有数据库死锁/库存校验失败
4. 执行一次对账修复：

```bash
php think flash-sale:reconcile
```

### 场景 D：活动库存或预留库存异常

1. 执行释放任务：

```bash
php think flash-sale:release-reserve
```

2. 检查秒杀活动商品有效性：

```bash
php think flash-sale:audit-items --dry-run
```

3. 若确认需要修复失效商品：

```bash
php think flash-sale:audit-items --fix
```

### 5 分钟通用排查命令（建议按顺序）

```bash
docker compose ps
docker compose logs --tail=200 supervisor
docker compose logs --tail=200 php-djxs
```

```bash
php think flash-sale:reconcile
php think flash-sale:release-reserve
```

### 升级为 P1 的判断标准（建议）

- 同一错误在 1 分钟内持续大量出现（如 token 或 publish failed）
- 秒杀订单无法进入终态（成功/失败）且队列持续积压
- RabbitMQ/Redis/MySQL 任一核心依赖不可用

## 14. 错误码与提示对照（秒杀）

> 说明：前端最终提示可能经过二次映射，定位时以后端日志中的 `reason` 为准。

| 前端常见提示 | 后端 `reason` / 现象 | 含义 | 处理动作（值班） |
|---|---|---|---|
| 抢购参数已变化，请重新点击立即抢购获取新令牌 | `token_mismatch` | 令牌绑定的用户/活动/商品与本次请求不一致 | 刷新页面后重新领取 token；核对前端提交参数是否被缓存污染 |
| 抢购令牌已被使用，请勿重复提交 | `token_reused` | 同一 token 被重复消费 | 检查按钮防抖、重复请求、重试策略是否过激 |
| 抢购令牌已过期，请重新点击立即抢购 | `token_expired_or_missing` | token 到期或 Redis 中不存在 | 核对 `FLASH_SALE_TOKEN_TTL_SECONDS`、客户端耗时、时钟漂移 |
| 抢购令牌处理中，请稍后重试 | `token_consume_conflict` | token 消费阶段冲突（并发/删除失败） | 核对 `REDIS_PREFIX`、消费重试参数、并发峰值与请求风暴 |
| 抢购令牌已失效，请重试抢购 | `token_invalid_legacy` | 历史兼容分支的泛化失败 | 结合同时间段日志进一步归因到上面几类 |
| 秒杀排队服务繁忙，请稍后重试 | `publish failed`（常见 `queue_declare_mismatch`、`connection_failed`） | MQ 发布失败，订单未进入异步队列 | 查 RabbitMQ 连通与队列参数；确认消费者存活；必要时按变更计划重建队列（保留 DLQ） |
| 数据库锁冲突，请稍后重试 | 死锁重试耗尽 | 高并发下 DB 锁竞争过高 | 查热点 SQL/索引与事务范围；短期降并发，长期优化事务 |

### 快速映射规则

- 有 `reason=`：优先按 `reason` 处理，不以前端文案为准。
- 无 `reason=`：先补齐日志上下文，再按“队列发布失败 / token 消费失败 / DB 死锁”三分法定位。
- 同时出现多个错误：先处理基础设施类（MQ/Redis/DB）再处理业务参数类（token_mismatch）。

## 15. 压测前检查清单（k6 专用）

压测脚本目录：`perf/flash-sale`

### 压测前 10 分钟检查

- 环境与脚本
  - [ ] `perf/flash-sale/k6/tokens.json` 已准备有效 JWT
  - [ ] `BASE_URL` 指向目标网关（如 `http://localhost:8082`）
  - [ ] `ACTIVITY_ID`、`ITEM_ID` 与目标活动一致
- 秒杀活动状态
  - [ ] 活动已发布且在有效时间窗内
  - [ ] 商品库存与限购策略符合本次压测目标
  - [ ] `flash-sale:audit-items --dry-run` 无异常项
- 基础服务容量
  - [ ] RabbitMQ、Redis、MySQL 正常
  - [ ] `supervisor` 中秒杀消费者 RUNNING
  - [ ] 队列无历史异常积压（Ready/Unacked 可接受）
- 参数基线
  - [ ] `FLASH_SALE_TOKEN_TTL_SECONDS` 满足压测链路耗时
  - [ ] `FLASH_SALE_QUEUE_PUBLISH_RETRY_TIMES`、`FLASH_SALE_TOKEN_CONSUME_RETRY_TIMES` 与测试目标一致
  - [ ] 限频参数已按压测计划设置（避免误触限流）

### 推荐执行方式

在仓库根目录执行：

```bash
bash perf/flash-sale/run-flash-sale-k6.sh
```

可覆盖参数示例：

```bash
BASE_URL=http://localhost:8082 \
ACTIVITY_ID=1 \
ITEM_ID=1 \
STAGE1_TARGET=100 \
STAGE2_TARGET=200 \
STAGE3_TARGET=400 \
STAGE1_DURATION=1m \
STAGE2_DURATION=2m \
STAGE3_DURATION=2m \
POLL_MAX=40 \
POLL_INTERVAL_MS=300 \
bash perf/flash-sale/run-flash-sale-k6.sh
```

仅看 k6 输出（关闭监控旁路输出）：

```bash
MONITOR_ENABLED=0 bash perf/flash-sale/run-flash-sale-k6.sh
```

### 压测中重点观测

- 业务指标
  - `flashsale_final_success_rate`
  - `flashsale_e2e_latency_ms`
  - `flashsale_create_biz_fail_total`
  - `flashsale_final_fail_total`
- 系统侧指标
  - 秒杀分片队列 backlog 是否持续上升
  - `flash-sale-order-consume` 是否稳定运行
  - `warning` 日志是否出现集中 `reason`

### 压测后收口

- [ ] 保存本次压测参数与结果快照（并发、成功率、P95/P99）
- [ ] 记录失败类型占比（token、publish、deadlock）
- [ ] 执行一次对账/释放，避免遗留预留库存

```bash
php think flash-sale:reconcile
php think flash-sale:release-reserve
```

- [ ] 如有临时调参，恢复到基线配置

## 16. 压测结果记录模板（可复制）

以下模板建议每次压测都留档一次，便于横向对比与容量评估。

```text
# 秒杀压测记录

## 1) 基本信息
- 测试时间：
- 测试环境：dev / staging / prod-shadow
- 提交版本（Git SHA）：
- 执行人：
- 目标场景：预热 / 峰值 / 稳态 / 回归

## 2) 压测参数
- BASE_URL：
- ACTIVITY_ID：
- ITEM_ID：
- STAGE1_TARGET / DURATION：
- STAGE2_TARGET / DURATION：
- STAGE3_TARGET / DURATION：
- POLL_MAX：
- POLL_INTERVAL_MS：
- MONITOR_ENABLED：

## 3) 环境配置快照
- FLASH_SALE_TOKEN_TTL_SECONDS：
- FLASH_SALE_TOKEN_CONSUME_RETRY_TIMES：
- FLASH_SALE_QUEUE_PUBLISH_RETRY_TIMES：
- FLASH_SALE_REQUEST_LOCK_TTL_SECONDS：
- FLASH_SALE_USER_ITEM_LOCK_TTL_SECONDS：
- 限频参数（token/create）：
- 消费者副本数（flash-sale-order-consume）：

## 4) 核心结果
- 请求总量：
- 最终成功率（flashsale_final_success_rate）：
- 端到端延迟 P50/P95/P99（flashsale_e2e_latency_ms）：
- 创建阶段业务失败数（flashsale_create_biz_fail_total）：
- 最终未收敛失败数（flashsale_final_fail_total）：

## 5) 系统观测
- RabbitMQ 队列峰值积压（Ready / Unacked）：
- Redis CPU/内存变化：
- MySQL CPU/慢查询情况：
- Supervisor 进程状态是否稳定：
- warning 日志主导错误：
  - token reason TopN：
  - publish reason TopN：

## 6) 问题与结论
- 发现问题：
  1.
  2.
- 根因判断：
- 是否达到上线门槛：是 / 否
- 建议并发上限：

## 7) 后续动作（Action Items）
- [ ] 调整参数：
- [ ] 优化代码/索引：
- [ ] 增加监控项：
- [ ] 安排复测时间：
```

可选：在模板后附上本次关键日志片段（`reason=`）和队列截图，方便后续复盘快速定位。
