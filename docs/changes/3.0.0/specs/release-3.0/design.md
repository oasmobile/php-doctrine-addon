# Design Document

`<spec-dir>` — Release 3.0 (PHP 8.5 Upgrade) 技术设计。

---

## Overview

本设计覆盖 `oasis/doctrine-addon` 从 PHP 7.4 / PHPUnit 8 / Doctrine Annotation 工具链全面升级到 PHP 8.4+ / PHPUnit 13 / PHP Attribute 的完整方案，同时引入 Eris property-based testing 补充 `AutoIdTrait` 和 `CascadeRemoveTrait` 的测试覆盖。

### 变更范围

升级涉及 6 个维度：

1. **Composer 依赖约束**：声明 `php ^8.4`，升级 `phpunit/phpunit` 至 `^13.0`、`doctrine/orm` 至 `^2.20`、`symfony/cache` 至 `^7.2`、`oasis/logging` 至最新版本，新增 `giorgiosironi/eris ^1.0`，移除 `doctrine/annotations`
2. **PHPUnit 配置**：`phpunit.xml` 适配 PHPUnit 13 schema
3. **Doctrine Annotation → PHP Attribute 迁移**：`src/` 和 `ut/Entity/` 中所有 ORM mapping metadata 从 docblock annotation 迁移到 PHP 8 原生 attribute
4. **TestEnv deprecated API 替换**：`Setup::createAnnotationMetadataConfiguration` → `ORMSetup::createAttributeMetadataConfiguration`，`EntityManager::create` → `new EntityManager` 构造函数
5. **PBT 引入**：使用 Eris 编写 AutoIdTrait 和 CascadeRemoveTrait 的 property-based test
6. **零 deprecation warning**：严格要求全量测试运行时无任何 PHP deprecated warning（包括第三方依赖）

### 设计决策

| 决策 | 选择 | 理由 |
|------|------|------|
| PHPUnit 升级路径 | 直接 8 → 13 | 项目测试量小（4 个测试文件），API 变更可一次性适配 |
| Attribute 迁移方式 | 手动逐文件迁移 | 文件数量少（3 src + 5 entity），无需自动化工具 |
| PBT 批量大小 | 1–100（中等范围） | CR Q1 决策：平衡覆盖度和执行时间 |
| Entity 拓扑随机化 | 半随机拓扑 | CR Q2 决策：预定义关联模式，随机选择并组合 |
| Deprecation warning 策略 | 严格零 warning | CR Q3 决策：包括第三方依赖，如有则降级或 patch |
| Eris 迭代次数 | 默认 100 次（Eris 默认值） | 满足 PBT 最低 100 次迭代要求，且与中等批量大小配合执行时间可控 |

---

## Architecture

升级不改变项目的模块结构和职责划分。变更集中在三个层面：

### 依赖层变更

```
composer.json 变更:

require:
  php: ^8.4                          # 新增
  doctrine/orm: ^2.7 → ^2.20        # 收窄
  doctrine/annotations: ^1.13 → 移除  # 删除
  oasis/logging: ^1.1 → 最新版本      # 升级

require-dev:
  phpunit/phpunit: ^8.5 → ^13.0     # 升级
  symfony/cache: ^5.4 → ^7.2        # 升级
  giorgiosironi/eris: ^1.0          # 新增
```

### Metadata Driver 变更

```
迁移前:
  TestEnv → Setup::createAnnotationMetadataConfiguration()
         → AnnotationDriver 解析 @ORM\* docblock

迁移后:
  TestEnv → ORMSetup::createAttributeMetadataConfiguration()
         → AttributeDriver 解析 #[ORM\*] attribute
```

### 测试结构变更

```
ut/Test/
├── AutoIdTraitTest.php              # 现有，适配 PHPUnit 13
├── CascadeRemoveTest.php            # 现有，适配 PHPUnit 13
├── CascadeRemoveTraitTest.php       # 现有，适配 PHPUnit 13
├── CascadeRemoveContrastTest.php    # 现有，适配 PHPUnit 13
├── AutoIdTraitPbtTest.php           # 新增 PBT
└── CascadeRemoveTraitPbtTest.php    # 新增 PBT
```

---

## Components and Interfaces

### 1. Composer 依赖变更（Req 1, 2, 4, 6, 7, 9）

直接修改 `composer.json` 中的版本约束。关键变更点：

- 移除 `doctrine/annotations` 后，Doctrine ORM 2.20 不再需要 annotation reader，`ORMSetup::createAttributeMetadataConfiguration()` 不依赖该包
- `symfony/cache` ^7.2 的 `ArrayAdapter` API 与 ^5.4 兼容，构造函数签名未变
- `giorgiosironi/eris` ^1.0 兼容 PHPUnit 13.x 和 PHP 8.4+

### 2. PHPUnit 配置适配（Req 3）

当前 `phpunit.xml` 使用 PHPUnit 5.3 schema，需升级到 PHPUnit 13 兼容格式：

