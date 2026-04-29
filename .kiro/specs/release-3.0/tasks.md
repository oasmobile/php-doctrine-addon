# Implementation Plan: Release 3.0 (PHP 8.5 Upgrade)

## Overview

基于 design.md 的 6 个变更维度，按 Design CR Q1 决策的实现顺序编排：先完成 Composer 依赖变更与 PHPUnit 配置适配，再将 Annotation → Attribute 迁移与 TestEnv deprecated API 替换合并为一个 task，最后分两个独立 task 完成 PBT（Design CR Q2 决策）。State 文档更新不纳入本 spec（Design CR Q3 决策），由 release 收敛阶段统一处理。

## Tasks

- [x] 1. Composer 依赖变更与 PHPUnit 配置适配
  - [x] 1.1 修改 `composer.json` 依赖约束
    - 在 `require` 段新增 `php: ^8.4`
    - 将 `doctrine/orm` 从 `^2.7` 改为 `^2.20`
    - 将 `oasis/logging` 从 `^1.1` 升级到最新版本
    - 移除 `doctrine/annotations`
    - 在 `require-dev` 段将 `phpunit/phpunit` 从 `^8.5` 改为 `^13.0`
    - 将 `symfony/cache` 从 `^5.4` 改为 `^7.2`
    - 新增 `giorgiosironi/eris: ^1.0`
    - 执行 `composer update` 确认依赖解析成功
    - _Requirements: 1.1, 1.2, 2.1, 4.1, 4.2, 6.1, 7.1, 7.2, 9.1, 9.2_
  - [x] 1.2 适配 `phpunit.xml` 为 PHPUnit 13 格式
    - 将 schema 声明从 `http://schema.phpunit.de/5.3/phpunit.xsd` 改为 `vendor/phpunit/phpunit/phpunit.xsd`
    - 添加 `displayDetailsOnTestsThatTriggerDeprecations="true"` 属性
    - 添加 `failOnDeprecation="true"` 属性（CR Q3 严格零 warning 决策）
    - 保留 `bootstrap="ut/bootstrap.php"` 和现有 test suite 定义
    - 预留新增 PBT 测试文件的 `<file>` 条目（`AutoIdTraitPbtTest.php`、`CascadeRemoveTraitPbtTest.php`）
    - _Requirements: 3.1, 3.2, 3.3, 12.2_
  - [x] 1.3 Checkpoint: 执行 `composer validate` 确认配置合法，commit
    - 验证：`composer validate` 无 error
    - 注意：此时测试尚不能通过（TestEnv 仍使用 deprecated API + Annotation driver），仅验证依赖解析和配置格式
    - commit message 参考：`chore: upgrade composer deps for PHP 8.5 + PHPUnit 13`

