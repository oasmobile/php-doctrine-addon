# Requirements Document

`.kiro/specs/release-3.1/` — Release 3.1 (Doctrine ORM 3 / DBAL 4 Upgrade) 需求规格。

---

## Introduction

`oasis/doctrine-addon` 当前依赖 Doctrine ORM 2.20 + DBAL 3.10，间接引入了已 abandoned 的 `doctrine/cache` 和 `doctrine/common`。ORM 2.x 维护窗口至 2027-02，之后仅安全修复。

库的核心组件 `CascadeRemoveTrait` 解决的是"数据库 `ON DELETE CASCADE` 删除了行，但 EntityManager identity map 和 Second Level Cache 中仍残留脏数据"的问题。该 trait 的实现直接调用了 `UnitOfWork` 的 internal API（`getEntityIdentifier`、`isScheduledForDelete`、`isInIdentityMap`），在 ORM 3 中 `UnitOfWork` 已被标记为 `@internal`，存在长期维护风险。

本次 Release 3.1 的目标是：升级 Doctrine ORM 至 ^3.6、DBAL 至 ^4.4，消除 abandoned 间接依赖，验证 ORM 3 是否原生解决了 identity map / L2 cache 脏数据问题，并根据验证结果决定 CascadeRemoveTrait 的处置方案。

**不涉及的内容**：不新增业务功能；不迁移到 Doctrine ORM 4.x（目前仍为 dev 状态）。

---

## Glossary

- **Composer_Config**: `composer.json` 文件，声明项目依赖与版本约束
- **ORM**: Doctrine ORM，对象关系映射框架
- **DBAL**: Doctrine DBAL，数据库抽象层
- **EntityManager**: Doctrine ORM 的核心管理器，负责 entity 的持久化、查询和生命周期管理
- **UnitOfWork**: EntityManager 内部的工作单元，跟踪 entity 状态变更；ORM 3 中已标记为 `@internal`
- **Identity_Map**: EntityManager 内部维护的 entity 缓存，确保同一 entity 在同一请求中只有一个 PHP 对象实例
- **Second_Level_Cache**: Doctrine ORM 二级缓存，用于跨请求缓存 entity 数据
- **CascadeRemoveTrait**: `src/CascadeRemoveTrait.php`，实现级联删除时 identity map 和 L2 cache 失效的 trait
- **CascadeRemovableInterface**: `src/CascadeRemovableInterface.php`，entity 实现此接口以启用级联删除缓存失效机制
- **Source_Component**: `src/` 下的库源码文件集合（`AutoIdTrait.php`、`CascadeRemoveTrait.php`、`CascadeRemovableInterface.php`）
- **Strong_Entity**: 强关联实体，当前实体删除时也应被删除的实体，由数据库 `ON DELETE CASCADE` 约束实际执行删除
- **Dirty_Entity**: 弱关联实体，持有当前实体引用的实体，删除后需刷新其缓存以避免引用失效
- **Contrast_Test**: `ut/Test/CascadeRemoveContrastTest.php`，有/无 trait 的对比测试，用于展示 CascadeRemoveTrait 解决的问题
- **TestEnv**: `ut/TestEnv.php`，测试环境初始化类，负责创建 EntityManager 和 SQLite in-memory 数据库
- **Entity_Fixture**: `ut/Entity/` 下的 Doctrine entity 类，用于测试
- **Test_Suite**: 项目全量测试集合，包含 `ut/Test/` 下所有测试用例
- **PBT_Suite**: 使用 Eris 编写的 property-based test 用例集合
- **Plain_Entity**: `ut/Entity/` 下不使用 CascadeRemoveTrait 的对照组 entity（`PlainArticle`、`PlainCategory`）

---

## Requirements

### Requirement 1: Doctrine ORM 大版本升级

**User Story:** 作为库维护者，我希望将 `doctrine/orm` 升级到 ^3.6，以便跟进 Doctrine 主线版本并获得持续维护支持。

#### Acceptance Criteria

