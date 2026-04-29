# Development

`docs/manual/` — 本地开发与测试环境搭建说明。

---

## 环境要求

- PHP 7.x+
- Composer
- MySQL
- Memcached

---

## 本地搭建

### 1. 安装依赖

```bash
composer install
```

### 2. 准备 MySQL

创建测试数据库和用户：

```sql
CREATE DATABASE doctrine_addon;
CREATE USER 'doctrine_addon'@'localhost' IDENTIFIED BY '123456';
GRANT ALL PRIVILEGES ON doctrine_addon.* TO 'doctrine_addon'@'localhost';
FLUSH PRIVILEGES;
```

### 3. 确认 Memcached

确保 Memcached 服务运行在 `localhost:11211`（默认端口）。

### 4. 运行测试

```bash
vendor/bin/phpunit
```

测试启动时会自动通过 Doctrine CLI 重建数据库 schema。

---

## 测试实体

`ut/` 目录下包含三个测试实体，模拟简单 CMS 场景：

| 实体 | 说明 |
|------|------|
| `Category` | 分类，支持自引用父子关系 |
| `Article` | 文章，属于一个 Category，可关联多个 Tag |
| `Tag` | 标签，可被多个 Article 共享 |

关联关系：

- `Article` ↔ `Category`：ManyToOne / OneToMany
- `Article` ↔ `Tag`：ManyToMany（通过 `article_tags` 中间表）
- `Category` ↔ `Category`：ManyToOne / OneToMany（自引用，`parent` / `children`）

---

## 配置位置

| 配置 | 文件 |
|------|------|
| PHPUnit 配置 | `phpunit.xml` |
| Doctrine CLI 配置 | `ut/cli-config.php` |
| 测试环境参数 | `ut/TestEnv.php` |