```xml
<!-- 迁移前 -->
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="http://schema.phpunit.de/5.3/phpunit.xsd"
    bootstrap="ut/bootstrap.php">

<!-- 迁移后 -->
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
    bootstrap="ut/bootstrap.php"
    displayDetailsOnTestsThatTriggerDeprecations="true"
    failOnDeprecation="true">
```

关键变更：
- Schema 指向本地 vendor 路径
- 添加 `displayDetailsOnTestsThatTriggerDeprecations="true"` 以显示 deprecation 详情
- 添加 `failOnDeprecation="true"` 以确保零 deprecation warning（CR Q3 决策）
- Test suite 定义和 bootstrap 路径保持不变
- 新增 PBT 测试文件到 test suite

### 3. Annotation → Attribute 迁移（Req 5）

#### 3.1 Source Components（`src/`）

**AutoIdTrait.php**：

```php
// 迁移前
/** @ORM\Id() @ORM\GeneratedValue(strategy="AUTO") @ORM\Column(type="integer") */
protected $id;

// 迁移后
#[ORM\Id]
#[ORM\GeneratedValue(strategy: 'AUTO')]
#[ORM\Column(type: 'integer')]
protected int $id;
```

**CascadeRemoveTrait.php**：

```php
// 迁移前
/** @ORM\PreRemove() */
public function onPreRemove(...) { ... }
/** @ORM\PostRemove() */
public function onPostRemove(...) { ... }

// 迁移后
#[ORM\PreRemove]
public function onPreRemove(...) { ... }
#[ORM\PostRemove]
public function onPostRemove(...) { ... }
```

**CascadeRemovableInterface.php**：无 ORM annotation，无需迁移。

#### 3.2 Entity Fixtures（`ut/Entity/`）

每个 entity 文件需迁移：类级别注解（`@ORM\Entity`、`@ORM\Table`、`@ORM\Cache`、`@ORM\HasLifecycleCallbacks`）和属性级别注解（`@ORM\ManyToOne`、`@ORM\OneToMany`、`@ORM\ManyToMany`、`@ORM\JoinColumn`、`@ORM\JoinTable`、`@ORM\Column`、`@ORM\Id`、`@ORM\GeneratedValue`）。

涉及文件：`Article.php`、`Category.php`、`Tag.php`、`PlainArticle.php`、`PlainCategory.php`。

迁移规则：
- `@ORM\Entity()` → `#[ORM\Entity]`
- `@ORM\Table(name="xxx")` → `#[ORM\Table(name: 'xxx')]`
- `@ORM\Cache(usage="NONSTRICT_READ_WRITE")` → `#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]`
- `@ORM\HasLifecycleCallbacks()` → `#[ORM\HasLifecycleCallbacks]`
- `@ORM\ManyToOne(targetEntity="Xxx", inversedBy="yyy")` → `#[ORM\ManyToOne(targetEntity: Xxx::class, inversedBy: 'yyy')]`
- `@ORM\JoinColumn(onDelete="CASCADE")` → `#[ORM\JoinColumn(onDelete: 'CASCADE')]`
- 复合注解（如 `@ORM\JoinTable` 内嵌 `@ORM\JoinColumn`）转为嵌套 attribute 语法

### 4. TestEnv Deprecated API 替换（Req 8）

```php
// 迁移前
use Doctrine\ORM\Tools\Setup;
$config = Setup::createAnnotationMetadataConfiguration([...], $isDevMode, null, null, false);
self::$entityManager = EntityManager::create($connection, $config);

// 迁移后
use Doctrine\ORM\ORMSetup;
$config = ORMSetup::createAttributeMetadataConfiguration([...], $isDevMode);
self::$entityManager = new EntityManager($connection, $config);
```

变更点：
- `Setup` 类（deprecated）→ `ORMSetup` 类
- `createAnnotationMetadataConfiguration()` → `createAttributeMetadataConfiguration()`（同名方法，仅类不同）
- `EntityManager::create()` 静态工厂（deprecated）→ `new EntityManager()` 构造函数
- 移除 `Setup` 的 `use` 语句，添加 `ORMSetup` 的 `use` 语句

### 5. Eris PBT 集成（Req 9, 10, 11）

#### 5.1 Eris 使用模式

所有 PBT 测试类通过 `use Eris\TestTrait` 引入 Eris 能力，使用 `$this->forAll(...)->then(...)` 模式编写 property test。Eris 默认每个 `forAll()` 运行 100 次迭代，满足最低要求。

#### 5.2 AutoIdTrait PBT（Req 10）

**测试文件**：`ut/Test/AutoIdTraitPbtTest.php`

**Generator 设计**：
- 批量大小：`Generator\choose(1, 100)` — 生成 1 到 100 之间的随机整数作为 entity 数量（CR Q1 决策）
- Entity 类型：使用 `Article` 作为测试载体（最简单的使用 AutoIdTrait 的 entity）

