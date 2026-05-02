# Migration Guide: 1.x → ^3.1

`docs/manual/` — 从 `oasis/doctrine-addon` 1.x 升级到 ^3.1 的迁移指南。

---

## Overview

1.x → ^3.1 跨越三个大版本，涉及以下关键变更：

| 版本 | 核心变更 |
|------|----------|
| 2.1.0 | 命名空间迁移、测试环境现代化 |
| 3.0.0 | PHP ^8.4、Annotation → Attribute、PHPUnit ^13、移除 `doctrine/annotations` |
| 3.1.0 | Doctrine ORM ^3.6 / DBAL ^4.4、CascadeRemoveTrait 内部重构 |

---

## 环境要求

升级前确认环境满足以下最低要求：

| 依赖 | 1.x | ^3.1 |
|------|-----|------|
| `php` | >=5.6（典型） | ^8.4 |
| `doctrine/orm` | ^2.5 | ^3.6 |
| `doctrine/dbal` | 2.x（间接） | ^4.4（随 ORM 3 拉入） |
| `oasis/logging` | ^1.1 | ^3.0 |
| `doctrine/annotations` | 需要 | **已移除** |

---

## Step 1: 更新 Composer 依赖

```bash
composer require oasis/doctrine-addon:^3.1
```

由于 ORM 大版本升级，可能需要同步升级项目中的其他 Doctrine 相关包。常见的间接依赖变化：

- `doctrine/cache` — ORM 3 不再依赖，如果项目直接使用需自行保留
- `doctrine/common` — ORM 3 不再依赖，同上
- `doctrine/annotations` — 已被 PHP Attribute 取代，可移除

---

## Step 2: 命名空间迁移

1.x 使用 `Doctrine\Common\Persistence` 命名空间，^3.1 使用 `Doctrine\Persistence`。

如果你的代码中有直接引用（例如 `LifecycleEventArgs`），需要更新：

```php
// 旧
use Doctrine\Common\Persistence\Event\LifecycleEventArgs;

// 新
use Doctrine\Persistence\Event\LifecycleEventArgs;
```

> 本库的 `CascadeRemovableInterface` 已完成此迁移，但如果你的项目代码中有直接 `use` 或 type-hint，需要同步修改。

---

## Step 3: Annotation → Attribute 迁移

^3.1 要求使用 PHP Attribute 声明 ORM mapping metadata，不再支持 `@ORM\...` 注解。

### AutoIdTrait

无需修改。trait 内部已使用 Attribute 语法，升级后自动生效。

### CascadeRemoveTrait / CascadeRemovableInterface

无需修改接口实现。trait 内部的 `@ORM\PreRemove` / `@ORM\PostRemove` 已迁移为 `#[ORM\PreRemove]` / `#[ORM\PostRemove]`。

### 你的 Entity 代码

所有使用本库的 entity 必须从 Annotation 迁移到 Attribute。示例：

```php
// 旧（1.x Annotation 风格）
/**
 * @ORM\Entity
 * @ORM\Table(name="articles")
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @ORM\HasLifecycleCallbacks
 */
class Article implements CascadeRemovableInterface
{
    use CascadeRemoveTrait;
    use AutoIdTrait;

    /**
     * @ORM\ManyToMany(targetEntity="Tag", inversedBy="articles")
     * @ORM\JoinTable(name="article_tags")
     */
    protected $tags;
}
```

```php
// 新（^3.1 Attribute 风格）
#[ORM\Entity]
#[ORM\Table(name: 'articles')]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
#[ORM\HasLifecycleCallbacks]
class Article implements CascadeRemovableInterface
{
    use CascadeRemoveTrait;
    use AutoIdTrait;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
    #[ORM\JoinTable(name: 'article_tags',
        inverseJoinColumns: [new ORM\JoinColumn(onDelete: 'CASCADE')],
        joinColumns: [new ORM\JoinColumn(onDelete: 'CASCADE')])]
    protected $tags;
}
```

关键变化：

- `@ORM\...` 注解 → `#[ORM\...]` Attribute
- `targetEntity="ClassName"` 字符串 → `targetEntity: ClassName::class` 引用
- 嵌套注解（如 `@JoinColumn`）→ `new ORM\JoinColumn(...)` 构造

---

## Step 4: Metadata Driver 切换