1. THE Composer_Config SHALL 在 `require` 段声明 `doctrine/orm` 约束为 `^3.6`
2. WHEN 执行 `composer install` 时，THE Composer_Config SHALL 成功解析依赖且无冲突
3. WHEN 执行 `composer install` 时，THE Composer_Config SHALL 安装 DBAL ^4.4 版本（作为 ORM ^3.6 的传递依赖或显式声明）

---

### Requirement 2: 消除 abandoned 间接依赖

**User Story:** 作为库维护者，我希望消除 `doctrine/cache` 和 `doctrine/common` 的间接依赖，以便 `composer install` 不再产生 abandoned warning。

#### Acceptance Criteria

1. WHEN 执行 `composer install` 时，THE Composer_Config SHALL 不安装 `doctrine/cache` 包
2. WHEN 执行 `composer install` 时，THE Composer_Config SHALL 不安装 `doctrine/common` 包
3. WHEN 执行 `composer install` 时，THE Composer_Config SHALL 不产生 abandoned package warning

---

### Requirement 3: TestEnv 适配 ORM 3 / DBAL 4

**User Story:** 作为库维护者，我希望 TestEnv 适配 ORM 3 和 DBAL 4 的 API 变更，以便测试环境能正常初始化。

#### Acceptance Criteria

1. THE TestEnv SHALL 使用 ORM 3 推荐的方式创建 EntityManager 实例，不再使用 ORM 2 中已移除的构造函数
2. THE TestEnv SHALL 使用 DBAL 4 兼容的 SQLite 驱动标识符，不再使用 DBAL 3 中已移除的驱动名称
3. WHEN TestEnv 初始化 EntityManager 时，THE TestEnv SHALL 成功启用 Second_Level_Cache 且无 error
4. WHEN TestEnv 初始化 EntityManager 时，THE TestEnv SHALL 启用 SQLite 外键约束

---

### Requirement 4: 验证 ORM 3 对 identity map 脏数据问题的原生解决情况

**User Story:** 作为库维护者，我希望验证 ORM 3 + DBAL 4 环境下，不使用 CascadeRemoveTrait 时 identity map 脏数据问题是否仍然存在，以便决定 trait 的处置方案。

#### Acceptance Criteria

1. WHEN 在 ORM 3 环境下，一个不使用 CascadeRemoveTrait 的 Plain_Entity 的父实体被删除，且子实体被数据库 `ON DELETE CASCADE` 级联删除时，THE EntityManager SHALL 被验证其 Identity_Map 是否仍返回已删除的子实体
2. WHEN 在 ORM 3 环境下，一个不使用 CascadeRemoveTrait 的 Plain_Entity 的父实体被删除，且子实体被数据库 `ON DELETE CASCADE` 级联删除时，THE Second_Level_Cache SHALL 被验证其是否仍包含已删除的子实体
3. WHEN 在 ORM 3 环境下，一个不使用 CascadeRemoveTrait 的 Plain_Entity 的父实体被删除时，THE EntityManager SHALL 被验证子实体的 PHP 对象引用是否仍持有已删除父实体的引用

---

### Requirement 5: 根据验证结果处置 CascadeRemoveTrait — 移除路径

**User Story:** 作为库维护者，如果 ORM 3 已原生解决 identity map / L2 cache 脏数据问题，我希望直接移除 CascadeRemoveTrait 和 CascadeRemovableInterface，以便消除不必要的复杂度。

#### Acceptance Criteria

1. IF Requirement 4 的验证结果表明 ORM 3 已原生解决所有三个脏数据场景（identity map、L2 cache、stale reference），THEN THE Source_Component SHALL 移除 `CascadeRemoveTrait.php` 文件
2. IF Requirement 4 的验证结果表明 ORM 3 已原生解决所有三个脏数据场景，THEN THE Source_Component SHALL 移除 `CascadeRemovableInterface.php` 文件
3. IF CascadeRemoveTrait 和 CascadeRemovableInterface 被移除，THEN THE Entity_Fixture SHALL 不包含任何对 CascadeRemoveTrait 和 CascadeRemovableInterface 的引用
4. IF CascadeRemoveTrait 和 CascadeRemovableInterface 被移除，THEN THE Test_Suite SHALL 移除仅测试 CascadeRemoveTrait 行为的测试文件（`CascadeRemoveTest.php`、`CascadeRemoveTraitTest.php`、`CascadeRemoveTraitPbtTest.php`）