**Property 设计**：
- Property 1（ID 唯一性与正整数约束）：批量 persist N 个 entity 后，所有 ID 互不相同且均为正整数
- Property 2（ID round-trip）：persist → flush → clear → find 后 ID 保持不变

#### 5.3 CascadeRemoveTrait PBT（Req 11）

**测试文件**：`ut/Test/CascadeRemoveTraitPbtTest.php`

**半随机拓扑设计**（CR Q2 决策）：

预定义 4 种关联模式（topology pattern），每次迭代随机选择一种：

| 模式 | 结构 | 测试重点 |
|------|------|----------|
| `single-parent` | 1 Category → N Articles | 基本级联删除 |
| `parent-with-tags` | 1 Category → N Articles，每个 Article 关联 M Tags | 级联删除 + dirty entity 刷新 |
| `tag-hub` | N Articles 共享 M Tags，删除某个 Tag | ManyToMany dirty entity |
| `deep-chain` | 1 Category → N Articles → M Tags，删除 Category | 深层级联链 |

每种模式内的数量参数（N, M）由 Eris generator 随机生成（范围 1–10，保持单次迭代的数据库操作量可控）。

**Generator 实现**：
- 使用 `Generator\oneOf()` 在 4 种模式间随机选择
- 每种模式使用 `Generator\choose(1, 10)` 生成数量参数
- 使用 `Generator\associative()` 组合模式类型和数量参数

**Property 设计**：
- Property 3（identity map 清洁性）：删除 entity 后，所有强关联实体不在 identity map 中
- Property 4（二级缓存一致性）：删除 entity 后，所有强关联实体不在 Second Level Cache 中
- Property 5（dirty entity 刷新正确性）：删除 entity 后，所有弱关联实体被正确 refresh（除非已被调度删除或不在 identity map 中）

**测试基础设施**：
- 每次 PBT 迭代前需要 fresh schema（`setUp` 中 `resetEntityManager` + `dropDatabase` + `createSchema`）
- 使用 helper 方法构建各种拓扑并返回待验证的 entity 引用


---

## Data Models

本次升级不改变数据模型的语义，仅改变 metadata 声明方式（Annotation → Attribute）。

### Entity Mapping 迁移对照

以 `Category` entity 为例展示完整迁移：

```php
// 迁移前（Annotation）
/**
 * @ORM\Entity()
 * @ORM\Table(name="categories")
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 * @ORM\HasLifecycleCallbacks()
 */
class Category implements CascadeRemovableInterface
{
    use CascadeRemoveTrait;
    use AutoIdTrait;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="Article", mappedBy="category")
     */
    protected $articles;

    /**
     * @var Category
     * @ORM\ManyToOne(targetEntity="Category", inversedBy="children")
     * @ORM\JoinColumn(onDelete="SET NULL");
     */
    protected $parent;
    // ...
}

// 迁移后（Attribute）
#[ORM\Entity]
#[ORM\Table(name: 'categories')]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
#[ORM\HasLifecycleCallbacks]
class Category implements CascadeRemovableInterface
{
    use CascadeRemoveTrait;
    use AutoIdTrait;

    #[ORM\OneToMany(targetEntity: Article::class, mappedBy: 'category')]
    protected ArrayCollection $articles;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    protected ?Category $parent = null;
    // ...
}
```

### 关联关系不变

所有 entity 间的关联关系（OneToMany、ManyToOne、ManyToMany）、外键约束（`onDelete`）、缓存策略（`NONSTRICT_READ_WRITE`）在迁移后保持完全一致。

### PBT 测试数据模型

PBT 测试复用现有 entity fixture（`Article`、`Category`、`Tag`），不引入新的 entity 类。测试数据通过 SQLite in-memory 数据库创建和销毁，每次迭代独立。


---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

本项目的 PBT 适用于 `AutoIdTrait`（Req 10）和 `CascadeRemoveTrait`（Req 11）两个模块。它们包含纯逻辑（ID 分配、递归级联删除、缓存失效），行为随输入（entity 数量、拓扑结构）显著变化，且运行在 SQLite in-memory 上成本极低，非常适合 property-based testing。

Req 1–9 和 Req 12 为依赖升级、配置变更和集成验证，不涉及可变输入的逻辑测试，不适用 PBT。

### Property 1: AutoIdTrait 批量 ID 唯一性与正整数约束

*For any* 批量大小 N（1 ≤ N ≤ 100），当 persist 并 flush N 个使用 AutoIdTrait 的 entity 后，所有 entity 的 ID 应互不相同，且每个 ID 均为正整数（> 0）。

**Validates: Requirements 10.2**

### Property 2: AutoIdTrait ID 持久化 round-trip

*For any* 批量大小 N（1 ≤ N ≤ 100），当 persist 并 flush N 个 entity 后，清空 EntityManager identity map，再逐个通过 `find()` 从数据库重新加载，每个 entity 的 ID 应与 persist 时记录的 ID 完全一致。