项目的 Doctrine 配置中，metadata driver 必须从 AnnotationDriver 切换为 AttributeDriver。

```php
// 旧
use Doctrine\ORM\Tools\Setup;

$config = Setup::createAnnotationMetadataConfiguration($paths, $isDevMode);
```

```php
// 新
use Doctrine\ORM\ORMSetup;

$config = ORMSetup::createAttributeMetadataConfig($paths, $isDevMode);
```

> 注意方法名变化：`createAnnotationMetadataConfiguration()` → `createAttributeMetadataConfig()`。中间版本（ORM 2.x 后期）曾使用 `createAttributeMetadataConfiguration()`，ORM 3.5+ 已 deprecated 该方法，改用更短的 `createAttributeMetadataConfig()`。

---

## Step 5: EntityManager 创建方式

ORM 3 移除了 `EntityManager::create()` 静态工厂方法。

```php
// 旧
$em = EntityManager::create($connection, $config);
```

```php
// 新
$em = new EntityManager($connection, $config);
```

---

## Step 6: 启用 Native Lazy Objects（PHP 8.4+）

ORM 3.5+ 在 PHP 8.4+ 环境下要求启用 native lazy objects：

```php
$config->enableNativeLazyObjects(true);
```

在创建 `EntityManager` 之前调用。

---

## Step 7: Doctrine CLI 适配（如使用）

如果项目使用 Doctrine CLI（`vendor/bin/doctrine`），`ConsoleRunner` API 已变更：

```php
// 旧
use Doctrine\ORM\Tools\Console\ConsoleRunner;

$helperSet = ConsoleRunner::createHelperSet($em);
ConsoleRunner::run($helperSet);
```

```php
// 新
use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

ConsoleRunner::run(new SingleManagerProvider($em));
```

---

## Step 8: PHPUnit 升级（如使用 ^5.x / ^8.x）

如果项目测试依赖本库的 test fixture 或使用相同的 PHPUnit 版本约束：

- PHPUnit ^5.x / ^8.x → ^13.0
- `phpunit.xml` schema 需升级为 PHPUnit 13 格式
- 建议添加 `failOnDeprecation="true"` 确保零 deprecation warning

---

## Breaking Changes 汇总

| 变更 | 影响范围 | 迁移动作 |
|------|----------|----------|
| PHP ^8.4 | 运行时 | 升级 PHP 版本 |
| `Doctrine\Common\Persistence` → `Doctrine\Persistence` | 命名空间引用 | 全局替换 |
| Annotation → Attribute | 所有 entity mapping | 逐文件迁移 |
| `doctrine/annotations` 移除 | 依赖 | 从 `composer.json` 移除 |
| `doctrine/orm` ^2.x → ^3.6 | ORM API | 见 Step 4–7 |
| `doctrine/dbal` 3.x → ^4.4 | 数据库层 | 检查 DBAL API 用法 |
| `EntityManager::create()` 移除 | EM 创建 | 改用 `new EntityManager()` |
| `Setup::createAnnotationMetadataConfiguration()` 移除 | 配置 | 改用 `ORMSetup::createAttributeMetadataConfig()` |
| `oasis/logging` ^1.1 → ^3.0 | 日志 | 升级 logging 包 |

---

## 无需修改的部分

以下内容在升级过程中**无需用户侧修改**：

- `CascadeRemovableInterface` 接口签名不变
- `getCascadeRemoveableEntities()` / `getDirtyEntitiesOnInvalidation()` 返回值契约不变
- `AutoIdTrait` 提供的 `$id` 字段和 `getId()` 方法不变
- CascadeRemoveTrait 的外部行为不变（内部从 `UnitOfWork` internal API 改为 EntityManager public API，对使用方透明）

---

## 验证清单

升级完成后，逐项确认：

- [ ] `composer install` / `composer update` 无错误
- [ ] 所有 entity 已从 Annotation 迁移为 Attribute
- [ ] Metadata driver 已切换为 `createAttributeMetadataConfig()`
- [ ] `EntityManager` 使用 `new EntityManager()` 创建
- [ ] 已调用 `$config->enableNativeLazyObjects(true)`
- [ ] 项目中无 `Doctrine\Common\Persistence` 引用
- [ ] 项目中无 `doctrine/annotations` 依赖
- [ ] 测试全部通过，零 deprecation warning
