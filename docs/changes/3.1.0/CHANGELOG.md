# Changelog v3.1.0

本文件记录 v3.1.0 release 的变更内容。

---

## 包含的 Feature

### Doctrine ORM 3 / DBAL 4 Upgrade（PRP-002）

升级 Doctrine ORM 至 ^3.6、DBAL 至 ^4.4，消除 abandoned 依赖，消除对 UnitOfWork internal API 的直接依赖。

#### 依赖变更

- `doctrine/orm`：`^2.20` → `^3.6`
- `doctrine/dbal`：3.10.x（间接）→ ^4.4（随 ORM 3 拉入）
- `doctrine/cache`：2.2.0（间接）→ 移除（ORM 3 不再依赖）
- `doctrine/common`：3.5.0（间接）→ 移除（ORM 3 不再依赖）

#### CascadeRemoveTrait 重构

- 消除对 `UnitOfWork` internal API 的全部依赖（`getEntityIdentifier`、`isScheduledForDelete`、`isInIdentityMap`）
- `getEntityIdentifier()` → `ClassMetadata::getIdentifierValues()`（3 处）
- `isScheduledForDelete()` + `isInIdentityMap()` → `EntityManager::contains()`（合并为 1 处）
- 外部行为不变，仅内部实现改用 EntityManager public API

#### ORM 3 脏数据验证结果

- 验证 ORM 3.6 + DBAL 4.4 环境下，identity map / L2 cache / collection reference 三个脏数据场景均**未被原生解决**
- CascadeRemoveTrait 仍有存在价值，保留

#### TestEnv 适配

- `ORMSetup::createAttributeMetadataConfiguration()` → `ORMSetup::createAttributeMetadataConfig()`（ORM 3.5+ deprecated 旧方法）
- 新增 `$config->enableNativeLazyObjects(true)`（PHP 8.4+ 环境下 ORM 3.5+ 要求）

#### CLI 配置适配

- `ConsoleRunner::createHelperSet()` → `ConsoleRunner::run(new SingleManagerProvider(...))`（ORM 3 新 API）

---

## 修复的 Issue

无。

---

## 工程变更

- Doctrine ORM 大版本升级（2.x → 3.x），DBAL 大版本升级（3.x → 4.x）
- 消除 `doctrine/cache`（abandoned）和 `doctrine/common` 间接依赖
- CascadeRemoveTrait 不再依赖 `@internal` 的 UnitOfWork 类
- Entity fixture `Category` 的 `removeArticle()` / `removeChild()` 方法修复：使用 `remove()` 替代 `removeElement()`

---

## 测试覆盖

- 26 tests, 22385 assertions, 全部通过
- 零 PHP deprecation warning
- 新增 `Category::removeArticle()` / `removeChild()` 回归测试
