# Architecture

`docs/state/` SSOT — 系统架构与工程约束。

---

## 项目定位

`oasis/doctrine-addon` 是一个 Doctrine ORM 扩展库，提供两个独立功能模块：

1. **AutoIdTrait** — 简化自增主键声明
2. **CascadeRemove 机制** — 解决使用数据库 `ON DELETE CASCADE` 时，Doctrine EntityManager identity map 和 Second Level Cache 中残留脏数据的问题

---

## 模块结构

```
src/
├── AutoIdTrait.php                 # 自增 ID trait
├── CascadeRemovableInterface.php   # 级联删除接口
└── CascadeRemoveTrait.php          # 级联删除实现 trait
```

---

## 依赖关系

| 依赖 | 版本 | 用途 |
|------|------|------|
| `php` | ^8.4 | 运行时 |
| `doctrine/orm` | ^2.20 | ORM 核心 |
| `oasis/logging` | ^3.0 | 日志 |

开发依赖：

| 依赖 | 版本 | 用途 |
|------|------|------|
| `phpunit/phpunit` | ^13.0 | 单元测试 |
| `symfony/cache` | ^7.2 | 测试环境的 Second Level Cache backend（`ArrayAdapter`） |
| `giorgiosironi/eris` | ^1.0 | Property-based testing |

---

## 测试结构

```
ut/
├── bootstrap.php               # PHPUnit 引导
├── cli-config.php              # Doctrine CLI 配置
├── TestEnv.php                 # 测试环境（SQLite in-memory + ArrayAdapter 二级缓存）
├── Entity/                     # 测试用 Doctrine entity fixture
│   ├── Article.php             # 使用 CascadeRemoveTrait 的文章实体
│   ├── Category.php            # 使用 CascadeRemoveTrait 的分类实体
│   ├── Tag.php                 # 使用 CascadeRemoveTrait 的标签实体
│   ├── PlainArticle.php        # 不使用 trait 的对照组文章实体
│   └── PlainCategory.php       # 不使用 trait 的对照组分类实体
└── Test/                       # 测试用例
    ├── AutoIdTraitTest.php     # AutoIdTrait 测试
    ├── AutoIdTraitPbtTest.php  # AutoIdTrait property-based test
    ├── CascadeRemoveTest.php   # 原始集成测试
    ├── CascadeRemoveTraitTest.php    # trait 各分支覆盖测试
    ├── CascadeRemoveTraitPbtTest.php # CascadeRemoveTrait property-based test
    └── CascadeRemoveContrastTest.php # 有/无 trait 的对比测试
```

测试零外部依赖：使用 SQLite in-memory 数据库 + `Symfony\Component\Cache\Adapter\ArrayAdapter` 作为 Second Level Cache backend。

---

## 工程约束

- PHP library，无独立运行入口
- 通过 Composer 分发，版本号由 git tag 决定
- 使用 PHP Attribute 声明 ORM mapping metadata，要求 PHP ^8.4
- CascadeRemoveTrait 要求使用方启用 Doctrine Second Level Cache（`#[ORM\Cache]` attribute + cache 配置），否则 `$em->getCache()` 返回 null 会导致 fatal error