---

### Requirement 6: 根据验证结果处置 CascadeRemoveTrait — 保留路径

**User Story:** 作为库维护者，如果 ORM 3 未完全解决脏数据问题，我希望保留 CascadeRemoveTrait 但消除其对 UnitOfWork internal API 的依赖，以便降低长期维护风险。

#### Acceptance Criteria

1. IF Requirement 4 的验证结果表明 ORM 3 未完全解决脏数据问题，THEN THE CascadeRemoveTrait SHALL 使用 EntityManager public API 替代所有对 UnitOfWork internal API 的直接调用
2. IF CascadeRemoveTrait 被保留，THEN THE CascadeRemoveTrait SHALL 不包含任何对 `UnitOfWork::getEntityIdentifier()`、`UnitOfWork::isScheduledForDelete()`、`UnitOfWork::isInIdentityMap()` 的调用
3. IF CascadeRemoveTrait 被保留，THEN WHEN 删除一个实现 CascadeRemovableInterface 的 entity 时，THE CascadeRemoveTrait SHALL 保持与 ORM 2 版本等价的行为（Strong_Entity 被 detach 并从 L2 cache evict，Dirty_Entity 被 refresh 并从 L2 cache evict）
4. IF CascadeRemoveTrait 的 public API 替代方案需要自维护状态跟踪（如 pendingRemovals 集合），THEN THE CascadeRemoveTrait SHALL 在实施前获得用户确认

---

### Requirement 7: Contrast_Test 断言反转

**User Story:** 作为库维护者，我希望根据 ORM 3 的验证结果更新 Contrast_Test 的断言方向，以便对照组测试作为 ORM 3 行为的回归保护。

#### Acceptance Criteria

1. IF Requirement 4 的验证结果表明 ORM 3 已原生解决某个脏数据场景，THEN THE Contrast_Test 中对应的 "Without trait" 测试用例 SHALL 反转断言（从断言脏数据存在改为断言脏数据不存在）
2. IF Requirement 4 的验证结果表明 ORM 3 未解决某个脏数据场景，THEN THE Contrast_Test 中对应的 "Without trait" 测试用例 SHALL 保持原有断言不变
3. IF CascadeRemoveTrait 被移除（Requirement 5 路径），THEN THE Contrast_Test 中 "With trait" 测试用例 SHALL 被移除，仅保留 "Without trait" 用例作为 ORM 3 原生行为的回归保护
4. IF CascadeRemoveTrait 被保留（Requirement 6 路径），THEN THE Contrast_Test SHALL 保留全部 "With trait" 和 "Without trait" 用例

---

### Requirement 8: 源码适配 ORM 3 API 变更

**User Story:** 作为库维护者，我希望源码中所有 Doctrine API 调用都兼容 ORM 3 / DBAL 4，以便库在新版本下正常工作。

#### Acceptance Criteria

1. THE Source_Component SHALL 不包含任何 ORM 3 中已移除或已变更签名的 API 调用
2. THE Entity_Fixture SHALL 不包含任何 ORM 3 中已移除或已变更签名的 mapping attribute 用法
3. IF `CascadeRemovableInterface` 中的 `LifecycleEventArgs` import 路径在 ORM 3 中发生变更，THEN THE CascadeRemovableInterface SHALL 更新为 ORM 3 兼容的 import 路径

---

### Requirement 9: CLI 配置适配

**User Story:** 作为库维护者，我希望 `ut/cli-config.php` 适配 ORM 3 的 CLI API 变更，以便 Doctrine CLI 工具可用。

#### Acceptance Criteria

1. THE `ut/cli-config.php` SHALL 使用 ORM 3 兼容的 CLI 配置方式，不再使用 ORM 2 中已移除的 `ConsoleRunner::createHelperSet()` 方法
2. WHEN 执行 Doctrine CLI 命令时，THE `ut/cli-config.php` SHALL 正确提供 EntityManager 实例

---

### Requirement 10: PHPUnit 配置适配

