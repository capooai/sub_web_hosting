<h1 align="center">
  <img src="./public/icons/auto.svg" alt="CloudflareSub Logo" height="40" align="absmiddle" /> CloudflareSub PHP
</h1>

<p align="center"><em>轻量化的优选 IP 订阅生成器 — PHP + MySQL 版</em></p>

<p align="center">
  <img src="https://img.shields.io/badge/license-MIT-2ea44f" alt="License MIT" />
  <img src="https://img.shields.io/badge/PHP-%3E%3D8.1-777BB4?logo=php&logoColor=white" alt="PHP" />
  <img src="https://img.shields.io/badge/MySQL-%3E%3D5.7-4479A1?logo=mysql&logoColor=white" alt="MySQL" />
  <img src="https://img.shields.io/badge/status-active-00C853" alt="Status Active" />
</p>

> 本项目是 [CloudflareSub](https://github.com/InfiCheesy/cloudflaresub) 的 PHP + MySQL 移植版，功能完全一致，适用于不支持 Cloudflare Workers 的普通虚拟主机环境。

## 功能特性

- 支持 `vmess`、`vless`、`trojan` 节点解析
- 支持 Base64 订阅文本自动展开
- 支持 `host[:port][#remark]` 格式的优选地址
- 结果写入 MySQL，生成 `/sub/:id` 短链
- 相同输入自动去重（7 天 TTL）
- 支持 `SUB_ACCESS_TOKEN` 访问令牌保护
- 支持导出：Raw（Base64）/ Clash（YAML）/ Surge（文本）

## 环境要求

- **PHP** >= 8.1
- **MySQL** >= 5.7 或 MariaDB >= 10.3
- **Apache**（mod_rewrite）或 **Nginx**
- PDO MySQL 扩展

> 免费虚拟主机（如 InfinityFree、000webhost 等）通常已满足以上条件，可直接部署。

## 项目结构

```
cloudflaresub-php/
├─ index.php              # 入口路由（API + 静态文件 + SPA 回退）
├─ .htaccess              # Apache URL 重写规则
├─ src/
│  ├─ config.php          # 配置（数据库连接 + 环境变量）
│  ├─ db.php              # MySQL PDO 连接
│  ├─ core.php            # 核心逻辑：节点解析 / 优选替换 / Clash & Surge 渲染
│  └─ api.php             # API 处理：POST /api/generate + GET /sub/:id
├─ public/                # 前端静态资源
│  ├─ index.html
│  ├─ app.js
│  ├─ styles.css
│  └─ icons/
├─ sql/
│  └─ init.sql            # 建表脚本
└─ README.md
```

## 快速开始

### 1. 创建数据库表

在 phpMyAdmin 或命令行中执行 `sql/init.sql`：

```sql
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `short_id` VARCHAR(12) NOT NULL,
    `payload` JSON NOT NULL,
    `dedup_hash` VARCHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_short_id` (`short_id`),
    UNIQUE KEY `uk_dedup_hash` (`dedup_hash`),
    KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. 配置数据库连接

编辑 `src/config.php`，填入你的数据库信息：

```php
'db' => [
    'host'     => '你的数据库主机',
    'port'     => 3306,
    'name'     => '你的数据库名',
    'user'     => '你的数据库用户名',
    'password' => '你的数据库密码',
    'charset'  => 'utf8mb4',
],
```

也支持通过环境变量配置（优先级高于默认值）：

| 环境变量 | 说明 |
|---|---|
| `DB_HOST` | 数据库主机地址 |
| `DB_PORT` | 数据库端口（默认 3306） |
| `DB_NAME` | 数据库名称 |
| `DB_USER` | 数据库用户名 |
| `DB_PASS` | 数据库密码 |
| `SUB_ACCESS_TOKEN` | 订阅访问令牌（可选，不设置则无保护） |

### 3. 上传文件

将项目文件上传到网站根目录。

### 4. 配置 URL 重写

**Apache**：确保启用了 `mod_rewrite`，项目自带 `.htaccess`，无需额外配置。

**Nginx**：在站点配置中添加：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 5. 设置访问令牌（可选）

在 `src/config.php` 中设置 `'access_token'` 的默认值，或通过环境变量 `SUB_ACCESS_TOKEN` 配置。

设置后，访问 `/sub/:id` 必须携带 `?token=xxx` 参数。

### 6. 验证部署

- 访问网站首页 `/`，应看到订阅生成表单
- 填入节点链接和优选地址，点击"生成订阅"
- 测试生成的订阅链接是否正常返回内容

## API 说明

### `POST /api/generate`

输入原始节点与优选地址，返回短链订阅。

**请求体：**

```json
{
  "nodeLinks": "vmess://...\nvless://...",
  "preferredIps": "104.16.1.2#HK-01\n104.17.2.3:2053#HK-02",
  "namePrefix": "CF",
  "keepOriginalHost": true
}
```

| 字段 | 类型 | 说明 |
|---|---|---|
| `nodeLinks` | string | 多行节点链接（vmess:// / vless:// / trojan://），支持 Base64 订阅 |
| `preferredIps` | string | 多行优选地址，格式 `host[:port][#remark]` |
| `namePrefix` | string | 节点名附加前缀（可选） |
| `keepOriginalHost` | bool | 是否保留原始 Host/SNI，默认 `true` |

**返回：**

```json
{
  "ok": true,
  "storage": "mysql",
  "deduplicated": false,
  "shortId": "AbC123xYz9",
  "urls": {
    "auto": "https://example.com/sub/AbC123xYz9?token=...",
    "raw": "https://example.com/sub/AbC123xYz9?target=raw&token=...",
    "clash": "https://example.com/sub/AbC123xYz9?target=clash&token=...",
    "surge": "https://example.com/sub/AbC123xYz9?target=surge&token=..."
  },
  "counts": {
    "inputNodes": 3,
    "preferredEndpoints": 2,
    "outputNodes": 6
  }
}
```

### `GET /sub/:id`

按 `target` 参数返回订阅内容：

| target | 返回格式 | Content-Type |
|---|---|---|
| `raw`（默认） | Base64 原始订阅 | `text/plain` |
| `clash` | Clash YAML 配置 | `text/yaml` |
| `surge` | Surge 配置 | `text/plain` |

**示例：**

```bash
curl "https://example.com/sub/AbC123xYz9?target=clash&token=YOUR_TOKEN"
```

## 前端页面

根路径 `/` 提供网页表单：

- 粘贴节点链接（vmess / vless / trojan）
- 粘贴优选 IP / 域名
- 一键生成各客户端订阅链接
- 支持复制链接 / 生成二维码
- 预览前 20 个生成节点

## 定时清理

订阅记录默认保存 7 天。如需自动清理过期数据，添加 crontab：

```bash
0 * * * * cd /path/to/cloudflaresub-php && php index.php cleanup >> /var/log/sub_cleanup.log 2>&1
```

> 免费虚拟主机通常不支持 crontab，不影响功能使用，只是数据不会自动清理。

## 注意事项

- 数据库 `payload` 字段使用 `JSON` 类型，需 MySQL 5.7+ 或 MariaDB 10.3+
- 相同的输入（节点 + 优选地址 + 配置）会自动去重，返回已有短链
- 不设置 `SUB_ACCESS_TOKEN` 也可正常使用，但订阅链接无二次访问保护
- 本项目不提供优选 IP，只负责将已有优选 IP 替换进节点

## 致谢

- 原项目：[InfiCheesy/cloudflaresub](https://github.com/InfiCheesy/cloudflaresub)（Cloudflare Workers 版）

## License

MIT