**Validates: Requirements 10.3**

### Property 3: CascadeRemoveTrait 强关联实体清理

*For any* 随机生成的 entity 拓扑（从预定义的 4 种关联模式中随机选择并组合），当删除一个实现 `CascadeRemovableInterface` 的根 entity 后，所有强关联实体（由 `getCascadeRemoveableEntities()` 返回的实体及其递归子实体）应同时满足：(a) 不在 EntityManager identity map 中，(b) 不在 Second Level Cache 中。

**Validates: Requirements 11.2, 11.3**

### Property 4: CascadeRemoveTrait 弱关联实体刷新正确性

*For any* 随机生成的 entity 拓扑（从预定义的 4 种关联模式中随机选择并组合），当删除一个实现 `CascadeRemovableInterface` 的根 entity 后，所有弱关联实体（由 `getDirtyEntitiesOnInvalidation()` 返回的实体）应满足：若该实体未被调度删除且仍在 identity map 中，则其缓存应已被 evict 且数据应已被 refresh（即从数据库重新加载的状态与 EM 中的状态一致）。

**Validates: Requirements 11.4**


---

## Error Handling

### 依赖冲突

- **场景**：`composer install` 或 `composer update` 时版本约束无法解析
- **处理**：逐个调整约束，优先满足 `php ^8.4` 和 `phpunit/phpunit ^13.0` 的硬约束，其他依赖在兼容范围内灵活调整
- **验证**：`composer validate` 和 `composer install --dry-run`

### Deprecation Warning

- **策略**：CR Q3 决策要求严格零 deprecated warning（包括第三方依赖）
- **PHPUnit 配置**：`failOnDeprecation="true"` 确保任何 deprecation warning 都会导致测试失败
- **项目自身代码**：通过 API 替换消除（`Setup` → `ORMSetup`，`EntityManager::create` → `new EntityManager`，Annotation → Attribute）
- **第三方依赖**：如果升级后的依赖版本仍产生 deprecation warning，优先升级到更高版本；如无更高版本可用，通过 patch 或 fork 解决

### PBT 测试隔离

- **场景**：PBT 迭代间数据库状态污染
- **处理**：每次 PBT 迭代前重置 EntityManager 并重建 schema（`resetEntityManager` + `dropDatabase` + `createSchema`）
- **代价**：增加测试执行时间，但 SQLite in-memory 操作极快，100 次迭代仍可在秒级完成

### Annotation 迁移遗漏

- **场景**：迁移后仍有 `@ORM\*` 注解残留
- **处理**：迁移完成后通过 grep 扫描 `src/` 和 `ut/Entity/` 确认无残留
- **验证**：`grep -r '@ORM\\' src/ ut/Entity/` 应返回空结果

---

## Testing Strategy

### 双轨测试方法

本项目采用 unit test + property-based test 双轨策略：

| 测试类型 | 工具 | 覆盖范围 | 文件 |
|----------|------|----------|------|
| Unit test | PHPUnit 13 | 具体场景、边界条件、错误路径 | 现有 4 个测试文件 |
| Property test | Eris + PHPUnit 13 | 通用属性、随机输入覆盖 | 新增 2 个 PBT 文件 |

### 现有测试适配

现有 4 个测试文件需适配 PHPUnit 13：

- `AutoIdTraitTest.php`：无已知 API 兼容性问题，`setUp`/`setUpBeforeClass` 已使用 `: void` 返回类型
- `CascadeRemoveTest.php`：同上
- `CascadeRemoveTraitTest.php`：同上
- `CascadeRemoveContrastTest.php`：同上

PHPUnit 8 → 13 的主要 API 变更点：
- `assertContains` 对非字符串的行为变更（本项目未使用）
- Mock API 变更（本项目使用 `createMock`，PHPUnit 13 兼容）
- `@test` annotation 变更（本项目使用 `test` 前缀方法名，无影响）

### PBT 测试配置

- **库**：`giorgiosironi/eris` ^1.0
- **集成方式**：`use Eris\TestTrait` 在 PHPUnit TestCase 中
- **迭代次数**：默认 100 次（Eris 默认值），满足最低要求
- **Tag 格式**：每个 property test 方法的 docblock 中标注 `Feature: release-3.0, Property N: {property_text}`

### AutoIdTrait PBT 详细设计

**文件**：`ut/Test/AutoIdTraitPbtTest.php`

```php
class AutoIdTraitPbtTest extends TestCase
{
    use \Eris\TestTrait;

    // Property 1: ID 唯一性与正整数约束
    // Generator: choose(1, 100) 生成批量大小
    // 每次迭代: resetEM → createSchema → persist N 个 Article → flush → 验证

    // Property 2: ID round-trip
    // Generator: choose(1, 100) 生成批量大小
    // 每次迭代: resetEM → createSchema → persist N → flush → 记录 ID → clear → find → 比较
}
```