**User Story:** 作为库维护者，我希望 PHPUnit 配置在 trait 移除或保留场景下都正确反映测试文件列表，以便测试运行器能执行正确的测试集合。

#### Acceptance Criteria

1. IF CascadeRemoveTrait 被移除（Requirement 5 路径），THEN THE `phpunit.xml` SHALL 从 test suite 定义中移除已删除的测试文件引用
2. IF CascadeRemoveTrait 被保留（Requirement 6 路径），THEN THE `phpunit.xml` SHALL 保持现有 test suite 定义不变
3. THE `phpunit.xml` SHALL 保留 `bootstrap` 属性指向 `ut/bootstrap.php`

---

### Requirement 11: 全量测试通过

**User Story:** 作为库维护者，我希望所有测试在 ORM 3 + DBAL 4 环境下全部通过，以确保升级不引入回归。

#### Acceptance Criteria

1. WHEN 执行 `vendor/bin/phpunit` 时，THE Test_Suite SHALL 全部通过（exit code 0）
2. WHEN 执行 `vendor/bin/phpunit` 时，THE Test_Suite SHALL 不产生 deprecation warning（PHPUnit 配置中 `failOnDeprecation` 为 `true`）
3. THE Test_Suite SHALL 包含 PBT_Suite 用例（AutoIdTrait PBT 和 CascadeRemoveTrait PBT，后者视 Requirement 5/6 路径决定保留或移除）

---

### Requirement 12: 升级过程中发现的问题修复

**User Story:** 作为库维护者，我希望升级过程中发现的所有问题都被修复，不区分问题是否由升级引入，以便交付一个干净的 3.1 版本。

#### Acceptance Criteria

1. WHEN 升级过程中发现代码问题（编译错误、运行时错误、测试失败）时，THE Source_Component 或 Test_Suite SHALL 修复该问题，无论问题是由 ORM 3 / DBAL 4 变更引入还是既有缺陷
2. WHEN 升级过程中发现依赖兼容性问题时，THE Composer_Config SHALL 调整依赖约束或替换不兼容的依赖

---

## Socratic Review

### Q1: 需求是否完整覆盖了 goal.md 中的所有目标？

逐项对照：

| Goal | 对应 Requirement |
|------|-----------------|
| 升级 `doctrine/orm` 至 `^3.6`，`doctrine/dbal` 至 `^4.4` | Req 1 |
| 消除 `doctrine/cache` 和 `doctrine/common` 间接依赖 | Req 2 |
| 验证 ORM 3 是否原生解决脏数据问题 | Req 4 |
| 如果已解决：移除 CascadeRemoveTrait 和 CascadeRemovableInterface | Req 5 |
| 如果未解决：保留 trait 但消除 UnitOfWork internal API 依赖 | Req 6 |
| Contrast_Test 根据验证结果反转断言 | Req 7 |
| TestEnv 适配 ORM 3 / DBAL 4 | Req 3 |
| 源码适配 ORM 3 API 变更 | Req 8 |
| CLI 配置适配 | Req 9 |
| PHPUnit 配置适配 | Req 10 |
| 全量测试通过（含 PBT） | Req 11 |
| 修复升级过程中发现的所有问题 | Req 12 |

全部覆盖，无遗漏。

### Q2: 每条 AC 是否遵循 EARS 模式？

逐条检查：
- Req 1: AC1 ubiquitous (SHALL)，AC2–AC3 event-driven (WHEN...SHALL) ✓
- Req 2: AC1–AC3 event-driven (WHEN...SHALL) ✓
- Req 3: AC1–AC2 ubiquitous，AC3–AC4 event-driven ✓
- Req 4: AC1–AC3 event-driven (WHEN...SHALL) ✓
- Req 5: AC1–AC4 unwanted-event / complex (IF...THEN...SHALL) ✓
- Req 6: AC1–AC4 unwanted-event / complex (IF...THEN...SHALL) ✓
- Req 7: AC1–AC4 unwanted-event / complex (IF...THEN...SHALL) ✓
- Req 8: AC1–AC2 ubiquitous，AC3 unwanted-event (IF...THEN...SHALL) ✓
- Req 9: AC1 ubiquitous，AC2 event-driven ✓
- Req 10: AC1–AC2 unwanted-event (IF...THEN...SHALL)，AC3 ubiquitous ✓
- Req 11: AC1–AC2 event-driven，AC3 ubiquitous ✓
- Req 12: AC1–AC2 event-driven (WHEN...SHALL) ✓

