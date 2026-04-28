# 网站监控告警系统

## 功能特性

- ✅ **系统服务监控**: MySQL、Redis、RabbitMQ 存活检查
- ✅ **进程状态监控**: Supervisor 关键进程状态
- ✅ **队列积压监控**: RabbitMQ 秒杀队列积压告警
- ✅ **API健康检查**: 接口响应时间和可用性
- ✅ **错误日志监控**: Supervisor 错误日志实时检测
- ✅ **告警抑制**: 避免相同告警频繁发送
- ✅ **多渠道通知**: 企业微信、钉钉、邮件

## 快速开始

### 1. 配置环境变量

```bash
cd /home/wsg/web_project/app/djxs_manage/monitor
cp .env.monitor.example .env.monitor
vim .env.monitor  # 编辑配置
```

### 2. 安装依赖

```bash
pip3 install requests redis
```

### 3. 测试运行

```bash
python3 site_monitor.py
```

### 4. 安装定时任务

```bash
chmod +x setup_cron.sh
./setup_cron.sh
```

## 通知配置

### 企业微信机器人

1. 在企业微信群聊中添加"群机器人"
2. 获取 Webhook 地址
3. 配置 `MONITOR_WECHAT_WEBHOOK`

示例：
```bash
MONITOR_USE_WECHAT=true
MONITOR_WECHAT_WEBHOOK=https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxxx-xxxx-xxxx
```

### 钉钉机器人

1. 在钉钉群聊中添加"自定义机器人"
2. 获取 Webhook 地址
3. 配置 `MONITOR_DINGTALK_WEBHOOK`

示例：
```bash
MONITOR_USE_DINGTALK=true
MONITOR_DINGTALK_WEBHOOK=https://oapi.dingtalk.com/robot/send?access_token=xxxx
```

### 邮件通知

1. 配置 SMTP 服务器信息
2. 配置收件人邮箱列表

示例：
```bash
MONITOR_USE_EMAIL=true
MONITOR_EMAIL_SMTP=smtp.qq.com
MONITOR_EMAIL_PORT=465
MONITOR_EMAIL_USER=your_email@qq.com
MONITOR_EMAIL_PASS=your_smtp_password
MONITOR_EMAIL_TO=recipient@qq.com
```

## 监控项说明

| 监控项 | 告警条件 | 级别 |
|--------|---------|------|
| MySQL | 不可达 | CRITICAL |
| MySQL | 连接数 > 阈值 | WARNING |
| Redis | PING 失败 | CRITICAL |
| Redis | 内存使用 > 80% | WARNING |
| RabbitMQ | Management API 不可达 | CRITICAL |
| RabbitMQ | 队列积压 > 1000 | WARNING |
| Supervisor | 关键进程非 RUNNING | CRITICAL |
| API | 接口不可达 | CRITICAL |
| API | 响应时间 > 3秒 | WARNING |
| 错误日志 | 发现 > 10条错误 | WARNING |

## 告警抑制

相同告警在 300 秒（5分钟）内只发送一次，避免告警风暴。

可通过 `MONITOR_ALERT_SUPPRESS_SECONDS` 调整。

## 日志查看

```bash
# 查看监控日志
tail -f /var/log/site_monitor.log

# 查看告警状态
cat /tmp/monitor_alert_state.json | python3 -m json.tool
```

## 手动触发测试

```bash
# 执行一次完整检查
python3 site_monitor.py

# 查看退出码
echo $?
# 0: 正常
# 1: 有严重告警
# 2: 脚本执行异常
```

## 卸载定时任务

```bash
crontab -l | grep -v "site_monitor.py" | crontab -
```

## 自定义监控

### 添加新的 API 端点

编辑 `.env.monitor` 文件，修改 `site_monitor.py` 中的 `MONITOR_CONFIG['api']['endpoints']`：

```python
'endpoints': [
    {'path': '/api/flash-sale/list', 'name': '秒杀列表'},
    {'path': '/api/drama/list', 'name': '短剧列表'},
    {'path': '/api/novel/list', 'name': '小说列表'},
    {'path': '/api/user/info', 'name': '用户信息'},  # 新增
],
```

### 调整告警阈值

```bash
# MySQL 连接数阈值
MONITOR_MAX_CONNECTIONS_THRESHOLD=150

# Redis 内存使用警告百分比
MONITOR_REDIS_MEMORY_WARNING=90.0

# RabbitMQ 队列积压阈值
MONITOR_QUEUE_BACKLOG_THRESHOLD=2000

# API 响应时间警告阈值（秒）
MONITOR_API_RESPONSE_TIME_WARNING=5.0
```

## 故障排查

### 问题：收不到告警通知

1. 检查通知配置是否正确
2. 查看监控日志：`tail -f /var/log/site_monitor.log`
3. 手动测试：`python3 site_monitor.py`

### 问题：告警太频繁

调整告警抑制时间：
```bash
MONITOR_ALERT_SUPPRESS_SECONDS=600  # 10分钟内相同告警只发送一次
```

### 问题：Redis 检查失败

安装 redis 模块：
```bash
pip3 install redis
```

## 文件说明

- `site_monitor.py` - 主监控脚本
- `.env.monitor.example` - 配置文件模板
- `.env.monitor` - 实际配置文件（需自行创建）
- `setup_cron.sh` - 定时任务安装脚本
- `README.md` - 说明文档
