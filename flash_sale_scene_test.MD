# 秒杀并发测试场景矩阵（可执行版）

## 1. 目标与适用范围

本矩阵用于 `djxs_manage` 秒杀链路的稳定性与容量验证，覆盖以下核心路径：

- `precheck -> token -> create -> result`
- Redis 预扣与回补逻辑
- 下单异步队列消费（`flash-sale-order-consume`）
- 风控日志异步消费（`flash-sale-risk-log-consume`）

适用对象：版本回归、上线前压测、故障复盘后的复测。

---

## 2. 统一测试前置

每轮场景执行前建议做一次环境清理，避免历史挂单影响结果可比性。

```bash
# 1) 释放历史挂单占用
docker exec web-mysql-djxs mysql -N -udjxs_user -p'djxs@pass@2026' -D djxs_manage -e \
"UPDATE djxs_flash_sale_order SET reserve_expire_time = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE item_id = 9 AND status = 0;"

# 2) 执行库存与缓存修复
docker exec web-supervisor php think flash-sale:release-reserve
docker exec web-supervisor php think flash-sale:reconcile
docker exec web-supervisor php think flash-sale:cleanup-cache
```

建议固定测试商品：

- `ACTIVITY_ID=2`
- `ITEM_ID=9`
- 库存基线：`100`

---

## 3. 核心指标与通过标准

### 必看指标（按阶段）

- `flashsale_precheck_fail_total`
- `flashsale_token_fail_total` / `flashsale_token_fail_rate`
- `flashsale_create_attempt_total`
- `flashsale_create_fail_total` / `flashsale_create_fail_rate`
- `flashsale_result_timeout_total`
- `flashsale_final_success_rate`
- `flashsale_e2e_latency_ms`（p95/p99）
- `http_req_failed`（辅助参考，不单独作为业务结论）

### 必达约束

- 绝不超卖：成功订单数 `<=` 商品库存。
- 各失败类型可解释：reason 分布稳定，不能出现大面积未知失败。
- 队列可恢复：活动后 RabbitMQ backlog 能回落到低位。
- 成功链路时延可控：同倍率回归时 p95/p99 不应显著恶化。

---

## 4. 并发测试场景矩阵

> 公共参数默认：  
> `BASE_URL=http://localhost:8082 ACTIVITY_ID=2 ITEM_ID=9 TOKEN_MODE=by_iteration MONITOR_ENABLED=1`

| 场景ID | 场景类型 | 用户/请求规模 | 库存比 | 目标 | 执行命令 |
|---|---|---:|---:|---|---|
| S1 | 基线（低压） | 200 / 200 | 2:1 | 验证功能与基本SLA | `BASE_URL=http://localhost:8082 ACTIVITY_ID=2 ITEM_ID=9 MONITOR_ENABLED=1 EXECUTOR=shared-iterations VUS=200 ITERATIONS=200 MAX_DURATION=3m SUMMARY_EXPORT=/work/summary-s1-baseline.json bash perf/flash-sale/run-flash-sale-k6.sh` |
| S2 | 常态（中压） | 500 / 500 | 5:1 | 观察失败分布是否合理 | `BASE_URL=http://localhost:8082 ACTIVITY_ID=2 ITEM_ID=9 MONITOR_ENABLED=1 EXECUTOR=shared-iterations VUS=500 ITERATIONS=500 MAX_DURATION=4m SUMMARY_EXPORT=/work/summary-s2-normal.json bash perf/flash-sale/run-flash-sale-k6.sh` |
| S3 | 热点（高压） | 1000 / 1000 | 10:1 | 验证热点秒杀稳定性 | `BASE_URL=http://localhost:8082 ACTIVITY_ID=2 ITEM_ID=9 MONITOR_ENABLED=1 EXECUTOR=shared-iterations VUS=1000 ITERATIONS=1000 MAX_DURATION=6m SUMMARY_EXPORT=/work/summary-s3-hotspot.json bash perf/flash-sale/run-flash-sale-k6.sh` |
| S4 | 极限（超压） | 2000 / 2000 | 20:1 | 验证拒绝链路与系统韧性 | `BASE_URL=http://localhost:8082 ACTIVITY_ID=2 ITEM_ID=9 MONITOR_ENABLED=1 EXECUTOR=shared-iterations VUS=2000 ITERATIONS=2000 MAX_DURATION=8m SUMMARY_EXPORT=/work/summary-s4-extreme.json bash perf/flash-sale/run-flash-sale-k6.sh` |
| S5 | 突刺（Spike） | 峰值1000VU | 约10:1 | 验证瞬时抗冲击与恢复 | `BASE_URL=http://localhost:8082 ACTIVITY_ID=2 ITEM_ID=9 MONITOR_ENABLED=1 EXECUTOR=ramping-vus START_VUS=0 STAGE1_TARGET=0 STAGE1_DURATION=10s STAGE2_TARGET=1000 STAGE2_DURATION=10s STAGE3_TARGET=1000 STAGE3_DURATION=40s STAGE4_TARGET=0 GRACEFUL_RAMP_DOWN=20s SUMMARY_EXPORT=/work/summary-s5-spike.json bash perf/flash-sale/run-flash-sale-k6.sh` |
| S6 | 长稳（Soak） | 稳态200VU，30分钟 | 压力持续 | 验证长期稳定性与资源泄漏 | `BASE_URL=http://localhost:8082 ACTIVITY_ID=2 ITEM_ID=9 MONITOR_ENABLED=1 EXECUTOR=ramping-vus START_VUS=0 STAGE1_TARGET=200 STAGE1_DURATION=2m STAGE2_TARGET=200 STAGE2_DURATION=30m STAGE3_TARGET=0 STAGE3_DURATION=2m SUMMARY_EXPORT=/work/summary-s6-soak.json bash perf/flash-sale/run-flash-sale-k6.sh` |