### CascadeRemoveTrait PBT 详细设计

**文件**：`ut/Test/CascadeRemoveTraitPbtTest.php`

**半随机拓扑 Generator**（CR Q2 决策）：

```php
// 拓扑模式 generator
$topologyGen = Generator\oneOf(
    Generator\constant('single-parent'),
    Generator\constant('parent-with-tags'),
    Generator\constant('tag-hub'),
    Generator\constant('deep-chain')
);

// 数量参数 generator
$countGen = Generator\choose(1, 10);

// 组合 generator
$this->forAll($topologyGen, $countGen, $countGen)
    ->then(function ($mode, $n, $m) { ... });
```

**拓扑构建 helper**：

| 模式 | 构建逻辑 | 删除目标 | 强关联 | 弱关联 |
|------|----------|----------|--------|--------|
| `single-parent` | 1 Category + N Articles | Category | Articles | — |
| `parent-with-tags` | 1 Category + N Articles + M Tags（每 Article 关联所有 Tags） | Category | Articles | Tags |
| `tag-hub` | N Articles + M Tags（每 Article 关联所有 Tags） | 随机选一个 Tag | — | Articles |
| `deep-chain` | 1 Category + N Articles + M Tags（每 Article 关联所有 Tags） | Category | Articles | Tags |

**Property 3 验证逻辑**：
1. 构建拓扑，persist 所有 entity，flush
2. 预热二级缓存（clear → find 所有 entity）
3. 重新加载删除目标，执行 remove + flush
4. 对每个强关联实体：断言 `$em->find(Class, id)` 返回 null（不在 identity map 且 DB 已删除）
5. 对每个强关联实体：断言 `$cache->containsEntity(Class, id)` 返回 false

**Property 4 验证逻辑**：
1. 同 Property 3 的步骤 1–3
2. 对每个弱关联实体：如果该实体未被同时删除，断言 `$em->find(Class, id)` 返回非 null（仍存在）
3. 对每个弱关联实体：断言 `$cache->containsEntity(Class, id)` 返回 false（缓存已 evict）

### 验证矩阵

| Requirement | 验证方式 | 验证时机 |
|-------------|----------|----------|
| Req 1 (PHP 约束) | `composer validate` | 依赖变更后 |
| Req 2 (PHPUnit 升级) | `vendor/bin/phpunit` | 升级后 |
| Req 3 (phpunit.xml) | PHPUnit 启动无 schema warning | 配置变更后 |
| Req 4 (Doctrine ORM) | `composer install` | 依赖变更后 |
| Req 5 (Attribute 迁移) | `grep -r '@ORM\\' src/ ut/Entity/` + 全量测试 | 迁移后 |
| Req 6 (symfony/cache) | 全量测试 | 依赖变更后 |
| Req 7 (oasis/logging) | `composer install` | 依赖变更后 |
| Req 8 (TestEnv API) | 全量测试 + 无 deprecation warning | API 替换后 |
| Req 9 (Eris 引入) | `composer install` | 依赖变更后 |
| Req 10 (AutoIdTrait PBT) | PBT 测试通过 | PBT 编写后 |
| Req 11 (CascadeRemoveTrait PBT) | PBT 测试通过 | PBT 编写后 |
| Req 12 (全量测试) | `vendor/bin/phpunit` exit code 0 + 零 deprecation | 全部完成后 |


---

## Impact Analysis

### 受影响的 State 文档

| 文件 | 受影响 Section | 变更内容 |
|------|---------------|----------|
| `docs/state/architecture.md` | 依赖关系 | `doctrine/annotations` 移除，`phpunit/phpunit` 升级至 ^13.0，`symfony/cache` 升级至 ^7.2，`doctrine/orm` 收窄至 ^2.20，`oasis/logging` 升级至最新，新增 `giorgiosironi/eris ^1.0` |
| `docs/state/architecture.md` | 工程约束 | "使用 Doctrine Annotation（非 Attribute），兼容 PHP 7.4+" → "使用 PHP Attribute，要求 PHP ^8.4" |
| `docs/state/architecture.md` | 测试结构 | 新增 `AutoIdTraitPbtTest.php` 和 `CascadeRemoveTraitPbtTest.php` |
| `docs/state/data-model.md` | AutoIdTrait 提供的字段 | 注解列从 `@ORM\Id` 等改为 `#[ORM\Id]` 等 Attribute 语法 |
| `docs/state/data-model.md` | CascadeRemoveTrait 使用前提 | `@ORM\HasLifecycleCallbacks` 注解 → `#[ORM\HasLifecycleCallbacks]` attribute |
| `PROJECT.md` | 技术栈 | PHP 版本、依赖版本、测试框架版本均需更新 |

### 现有行为变化