- [x] 2. Annotation → Attribute 迁移与 TestEnv API 替换
  - [x] 2.1 迁移 `src/AutoIdTrait.php` 的 ORM Annotation 到 Attribute
    - 将 `@ORM\Id()`、`@ORM\GeneratedValue(strategy="AUTO")`、`@ORM\Column(type="integer")` 迁移为 `#[ORM\Id]`、`#[ORM\GeneratedValue(strategy: 'AUTO')]`、`#[ORM\Column(type: 'integer')]`
    - 移除包含 ORM annotation 的 docblock
    - _Requirements: 5.1_
  - [x] 2.2 迁移 `src/CascadeRemoveTrait.php` 的 ORM Annotation 到 Attribute
    - 将 `@ORM\PreRemove()` 和 `@ORM\PostRemove()` 迁移为 `#[ORM\PreRemove]` 和 `#[ORM\PostRemove]`
    - 移除包含 ORM annotation 的 docblock（保留非 ORM 的 PHPDoc）
    - _Requirements: 5.1_
  - [x] 2.3 迁移 `ut/Entity/Article.php` 的 ORM Annotation 到 Attribute
    - 迁移类级别注解：`@ORM\Entity`、`@ORM\Table`、`@ORM\Cache`、`@ORM\HasLifecycleCallbacks`
    - 迁移属性级别注解：`@ORM\ManyToOne`、`@ORM\JoinColumn`、`@ORM\ManyToMany`、`@ORM\JoinTable`（含嵌套 `@ORM\JoinColumn`）
    - `targetEntity` 参数从字符串改为 `::class` 引用
    - _Requirements: 5.2_
  - [x] 2.4 迁移 `ut/Entity/Category.php` 的 ORM Annotation 到 Attribute
    - 迁移类级别注解：`@ORM\Entity`、`@ORM\Table`、`@ORM\Cache`、`@ORM\HasLifecycleCallbacks`
    - 迁移属性级别注解：`@ORM\OneToMany`、`@ORM\ManyToOne`、`@ORM\JoinColumn`
    - _Requirements: 5.2_
  - [x] 2.5 迁移 `ut/Entity/Tag.php` 的 ORM Annotation 到 Attribute
    - 迁移类级别注解：`@ORM\Entity`、`@ORM\Cache`、`@ORM\HasLifecycleCallbacks`
    - 迁移属性级别注解：`@ORM\ManyToMany`
    - _Requirements: 5.2_
  - [x] 2.6 迁移 `ut/Entity/PlainArticle.php` 的 ORM Annotation 到 Attribute
    - 迁移类级别注解：`@ORM\Entity`、`@ORM\Table`、`@ORM\Cache`
    - 迁移属性级别注解：`@ORM\ManyToOne`、`@ORM\JoinColumn`
    - _Requirements: 5.2_
  - [x] 2.7 迁移 `ut/Entity/PlainCategory.php` 的 ORM Annotation 到 Attribute
    - 迁移类级别注解：`@ORM\Entity`、`@ORM\Table`、`@ORM\Cache`
    - 迁移属性级别注解：`@ORM\OneToMany`
    - _Requirements: 5.2_
  - [x] 2.8 替换 `ut/TestEnv.php` 中的 deprecated API
    - 将 `use Doctrine\ORM\Tools\Setup` 替换为 `use Doctrine\ORM\ORMSetup`
    - 将 `Setup::createAnnotationMetadataConfiguration(...)` 替换为 `ORMSetup::createAttributeMetadataConfiguration([__DIR__ . '/Entity'], $isDevMode)`
    - 将 `EntityManager::create($connection, $config)` 替换为 `new EntityManager($connection, $config)`
    - _Requirements: 5.3, 5.4, 8.1, 8.2, 8.3_
  - [x] 2.9 验证 Annotation 残留并运行全量测试
    - 执行 `grep -r '@ORM\\' src/ ut/Entity/` 确认无 Annotation 残留
    - 执行 `vendor/bin/phpunit` 确认全量测试通过且零 deprecation warning
    - _Requirements: 2.2, 2.3, 5.5, 6.2, 12.1, 12.2_
  - [x] 2.10 Checkpoint: 全量测试通过且零 deprecation warning，commit
    - 验证：`vendor/bin/phpunit` exit code 0，无 deprecation warning
    - commit message 参考：`refactor: migrate annotations to attributes, replace deprecated APIs`

- [x] 3. AutoIdTrait Property-Based Test
  - [x] 3.1 编写 `ut/Test/AutoIdTraitPbtTest.php`
    - 创建测试类，`use Eris\TestTrait`
    - 实现 Property 1（ID 唯一性与正整数约束）：使用 `Generator\choose(1, 100)` 生成批量大小 N，persist N 个 Article，flush 后验证所有 ID 互不相同且均为正整数
    - 实现 Property 2（ID round-trip）：使用 `Generator\choose(1, 100)` 生成批量大小 N，persist N 个 Article → flush → 记录 ID → clear → find → 验证 ID 一致
    - 每次迭代前 `resetEntityManager` + `dropDatabase` + `createSchema` 确保隔离
    - 每个 property test 方法的 docblock 标注 `Feature: release-3.0, Property N: {property_text}`
    - **Property 1: AutoIdTrait 批量 ID 唯一性与正整数约束**
    - **Validates: Requirements 10.2**
    - **Property 2: AutoIdTrait ID 持久化 round-trip**
    - **Validates: Requirements 10.3**
    - _Requirements: 10.1, 10.2, 10.3, 10.4_
  - [x] 3.2 Checkpoint: 执行 `vendor/bin/phpunit` 确认全量测试通过（含 PBT），commit
    - 验证：全量测试通过，AutoIdTrait PBT 100 次迭代全部通过，零 deprecation warning
    - commit message 参考：`test: add AutoIdTrait property-based tests`