---

## 5. 高可用故障注入场景（建议每周至少一次）

> 目的：验证“服务降级但不破坏一致性”能力。

| 场景ID | 注入点 | 操作建议 | 观察点 |
|---|---|---|---|
| F1 | Redis 短时抖动 | 压测中短时重启 `web-redis` 或限流 | 是否出现超卖；失败reason是否集中且可解释 |
| F2 | RabbitMQ 短时不可用 | 压测中重启 `web-rabbitmq` | `queue_publish_failed` 占比、恢复后 backlog 回落 |
| F3 | DB 压力抬升 | 并行执行慢SQL/高IO任务 | `create_fail_rate`、死锁重试效果、p95是否失控 |
| F4 | 消费者缩容 | 临时将 `flash-sale-order-consume` 从2降1 | backlog 增长斜率与恢复时间 |
| F5 | 风控日志消费者停机 | 停 `flash-sale-risk-log-consume` 再恢复 | 业务是否不受影响；日志队列是否可追赶清空 |

---

## 6. 执行顺序建议（标准回归流水）

1. S1 -> S2（确认基本可用）
2. S3（核心热点验证）
3. S5（冲击恢复）
4. S6（长期稳定）
5. F1~F5（高可用验证）
6. 复跑 S3（验证故障后恢复质量）

### 一键执行（推荐）

可直接使用仓库脚本按矩阵顺序执行 S1~S6 并输出汇总报告：

```bash
bash perf/flash-sale/run-scene-matrix.sh
```

常用覆盖参数示例：

```bash
ACTIVITY_ID=2 ITEM_ID=9 MONITOR_ENABLED=1 PREPARE_EACH_SCENE=1 bash perf/flash-sale/run-scene-matrix.sh
```

### 场景级独立商品（强烈建议）

为避免前序场景消耗库存污染后续结果，建议为每个场景指定独立 `item_id`。

方式一（推荐，显式变量）：

```bash
ACTIVITY_ID=2 MONITOR_ENABLED=1 PREPARE_EACH_SCENE=1 \
SCENE_S1_ITEM_ID=1 \
SCENE_S2_ITEM_ID=2 \
SCENE_S3_ITEM_ID=3 \
SCENE_S4_ITEM_ID=9 \
SCENE_S5_ITEM_ID=1 \
SCENE_S6_ITEM_ID=2 \
bash perf/flash-sale/run-scene-matrix.sh
```

方式二（映射串）：

```bash
ACTIVITY_ID=2 MONITOR_ENABLED=1 PREPARE_EACH_SCENE=1 \
SCENE_ITEM_MAP="S1:1,S2:2,S3:3,S4:9,S5:1,S6:2" \
bash perf/flash-sale/run-scene-matrix.sh
```

