# Development

`docs/manual/` — 本地开发与测试环境搭建说明。

---

## 环境要求

- PHP 8.4+（需启用 `pdo_sqlite` 扩展）
- Composer
- 可选：pcov 扩展（用于生成覆盖率报告）

无需 MySQL、Memcached 等外部服务。

---

## 本地搭建

### 1. 安装依赖

```bash
composer install
```

### 2. 运行测试

```bash
vendor/bin/phpunit
```

测试使用 SQLite in-memory 数据库，启动时通过 `SchemaTool` 自动创建 schema，无需手动建库。

### 3. 覆盖率报告

```bash
# 文本摘要
vendor/bin/phpunit --coverage-text --whitelist=src/

# HTML 报告
vendor/bin/phpunit --coverage-html=coverage-html --whitelist=src/
```

---

## 测试环境说明

| 组件 | 实现 |
|------|------|
| 数据库 | SQLite in-memory（`pdo_sqlite`） |
| Second Level Cache | `Symfony\Component\Cache\Adapter\ArrayAdapter`（PSR-6） |
| Schema 管理 | `Doctrine\ORM\Tools\SchemaTool`（每个 test class 的 `setUpBeforeClass` 中重建） |

---

## 测试 Entity

`ut/Entity/` 目录下包含两组测试实体：

### 使用 CascadeRemoveTrait 的实体

| 实体 | 说明 |
|------|------|
| `Category` | 分类，支持自引用父子关系，强关联 Article |
| `Article` | 文章，属于一个 Category，可关联多个 Tag |
| `Tag` | 标签，可被多个 Article 共享 |

### 不使用 trait 的对照组实体

| 实体 | 说明 |
|------|------|
| `PlainCategory` | 对照组分类，用于对比测试证明不用 trait 时的脏数据问题 |
| `PlainArticle` | 对照组文章，同上 |

### 关联关系

- `Article` ↔ `Category`：ManyToOne / OneToMany（`onDelete="CASCADE"`）
- `Article` ↔ `Tag`：ManyToMany（通过 `article_tags` 中间表，`onDelete="CASCADE"`）
- `Category` ↔ `Category`：ManyToOne / OneToMany（自引用，`onDelete="SET NULL"`）

---

## 配置位置

| 配置 | 文件 |
|------|------|
| PHPUnit 配置 | `phpunit.xml` |
| Doctrine CLI 配置 | `ut/cli-config.php` |
| 测试环境参数 | `ut/TestEnv.php` |
