# Changelog v3.0.0

本文件记录 v3.0.0 release 的变更内容。

---

## 包含的 Feature

### PHP 8.5 Upgrade（PRP-001）

全面升级工具链，支持 PHP 8.5 运行时，引入 property-based testing。

#### 依赖变更

- `php` 版本约束：新增 `^8.4`（显式声明）
- `doctrine/orm`：`^2.7` → `^2.20`
- `oasis/logging`：`^1.1` → `^3.0`
- `doctrine/annotations`：`^1.13` → 移除
- `phpunit/phpunit`：`^8.5` → `^13.0`
- `symfony/cache`：`^5.4` → `^7.2`
- `giorgiosironi/eris`：新增 `^1.0`（property-based testing）

#### Doctrine Annotation → PHP Attribute 迁移

- `src/AutoIdTrait.php`：`@ORM\Id` 等注解迁移为 `#[ORM\Id]` 等 Attribute
- `src/CascadeRemoveTrait.php`：`@ORM\PreRemove`、`@ORM\PostRemove` 迁移为 Attribute
- `ut/Entity/` 下 5 个 entity fixture 全部迁移为 Attribute 语法
- `targetEntity` 参数从字符串改为 `::class` 引用

#### TestEnv API 替换

- `Setup::createAnnotationMetadataConfiguration()` → `ORMSetup::createAttributeMetadataConfiguration()`
- `EntityManager::create()` → `new EntityManager()`

#### PHPUnit 配置适配

- `phpunit.xml` schema 升级为 PHPUnit 13 格式
- 新增 `failOnDeprecation="true"` 确保零 deprecation warning

#### Property-Based Testing

- 新增 `AutoIdTraitPbtTest.php`：ID 唯一性与正整数约束、ID round-trip 两个 property
- 新增 `CascadeRemoveTraitPbtTest.php`：强关联实体清理、弱关联实体刷新正确性两个 property
- 使用 Eris 半随机拓扑 generator（4 种关联模式），每个 property 100 次迭代

---

## 工程变更

- PHP 最低版本要求从 7.4 提升至 8.4
- Metadata driver 从 AnnotationDriver 切换为 AttributeDriver
- 移除 `doctrine/annotations` 依赖（已 abandoned）
- `symfony/cache` 升级至活跃维护版本

---

## 测试覆盖

- 24 tests, 22812 assertions, 全部通过
- 零 PHP deprecation warning