- [x] 4. CascadeRemoveTrait Property-Based Test
  - [x] 4.1 编写 `ut/Test/CascadeRemoveTraitPbtTest.php`
    - 创建测试类，`use Eris\TestTrait`
    - 实现半随机拓扑 generator（CR Q2 决策）：使用 `Generator\oneOf()` 在 4 种模式间随机选择（`single-parent`、`parent-with-tags`、`tag-hub`、`deep-chain`），使用 `Generator\choose(1, 10)` 生成数量参数 N 和 M
    - 实现拓扑构建 helper 方法，根据模式和参数创建 entity 图并返回删除目标、强关联实体列表、弱关联实体列表
    - 实现 Property 3（强关联实体清理）：删除根 entity 后，验证所有强关联实体 (a) `$em->find()` 返回 null（不在 identity map 且 DB 已删除），(b) `$cache->containsEntity()` 返回 false
    - 实现 Property 4（弱关联实体刷新正确性）：删除根 entity 后，验证所有弱关联实体若未被同时删除则 `$em->find()` 返回非 null 且 `$cache->containsEntity()` 返回 false
    - 每次迭代前 `resetEntityManager` + `dropDatabase` + `createSchema` 确保隔离，并预热二级缓存
    - 每个 property test 方法的 docblock 标注 `Feature: release-3.0, Property N: {property_text}`
    - **Property 3: CascadeRemoveTrait 强关联实体清理（identity map + L2 cache）**
    - **Validates: Requirements 11.2, 11.3**
    - **Property 4: CascadeRemoveTrait 弱关联实体刷新正确性**
    - **Validates: Requirements 11.4**
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5_
  - [x] 4.2 Checkpoint: 执行 `vendor/bin/phpunit` 确认全量测试通过（含所有 PBT），commit
    - 验证：全量测试通过，CascadeRemoveTrait PBT 100 次迭代全部通过，零 deprecation warning
    - commit message 参考：`test: add CascadeRemoveTrait property-based tests`

- [-] 5. 手工测试与集成验证
  - [x] 5.1 Increment alpha tag
    - 查询已有 alpha tag（`git tag -l 'v3.0-alpha*'`），取最大序号 +1，打新 tag
    - 如无 alpha tag 则打 `v3.0-alpha1`
  - [x] 5.2 全量测试验证
    - 执行 `vendor/bin/phpunit`，确认 exit code 0
    - 确认零 PHP deprecated warning（包括第三方依赖）
    - 确认所有现有测试 + PBT 用例全部通过
    - _Requirements: 12.1, 12.2, 12.3_
  - [x] 5.3 Composer 配置验证
    - 执行 `composer validate` 确认无 error
    - 执行 `composer install --dry-run` 确认依赖解析无冲突
    - _Requirements: 1.2, 4.2, 7.2, 9.2_
  - [x] 5.4 Annotation 残留扫描
    - 执行 `grep -r '@ORM\\' src/ ut/Entity/` 确认返回空结果
    - _Requirements: 5.1, 5.2_
  - [-] 5.5 Checkpoint: 所有验证通过，commit
    - 验证：全量测试通过、composer validate 通过、无 Annotation 残留
    - commit message 参考：`test: release 3.0 alpha integration verification`

- [~] 6. Code Review
  - 委托给 `code-reviewer` sub-agent 执行
  - Review 范围：本 spec 所有变更文件（`composer.json`、`phpunit.xml`、`src/` 下 3 个文件、`ut/Entity/` 下 5 个文件、`ut/TestEnv.php`、新增的 2 个 PBT 测试文件）

## Issues

（stabilize 阶段新发现的 issue，初始为空）

## Notes