> 优先级：`SCENE_Sx_ITEM_ID` > `SCENE_ITEM_MAP` > `ITEM_ID`（默认值）。

### 跨活动双映射（activity + item）

当测试商品分布在不同活动下时，建议同时指定场景级 `activity_id` 与 `item_id`：

```bash
MONITOR_ENABLED=1 PREPARE_EACH_SCENE=1 \
SCENE_S1_ACTIVITY_ID=1 SCENE_S1_ITEM_ID=2 \
SCENE_S2_ACTIVITY_ID=1 SCENE_S2_ITEM_ID=10 \
SCENE_S3_ACTIVITY_ID=2 SCENE_S3_ITEM_ID=3 \
SCENE_S4_ACTIVITY_ID=2 SCENE_S4_ITEM_ID=9 \
SCENE_S5_ACTIVITY_ID=2 SCENE_S5_ITEM_ID=11 \
SCENE_S6_ACTIVITY_ID=1 SCENE_S6_ITEM_ID=1 \
bash perf/flash-sale/run-scene-matrix.sh
```

也支持映射串写法：

```bash
MONITOR_ENABLED=1 PREPARE_EACH_SCENE=1 \
SCENE_ACTIVITY_MAP="S1:1,S2:1,S3:2,S4:2,S5:2,S6:1" \
SCENE_ITEM_MAP="S1:2,S2:10,S3:3,S4:9,S5:11,S6:1" \
bash perf/flash-sale/run-scene-matrix.sh
```

> 优先级：`SCENE_Sx_ACTIVITY_ID` > `SCENE_ACTIVITY_MAP` > `ACTIVITY_ID`（默认值）。

---

## 7. 结果记录模板（每轮复制填写）

```md
### 测试轮次：<日期-版本-场景ID>

- 场景参数：
  - ACTIVITY_ID:
  - ITEM_ID:
  - 库存:
  - VUS / ITERATIONS:
  - 执行时长:
- 核心结果：
  - final_success_rate:
  - token_fail_rate:
  - create_attempt_total:
  - create_fail_rate:
  - result_timeout_total:
  - e2e_p95 / p99:
  - http_req_failed:
- 一致性核对：
  - 成功订单数:
  - 是否超卖:
  - backlog 峰值/回落时间:
- 结论：
  - 通过 / 不通过
  - 主要瓶颈
  - 下一步优化项
```

---

## 8. 推荐阈值（可按业务再收紧）

- S1/S2：`e2e p95 <= 6s`
- S3：`e2e p95 <= 15s`
- S4：`e2e p95 <= 25s`（重点看一致性和可恢复性）
- S6：30分钟内无持续恶化趋势（内存、延迟、backlog）

> 说明：阈值应以“当前稳定版本”作为基线，每次变更后只允许小幅波动，不应出现阶跃恶化。

---

## 9. 基线样例结果（S4 三轮 clean）

以下为 2026-04-23 基于独立商品的 S4（20:1）三轮复测样例，可作为后续回归对照参考：

- run 标签：`s4-repeat-clean-20260423-083641`
- 轮次商品：
  - Round1：`activity_id=2, item_id=3`
  - Round2：`activity_id=2, item_id=9`
  - Round3：`activity_id=2, item_id=11`

| 指标 | mean | median | min | max | range |
|---|---:|---:|---:|---:|---:|
| final_success_rate | 5.00% | 5.00% | 5.00% | 5.00% | 0.00% |
| token_fail_rate | 94.53% | 94.61% | 94.25% | 94.73% | 0.48% |
| create_attempts | 100 | 100 | 100 | 100 | 0 |
| create_fail_rate | 0.00% | 0.00% | 0.00% | 0.00% | 0.00% |
| e2e_p95_ms | 21969.52 | 21479.15 | 20582.25 | 23847.15 | 3264.90 |
| http_req_failed | 47.03% | 46.72% | 46.35% | 48.00% | 1.65% |

说明：

- 上述样例已规避“同一商品连续复测导致库存被首轮消耗”的污染问题。
- 若后续同口径压测中 `create_fail_rate` 明显高于 `0%`，或 `e2e_p95_ms` 明显高于该区间，可判定为回归风险。

