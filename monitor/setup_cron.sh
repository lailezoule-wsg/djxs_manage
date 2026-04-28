#!/bin/bash

# 网站监控定时任务安装脚本

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MONITOR_SCRIPT="$SCRIPT_DIR/site_monitor.py"
ENV_FILE="$SCRIPT_DIR/.env.monitor"

echo "========================================="
echo "  网站监控定时任务安装"
echo "========================================="
echo ""

# 检查监控脚本是否存在
if [ ! -f "$MONITOR_SCRIPT" ]; then
    echo "❌ 错误: 监控脚本不存在: $MONITOR_SCRIPT"
    exit 1
fi

# 检查环境配置文件
if [ ! -f "$ENV_FILE" ]; then
    echo "⚠️  警告: 环境配置文件不存在: $ENV_FILE"
    echo "请先复制 .env.monitor.example 为 .env.monitor 并配置"
    echo ""
    echo "执行以下命令："
    echo "  cp $SCRIPT_DIR/.env.monitor.example $ENV_FILE"
    echo "  vim $ENV_FILE"
    exit 1
fi

# 检查 Python3 是否安装
if ! command -v python3 &> /dev/null; then
    echo "❌ 错误: Python3 未安装"
    exit 1
fi

echo "✅ Python3 已安装: $(python3 --version)"
echo ""

# 安装依赖
echo "📦 安装 Python 依赖..."
pip3 install requests 2>/dev/null || pip install requests 2>/dev/null

if python3 -c "import redis" 2>/dev/null; then
    echo "✅ redis 模块已安装"
else
    echo "⚠️  redis 模块未安装，将跳过 Redis 检查"
    echo "   安装命令: pip3 install redis"
fi

echo ""

# 创建日志文件
LOG_FILE="/home/wsg/web_project/app/djxs_manage/monitor/site_monitor.log"
echo "📝 创建日志文件: $LOG_FILE"
touch "$LOG_FILE"
if [ $? -eq 0 ]; then
    echo "✅ 日志文件创建成功"
fi

echo ""

# 添加定时任务
echo "⏰ 添加定时任务..."

# 检查是否已存在
if crontab -l 2>/dev/null | grep -q "site_monitor.py"; then
    echo "⚠️  定时任务已存在，先删除旧任务..."
    crontab -l 2>/dev/null | grep -v "site_monitor.py" | crontab -
    echo "✅ 旧任务已删除"
fi

# 获取 Python3 完整路径
PYTHON_PATH=$(which python3)

# 添加新任务（每5分钟执行一次）
CRON_JOB="*/5 * * * * cd $SCRIPT_DIR && set -a && source .env.monitor && set +a && $PYTHON_PATH $MONITOR_SCRIPT >> $LOG_FILE 2>&1"

(crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -

if [ $? -eq 0 ]; then
    echo "✅ 定时任务已添加（每5分钟执行一次）"
else
    echo "❌ 定时任务添加失败"
    exit 1
fi

echo ""
echo "========================================="
echo "  安装完成"
echo "========================================="
echo ""
echo "📋 常用命令："
echo "  查看定时任务:     crontab -l"
echo "  查看监控日志:     tail -f $LOG_FILE"
echo "  手动执行测试:     cd $SCRIPT_DIR && python3 site_monitor.py"
echo "  卸载定时任务:     crontab -l | grep -v 'site_monitor.py' | crontab -"
echo ""
echo "🔧 下一步："
echo "  1. 编辑配置文件: vim $ENV_FILE"
echo "  2. 配置通知方式（企业微信/钉钉/邮件）"
echo "  3. 手动测试: python3 $MONITOR_SCRIPT"
echo ""
