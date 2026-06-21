# 海外仓一件代发金路径 - 部署文档

## 目录

1. [项目概述](#1-项目概述)
2. [技术栈](#2-技术栈)
3. [环境要求](#3-环境要求)
4. [环境变量配置](#4-环境变量配置)
5. [数据库迁移与种子](#5-数据库迁移与种子)
6. [队列任务配置](#6-队列任务配置)
7. [后端部署](#7-后端部署)
8. [前端部署](#8-前端部署)
9. [验收命令](#9-验收命令)
10. [监控与告警](#10-监控与告警)
11. [故障排查](#11-故障排查)

---

## 1. 项目概述

海外仓一件代发金路径是电商订单库存后台的核心功能模块，提供：

- 海外仓WMS系统对接与配置管理
- 代发订单全生命周期管理（创建→审核→推送WMS→发货→物流轨迹→签收完成）
- 自动化规则引擎（自动分仓、自动审核、自动推单、自动通知等）
- WMS回调处理（订单状态、发货通知、物流轨迹、库存同步、库存调整）
- 五大数据表支撑：海外仓配置、代发订单、订单商品项、自动化规则、WMS回调日志

**金路径核心流程**：
```
创建代发单 → 自动分仓 → 自动审核 → 推送到WMS队列 → 
WMS处理 → 发货回调 → 物流轨迹同步 → 签收 → 完成
```

---

## 2. 技术栈

### 后端
- **框架**：Laravel 10+ (PHP 8.1+)
- **数据库**：MySQL 8.0+
- **缓存/队列**：Redis 6.0+
- **认证**：Laravel Sanctum (Token认证)
- **API签名**：MD5 (k排序 + app_key + timestamp + nonce + secret)

### 前端
- **框架**：Vue 2.7 + Vue Router 3 + Vuex 3
- **UI组件**：Element UI 2.15
- **构建工具**：Vite 4.x
- **HTTP客户端**：Axios 1.6
- **测试框架**：Jest 29 + @vue/test-utils 1.3

---

## 3. 环境要求

### 3.1 服务器要求
| 组件 | 最低版本 | 推荐版本 |
|------|---------|---------|
| PHP | 8.1 | 8.2+ |
| MySQL | 8.0 | 8.0+ |
| Redis | 6.0 | 7.0+ |
| Node.js | 16.0 | 18 LTS |
| npm | 8.0 | 9.0+ |
| Composer | 2.0 | 2.5+ |
| Nginx | 1.20 | 1.24+ |

### 3.2 PHP扩展要求
```
php-mysqlnd
php-redis
php-curl
php-mbstring
php-xml
php-json
php-bcmath
php-openssl
php-fileinfo
```

### 3.3 PHP配置建议
```ini
memory_limit = 512M
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
date.timezone = Asia/Shanghai
```

---

## 4. 环境变量配置

### 4.1 后端环境变量

复制 `.env.example` 为 `.env`：

```bash
cd backend
cp .env.example .env
```

#### 4.1.1 基础配置
| 变量名 | 说明 | 默认值 | 必填 |
|--------|------|--------|------|
| APP_NAME | 应用名称 | 电商订单库存后台 | 是 |
| APP_ENV | 运行环境 | local | 是 (production/staging/local/testing) |
| APP_KEY | 应用密钥 | - | 是 (`php artisan key:generate`) |
| APP_DEBUG | 调试模式 | true | 生产环境必须为 false |
| APP_URL | 应用URL | http://localhost:8000 | 是 |

#### 4.1.2 数据库配置
| 变量名 | 说明 | 默认值 | 必填 |
|--------|------|--------|------|
| DB_CONNECTION | 数据库驱动 | mysql | 是 |
| DB_HOST | 数据库主机 | 127.0.0.1 | 是 |
| DB_PORT | 数据库端口 | 3306 | 是 |
| DB_DATABASE | 数据库名 | ecommerce_order_inventory | 是 |
| DB_USERNAME | 用户名 | root | 是 |
| DB_PASSWORD | 密码 | - | 是 |

#### 4.1.3 Redis/队列配置
| 变量名 | 说明 | 默认值 | 必填 |
|--------|------|--------|------|
| REDIS_CLIENT | Redis客户端 | phpredis | 是 (phpredis/predis) |
| REDIS_HOST | Redis主机 | 127.0.0.1 | 是 |
| REDIS_PORT | Redis端口 | 6379 | 是 |
| REDIS_PASSWORD | Redis密码 | null | 如有密码必填 |
| REDIS_QUEUE | Redis队列名 | dropship | 是 |
| QUEUE_CONNECTION | 队列驱动 | redis | 是 (redis/database/sync) |
| QUEUE_FAILED_DRIVER | 失败队列驱动 | database-uuids | 是 |

#### 4.1.4 代发订单队列配置
| 变量名 | 说明 | 默认值 | 必填 |
|--------|------|--------|------|
| DROPSHIP_QUEUE_NAME | 代发推送队列名 | dropship | 是 |
| DROPSHIP_CALLBACK_QUEUE_NAME | WMS回调队列名 | dropship_callback | 是 |
| DROPSHIP_PUSH_MAX_ATTEMPTS | 推单最大尝试次数 | 5 | 是 |
| DROPSHIP_PUSH_BACKOFF_SECONDS | 推单退避基础秒数 | 60 | 是 |
| DROPSHIP_CALLBACK_MAX_ATTEMPTS | 回调处理最大尝试次数 | 3 | 是 |
| DROPSHIP_CALLBACK_BACKOFF_SECONDS | 回调退避基础秒数 | 60 | 是 |
| DROPSHIP_WMS_API_TIMEOUT | WMS API超时(秒) | 30 | 是 |
| DROPSHIP_WMS_RETRY_UNTIL_HOURS | 推单重试窗口(小时) | 24 | 是 |
| DROPSHIP_CALLBACK_RETRY_UNTIL_HOURS | 回调重试窗口(小时) | 12 | 是 |

#### 4.1.5 WMS集成配置
| 变量名 | 说明 | 默认值 | 必填 |
|--------|------|--------|------|
| WMS_DEFAULT_PROVIDER | 默认WMS服务商 | custom | 是 |
| WMS_SUPPORTED_PROVIDERS | 支持的WMS服务商列表 | custom,shipbob,easypost,4px,yanwen | 是 |
| DROPSHIP_SIGN_ALGORITHM | 签名算法 | md5 | 是 |
| DROPSHIP_SIGN_UPPERCASE | 签名是否大写 | true | 是 |

#### 4.1.6 业务默认配置
| 变量名 | 说明 | 默认值 | 必填 |
|--------|------|--------|------|
| DROPSHIP_DEFAULT_CURRENCY | 默认结算币种 | USD | 是 |
| DROPSHIP_DEFAULT_COUNTRY | 默认国家 | US | 是 |
| DROPSHIP_AUTO_PUSH_ENABLED | 全局自动推单开关 | false | 是 |
| DROPSHIP_AUTO_SYNC_INVENTORY | 全局自动同步库存开关 | false | 是 |
| DROPSHIP_INVENTORY_SYNC_INTERVAL_MIN | 库存同步间隔(分钟) | 60 | 是 |
| DROPSHIP_AUTO_SYNC_TRACKING | 全局自动同步物流轨迹开关 | false | 是 |
| DROPSHIP_TRACKING_SYNC_INTERVAL_MIN | 物流轨迹同步间隔(分钟) | 120 | 是 |

#### 4.1.7 通知告警配置
| 变量名 | 说明 | 默认值 | 必填 |
|--------|------|--------|------|
| DROPSHIP_NOTIFICATION_EMAIL_ENABLED | 邮件通知开关 | false | 否 |
| DROPSHIP_NOTIFICATION_WEBHOOK_URL | 告警Webhook地址 | - | 否 |
| DROPSHIP_ALERT_RECIPIENTS | 告警收件人(逗号分隔) | admin@example.com | 否 |

---

### 4.2 前端环境变量

复制 `.env.example` 为 `.env.development` 或 `.env.production`：

```bash
cd frontend
cp .env.example .env.development
```

| 变量名 | 说明 | 默认值 |
|--------|------|--------|
| VITE_APP_TITLE | 应用标题 | 电商订单库存后台 |
| VITE_APP_API_BASE_URL | API基础路径 | /api/v1 |

**注意**：
- 开发环境：Vite代理会将 `/api/v1` 转发到后端
- 生产环境：`VITE_APP_API_BASE_URL` 需设置为完整的后端API域名，如 `https://api.example.com/api/v1`

---

## 5. 数据库迁移与种子

### 5.1 数据库迁移

海外仓一件代发模块包含 **5 张数据表**：

| 迁移文件 | 数据表 | 说明 |
|----------|--------|------|
| `100001` | `oversea_warehouse_configs` | 海外仓WMS配置 |
| `100002` | `dropship_orders` | 代发订单主表 |
| `100003` | `dropship_order_items` | 代发订单商品项 |
| `100004` | `automation_rules` | 自动化规则 |
| `100005` | `wms_callback_logs` | WMS回调日志 |

**前置依赖表**（需提前存在）：
- `users` - 用户表
- `warehouses` - 仓库表
- `orders` - 平台订单表
- `order_items` - 平台订单商品项
- `products` - 商品表
- `suppliers` - 供应商表
- `distributors` - 分销商表

### 5.2 执行迁移

```bash
cd backend

# 生成应用密钥（首次部署）
php artisan key:generate

# 执行所有迁移
php artisan migrate

# 仅执行海外仓模块迁移
php artisan migrate --path=database/migrations/2026_06_21_100001_create_oversea_warehouse_configs_table.php
php artisan migrate --path=database/migrations/2026_06_21_100002_create_dropship_orders_table.php
php artisan migrate --path=database/migrations/2026_06_21_100003_create_dropship_order_items_table.php
php artisan migrate --path=database/migrations/2026_06_21_100004_create_automation_rules_table.php
php artisan migrate --path=database/migrations/2026_06_21_100005_create_wms_callback_logs_table.php

# 回滚迁移（谨慎使用）
php artisan migrate:rollback --step=5
```

### 5.3 数据种子

种子文件：[OverseaDropshipDatabaseSeeder.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/database/seeders/OverseaDropshipDatabaseSeeder.php)

```bash
cd backend

# 执行所有种子
php artisan db:seed --class=OverseaDropshipDatabaseSeeder

# 或分步骤执行（如果有单独的Seeder）
php artisan db:seed --class=UsersTableSeeder
php artisan db:seed --class=WarehousesTableSeeder
php artisan db:seed --class=OverseaWarehouseConfigsTableSeeder
php artisan db:seed --class=AutomationRulesTableSeeder
php artisan db:seed --class=SampleDropshipOrdersSeeder
```

**种子数据内容**：
- 3个测试用户（admin/operator/warehouse）
- 3个海外仓（洛杉矶/法兰克福/东京）
- 3套WMS配置（ShipBob/EasyPost/4PX）
- 5条自动化规则（自动分仓×2、自动审核、自动推单、异常通知）
- 3个示例代发订单（已发货/待审核/推送失败）+ 5个商品项

**测试账号**：
| 角色 | 邮箱 | 密码 |
|------|------|------|
| 超级管理员 | admin@example.com | admin123456 |
| 运营主管 | operator@example.com | operator123 |
| 仓库管理员 | warehouse@example.com | warehouse123 |

---

## 6. 队列任务配置

### 6.1 队列任务清单

海外仓模块使用 **2 个独立队列** 和 **2 个核心 Job**：

| 队列名 | Job类 | 说明 | 重试策略 |
|--------|-------|------|----------|
| `dropship` | [ProcessDropshipOrderJob.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/app/Jobs/ProcessDropshipOrderJob.php) | 代发订单推送WMS | 5次重试，退避 60/180/300/600/900s，24小时超时窗口 |
| `dropship_callback` | [ProcessWmsCallbackJob.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/app/Jobs/ProcessWmsCallbackJob.php) | WMS回调异步处理 | 3次重试，退避 60/300/900s，12小时超时窗口 |

### 6.2 队列Worker启动

使用 `queue:work` 启动队列消费进程（**生产环境必须使用Supervisor/Systemd守护**）：

```bash
cd backend

# 启动代发订单推送队列（推荐 2-4 个进程）
php artisan queue:work redis --queue=dropship --tries=5 --timeout=120

# 启动WMS回调处理队列（推荐 1-2 个进程）
php artisan queue:work redis --queue=dropship_callback --tries=3 --timeout=60

# 启动全部队列（默认顺序消费）
php artisan queue:work redis --queue=dropship,dropship_callback --tries=5 --timeout=120
```

### 6.3 Supervisor 配置示例

创建 `/etc/supervisor/conf.d/dropship-queue.conf`：

```ini
[program:dropship-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/backend/artisan queue:work redis --queue=dropship --sleep=3 --tries=5 --timeout=120
autostart=true
autorestart=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/backend/storage/logs/dropship-queue.log
stopwaitsecs=3600

[program:dropship-callback-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/backend/artisan queue:work redis --queue=dropship_callback --sleep=3 --tries=3 --timeout=60
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/backend/storage/logs/dropship-callback-queue.log
stopwaitsecs=3600
```

启动并配置开机自启：

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start dropship-queue:*
sudo supervisorctl start dropship-callback-queue:*
sudo supervisorctl status
```

### 6.4 失败任务处理

```bash
# 查看所有失败任务
php artisan queue:failed

# 重试所有失败任务
php artisan queue:retry all

# 重试指定ID的失败任务
php artisan queue:retry 1 2 3

# 清空所有失败任务
php artisan queue:flush

# 删除指定ID的失败任务
php artisan queue:forget 1
```

### 6.5 队列监控命令

```bash
# 查看队列状态
php artisan queue:monitor redis:default

# 查看失败任务表结构
php artisan queue:failed-table

# 优雅重启所有队列Worker（代码更新后必须执行）
php artisan queue:restart
```

---

## 7. 后端部署

### 7.1 安装依赖

```bash
cd backend
composer install --no-dev --optimize-autoloader
```

### 7.2 配置文件生成

```bash
cp .env.example .env
php artisan key:generate
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 7.3 目录权限

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### 7.4 Nginx配置示例

```nginx
server {
    listen 80;
    server_name api.example.com;
    root /var/www/backend/public;

    index index.php;
    charset utf-8;

    client_max_body_size 50M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # WMS回调接口 - 放宽超时限制
    location ~ ^/api/v1/wms/callback/ {
        fastcgi_read_timeout 120;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 7.5 API路由

路由文件：[dropship_api.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/routes/dropship_api.php)

确保在 `routes/api.php` 或 `app/Providers/RouteServiceProvider.php` 中加载：

```php
// app/Providers/RouteServiceProvider.php
Route::middleware('api')
    ->prefix('api/v1')
    ->group(base_path('routes/dropship_api.php'));
```

**核心接口清单**：

| 方法 | 路径 | 说明 | 认证 |
|------|------|------|------|
| POST | `/api/v1/wms/callback/{warehouseId}` | WMS回调接收入口 | 否 (IP白名单+签名) |
| GET | `/api/v1/dropship/statistics` | 代发订单统计 | 是 |
| POST | `/api/v1/dropship/batch-review` | 批量审核 | 是 |
| POST | `/api/v1/dropship/batch-push` | 批量推送WMS | 是 |
| GET | `/api/v1/dropship/orders` | 代发订单列表 | 是 |
| POST | `/api/v1/dropship/orders` | 创建代发订单 | 是 |
| GET | `/api/v1/dropship/orders/{order}` | 订单详情 | 是 |
| POST | `/api/v1/dropship/orders/{order}/review` | 审核订单 | 是 |
| POST | `/api/v1/dropship/orders/{order}/push` | 推送WMS | 是 |
| POST | `/api/v1/dropship/orders/{order}/retry-push` | 重试推送 | 是 |
| POST | `/api/v1/dropship/orders/{order}/cancel` | 取消订单 | 是 |
| POST | `/api/v1/dropship/orders/{order}/sync-tracking` | 同步物流轨迹 | 是 |
| GET | `/api/v1/warehouse-configs` | 海外仓配置列表 | 是 |
| POST | `/api/v1/warehouse-configs/{config}/test-connection` | 测试WMS连接 | 是 |
| POST | `/api/v1/warehouse-configs/{config}/sync-inventory` | 同步库存 | 是 |
| GET | `/api/v1/automation-rules` | 自动化规则列表 | 是 |
| POST | `/api/v1/automation-rules/{rule}/trigger` | 手动触发规则 | 是 |
| GET | `/api/v1/wms-callback-logs` | WMS回调日志 | 是 |
| POST | `/api/v1/wms-callback-logs/{log}/retry` | 重试回调处理 | 是 |

---

## 8. 前端部署

### 8.1 安装依赖

```bash
cd frontend
npm install --production
```

### 8.2 构建生产包

```bash
cd frontend

# 开发环境
npm run dev

# 生产构建
npm run build

# 本地预览生产构建
npm run preview
```

构建产物输出目录：`frontend/dist/`

### 8.3 Nginx配置示例

```nginx
server {
    listen 80;
    server_name admin.example.com;
    root /var/www/frontend/dist;

    index index.html;
    charset utf-8;

    # Gzip压缩
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript image/svg+xml;
    gzip_min_length 1024;

    # 静态资源缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # SPA路由回退
    location / {
        try_files $uri $uri/ /index.html;
    }

    # API代理（如果前后端同域部署）
    location /api/ {
        proxy_pass http://127.0.0.1:8000/api/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 120;
    }
}
```

### 8.4 前端页面路由

路由配置：[dropship.js](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/frontend/src/router/dropship.js)

| 路径 | 页面 | 说明 |
|------|------|------|
| `/dropship` | Dashboard | 代发管理首页/统计概览 |
| `/dropship/orders` | List.vue | 代发订单列表 |
| `/dropship/orders/create` | Create.vue | 创建代发订单 |
| `/dropship/orders/:id` | Detail.vue | 订单详情 |
| `/warehouse-configs` | List.vue | 海外仓配置列表 |
| `/warehouse-configs/callback-logs` | CallbackLogs.vue | WMS回调日志 |
| `/automation-rules` | List.vue | 自动化规则列表 |
| `/automation-rules/create` | Create.vue | 创建自动化规则 |

---

## 9. 验收命令

### 9.1 后端单元测试

PHPUnit配置：[phpunit.xml](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/phpunit.xml)

```bash
cd backend

# 运行所有测试
vendor/bin/phpunit

# 仅运行金路径测试（核心验收）
vendor/bin/phpunit --filter=GoldenPath

# 金路径测试完整清单：
vendor/bin/phpunit --filter=OverseaDropshipServiceGoldenPathTest
vendor/bin/phpunit --filter=AutomationEngineGoldenPathTest
vendor/bin/phpunit --filter=WmsCallbackGoldenPathTest

# 按测试套件运行
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature

# 仅运行海外仓模块测试
vendor/bin/phpunit tests/Unit/Services/OverseaDropshipServiceTest.php
vendor/bin/phpunit tests/Unit/Services/OverseaDropshipServiceGoldenPathTest.php
vendor/bin/phpunit tests/Unit/Services/AutomationEngineServiceTest.php
vendor/bin/phpunit tests/Unit/Services/AutomationEngineGoldenPathTest.php
vendor/bin/phpunit tests/Unit/Services/WmsIntegrationServiceTest.php
vendor/bin/phpunit tests/Unit/Services/WmsCallbackGoldenPathTest.php
vendor/bin/phpunit tests/Unit/Services/DropshipStateMachineTest.php
vendor/bin/phpunit tests/Unit/Services/DropshipPermissionServiceTest.php
vendor/bin/phpunit tests/Unit/Services/DropshipQueryServiceTest.php
vendor/bin/phpunit tests/Unit/Enums/DropshipOrderStatusTest.php
vendor/bin/phpunit tests/Unit/Enums/AutomationRuleTypeTest.php
vendor/bin/phpunit tests/Unit/Exceptions/DropshipExceptionTest.php
vendor/bin/phpunit tests/Unit/Models/DropshipOrderTest.php
vendor/bin/phpunit tests/Unit/Models/DropshipOrderItemTest.php
vendor/bin/phpunit tests/Unit/Models/OverseaWarehouseConfigTest.php
vendor/bin/phpunit tests/Unit/Models/AutomationRuleTest.php
vendor/bin/phpunit tests/Unit/Http/Controllers/OverseaDropshipControllerStructureTest.php
vendor/bin/phpunit tests/Unit/Http/Controllers/WmsCallbackLogControllerTest.php
vendor/bin/phpunit tests/Feature/Http/Controllers/OverseaDropshipControllerApiTest.php
vendor/bin/phpunit tests/Feature/Http/Controllers/OverseaDropshipControllerFeatureTest.php

# 带代码覆盖率报告（需安装 Xdebug 或 PCOV）
vendor/bin/phpunit --coverage-html=./coverage-report

# 测试命令快捷方式（如配置了composer脚本）
composer test
composer test:unit
composer test:feature
composer test:coverage
```

### 9.2 前端单元测试

Jest配置：[jest.config.js](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/frontend/jest.config.js)

```bash
cd frontend

# 运行所有测试
npm run test

# 监听模式（开发时使用）
npm run test:watch

# 生成覆盖率报告
npm run test:coverage

# 仅运行代发订单相关测试
npm test -- --testPathPattern=OverseaDropship
npm test -- tests/__tests__/views/OverseaDropshipList.spec.js
npm test -- tests/__tests__/views/OverseaDropshipCreate.spec.js
npm test -- tests/__tests__/views/OverseaDropshipDetail.spec.js
npm test -- tests/__tests__/api/dropship.spec.js
npm test -- tests/__tests__/utils/request.spec.js
```

### 9.3 完整验收测试脚本

创建 `scripts/acceptance-test.sh`：

```bash
#!/bin/bash
set -e

echo "=========================================="
echo "  海外仓一件代发金路径 - 验收测试"
echo "=========================================="

PROJECT_ROOT=$(cd "$(dirname "$0")/.." && pwd)

echo ""
echo "[1/5] 检查环境变量配置..."
if [ ! -f "$PROJECT_ROOT/backend/.env" ]; then
    echo "  ✗ 后端 .env 文件不存在"
    exit 1
fi
echo "  ✓ 后端 .env 已配置"

if [ ! -f "$PROJECT_ROOT/frontend/.env.production" ]; then
    echo "  ⚠ 前端 .env.production 不存在（使用默认值）"
else
    echo "  ✓ 前端 .env.production 已配置"
fi

echo ""
echo "[2/5] 检查数据库迁移..."
cd "$PROJECT_ROOT/backend"
php artisan migrate:status --no-ansi 2>&1 | head -30
echo "  ✓ 迁移状态检查完成"

echo ""
echo "[3/5] 运行后端金路径测试..."
vendor/bin/phpunit --filter=GoldenPath --colors=never 2>&1
echo "  ✓ 后端金路径测试通过"

echo ""
echo "[4/5] 运行前端单元测试..."
cd "$PROJECT_ROOT/frontend"
npm test -- --colors=false 2>&1
echo "  ✓ 前端单元测试通过"

echo ""
echo "[5/5] 检查队列进程状态..."
if command -v supervisorctl &> /dev/null; then
    supervisorctl status dropship-queue:* dropship-callback-queue:* 2>&1 || true
fi

echo ""
echo "=========================================="
echo "  验收完成！请查看上方输出确认结果"
echo "=========================================="
```

执行：

```bash
chmod +x scripts/acceptance-test.sh
./scripts/acceptance-test.sh
```

### 9.4 健康检查与冒烟测试

```bash
cd backend

# 应用健康检查
php artisan about

# 路由列表确认
php artisan route:list --path=dropship

# 检查队列状态
php artisan queue:monitor redis:dropship,redis:dropship_callback

# 缓存状态
php artisan config:show
php artisan route:clear
php artisan cache:clear

# 数据库连接测试
php artisan db:show
```

手动冒烟测试API：

```bash
# 获取Token
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"admin123456"}'

# 测试代发订单列表（使用返回的Token）
curl -X GET http://localhost:8000/api/v1/dropship/orders \
  -H "Authorization: Bearer {YOUR_TOKEN}" \
  -H "Accept: application/json"

# 测试海外仓配置
curl -X GET http://localhost:8000/api/v1/warehouse-configs \
  -H "Authorization: Bearer {YOUR_TOKEN}" \
  -H "Accept: application/json"

# 测试WMS连接
curl -X POST http://localhost:8000/api/v1/warehouse-configs/1/test-connection \
  -H "Authorization: Bearer {YOUR_TOKEN}" \
  -H "Accept: application/json"

# 测试模拟WMS回调（无需认证）
curl -X POST http://localhost:8000/api/v1/wms/callback/1 \
  -H "Content-Type: application/json" \
  -H "X-Request-Id: test-$(date +%s)" \
  -d '{
    "data": {
      "wms_order_no": "WMS-CB-TEST-001",
      "out_order_no": "DS2026062100001",
      "status": "SHIPPED",
      "tracking_no": "1Z999TEST",
      "carrier_name": "UPS"
    }
  }'
```

---

## 10. 监控与告警

### 10.1 关键日志文件

```
backend/storage/logs/
├── laravel.log                    # 主应用日志
├── dropship-queue.log             # 代发队列日志 (Supervisor配置)
├── dropship-callback-queue.log    # 回调队列日志 (Supervisor配置)
└── queue-failed.log               # 失败队列日志 (如配置)
```

### 10.2 关键监控指标

| 指标 | 告警阈值 | 说明 |
|------|---------|------|
| `dropship` 队列积压 | > 100 | 代发单推送队列积压 |
| `dropship_callback` 队列积压 | > 50 | WMS回调处理积压 |
| 失败任务数 | 新增 > 10/小时 | 队列失败率异常 |
| WMS API平均响应时间 | > 5000ms | WMS接口变慢 |
| WMS API失败率 | > 5% | WMS接口异常 |
| 订单状态长时间未推进 | 超过SLA 2倍 | 订单卡住 |

### 10.3 日志级别与关键字

| 级别 | 关键字 | 触发场景 |
|------|--------|---------|
| INFO | `[ProcessDropshipOrderJob] 代发单.*推送WMS成功` | 推单成功 |
| WARNING | `[ProcessDropshipOrderJob] 代发单.*未分配海外仓` | 配置缺失 |
| ERROR | `[ProcessDropshipOrderJob] 代发单.*推送WMS失败` | 推单失败(可重试) |
| CRITICAL | `[ProcessDropshipOrderJob][ALERT] 代发单.*最终推送失败` | 推单最终失败(需人工介入) |
| CRITICAL | `[ProcessWmsCallbackJob][ALERT] 回调日志.*连续失败` | 回调最终失败(需人工介入) |

---

## 11. 故障排查

### 11.1 队列相关

**Q1: 代发订单审核通过后一直停留在 REVIEW_PASS 状态**

```bash
# 1. 检查队列Worker是否运行
supervisorctl status dropship-queue:*
php artisan queue:monitor redis:dropship

# 2. 检查队列是否有积压
redis-cli LLEN queues:dropship
redis-cli LRANGE queues:dropship 0 -1

# 3. 重启队列Worker
php artisan queue:restart
supervisorctl restart dropship-queue:*

# 4. 手动触发推送
php artisan tinker
>>> $order = App\Models\DropshipOrder::find(1);
>>> App\Jobs\ProcessDropshipOrderJob::dispatch($order);
```

**Q2: WMS回调已接收但订单状态未更新**

```bash
# 1. 检查回调处理队列
supervisorctl status dropship-callback-queue:*
redis-cli LLEN queues:dropship_callback

# 2. 查看回调日志状态
php artisan tinker
>>> App\Models\WmsCallbackLog::latest()->take(5)->get(['id','callback_type','status','error_message']);

# 3. 重试失败的回调
php artisan tinker
>>> $log = App\Models\WmsCallbackLog::where('status','failed')->first();
>>> App\Jobs\ProcessWmsCallbackJob::dispatch($log);
```

### 11.2 WMS集成相关

**Q3: 推送WMS返回签名错误**

检查签名算法一致性：
- 后端签名逻辑：[WmsIntegrationService.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/app/Services/WmsIntegrationService.php#L237-L248) 中的 `signPayload`
- 签名规则：所有参数按key升序排列 → `http_build_query` → 末尾追加 `&secret={api_secret}` → `strtoupper(md5(...))`

**Q4: 海外仓测试连接失败**

```bash
php artisan tinker
>>> $config = App\Models\OverseaWarehouseConfig::find(1);
>>> app(App\Services\WmsIntegrationService::class)->testConnection($config);
```

检查：
1. `api_endpoint` 是否可达（DNS/防火墙）
2. `api_key` / `api_secret` 是否正确
3. WMS服务商是否限制了来源IP

### 11.3 权限相关

**Q5: 用户登录后无法访问代发模块**

检查权限配置：
- 后端权限服务：[DropshipPermissionService.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/app/Services/DropshipPermissionService.php)
- 确保用户角色包含：`admin`、`operator` 或 `warehouse`

---

## 附录：文件索引

| 文件 | 路径 |
|------|------|
| 后端环境变量示例 | [.env.example](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/.env.example) |
| 数据种子 | [OverseaDropshipDatabaseSeeder.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/database/seeders/OverseaDropshipDatabaseSeeder.php) |
| 数据库迁移(共5个) | [database/migrations/](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/database/migrations/) |
| 代发推送Job | [ProcessDropshipOrderJob.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/app/Jobs/ProcessDropshipOrderJob.php) |
| WMS回调Job | [ProcessWmsCallbackJob.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/app/Jobs/ProcessWmsCallbackJob.php) |
| WMS集成服务 | [WmsIntegrationService.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/app/Services/WmsIntegrationService.php) |
| 代发订单服务 | [OverseaDropshipService.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/app/Services/OverseaDropshipService.php) |
| 自动化引擎 | [AutomationEngineService.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/app/Services/AutomationEngineService.php) |
| API路由 | [dropship_api.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/routes/dropship_api.php) |
| 后端PHPUnit配置 | [phpunit.xml](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/phpunit.xml) |
| 后端金路径测试(共3个) |  [OverseaDropshipServiceGoldenPathTest.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/tests/Unit/Services/OverseaDropshipServiceGoldenPathTest.php) / [AutomationEngineGoldenPathTest.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/tests/Unit/Services/AutomationEngineGoldenPathTest.php) / [WmsCallbackGoldenPathTest.php](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/backend/tests/Unit/Services/WmsCallbackGoldenPathTest.php) |
| 前端环境变量 | [.env.example](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/frontend/.env.example) / [.env.development](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/frontend/.env.development) / [.env.production](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/frontend/.env.production) |
| 前端Jest配置 | [jest.config.js](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/frontend/jest.config.js) |
| 前端package.json | [package.json](file:///Users/wuzhijie/Documents/xiaohongshu/biaozhu/tishiwen/002-电商订单库存后台/frontend/package.json) |