- **Metadata Driver**：从 AnnotationDriver 切换到 AttributeDriver。对库使用者无影响（metadata driver 仅在测试环境 `TestEnv` 中配置），但 `src/` 中的 trait 注解格式变化对下游使用者有影响——下游项目如果通过 annotation 方式使用本库的 trait，需确认其 metadata driver 也支持 attribute
- **PHPUnit 版本**：开发者本地运行测试需要 PHPUnit 13（PHP ≥ 8.4）
- **测试输出**：`failOnDeprecation="true"` 使得任何 deprecation warning 都会导致测试失败，比之前更严格

### 数据模型变更

不涉及。本次升级仅改变 metadata 声明方式（Annotation → Attribute），不改变 entity 字段、关联关系或数据库 schema。无旧数据兼容性问题。

### 外部系统交互

不涉及。本库为 Doctrine ORM 扩展组件，无外部系统交互。

### 配置项变更

| 配置文件 | 变更 |
|----------|------|
| `composer.json` | `require.php` 新增 `^8.4`；多项依赖版本变更；`doctrine/annotations` 移除；`giorgiosironi/eris` 新增 |
| `phpunit.xml` | Schema 更新；新增 `displayDetailsOnTestsThatTriggerDeprecations` 和 `failOnDeprecation` 属性 |

### Graphify 辅助分析

基于 graphify 图谱，`CascadeRemoveTrait`（community 6）和 `Doctrine Annotation (non-Attribute)`（community 3）是本次变更的核心影响节点。`CascadeRemoveTrait` 的 3 个方法（`onPreRemove`、`onPostRemove`、`findCascadeDetachableEntities`）均需从 annotation 迁移到 attribute。God nodes `Category`（12 edges）、`Article`（9 edges）、`Tag`（7 edges）是 entity fixture 中连接最密集的节点，迁移时需特别注意其复杂的关联注解（嵌套 `@ORM\JoinTable` 等）。`TestEnv`（community 5，4 edges）是测试基础设施的核心节点，其 API 替换影响所有测试用例的运行。

---

## Alternatives Considered

### PHPUnit 升级路径：直接 8 → 13 vs 渐进升级

- **选择**：直接 8 → 13
- **落选方案**：先升级到 9，再到 10，逐步到 13
- **理由**：项目测试量小（4 个测试文件，约 20 个测试方法），API 变更可一次性适配。渐进升级需要多次修改 `composer.json` 和测试代码，增加不必要的中间状态

### Attribute 迁移方式：手动 vs 自动化工具

- **选择**：手动逐文件迁移
- **落选方案**：使用 `rector/rector` 自动迁移
- **理由**：需迁移的文件仅 8 个（3 src + 5 entity），手动迁移可控且无需引入额外开发依赖。Rector 规则可能对嵌套注解（如 `@ORM\JoinTable` 内嵌 `@ORM\JoinColumn`）处理不完美，手动迁移更可靠

### PBT Property 3 和 AC2/AC3 的映射：分开 vs 合并

- **选择**：合并为一个 Property（Property 3 同时验证 identity map 和二级缓存）
- **落选方案**：拆为两个独立 Property
- **理由**：两者在同一次删除操作中验证同一组实体的不同方面，合并后共享拓扑构建和删除操作，减少重复代码和执行时间，无信息损失

---

## Socratic Review

### Q1: Design 是否覆盖了 requirements.md 中的所有 Requirement 和 AC？

逐项对照：

| Requirement | Design 覆盖 |
|-------------|-------------|
| Req 1 (PHP 约束) | Architecture 依赖层变更 ✓ |
| Req 2 (PHPUnit 升级) | Architecture 依赖层变更 + Components §1 ✓ |
| Req 3 (phpunit.xml) | Components §2 ✓ |
| Req 4 (Doctrine ORM) | Architecture 依赖层变更 ✓ |
| Req 5 (Attribute 迁移) | Components §3 ✓ |
| Req 6 (symfony/cache) | Components §1 ✓ |
| Req 7 (oasis/logging) | Components §1 ✓ |
| Req 8 (TestEnv API) | Components §4 ✓ |
| Req 9 (Eris 引入) | Components §5.1 ✓ |
| Req 10 (AutoIdTrait PBT) | Components §5.2 + Property 1, 2 ✓ |
| Req 11 (CascadeRemoveTrait PBT) | Components §5.3 + Property 3, 4 ✓ |
| Req 12 (全量测试) | Testing Strategy 验证矩阵 ✓ |

全部覆盖，无遗漏。

### Q2: CR 决策是否已体现在 design 中？

- CR Q1（PBT 批量大小 1–100）：体现在 Property 1, 2 的 generator 设计和 Components §5.2 ✓
- CR Q2（半随机拓扑）：体现在 Components §5.3 的 4 种拓扑模式设计 ✓
- CR Q3（严格零 deprecation warning）：体现在 Components §2 的 `failOnDeprecation="true"` 和 Error Handling ✓

### Q3: Correctness Properties 是否与 requirements 中的 PBT AC 对应？

