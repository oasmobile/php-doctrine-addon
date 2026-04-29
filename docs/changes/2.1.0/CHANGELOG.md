# Changelog v2.1.0

本文件记录 v2.1.0 release 的变更内容。

---

## 依赖升级

- `doctrine/orm` 最低版本从 `^2.5` 升至 `^2.7`
- 新增 `doctrine/annotations` `^1.13` 为显式依赖
- `phpunit/phpunit` 从 `^5.3` 升至 `^8.5`
- 新增开发依赖 `symfony/cache` `^5.4`（测试用 Second Level Cache backend）

## 源码变更

- `CascadeRemovableInterface` / `CascadeRemoveTrait`：`Doctrine\Common\Persistence` 命名空间迁移至 `Doctrine\Persistence`

## 测试环境

- 数据库从 MySQL 改为 SQLite in-memory（`pdo_sqlite`）
- Second Level Cache backend 从 Memcached 改为 `Symfony\Component\Cache\Adapter\ArrayAdapter`（PSR-6）
- 移除 `oasis/logging` 的 `ConsoleHandler` 依赖
- 测试零外部服务依赖

## 测试目录重构

- Entity fixture 移入 `ut/Entity/`（`Article`、`Category`、`Tag`）
- 新增对照组 entity：`PlainArticle`、`PlainCategory`（不使用 trait，用于对比测试）
- 测试用例移入 `ut/Test/`
- 新增测试：`AutoIdTraitTest`、`CascadeRemoveTraitTest`、`CascadeRemoveContrastTest`

## 文档

- `README.md` 重写为中文，精简示例
- `PROJECT.md` 更新技术栈版本和命名空间映射
- `docs/state/` 和 `docs/manual/` 同步更新

## 工程变更

- 新增 agent 配置（Cursor / Kiro）、steering 规则、文档框架
- 新增 graphify 知识图谱

---

## 测试覆盖

- 20 tests, 39 assertions, 全部通过