- 执行时须遵循 `spec-execution.md` 规范，commit 随 checkpoint 一起执行
- 测试命令：`vendor/bin/phpunit`（SQLite in-memory，零外部依赖）
- PHP 运行环境要求 ≥ 8.4（当前环境为 8.5）
- Design CR Q3 决策：state 文档（`docs/state/architecture.md`、`docs/state/data-model.md`、`PROJECT.md`）更新不在本 spec 范围内，由 release 收敛阶段统一处理
- Annotation → Attribute 迁移与 TestEnv API 替换合并为一个 task（Design CR Q1 决策），因为两者都依赖 ORM 2.20 且需同时完成才能通过测试
- PBT 拆为两个独立 task（Design CR Q2 决策），AutoIdTrait PBT 和 CascadeRemoveTrait PBT 可分别验证和提交
- PBT 批量大小范围 1–100（Requirements CR Q1 决策），entity 拓扑采用半随机模式（Requirements CR Q2 决策）
- `failOnDeprecation="true"` 确保任何 deprecation warning 都会导致测试失败（Requirements CR Q3 决策）
- Task 2 的 sub-task 2.1–2.7 修改不同文件，可并行执行；2.8 依赖 2.1–2.7 完成后执行；2.9–2.10 串行

---

## Socratic Review

### Q1: tasks 是否完整覆盖了 design 中的所有实现项？

逐项对照 design 的 6 个变更维度：

| Design 变更维度 | 对应 Task |
|----------------|-----------|
| Composer 依赖约束 | Task 1.1 ✓ |
| PHPUnit 配置 | Task 1.2 ✓ |
| Annotation → Attribute 迁移 | Task 2.1–2.7 ✓ |
| TestEnv deprecated API 替换 | Task 2.8 ✓ |
| PBT 引入（AutoIdTrait） | Task 3.1 ✓ |
| PBT 引入（CascadeRemoveTrait） | Task 4.1 ✓ |
| 零 deprecation warning | Task 1.2（failOnDeprecation）+ Task 2.9/5.2（验证）✓ |

全部覆盖，无遗漏。

### Q2: task 之间的依赖顺序是否正确？

- Task 1（Composer + phpunit.xml）→ Task 2（Annotation 迁移 + TestEnv）：正确，Task 2 依赖 ORM 2.20 和 PHPUnit 13 已安装
- Task 2 → Task 3/4（PBT）：正确，PBT 依赖 Eris 已安装且 Attribute 迁移完成
- Task 3 → Task 4：无强依赖，但顺序合理（AutoIdTrait PBT 更简单，先完成可验证 Eris 集成）
- Task 4 → Task 5（手工测试）→ Task 6（Code Review）：正确，先完成所有实现再验证再 review

无循环依赖，无隐含的前置依赖未体现。

### Q3: 每个 task 的粒度是否合适？

- Task 1：3 个 sub-task（composer.json + phpunit.xml + checkpoint），粒度合适
- Task 2：10 个 sub-task（8 个文件迁移 + 验证 + checkpoint），每个 sub-task 对应一个文件，粒度合适
- Task 3：2 个 sub-task（编写 PBT + checkpoint），粒度合适
- Task 4：2 个 sub-task（编写 PBT + checkpoint），粒度合适
- Task 5：5 个 sub-task（alpha tag + 3 项验证 + checkpoint），粒度合适
- Task 6：Code Review，无需拆分

无过粗或过细的 task。

### Q4: checkpoint 的设置是否覆盖了关键阶段？

| Checkpoint | 验证内容 | 覆盖阶段 |
|------------|----------|----------|
| 1.3 | `composer validate` | 依赖变更后 ✓ |
| 2.10 | 全量测试 + 零 deprecation | Annotation 迁移后 ✓ |
| 3.2 | 全量测试含 AutoIdTrait PBT | PBT 第一阶段 ✓ |
| 4.2 | 全量测试含所有 PBT | PBT 第二阶段 ✓ |
| 5.5 | 集成验证 + composer validate + 残留扫描 | 最终验证 ✓ |

每个关键阶段都有 checkpoint，覆盖充分。

### Q5: 标注为可并行的 sub-task 是否满足并行条件？

Task 2 的 sub-task 2.1–2.7 标注为可并行：
- 2.1 修改 `src/AutoIdTrait.php`
- 2.2 修改 `src/CascadeRemoveTrait.php`
- 2.3 修改 `ut/Entity/Article.php`
- 2.4 修改 `ut/Entity/Category.php`
- 2.5 修改 `ut/Entity/Tag.php`
- 2.6 修改 `ut/Entity/PlainArticle.php`
- 2.7 修改 `ut/Entity/PlainCategory.php`