| Property | 对应 AC |
|----------|---------|
| Property 1 (ID 唯一性) | Req 10 AC2 ✓ |
| Property 2 (ID round-trip) | Req 10 AC3 ✓ |
| Property 3 (强关联清理) | Req 11 AC2 + AC3（合并，消除冗余） ✓ |
| Property 4 (弱关联刷新) | Req 11 AC4 ✓ |

Property 3 合并了 Req 11 AC2（identity map）和 AC3（二级缓存），因为两者在同一次删除操作中验证同一组实体的不同方面，合并为一个 property 更高效且无信息损失。

### Q4: PBT 设计是否可行？

- Eris ^1.0 兼容 PHPUnit 13.x 和 PHP 8.4+（已通过 GitHub README 确认）✓
- `TestTrait` + `forAll` + `then` 模式与 PHPUnit TestCase 兼容 ✓
- SQLite in-memory + ArrayAdapter 的测试环境支持每次迭代重建 schema ✓
- 4 种拓扑模式覆盖了项目中所有 entity 关联类型（OneToMany、ManyToOne、ManyToMany、自引用） ✓
- 数量参数范围 1–10 保持单次迭代数据量可控，100 次迭代总执行时间预计在秒级 ✓

### Q5: Annotation → Attribute 迁移是否完整？

需迁移的文件清单：

| 文件 | 类级别注解 | 属性级别注解 | 方法级别注解 |
|------|-----------|-------------|-------------|
| `src/AutoIdTrait.php` | — | `@ORM\Id`, `@ORM\GeneratedValue`, `@ORM\Column` | — |
| `src/CascadeRemoveTrait.php` | — | — | `@ORM\PreRemove`, `@ORM\PostRemove` |
| `src/CascadeRemovableInterface.php` | — | — | — (无 ORM 注解) |
| `ut/Entity/Article.php` | `@ORM\Entity`, `@ORM\Table`, `@ORM\Cache`, `@ORM\HasLifecycleCallbacks` | `@ORM\ManyToOne`, `@ORM\JoinColumn`, `@ORM\ManyToMany`, `@ORM\JoinTable` | — |
| `ut/Entity/Category.php` | `@ORM\Entity`, `@ORM\Table`, `@ORM\Cache`, `@ORM\HasLifecycleCallbacks` | `@ORM\OneToMany`, `@ORM\ManyToOne`, `@ORM\JoinColumn` | — |
| `ut/Entity/Tag.php` | `@ORM\Entity`, `@ORM\Cache`, `@ORM\HasLifecycleCallbacks` | `@ORM\ManyToMany` | — |
| `ut/Entity/PlainArticle.php` | `@ORM\Entity`, `@ORM\Table`, `@ORM\Cache` | `@ORM\ManyToOne`, `@ORM\JoinColumn` | — |
| `ut/Entity/PlainCategory.php` | `@ORM\Entity`, `@ORM\Table`, `@ORM\Cache` | `@ORM\OneToMany` | — |

共 8 个文件需迁移（`CascadeRemovableInterface.php` 无 ORM 注解，不需要）。

### Q6: 是否有遗漏的风险？

- **PHPUnit 8 → 13 API 兼容性**：已审查现有测试代码，未发现使用已移除的 API（`setUp`/`tearDown` 已有 `: void`，assertion 方法均为 PHPUnit 13 支持的版本）。风险低。
- **Doctrine ORM 2.20 内部 deprecation**：ORM 2.20 可能在内部使用 deprecated PHP 函数。需在升级后运行测试确认。如有 warning，需通过升级 ORM 小版本或 patch 解决。
- **oasis/logging 最新版本兼容性**：goal.md 已确认无兼容性障碍，但需在 `composer install` 后验证。

### Q7: Testing Strategy 是否充分？

- Unit test 覆盖具体场景和边界条件（现有 4 个测试文件，共约 20 个测试方法）✓
- PBT 覆盖通用属性和随机输入（新增 2 个文件，4 个 property，每个 100 次迭代）✓
- 集成验证通过 `vendor/bin/phpunit` 全量运行 ✓
- 零 deprecation warning 通过 `failOnDeprecation="true"` 强制保证 ✓
- 覆盖充分，无明显盲区。

### Q8: Impact Analysis 是否充分？

- 受影响的 state 文档已逐文件、逐 section 列出（`architecture.md`、`data-model.md`、`PROJECT.md`）✓
- 现有行为变化已说明（metadata driver 切换、PHPUnit 版本要求、测试严格度提升）✓
- 数据模型变更已确认不涉及（仅 metadata 声明方式变化）✓
- 外部系统交互已确认不涉及 ✓
- 配置项变更已列出（`composer.json`、`phpunit.xml`）✓
- Graphify 辅助识别了核心影响节点和高连接度 entity ✓
- 下游使用者影响已提及（trait 注解格式变化）✓



