#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
网站综合监控告警脚本（Docker 环境优化版）
监控维度：Docker容器、MySQL、Redis、RabbitMQ、Supervisor进程、API健康、错误日志
通知方式：企业微信/钉钉/邮件
运行环境：宿主机（通过 Docker 端口映射访问容器服务）
"""

import os
import sys
import json
import time
import socket
import logging
import subprocess
import requests
from datetime import datetime
from pathlib import Path
from urllib.parse import quote

# 自动加载环境变量文件
env_file = os.path.join(os.path.dirname(__file__), '.env.monitor')
if os.path.exists(env_file):
    with open(env_file, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#'):
                continue
            if '=' in line:
                key, val = line.split('=', 1)
                key = key.strip()
                val = val.strip().strip('"').strip("'")
                if key and val and key not in os.environ:
                    os.environ[key] = val

try:
    import smtplib
    from email.mime.text import MIMEText
    from email.mime.multipart import MIMEMultipart
    HAS_EMAIL = True
except ImportError:
    HAS_EMAIL = False

try:
    import redis
    HAS_REDIS = True
except ImportError:
    HAS_REDIS = False

try:
    import pymysql
    HAS_MYSQL = True
except ImportError:
    HAS_MYSQL = False

# ================== 配置区 ==================

MONITOR_CONFIG = {
    'docker': {
        'enabled': os.getenv('MONITOR_DOCKER_ENABLED', 'true').lower() == 'true',
        'containers': [
            'web-mysql-djxs',
            'web-redis',
            'web-rabbitmq',
            'web-supervisor',
            'web-php-djxs',
            'web-nginx-djxs',
        ],
    },
    
    'mysql': {
        'host': os.getenv('MONITOR_MYSQL_HOST', '127.0.0.1'),
        'port': int(os.getenv('MONITOR_MYSQL_PORT', 3307)),
        'user': os.getenv('MONITOR_MYSQL_USER', 'root'),
        'password': os.getenv('MONITOR_MYSQL_PASSWORD', ''),
        'database': os.getenv('MONITOR_MYSQL_DATABASE', 'djxs_manage'),
        'max_connections_threshold': int(os.getenv('MONITOR_MAX_CONNECTIONS_THRESHOLD', 100)),
    },
    
    'redis': {
        'host': os.getenv('MONITOR_REDIS_HOST', '127.0.0.1'),
        'port': int(os.getenv('MONITOR_REDIS_PORT', 6379)),
        'password': os.getenv('MONITOR_REDIS_PASSWORD', '') or None,
        'db': int(os.getenv('MONITOR_REDIS_DB', 0)),
        'memory_warning_percent': float(os.getenv('MONITOR_REDIS_MEMORY_WARNING', 80.0)),
    },
    
    'rabbitmq': {
        'host': os.getenv('MONITOR_RABBITMQ_HOST', '127.0.0.1'),
        'port': int(os.getenv('MONITOR_RABBITMQ_PORT', 15672)),
        'user': os.getenv('MONITOR_RABBITMQ_USER', 'admin'),
        'password': os.getenv('MONITOR_RABBITMQ_PASSWORD', 'wsg@666666666'),
        'queue_backlog_threshold': int(os.getenv('MONITOR_QUEUE_BACKLOG_THRESHOLD', 1000)),
        'flash_sale_queues': [
            'djxs.flash_sale.order_create.s0',
            'djxs.flash_sale.order_create.s1',
            'djxs.flash_sale.order_create.s2',
            'djxs.flash_sale.order_create.s3',
            'djxs.content_stat',
            'djxs.channel_distribution.publish',
        ],
    },
    
    'api': {
        'base_url': os.getenv('MONITOR_API_BASE_URL', 'http://localhost:8082'),
        'timeout': int(os.getenv('MONITOR_API_TIMEOUT', 5)),
        'response_time_warning': float(os.getenv('MONITOR_API_RESPONSE_TIME_WARNING', 3.0)),
        'endpoints': [
            {'path': '/api/flash-sale/list', 'name': '秒杀列表'},
            {'path': '/api/drama/list', 'name': '短剧列表'},
            {'path': '/api/novel/list', 'name': '小说列表'},
        ],
    },
    
    'supervisor': {
        'container': os.getenv('MONITOR_SUPERVISOR_CONTAINER', 'web-supervisor'),
        'http_host': os.getenv('MONITOR_SUPERVISOR_HTTP_HOST', 'supervisor'),
        'http_port': int(os.getenv('MONITOR_SUPERVISOR_HTTP_PORT', 9001)),
        'http_user': os.getenv('MONITOR_SUPERVISOR_HTTP_USER', 'admin'),
        'http_password': os.getenv('MONITOR_SUPERVISOR_HTTP_PASSWORD', 'DJXS_supervisor_123456'),
        'critical_processes': [
            'flash-sale-order-consume',
            'flash-sale-risk-log-consume',
            'content-stat-consume',
            'channel-distribution-consume',
        ],
    },
    
    'log': {
        'supervisor_log': os.getenv('MONITOR_SUPERVISOR_LOG', '/home/wsg/web_project/logs/supervisor/supervisord.log'),
        'error_count_threshold': int(os.getenv('MONITOR_ERROR_COUNT_THRESHOLD', 10)),
    },
    
    'system': {
        'cpu_available_threshold': float(os.getenv('MONITOR_SYSTEM_CPU_AVAILABLE_THRESHOLD', 10.0)),
        'memory_available_threshold': float(os.getenv('MONITOR_SYSTEM_MEMORY_AVAILABLE_THRESHOLD', 5.0)),
    },
}

NOTIFY_CONFIG = {
    'use_wechat': os.getenv('MONITOR_USE_WECHAT', 'false').lower() == 'true',
    'use_dingtalk': os.getenv('MONITOR_USE_DINGTALK', 'false').lower() == 'true',
    'use_email': os.getenv('MONITOR_USE_EMAIL', 'false').lower() == 'true',
    
    'wechat_webhook': os.getenv('MONITOR_WECHAT_WEBHOOK', ''),
    'dingtalk_webhook': os.getenv('MONITOR_DINGTALK_WEBHOOK', ''),
    
    'email_smtp': os.getenv('MONITOR_EMAIL_SMTP', 'smtp.qq.com'),
    'email_port': int(os.getenv('MONITOR_EMAIL_PORT', 465)),
    'email_user': os.getenv('MONITOR_EMAIL_USER', ''),
    'email_pass': os.getenv('MONITOR_EMAIL_PASS', ''),
    'email_to': os.getenv('MONITOR_EMAIL_TO', '').split(',') if os.getenv('MONITOR_EMAIL_TO') else [],
}

ALERT_SUPPRESS_SECONDS = int(os.getenv('MONITOR_ALERT_SUPPRESS_SECONDS', 300))
ALERT_STATE_FILE = os.getenv('MONITOR_ALERT_STATE_FILE', '/tmp/monitor_alert_state.json')

# 检测是否在容器内部运行
IN_CONTAINER = os.path.exists('/var/www/html/app/djxs_manage')

if IN_CONTAINER:
    # 在容器内部运行，直接使用容器内部路径，忽略环境变量
    LOG_PATH = '/var/www/html/app/djxs_manage/monitor/site_monitor.log'
    # 容器内部默认禁用 Docker 检查
    MONITOR_CONFIG['docker']['enabled'] = False
else:
    # 在主机上运行，使用环境变量或默认路径
    LOG_PATH = os.getenv('MONITOR_LOG_PATH', '/home/wsg/web_project/app/djxs_manage/monitor/site_monitor.log')
BACKUP_LOG_PATH = '/tmp/site_monitor.log'

# 获取 logger 实例
logger = logging.getLogger(__name__)

# 如果 logger 已经有处理器，说明已经配置过了，跳过
if not logger.handlers:
    logger.setLevel(logging.INFO)
    formatter = logging.Formatter('%(asctime)s [%(levelname)s] %(message)s')
    
    # 添加控制台处理器
    console_handler = logging.StreamHandler()
    console_handler.setFormatter(formatter)
    logger.addHandler(console_handler)
    
    # 尝试添加文件处理器
    file_written = False
    try:
        file_handler = logging.FileHandler(LOG_PATH)
        file_handler.setFormatter(formatter)
        logger.addHandler(file_handler)
        file_written = True
        logger.info(f"日志文件已配置: {LOG_PATH}")
    except OSError as e:
        # 文件系统只读，尝试使用备份位置
        print(f"警告: 无法写入主日志文件 {LOG_PATH}: {e}")
        try:
            backup_handler = logging.FileHandler(BACKUP_LOG_PATH)
            backup_handler.setFormatter(formatter)
            logger.addHandler(backup_handler)
            file_written = True
            print(f"已切换到备份日志位置: {BACKUP_LOG_PATH}")
        except OSError as e2:
            # 备份位置也无法写入，只使用控制台输出
            print(f"警告: 无法写入备份日志文件 {BACKUP_LOG_PATH}: {e2}")
            print("只能使用控制台输出，无持久化日志")


class AlertSuppressor:
    """告警抑制器：相同告警在指定时间内只发送一次"""
    
    def __init__(self, state_file):
        self.state_file = state_file
        self.state = self._load_state()
    
    def _load_state(self):
        try:
            if os.path.exists(self.state_file):
                with open(self.state_file, 'r') as f:
                    return json.load(f)
        except Exception as e:
            logger.error(f"加载告警状态失败: {e}")
        return {}
    
    def _save_state(self):
        try:
            with open(self.state_file, 'w') as f:
                json.dump(self.state, f)
        except Exception as e:
            logger.error(f"保存告警状态失败: {e}")
    
    def should_alert(self, alert_key):
        """判断是否应该发送告警"""
        now = time.time()
        last_alert_time = self.state.get(alert_key, 0)
        
        if now - last_alert_time > ALERT_SUPPRESS_SECONDS:
            self.state[alert_key] = now
            self._save_state()
            return True
        return False


class Notifier:
    """告警通知器"""
    
    @staticmethod
    def send_wechat(message, title="网站监控告警"):
        """发送企业微信告警"""
        if not NOTIFY_CONFIG['wechat_webhook']:
            return False
        
        data = {
            "msgtype": "markdown",
            "markdown": {
                "content": f"## {title}\n{message}"
            }
        }
        
        try:
            resp = requests.post(NOTIFY_CONFIG['wechat_webhook'], json=data, timeout=10)
            if resp.status_code == 200:
                result = resp.json()
                if result.get('errcode') == 0:
                    logger.info("企业微信通知发送成功")
                    return True
                else:
                    logger.error(f"企业微信返回错误: {result}")
            else:
                logger.error(f"企业微信HTTP错误: {resp.status_code}")
        except Exception as e:
            logger.error(f"企业微信异常: {e}")
        return False
    
    @staticmethod
    def send_dingtalk(message, title="网站监控告警"):
        """发送钉钉告警"""
        if not NOTIFY_CONFIG['dingtalk_webhook']:
            return False
        
        data = {
            "msgtype": "markdown",
            "markdown": {
                "title": title,
                "text": f"## {title}\n{message}"
            }
        }
        
        try:
            resp = requests.post(NOTIFY_CONFIG['dingtalk_webhook'], json=data, timeout=10)
            if resp.status_code == 200:
                logger.info("钉钉通知发送成功")
                return True
            else:
                logger.error(f"钉钉HTTP错误: {resp.status_code}")
        except Exception as e:
            logger.error(f"钉钉异常: {e}")
        return False
    
    @staticmethod
    def send_email(subject, body):
        """发送邮件告警"""
        if not HAS_EMAIL:
            return False
        if not NOTIFY_CONFIG['email_user'] or not NOTIFY_CONFIG['email_to']:
            return False
        
        msg = MIMEMultipart()
        msg['Subject'] = subject
        msg['From'] = NOTIFY_CONFIG['email_user']
        msg['To'] = ", ".join(NOTIFY_CONFIG['email_to'])
        msg.attach(MIMEText(body, 'html', 'utf-8'))
        
        try:
            with smtplib.SMTP_SSL(NOTIFY_CONFIG['email_smtp'], NOTIFY_CONFIG['email_port']) as server:
                server.login(NOTIFY_CONFIG['email_user'], NOTIFY_CONFIG['email_pass'])
                server.sendmail(
                    NOTIFY_CONFIG['email_user'],
                    NOTIFY_CONFIG['email_to'],
                    msg.as_string()
                )
            logger.info("邮件发送成功")
            return True
        except Exception as e:
            logger.error(f"邮件发送失败: {e}")
        return False
    
    @classmethod
    def notify(cls, title, message):
        """统一通知入口"""
        results = []
        
        if NOTIFY_CONFIG['use_wechat']:
            results.append(('企业微信', cls.send_wechat(message, title)))
        
        if NOTIFY_CONFIG['use_dingtalk']:
            results.append(('钉钉', cls.send_dingtalk(message, title)))
        
        if NOTIFY_CONFIG['use_email']:
            results.append(('邮件', cls.send_email(title, message)))
        
        success = any(r[1] for r in results)
        for channel, ok in results:
            logger.info(f"{channel}通知: {'成功' if ok else '失败'}")
        
        return success


class Monitor:
    """监控检查器"""
    
    def __init__(self):
        self.alerts = []
        self.suppressor = AlertSuppressor(ALERT_STATE_FILE)
    
    def add_alert(self, level, category, message):
        """添加告警"""
        alert_key = f"{category}:{message[:50]}"
        
        if self.suppressor.should_alert(alert_key):
            self.alerts.append({
                'level': level,
                'category': category,
                'message': message,
                'time': datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            })
            logger.warning(f"[{level}] {category}: {message}")
        else:
            logger.info(f"[抑制] {category}: {message}")
    
    def check_docker_containers(self):
        """检查 Docker 容器状态"""
        cfg = MONITOR_CONFIG['docker']
        
        if not cfg['enabled']:
            return
        
        try:
            cmd = "docker ps --format '{{.Names}}\t{{.Status}}'"
            result = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=10)
            
            if result.returncode != 0:
                self.add_alert('CRITICAL', 'Docker', f"Docker 命令执行失败: {result.stderr.strip()}")
                return
            
            running_containers = {}
            for line in result.stdout.strip().split('\n'):
                if not line.strip():
                    continue
                parts = line.split('\t')
                if len(parts) >= 2:
                    running_containers[parts[0]] = parts[1]
            
            for container_name in cfg['containers']:
                if container_name not in running_containers:
                    self.add_alert('CRITICAL', 'Docker', f"容器未运行: {container_name}")
                else:
                    status = running_containers[container_name]
                    if 'Up' not in status:
                        self.add_alert('WARNING', 'Docker', f"容器状态异常: {container_name} {status}")
                    else:
                        logger.info(f"容器 {container_name}: {status}")
        
        except Exception as e:
            self.add_alert('CRITICAL', 'Docker', f"Docker 检查异常: {str(e)}")
    
    def check_mysql(self):
        """检查 MySQL 状态（使用 Python 原生连接）"""
        cfg = MONITOR_CONFIG['mysql']
        
        # 直接使用配置文件中的主机名
        mysql_host = cfg['host']
        
        if not HAS_MYSQL:
            logger.warning("未安装 pymysql 模块，使用 socket 连接检查")
            try:
                sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
                sock.settimeout(5)
                result = sock.connect_ex((mysql_host, cfg['port']))
                sock.close()
                
                if result == 0:
                    logger.info(f"MySQL 端口可达: {mysql_host}:{cfg['port']}")
                else:
                    self.add_alert('CRITICAL', 'MySQL', f"MySQL 端口不可达: {mysql_host}:{cfg['port']}")
            except Exception as e:
                self.add_alert('CRITICAL', 'MySQL', f"MySQL 检查异常: {str(e)}")
            return
        
        try:
            conn = pymysql.connect(
                host=mysql_host,
                port=cfg['port'],
                user=cfg['user'],
                password=cfg['password'],
                database=cfg['database'],
                connect_timeout=5
            )
            
            with conn.cursor() as cursor:
                cursor.execute("SHOW STATUS LIKE 'Threads_connected'")
                row = cursor.fetchone()
                if row:
                    connections = int(row[1])
                    if connections > cfg['max_connections_threshold']:
                        self.add_alert(
                            'WARNING', 'MySQL',
                            f"连接数过高: {connections}/{cfg['max_connections_threshold']}"
                        )
                    else:
                        logger.info(f"MySQL 连接数: {connections}")
                
                cursor.execute("SELECT VERSION()")
                version = cursor.fetchone()
                if version:
                    logger.info(f"MySQL 版本: {version[0]}")
            
            conn.close()
        
        except pymysql.MySQLError as e:
            self.add_alert('CRITICAL', 'MySQL', f"MySQL 连接失败: {str(e)}")
        except Exception as e:
            self.add_alert('CRITICAL', 'MySQL', f"MySQL 检查异常: {str(e)}")
    
    def check_redis(self):
        """检查 Redis 状态"""
        cfg = MONITOR_CONFIG['redis']
        
        # 直接使用配置文件中的主机名
        redis_host = cfg['host']
        
        if not HAS_REDIS:
            logger.warning("未安装 redis 模块，使用 socket 连接检查")
            try:
                sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
                sock.settimeout(5)
                result = sock.connect_ex((redis_host, cfg['port']))
                sock.close()
                
                if result == 0:
                    logger.info(f"Redis 端口可达: {redis_host}:{cfg['port']}")
                else:
                    self.add_alert('CRITICAL', 'Redis', f"Redis 端口不可达: {redis_host}:{cfg['port']}")
            except Exception as e:
                self.add_alert('CRITICAL', 'Redis', f"Redis 检查异常: {str(e)}")
            return
        
        try:
            r = redis.Redis(
                host=redis_host,
                port=cfg['port'],
                password=cfg['password'],
                db=cfg['db'],
                socket_timeout=5
            )
            
            if not r.ping():
                self.add_alert('CRITICAL', 'Redis', 'Redis PING 失败')
                return
            
            info = r.info('memory')
            max_memory = info.get('maxmemory', 0)
            used_memory = info.get('used_memory', 0)
            
            if max_memory > 0:
                usage_percent = (used_memory / max_memory) * 100
                if usage_percent > cfg['memory_warning_percent']:
                    self.add_alert(
                        'WARNING', 'Redis',
                        f"内存使用率过高: {usage_percent:.1f}% ({used_memory/1024/1024:.0f}MB/{max_memory/1024/1024:.0f}MB)"
                    )
                else:
                    logger.info(f"Redis 内存使用: {usage_percent:.1f}%")
            
            clients = r.info('clients')
            connected = clients.get('connected_clients', 0)
            logger.info(f"Redis 客户端连接: {connected}")
        
        except Exception as e:
            self.add_alert('CRITICAL', 'Redis', f"Redis 检查异常: {str(e)}")
    
    def check_rabbitmq(self):
        """检查 RabbitMQ 状态"""
        cfg = MONITOR_CONFIG['rabbitmq']
        
        try:
            url = f"http://{cfg['host']}:{cfg['port']}/api/overview"
            resp = requests.get(
                url,
                auth=(cfg['user'], cfg['password']),
                timeout=5
            )
            
            if resp.status_code != 200:
                self.add_alert('CRITICAL', 'RabbitMQ', f"Management API 不可达: HTTP {resp.status_code}")
                return
            
            overview = resp.json()
            node_info = overview.get('node', 'unknown')
            if isinstance(node_info, dict):
                node_name = node_info.get('name', 'unknown')
            else:
                node_name = str(node_info)
            logger.info(f"RabbitMQ 状态: {node_name}")
            
            object_totals = overview.get('object_totals', {})
            queue_count = object_totals.get('queues', 0)
            logger.info(f"RabbitMQ 队列总数: {queue_count}")
            
            for queue_name in cfg['flash_sale_queues']:
                queue_name_encoded = quote(queue_name, safe='')
                queue_url = f"http://{cfg['host']}:{cfg['port']}/api/queues/%2f/{queue_name_encoded}"
                queue_resp = requests.get(
                    queue_url,
                    auth=(cfg['user'], cfg['password']),
                    timeout=5
                )
                
                if queue_resp.status_code == 200:
                    queue_info = queue_resp.json()
                    messages_ready = queue_info.get('messages_ready', 0)
                    consumers = queue_info.get('consumers', 0)
                    
                    if messages_ready > cfg['queue_backlog_threshold']:
                        self.add_alert(
                            'WARNING', 'RabbitMQ',
                            f"队列积压: {queue_name} Ready={messages_ready} (阈值: {cfg['queue_backlog_threshold']})"
                        )
                    else:
                        logger.info(f"队列 {queue_name}: Ready={messages_ready}, Consumers={consumers}")
                else:
                    logger.warning(f"无法获取队列信息: {queue_name} (HTTP {queue_resp.status_code})")
        
        except requests.exceptions.ConnectionError:
            self.add_alert('CRITICAL', 'RabbitMQ', 'RabbitMQ Management API 连接失败')
        except requests.exceptions.Timeout:
            self.add_alert('CRITICAL', 'RabbitMQ', 'RabbitMQ Management API 超时')
        except Exception as e:
            self.add_alert('CRITICAL', 'RabbitMQ', f"RabbitMQ 检查异常: {str(e)}")
    
    def check_supervisor(self):
        """检查 Supervisor 进程状态"""
        cfg = MONITOR_CONFIG['supervisor']
        
        if IN_CONTAINER:
            # 容器内部运行时，通过 HTTP 接口检查
            try:
                # 尝试使用 XML-RPC 接口
                import xmlrpc.client
                
                url = f"http://{cfg['http_user']}:{cfg['http_password']}@{cfg['http_host']}:{cfg['http_port']}/RPC2"
                server = xmlrpc.client.ServerProxy(url)
                processes = server.supervisor.getAllProcessInfo()
                
                for process in processes:
                    process_name = process.get('name')
                    status = process.get('statename')
                    
                    for critical in cfg['critical_processes']:
                        if critical in process_name:
                            if status != 'RUNNING':
                                self.add_alert(
                                    'CRITICAL', 'Supervisor',
                                    f"关键进程异常: {process_name} 状态={status}"
                                )
                            else:
                                logger.info(f"进程 {process_name}: {status}")
            except ImportError:
                # 如果没有 xmlrpc.client 模块，尝试使用 HTTP 基本认证
                try:
                    url = f"http://{cfg['http_host']}:{cfg['http_port']}/status"
                    response = requests.get(
                        url, 
                        auth=(cfg['http_user'], cfg['http_password']), 
                        timeout=10
                    )
                    
                    if response.status_code == 200:
                        logger.info("Supervisor 状态页面可达")
                    else:
                        self.add_alert('CRITICAL', 'Supervisor', f"Supervisor HTTP 接口连接失败: {response.status_code}")
                except subprocess.TimeoutExpired:
                    self.add_alert('CRITICAL', 'Supervisor', 'Supervisor 检查超时')
                except Exception as e:
                    self.add_alert('CRITICAL', 'Supervisor', f"Supervisor HTTP 检查异常: {str(e)}")
            except subprocess.TimeoutExpired:
                self.add_alert('CRITICAL', 'Supervisor', 'Supervisor 检查超时')
            except Exception as e:
                self.add_alert('CRITICAL', 'Supervisor', f"Supervisor XML-RPC 检查异常: {str(e)}")
            return
        
        # 主机上运行时，通过 docker 命令检查
        container = cfg['container']
        try:
            cmd = f"docker exec {container} supervisorctl -c /etc/supervisor/supervisord.conf status"
            result = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=20)
            
            if result.returncode not in [0, 3]:
                self.add_alert('CRITICAL', 'Supervisor', f"supervisorctl 执行失败: {result.stderr.strip()}")
                return
            
            if not result.stdout.strip():
                self.add_alert('CRITICAL', 'Supervisor', 'supervisorctl 返回空输出')
                return
            
            for line in result.stdout.strip().split('\n'):
                if not line.strip():
                    continue
                
                parts = line.split()
                if len(parts) < 2:
                    continue
                
                process_name = parts[0]
                status = parts[1]
                
                for critical in cfg['critical_processes']:
                    if critical in process_name:
                        if status != 'RUNNING':
                            self.add_alert(
                                'CRITICAL', 'Supervisor',
                                f"关键进程异常: {process_name} 状态={status}"
                            )
                        else:
                            logger.info(f"进程 {process_name}: {status}")
        
        except subprocess.TimeoutExpired:
            self.add_alert('CRITICAL', 'Supervisor', 'Supervisor 检查超时')
        except Exception as e:
            self.add_alert('CRITICAL', 'Supervisor', f"Supervisor 检查异常: {str(e)}")
    
    def check_api_health(self):
        """检查 API 健康状态"""
        cfg = MONITOR_CONFIG['api']
        
        for endpoint in cfg['endpoints']:
            url = f"{cfg['base_url']}{endpoint['path']}"
            
            try:
                start_time = time.time()
                resp = requests.get(url, timeout=cfg['timeout'])
                response_time = time.time() - start_time
                
                if resp.status_code != 200:
                    self.add_alert(
                        'CRITICAL', 'API',
                        f"接口异常: {endpoint['name']} HTTP {resp.status_code}"
                    )
                elif response_time > cfg['response_time_warning']:
                    self.add_alert(
                        'WARNING', 'API',
                        f"接口响应慢: {endpoint['name']} {response_time:.2f}s (阈值: {cfg['response_time_warning']}s)"
                    )
                else:
                    logger.info(f"API {endpoint['name']}: {response_time:.2f}s")
            
            except requests.exceptions.Timeout:
                self.add_alert('CRITICAL', 'API', f"接口超时: {endpoint['name']}")
            except requests.exceptions.ConnectionError:
                self.add_alert('CRITICAL', 'API', f"接口连接失败: {endpoint['name']}")
            except Exception as e:
                self.add_alert('CRITICAL', 'API', f"接口检查异常: {endpoint['name']} {str(e)}")
    
    def check_error_logs(self):
        """检查错误日志"""
        cfg = MONITOR_CONFIG['log']
        
        log_files = [cfg['supervisor_log']]
        
        for log_file in log_files:
            if not os.path.exists(log_file):
                logger.warning(f"日志文件不存在: {log_file}")
                continue
            
            try:
                error_count = 0
                recent_errors = []
                
                cmd = f"tail -n 1000 {log_file}"
                result = subprocess.run(cmd, shell=True, capture_output=True, text=True, timeout=5)
                
                if result.returncode == 0:
                    for line in result.stdout.split('\n'):
                        if 'error' in line.lower() or 'warning' in line.lower():
                            error_count += 1
                            if len(recent_errors) < 5:
                                recent_errors.append(line.strip()[:200])
                
                if error_count > cfg['error_count_threshold']:
                    error_summary = '\n'.join(recent_errors)
                    self.add_alert(
                        'WARNING', 'Log',
                        f"错误日志过多: {log_file} 发现 {error_count} 条错误\n{error_summary}"
                    )
                else:
                    logger.info(f"日志 {log_file}: {error_count} 条错误")
            
            except Exception as e:
                logger.error(f"检查日志失败 {log_file}: {e}")
    
    def check_system(self):
        """检查系统 CPU 和内存使用情况"""
        cfg = MONITOR_CONFIG['system']
        
        try:
            # 尝试使用 psutil 库
            try:
                import psutil
                
                # 检查 CPU 使用率
                cpu_percent = psutil.cpu_percent(interval=1)
                cpu_available = 100 - cpu_percent
                
                if cpu_available < cfg['cpu_available_threshold']:
                    self.add_alert(
                        'WARNING', '系统',
                        f"CPU 可用率过低: {cpu_available:.1f}% (使用率: {cpu_percent:.1f}%)"
                    )
                
                # 检查内存使用率
                memory = psutil.virtual_memory()
                memory_available_percent = memory.available * 100 / memory.total
                
                if memory_available_percent < cfg['memory_available_threshold']:
                    self.add_alert(
                        'WARNING', '系统',
                        f"内存可用率过低: {memory_available_percent:.1f}% (可用: {memory.available/1024/1024/1024:.1f}GB/总计: {memory.total/1024/1024/1024:.1f}GB)"
                    )
                
            except ImportError:
                pass
                    
        except Exception as e:
            self.add_alert('WARNING', '系统', f"系统监控异常: {str(e)}")

    def run_all_checks(self):
        """执行所有检查"""
        logger.info("=" * 50)
        logger.info("开始执行监控检查")
        logger.info("=" * 50)
        
        # 记录当前系统资源占用
        try:
            import psutil
            cpu_percent = psutil.cpu_percent(interval=0.1)
            memory = psutil.virtual_memory()
            memory_percent = memory.percent
            memory_used = memory.used / 1024 / 1024 / 1024
            memory_total = memory.total / 1024 / 1024 / 1024
            logger.info(f"当前系统资源占用: CPU={cpu_percent:.1f}%, 内存={memory_percent:.1f}% ({memory_used:.1f}GB/{memory_total:.1f}GB)")
        except ImportError:
            # 尝试使用系统命令获取基本信息
            try:
                cpu_result = subprocess.run(['top', '-bn1'], capture_output=True, text=True, timeout=5)
                mem_result = subprocess.run(['free', '-h'], capture_output=True, text=True, timeout=5)
                logger.info("当前系统资源占用: 已通过系统命令检查")
            except Exception as e:
                logger.warning(f"获取系统资源占用失败: {str(e)}")
        except Exception as e:
            logger.warning(f"获取系统资源占用失败: {str(e)}")
        
        self.check_docker_containers()
        self.check_system()  # 添加系统监控
        self.check_mysql()
        self.check_redis()
        self.check_rabbitmq()
        self.check_supervisor()
        self.check_api_health()
        self.check_error_logs()
        
        if self.alerts:
            critical_alerts = [a for a in self.alerts if a['level'] == 'CRITICAL']
            warning_alerts = [a for a in self.alerts if a['level'] == 'WARNING']
            
            title = f"🚨 网站监控告警 ({len(critical_alerts)}严重/{len(warning_alerts)}警告)"
            
            message = "### 告警详情\n\n"
            
            if critical_alerts:
                message += "#### 🔴 严重告警\n"
                for alert in critical_alerts:
                    message += f"- **[{alert['category']}]** {alert['message']}\n"
                message += "\n"
            
            if warning_alerts:
                message += "#### 🟡 警告信息\n"
                for alert in warning_alerts:
                    message += f"- **[{alert['category']}]** {alert['message']}\n"
                message += "\n"
            
            message += f"\n> 检查时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}"
            
            Notifier.notify(title, message)
        else:
            logger.info("所有检查项正常")
        
        return self.alerts


def main():
    """主函数"""
    try:
        monitor = Monitor()
        alerts = monitor.run_all_checks()
        
        if alerts:
            logger.info(f"发现 {len(alerts)} 条告警")
            sys.exit(1)
        else:
            logger.info("监控检查完成，无异常")
            sys.exit(0)
    except KeyboardInterrupt:
        logger.info("监控检查被用户中断")
        sys.exit(0)
    except Exception as e:
        logger.error(f"监控检查异常: {e}", exc_info=True)
        sys.exit(2)


if __name__ == '__main__':
    main()