各 sub-task 修改不同文件，无调用依赖，并行条件成立 ✓

### Q6: 手工测试是否覆盖了 requirements 中的关键用户场景？

Task 5 覆盖：
- 全量测试验证（Req 12）✓
- Composer 配置验证（Req 1, 4, 7, 9）✓
- Annotation 残留扫描（Req 5）✓
- 零 deprecation warning（Req 12 AC2）✓

作为 library 项目，无 UI 或用户交互场景，手工测试以命令行验证为主，覆盖充分。


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [结构] 缺少 `## Issues` section（release spec 要求必须存在），已补充空的 Issues section
- [结构] 缺少 `## Socratic Review` section，已补充覆盖 6 个维度（design 覆盖、依赖顺序、粒度、checkpoint、并行条件、手工测试）
- [内容] Task 2.9 的 requirement 引用遗漏了 Req 2 AC 2.2（PHPUnit API 适配）、Req 2 AC 2.3（测试通过无 deprecation）和 Req 6 AC 6.2（symfony/cache API 兼容），已补充
- [内容] Code Review task（Task 6）展开了 review checklist 细节（"重点关注"列表），违反 steering 要求（review checklist 由 code-reviewer agent 自身定义），已移除
- [内容] Notes section 缺少"commit 随 checkpoint 一起执行"的显式说明，已补充

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（requirement 编号、design 模块名）
- [x] checkbox 语法正确（`- [ ]`）
- [x] 无 markdown 格式错误
- [x] `## Tasks` section 存在
- [x] `## Issues` section 存在（release spec 要求）
- [x] Release spec 手工测试类 top-level task 的第一个 sub-task 是 "Increment alpha tag"
- [x] 最后一个 top-level task 是 Code Review
- [x] 自动化实现 task 排在手工测试和 Code Review 之前
- [x] 所有 task 使用 checkbox 语法
- [x] top-level task 有序号，sub-task 有层级序号，序号连续无跳号
- [x] 每个实现类 sub-task 引用了具体的 requirements 条款
- [x] requirements.md 中的每条 requirement 至少被一个 task 引用（12/12）
- [x] 无悬空引用
- [x] top-level task 按依赖关系排序，无循环依赖
- [x] 并行标注（Task 2.1–2.7）条件成立：各 sub-task 修改不同文件，无调用依赖
- [x] 已对核心模块执行 graphify 依赖查询（AutoIdTrait、CascadeRemoveTrait、TestEnv）
- [x] task 排序与 graphify 揭示的模块依赖一致，无遗漏的隐含跨模块依赖
- [x] 每个 top-level task 的最后一个 sub-task 是 checkpoint
- [x] checkpoint 包含具体验证命令和 commit 动作
- [x] 每个 sub-task 足够具体，可独立执行
- [x] 无过粗或过细的 task
- [x] 所有 task 均为 mandatory
- [x] 手工测试 top-level task 存在，覆盖关键验证场景
- [x] Code Review 是最后一个 top-level task，委托给 code-reviewer
- [x] Code Review task 未展开 review checklist
- [x] `## Notes` section 存在
- [x] Notes 明确提到遵循 `spec-execution.md`
- [x] Notes 明确 commit 随 checkpoint 一起执行
- [x] Notes 包含当前 spec 特有的执行要点（CR 决策、并行说明、测试命令）
- [x] Socratic Review 存在且覆盖充分
- [x] Design CR 决策已全部体现在 tasks 编排中（Q1 实现顺序、Q2 PBT 拆分、Q3 state 文档排除）
- [x] Requirements CR 决策已全部体现（Q1 批量大小、Q2 半随机拓扑、Q3 严格零 warning）
- [x] Design 全覆盖：所有 6 个变更维度均有对应 task
- [x] 每个 sub-task 可独立执行，无需猜测上下文
- [x] 验收闭环完整：checkpoint + 手工测试 + code review
- [x] 执行路径无歧义，依赖关系清晰