### Q3: 是否存在模糊用语或不可测试的 AC？

- Req 4 AC1–AC3 使用"SHALL 被验证"而非"SHALL 做某事"——这是验证性需求，验证结果决定后续路径（Req 5 或 Req 6），表达合理
- Req 6 AC3 中"等价的行为"——在 design 阶段需明确等价性的具体验证方式，但需求层面已通过括号列出了行为要点
- Req 12 AC1 中"代码问题"——范围较宽，但配合"编译错误、运行时错误、测试失败"的括号说明已足够明确
- 其余 AC 均有明确的可验证条件

### Q4: Requirement 5 和 Requirement 6 的互斥关系是否清晰？

- Req 5 以 "IF Requirement 4 的验证结果表明 ORM 3 已原生解决所有三个脏数据场景" 为前提
- Req 6 以 "IF Requirement 4 的验证结果表明 ORM 3 未完全解决脏数据问题" 为前提
- 两者互斥，不会同时生效
- Req 7 的 AC 分别引用了两条路径，覆盖了两种情况 ✓

### Q5: Non-Goal 是否被意外纳入？

- 未包含新业务功能需求 ✓
- 未包含 Doctrine ORM 4.x 迁移需求 ✓
- 未变更公共 API 签名（除 Req 5 移除路径，已在 goal.md 中明确允许 breaking change）✓

### Q6: 是否有遗漏的错误路径或边界条件？

- Req 4 验证可能出现"部分解决"的情况（如 identity map 已修复但 L2 cache 未修复）——Req 5 要求"所有三个场景"都解决才走移除路径，否则走 Req 6 保留路径，边界清晰 ✓
- Req 6 AC4 覆盖了"需要自维护状态跟踪"的特殊情况，要求用户确认 ✓
- Req 12 覆盖了升级过程中的意外问题 ✓

### Q7: 各 requirement 之间是否存在矛盾或重叠？

- Req 5 和 Req 6 互斥，不矛盾 ✓
- Req 7 依赖 Req 4 的验证结果和 Req 5/6 的路径选择，逻辑链清晰 ✓
- Req 11 AC2（无 deprecation warning）与 Req 8（源码适配）互补——Req 8 确保代码层面兼容，Req 11 确保运行时无 warning ✓
- 无实质矛盾 ✓

### Q8: 是否有隐含的前置假设未显式列出？

- 假设 ORM 3.6 + DBAL 4.4 依赖解析无冲突（PRP-002 Notes 已确认 dry-run 通过）
- 假设 `Doctrine\Persistence\Event\LifecycleEventArgs` 在 ORM 3 中仍可用（PRP-002 Scope 已提及需确认）
- 假设 Eris ^1.0 兼容 ORM 3 环境（Eris 不直接依赖 Doctrine，无兼容性风险）
- 假设 `symfony/cache` ^7.2 兼容 ORM 3 的 Second Level Cache API（两者独立，ArrayAdapter 接口稳定）
- 所有前置假设均有据可查 ✓


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [格式] 标题下方路径使用了占位符 `<spec-dir>`，修正为实际路径 `.kiro/specs/release-3.1/`
- [内容] Glossary 缺少 `Source_Component` 定义——该术语在 Req 5、Req 8、Req 12 的 AC 中作为 Subject 使用，已补充定义
- [内容] Req 5 AC3 列出了具体的代码元素（`use` 语句、`implements` 声明、attribute、方法名），属于实现细节，已简化为外部可观察的行为描述

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、术语表术语在正文中使用）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] Introduction 存在，描述了 feature 范围
- [x] Introduction 明确了不涉及的内容（Non-scope）
- [x] Glossary 存在且非空，术语格式正确
- [x] Glossary 术语在 AC 中被实际使用，AC 中的 Subject 在 Glossary 中有定义
- [x] Requirements section 存在且包含 12 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] 每条 requirement 包含 User Story 和 Acceptance Criteria
- [x] User Story 使用中文行文
- [x] AC 遵循 EARS 模式（SHALL / WHEN...SHALL / IF...THEN...SHALL）
- [x] AC Subject 使用 Glossary 中定义的术语
- [x] AC 编号连续，无跳号
- [x] Socratic Review 存在且覆盖充分（8 个维度）
- [x] Goal CR 决策已体现在 requirements 中（Q1→Req5, Q2→Req6, Q3→Req7, Q4→Introduction）
- [x] 文档整体目标清晰，scope 边界明确
- [x] AC 整体构成充分的验收条件
- [x] 仅凭 requirements 可进入 design 阶段

