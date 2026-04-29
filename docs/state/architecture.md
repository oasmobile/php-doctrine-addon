# Architecture

`docs/state/` SSOT — 系统架构与工程约束。

---

## 项目定位

`oasis/doctrine-addon` 是一个 Doctrine ORM 扩展库，提供两个独立功能模块：

1. **AutoIdTrait** — 简化自增主键声明
2. **CascadeRemove 机制** — 解决 ORM 级联删除时的缓存失效问题

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
| `doctrine/orm` | ^2.5 | ORM 核心 |
| `oasis/logging` | ^1.1 | 日志 |

开发依赖：

| 依赖 | 版本 | 用途 |
|------|------|------|
| `phpunit/phpunit` | ^5.3 | 单元测试 |

---

## 测试结构

```
ut/
├── bootstrap.php           # PHPUnit 引导
├── cli-config.php          # Doctrine CLI 配置
├── TestEnv.php             # 测试环境（EntityManager 工厂）
├── Article.php             # 测试实体
├── Category.php            # 测试实体
├── Tag.php                 # 测试实体
└── CascadeRemoveTest.php   # 测试用例
```

测试依赖外部服务：MySQL + Memcached（Second Level Cache）。

---

## 工程约束

- PHP library，无独立运行入口
- 通过 Composer 分发，版本号由 git tag 决定
- 使用 Doctrine Annotation（非 Attribute），兼容 PHP 7.x+
- Second Level Cache 为测试必需，生产环境由使用方决定