---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [内容] `ORMSetup::createAttributeMetadataConfig()` 方法名不存在，Doctrine ORM 2.20 的实际方法名为 `createAttributeMetadataConfiguration()`。已修正 5 处引用，并删除了错误的"PHP 8.4+ 短名方法"说明
- [结构] 缺少 `## Impact Analysis` section（steering 要求必须存在）。已补充完整的影响分析，覆盖受影响的 state 文档、现有行为变化、数据模型变更、外部系统交互、配置项变更，并利用 graphify 图谱辅助识别核心影响节点
- [结构] 缺少 `## Alternatives Considered` section（steering 推荐）。已补充 3 项备选方案及落选理由（PHPUnit 升级路径、Attribute 迁移方式、PBT Property 合并策略）
- [内容] Socratic Review 缺少 Impact Analysis 维度的审查。已补充 Q8 覆盖影响分析充分性检查

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirements 编号、术语引用）
- [x] 代码块语法正确（语言标注、闭合）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] 技术方案主体存在，且承接了 requirements 中的需求
- [x] 接口签名 / 数据模型有明确定义
- [x] 各 section 之间使用 `---` 分隔
- [x] 每条 requirement 在 design 中都有对应的实现描述（12/12）
- [x] 无遗漏的 requirement
- [x] design 中的方案不超出 requirements 的范围
- [x] Impact Analysis 覆盖所有必要维度（state 文档、行为变化、数据模型、外部系统、配置项）
- [x] Impact Analysis 利用 graphify 辅助识别受影响范围
- [x] 技术选型有明确理由
- [x] 接口签名足够清晰，能让 task 独立执行
- [x] 无过度设计
- [x] 与 state 文档中描述的现有架构一致
- [x] Socratic Review 覆盖充分（requirements 覆盖、CR 决策、Property 对应、PBT 可行性、迁移完整性、风险、测试策略、影响分析）
- [x] Requirements CR 决策已全部体现在 design 中（Q1 批量大小、Q2 半随机拓扑、Q3 严格零 warning）
- [x] 技术选型明确，无"待定"或含糊的选型
- [x] 接口定义可执行，参数类型和返回类型明确
- [x] 可 task 化——design 提供了足够的细节支持 task 拆分

### Clarification Round

**状态**: 已完成

**Q1:** Design 中 Annotation → Attribute 迁移和 TestEnv API 替换是两个独立的变更，但它们共享一个前提（Doctrine ORM ^2.20 已安装）。在拆分 task 时，实现顺序会影响中间状态的可测试性。你倾向哪种实现顺序？
- A) 先做 Composer 依赖变更 + phpunit.xml 适配，再做 Annotation → Attribute 迁移 + TestEnv API 替换，最后做 PBT（严格分层，每层完成后可独立验证）
- B) 先做 Composer 依赖变更，然后 Annotation 迁移和 TestEnv API 替换合并为一个 task（因为两者都依赖 ORM 2.20 且需同时完成才能通过测试），最后做 PBT
- C) 按模块切分——先完成 src/ 的 Attribute 迁移，再完成 ut/Entity/ 的迁移，再做 TestEnv，最后 PBT
- D) 其他（请说明）

**A:** B — 先做 Composer 依赖变更，然后 Annotation 迁移和 TestEnv API 替换合并为一个 task，最后做 PBT。

**Q2:** PBT 的两个测试文件（`AutoIdTraitPbtTest` 和 `CascadeRemoveTraitPbtTest`）复杂度差异较大——前者逻辑简单（persist + 验证 ID），后者需要实现 4 种拓扑构建 helper 和 3 个 property 的验证逻辑。在 task 拆分时，你倾向如何处理？
- A) 合并为一个 task（"编写 PBT 测试"），因为两者共享测试基础设施（schema 重建、EM 重置）
- B) 拆为两个独立 task（AutoIdTrait PBT 和 CascadeRemoveTrait PBT），允许分别验证和提交
- C) 拆为三个 task：测试基础设施（PBT 共用的 setUp/helper）、AutoIdTrait PBT、CascadeRemoveTrait PBT
- D) 其他（请说明）

**A:** B — 拆为两个独立 task（AutoIdTrait PBT 和 CascadeRemoveTrait PBT），允许分别验证和提交。

**Q3:** Design 的 Impact Analysis 指出升级完成后需要同步更新 `docs/state/architecture.md`、`docs/state/data-model.md` 和 `PROJECT.md`。这些 state 文档更新应该作为独立 task 还是合并到对应的实现 task 中？
- A) 独立 task——所有 state 文档更新集中在最后一个 task 中，确保反映最终状态
- B) 分散到对应 task——每个实现 task 完成后立即更新相关 state 文档（如 Attribute 迁移 task 同时更新 data-model.md）
- C) 不在 tasks 中处理——state 文档更新由 release 收敛阶段统一处理，不纳入 spec tasks
- D) 其他（请说明）

**A:** C — 不在 tasks 中处理，state 文档更新由 release 收敛阶段统一处理，不纳入 spec tasks。