### Clarification Round

**状态**: 已完成

**Q1:** Req 6（保留路径）中，EntityManager public API 替代 UnitOfWork internal API 时，`getEntityIdentifier()` 的替代方案存在多种可能。ORM 3 的 `EntityManager` 提供了 `getUnitOfWork()` 但 UoW 已标记 `@internal`；另一种方式是通过 ClassMetadata 获取 identifier field name 再从 entity 读取属性值。design 阶段应采用哪种策略？
- A) 通过 `ClassMetadata::getIdentifierValues($entity)` 获取 identifier，完全不触碰 UnitOfWork
- B) 通过 entity 的 `getId()` 方法获取 identifier（本项目所有 entity 都使用 AutoIdTrait，有统一的 `getId()`）
- C) 两者结合：优先使用 `getId()`，对未使用 AutoIdTrait 的 entity fallback 到 ClassMetadata
- D) 其他（请说明）

**A:** 延迟到 design 阶段决定——需要先分析 `getEntityIdentifier()` 在 trait 中的实际用途再选择替代方案

**Q2:** Req 5（移除路径）执行后，`AutoIdTrait` 将成为库唯一的功能组件。此时库的 `composer.json` 中 `doctrine/orm` 依赖是否应从 `require` 降级为 `require-dev`（因为 AutoIdTrait 仅使用 ORM 的 mapping attribute，运行时不需要完整 ORM）？还是保持 `require` 不变？
- A) 保持 `require` 不变——AutoIdTrait 使用了 `#[ORM\Id]` 等 attribute，这些 attribute class 在运行时需要可加载
- B) 降级为 `require-dev`——attribute 在 PHP 8 中是惰性加载的，不实例化就不需要 class 存在
- C) 拆分为两个包（超出本次 scope，仅记录为后续 proposal）
- D) 其他（请说明）

**A:** A — 保持 `require` 不变，库的用户本身就是 ORM 用户

**Q3:** Req 7（Contrast_Test 断言反转）中，如果 ORM 3 仅解决了部分脏数据场景（如 identity map 已修复但 L2 cache 未修复），Contrast_Test 中已修复场景的 "Without trait" 用例应如何处理？
- A) 逐场景反转——已修复的场景反转断言，未修复的保持原样，同一个 test class 中混合两种断言方向
- B) 按场景拆分 test method——将 "Without trait" 拆为独立的 test method，每个 method 根据验证结果独立决定断言方向
- C) 保持当前粒度不变——Contrast_Test 已经是按场景分 method 的（`testWithoutTrait_IdentityMap...`、`testWithoutTrait_SecondLevelCache...`、`testWithoutTrait_StaleCollection...`），直接在对应 method 中反转即可
- D) 其他（请说明）

**A:** C — 保持当前粒度，已按场景分 method，直接在对应 method 中反转

**Q4:** Req 3 AC4 要求 TestEnv 启用 SQLite 外键约束。当前 TestEnv 是否已启用外键约束？如果未启用，这属于既有缺陷修复（Req 12 范畴）还是新增行为？这影响 design 阶段是否需要额外验证外键约束对现有测试的影响。
- A) 当前已启用——AC4 是对现有行为的确认，design 阶段无需额外处理
- B) 当前未启用——属于既有缺陷，design 阶段需评估启用后对现有测试的影响
- C) 不确定——design 阶段先检查 TestEnv 源码再决定
- D) 其他（请说明）

**A:** C — 不确定，design 阶段先检查 TestEnv 源码再决定
