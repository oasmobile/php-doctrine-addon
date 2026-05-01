# Implementation Plan: Release 3.1 (Doctrine ORM 3 / DBAL 4 Upgrade)

## Overview

基于 design.md 的处置路径决策树，按 Design CR 决策编排实施顺序：先将 Composer 升级 + TestEnv 适配 + CLI 适配 + 源码适配合并为一个大 task（CR Q1 决策：各项变更相互依赖），再以独立 task 运行 Contrast_Test 验证 ORM 3 脏数据行为（CR Q2 决策），然后按两条路径分别编排 task 并标注 IF 条件（CR Q3 决策：两条路径同时存在于 tasks.md），执行时跳过不适用的 task。State 文档更新不纳入本 spec（CR Q4 决策），由 release 收敛阶段统一处理。

本次 release 不新增 Correctness Properties（design 决策：基础设施升级，无新业务逻辑，现有 PBT 覆盖保留路径下的行为等价性验证）。

## Tasks

- [x] 1. Composer 依赖升级 + TestEnv 适配 + CLI 适配 + 源码适配
  - [x] 1.1 修改 `composer.json` 依赖约束并执行 `composer update`
    - 将 `require` 段的 `doctrine/orm` 从 `^2.20` 改为 `^3.6`
    - `doctrine/dbal` 无需显式声明——ORM ^3.6 会自动拉入 DBAL ^4.4 作为传递依赖
    - `require-dev` 中的 `phpunit/phpunit`、`symfony/cache`、`giorgiosironi/eris` 版本约束不变
    - 执行 `composer update` 确认依赖解析成功
    - 验证 `composer.lock` 中 `doctrine/orm` 版本 ≥ 3.6，`doctrine/dbal` 版本 ≥ 4.4
    - 验证 `composer.lock` 中不包含 `doctrine/cache` 和 `doctrine/common`
    - _Requirements: 1.1, 1.2, 1.3, 2.1, 2.2, 2.3_
  - [x] 1.2 适配 `ut/TestEnv.php`
    - 将 `ORMSetup::createAttributeMetadataConfiguration(...)` 替换为 `ORMSetup::createAttributeMetadataConfig(...)`（ORM 3.5+ deprecated 旧方法名）
    - 在 `$config` 创建后添加 `$config->enableNativeLazyObjects(true)`（PHP 8.4+ 环境下 ORM 3.5+ 要求）
    - EntityManager 构造方式 `new EntityManager($connection, $config)` 无需变更（ORM 3.6 仍为 public 构造函数）
    - DBAL 驱动标识符 `pdo_sqlite` 无需变更（DBAL 4.4 仍支持）
    - SQLite FK 约束 `PRAGMA foreign_keys = ON` 无需变更（已启用）
    - 参考 design Components §2
    - _Requirements: 3.1, 3.2, 3.3, 3.4_
  - [x] 1.3 适配 `ut/cli-config.php`
    - 将 `return ConsoleRunner::createHelperSet($entityManager)` 替换为 `ConsoleRunner::run(new SingleManagerProvider($entityManager))`
    - 添加 `use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider`
    - 参考 design Components §7
    - _Requirements: 9.1, 9.2_
  - [x] 1.4 验证源码 ORM 3 API 兼容性
    - 确认 `src/CascadeRemovableInterface.php` 中 `Doctrine\Persistence\Event\LifecycleEventArgs` import 在 ORM 3 中仍有效（design 研究已确认无需变更）
    - 确认 `src/CascadeRemoveTrait.php` 中 `$em->detach()`、`$em->getCache()->evictEntity()`、`$em->refresh()` 在 ORM 3 中均为 public API（design 研究已确认无需变更）
    - 确认所有 Entity Fixture 的 PHP 8 Attribute mapping 在 ORM 3 中兼容（release-3.0 已完成迁移）
    - 参考 design Components §6
    - _Requirements: 8.1, 8.2, 8.3_
  - [x] 1.5 Checkpoint: 执行全量测试确认基础适配完成，commit
    - 执行 `vendor/bin/phpunit` 确认全量测试通过（exit code 0）且零 deprecation warning
    - 确认 `composer install` 无 abandoned package warning
    - 如有测试失败或 deprecation warning，先修复再继续（Req 12 范畴）
    - commit message 参考：`chore: upgrade doctrine/orm to ^3.6, adapt TestEnv and CLI config`
    - _Requirements: 11.1, 11.2_

- [x] 2. 验证 ORM 3 对脏数据问题的原生解决情况
  - [x] 2.1 运行 Contrast_Test 的三个 Without-trait 用例并记录结果
    - 执行 `vendor/bin/phpunit --filter 'testWithoutTrait_' ut/Test/CascadeRemoveContrastTest.php`
    - 记录三个场景的通过/失败状态：
      - `testWithoutTrait_IdentityMapReturnsStaleEntity`：当前断言 `assertNotNull($stale)`，如果 ORM 3 已修复则此用例会**失败**（因为 `find()` 返回 null 而非 stale entity）
      - `testWithoutTrait_SecondLevelCacheIsStale`：当前断言 `assertTrue($cache->containsEntity(...))`，如果 ORM 3 已修复则此用例会**失败**
      - `testWithoutTrait_StaleCollectionReference`：当前断言 `assertNotNull($article->getCategory())`，如果 ORM 3 已修复则此用例会**失败**
    - **路径判定规则**：三个用例全部失败 → 移除路径（Req 5）；任一用例通过 → 保留路径（Req 6）
    - 参考 design Architecture 处置路径决策树
    - _Requirements: 4.1, 4.2, 4.3_
    - **执行结果**：三个用例全部通过（OK, 3 tests, 5 assertions），ORM 3.6 + DBAL 4.4 未修复任何脏数据场景
      - `testWithoutTrait_IdentityMapReturnsStaleEntity` — ✅ 通过（identity map 仍返回 stale entity）
      - `testWithoutTrait_SecondLevelCacheIsStale` — ✅ 通过（L2 cache 仍包含已删除 entity）
      - `testWithoutTrait_StaleCollectionReference` — ✅ 通过（PHP 对象仍持有已删除引用）
    - **路径判定**：三个用例全部通过 → **保留路径（Req 6）**
  - [x] 2.2 Checkpoint: 记录路径判定结果并告知用户，commit
    - 明确输出：走移除路径还是保留路径
    - 如果部分场景修复、部分未修复，列出具体哪些已修复哪些未修复
    - commit message 参考：`test: verify ORM 3 native dirty-data behavior, path decision: <移除/保留>`

- ~~3. [IF 移除路径] 移除 CascadeRemoveTrait 和 CascadeRemovableInterface~~ **SKIP**（Task 2 判定为保留路径）
  - **前置条件**：Task 2 判定结果为移除路径（ORM 3 已原生解决所有三个脏数据场景）
  - ~~3.1 删除源码文件~~
  - ~~3.2 清理 Entity Fixture 中的 CascadeRemoveTrait / CascadeRemovableInterface 引用~~
  - ~~3.3 删除仅测试 CascadeRemoveTrait 行为的测试文件~~
  - ~~3.4 Checkpoint: 确认移除完成，commit~~

- [x] 4. [IF 保留路径] 替代 CascadeRemoveTrait 中的 UnitOfWork Internal API
  - **前置条件**：Task 2 判定结果为保留路径（ORM 3 未完全解决脏数据问题）
  - [x] 4.1 替换 `src/CascadeRemoveTrait.php` 中的 3 处 `getEntityIdentifier()` 调用
    - 将 `$em->getUnitOfWork()->getEntityIdentifier($entity)` 替换为 `$em->getClassMetadata(get_class($entity))->getIdentifierValues($entity)`
    - 涉及 `findCascadeDetachableEntities()` 方法中 2 处、`onPreRemove()` 方法中 1 处
    - 参考 design Components §3.1
    - _Requirements: 6.1, 6.2_
  - [x] 4.2 替换 `src/CascadeRemoveTrait.php` 中的 `isScheduledForDelete()` + `isInIdentityMap()` 调用
    - 将 `onPostRemove()` 中的条件判断：
      ```php
      if ($em->getUnitOfWork()->isScheduledForDelete($entity)
          || !$em->getUnitOfWork()->isInIdentityMap($entity)
      ) {
          continue;
      }
      ```
      替换为：
      ```php
      if (!$em->contains($entity)) {
          continue;
      }
      ```
    - 参考 design Components §3.2, §3.3
    - _Requirements: 6.1, 6.2_
  - [x] 4.3 验证 CascadeRemoveTrait 中无 UnitOfWork 调用残留
    - 执行 `grep -n 'getUnitOfWork\|UnitOfWork' src/CascadeRemoveTrait.php` 确认返回空结果
    - _Requirements: 6.2_
  - [x] 4.4 Checkpoint: 执行全量测试确认行为等价性，commit
    - 执行 `vendor/bin/phpunit` 确认全量测试通过（exit code 0）且零 deprecation warning
    - 现有 PBT 测试（Property 3: 强关联实体清理、Property 4: 弱关联实体刷新，4 种拓扑 × 100 次迭代）提供行为等价性回归保护
    - design 已确认所有替代方案均使用 EntityManager public API，无需自维护状态跟踪（Req 6 AC4 不触发）
    - commit message 参考：`refactor: replace UnitOfWork internal API with EntityManager public API`
    - _Requirements: 6.3, 6.4_

- [-] 5. Contrast_Test 断言反转 + PHPUnit 配置更新
  - [ ] 5.1 [IF 移除路径] 移除 With-trait 用例并反转 Without-trait 断言
    - **前置条件**：Task 2 判定结果为移除路径
    - 在 `ut/Test/CascadeRemoveContrastTest.php` 中：
      - 移除 `testWithTrait_IdentityMapIsClean()`、`testWithTrait_SecondLevelCacheIsEvicted()`、`testWithTrait_DirtyEntitiesAreRefreshed()` 三个方法
      - 移除对 `Article`、`Category`、`Tag` 的 `use` import（仅保留 `PlainArticle`、`PlainCategory`）
      - 反转 `testWithoutTrait_IdentityMapReturnsStaleEntity`：`assertNotNull($stale)` → `assertNull($result)`，更新断言消息
      - 反转 `testWithoutTrait_SecondLevelCacheIsStale`：`assertTrue($cache->containsEntity(...))` → `assertFalse($cache->containsEntity(...))`，更新断言消息
      - 反转 `testWithoutTrait_StaleCollectionReference`：`assertNotNull($article->getCategory())` → `assertNull($article->getCategory())`，更新断言消息
    - 参考 design Components §5.1
    - _Requirements: 7.1, 7.3_
  - [x] 5.2 [IF 保留路径] 按场景反转 Without-trait 断言
    - **前置条件**：Task 2 判定结果为保留路径
    - 在 `ut/Test/CascadeRemoveContrastTest.php` 中：
      - 保留全部 With-trait 和 Without-trait 用例
      - 仅对 Task 2 中确认 ORM 3 已修复的场景反转对应的 Without-trait 断言
      - 未修复的场景保持原有断言不变
    - 参考 design Components §5.2
    - _Requirements: 7.1, 7.2, 7.4_
  - [ ] 5.3 [IF 移除路径] 从 `phpunit.xml` 中移除已删除的测试文件引用
    - 移除 `<file>ut/Test/CascadeRemoveTest.php</file>`
    - 移除 `<file>ut/Test/CascadeRemoveTraitTest.php</file>`
    - 移除 `<file>ut/Test/CascadeRemoveTraitPbtTest.php</file>`
    - 保留 `bootstrap="ut/bootstrap.php"` 属性不变
    - _Requirements: 10.1, 10.3_
  - [x] 5.4 [IF 保留路径] 确认 `phpunit.xml` 无需变更
    - 保留路径下 test suite 定义不变，无需修改
    - _Requirements: 10.2, 10.3_
  - [-] 5.5 Checkpoint: 执行全量测试确认路径操作完成，commit
    - 执行 `vendor/bin/phpunit` 确认全量测试通过（exit code 0）且零 deprecation warning
    - 确认测试数量与路径一致（移除路径：减少 3 个测试文件；保留路径：测试文件数量不变）
    - commit message 参考：`test: update contrast test assertions and phpunit config for <移除/保留> path`
    - _Requirements: 11.1, 11.2, 11.3_

- [ ] 6. 手工测试与集成验证
  - [ ] 6.1 Increment alpha tag
    - 查询已有 alpha tag（`git tag -l 'v3.1-alpha*'`），取最大序号 +1，打新 tag
    - 如无 alpha tag 则打 `v3.1-alpha1`
  - [ ] 6.2 全量测试验证
    - 执行 `vendor/bin/phpunit`，确认 exit code 0
    - 确认零 deprecation warning（`failOnDeprecation="true"` 已启用）
    - 确认所有现有测试 + PBT 用例全部通过
    - _Requirements: 11.1, 11.2, 11.3_
  - [ ] 6.3 Composer 配置验证
    - 执行 `composer validate` 确认无 error
    - 执行 `composer install` 确认无 abandoned package warning
    - 确认 `doctrine/cache` 和 `doctrine/common` 不在已安装包列表中
    - _Requirements: 1.2, 2.1, 2.2, 2.3_
  - [ ] 6.4 [IF 移除路径] 源码残留扫描
    - 执行 `grep -r 'CascadeRemoveTrait\|CascadeRemovableInterface' src/ ut/Entity/` 确认返回空结果
    - 确认 `src/CascadeRemoveTrait.php` 和 `src/CascadeRemovableInterface.php` 文件不存在
    - _Requirements: 5.1, 5.2, 5.3_
  - [ ] 6.5 [IF 保留路径] UnitOfWork 调用残留扫描
    - 执行 `grep -r 'getUnitOfWork\|UnitOfWork' src/` 确认返回空结果
    - _Requirements: 6.2_
  - [ ] 6.6 Checkpoint: 所有验证通过，commit
    - 确认全量测试、composer 验证、残留扫描均通过
    - commit message 参考：`test: release 3.1 alpha integration verification`

- [ ] 7. Code Review
  - 委托给 `code-reviewer` sub-agent 执行
  - Review 范围：本 spec 所有变更文件（`composer.json`、`phpunit.xml`、`ut/TestEnv.php`、`ut/cli-config.php`、`src/` 下文件、`ut/Entity/` 下文件、`ut/Test/CascadeRemoveContrastTest.php`）

## Issues

（stabilize 阶段新发现的 issue，初始为空）

## Notes

- 执行时须遵循 `spec-execution.md` 规范，commit 随 checkpoint 一起执行
- 测试命令：`vendor/bin/phpunit`（SQLite in-memory，零外部依赖）
- PHP 运行环境要求 ≥ 8.4（当前环境为 8.5）
- **两条路径共存**：tasks.md 中移除路径和保留路径的 task 同时存在，标注 `[IF 移除路径]` 或 `[IF 保留路径]`，执行时跳过不适用的 task（Design CR Q3 决策）
- **基础适配合并**：Composer 升级 + TestEnv 适配 + CLI 适配 + 源码适配合并为 Task 1（Design CR Q1 决策），因为各项变更相互依赖，中间状态无法独立验证
- **验证独立**：ORM 3 脏数据验证作为独立 Task 2（Design CR Q2 决策），其输出决定后续走哪条路径
- **不新增 PBT**：本次 release 不新增 Correctness Properties（design 决策），保留路径下现有 PBT 提供行为等价性回归保护
- **State 文档排除**：state 文档更新不在本 spec 范围内（Design CR Q4 决策），由 release 收敛阶段统一处理
- Task 3 和 Task 4 互斥（移除路径 vs 保留路径），不会同时执行
- Task 5.1 和 Task 5.2 互斥，不会同时执行
- Task 5.3 和 Task 5.4 互斥，不会同时执行
- Task 6.4 和 Task 6.5 互斥，不会同时执行

---

## Socratic Review

### Q1: tasks 是否完整覆盖了 design 中的所有实现项？

逐项对照 design 的变更维度：

| Design 变更维度 | 对应 Task |
|----------------|-----------|
| Composer 依赖升级（Components §1） | Task 1.1 ✓ |
| TestEnv 适配（Components §2） | Task 1.2 ✓ |
| CLI 配置适配（Components §7） | Task 1.3 ✓ |
| 源码 ORM 3 API 兼容性（Components §6） | Task 1.4 ✓ |
| ORM 3 脏数据验证（Architecture 决策树） | Task 2.1 ✓ |
| 移除路径（Components §4） | Task 3 ✓ |
| 保留路径 UnitOfWork API 替代（Components §3） | Task 4 ✓ |
| Contrast_Test 断言反转（Components §5） | Task 5.1, 5.2 ✓ |
| PHPUnit 配置更新（Components §4） | Task 5.3, 5.4 ✓ |
| 全量测试通过（Testing Strategy） | Task 5.5 + Task 6.2 ✓ |

全部覆盖，无遗漏。

### Q2: task 之间的依赖顺序是否正确？

- Task 1（基础适配）→ Task 2（验证）：正确，验证依赖 ORM 3 环境已就绪
- Task 2 → Task 3/4（路径特定操作）：正确，路径由 Task 2 输出决定
- Task 3/4 → Task 5（断言反转 + phpunit.xml）：正确，断言反转依赖路径确定和代码变更完成
- Task 5 → Task 6（手工测试）→ Task 7（Code Review）：正确

无循环依赖，无隐含的前置依赖未体现。

### Q3: 每个 task 的粒度是否合适？

- Task 1：5 个 sub-task（composer + TestEnv + CLI + 源码验证 + checkpoint），合并为一个大 task 符合 CR Q1 决策
- Task 2：2 个 sub-task（运行验证 + checkpoint），粒度合适
- Task 3：4 个 sub-task（删除源码 + 清理 entity + 删除测试 + checkpoint），粒度合适
- Task 4：4 个 sub-task（替换 getEntityIdentifier + 替换 isScheduledForDelete/isInIdentityMap + 验证残留 + checkpoint），粒度合适
- Task 5：5 个 sub-task（移除路径断言反转 + 保留路径断言反转 + 移除路径 phpunit.xml + 保留路径确认 + checkpoint），粒度合适
- Task 6：6 个 sub-task（alpha tag + 全量测试 + composer + 残留扫描 + checkpoint），粒度合适
- Task 7：Code Review，无需拆分

无过粗或过细的 task。

### Q4: checkpoint 的设置是否覆盖了关键阶段？

| Checkpoint | 验证内容 | 覆盖阶段 |
|------------|----------|----------|
| Task 1.5 | 全量测试 + 零 deprecation + 无 abandoned warning | 基础适配后 ✓ |
| Task 2.2 | 路径判定结果 | 验证后 ✓ |
| Task 3.4 | 移除完整性扫描 | 移除路径操作后 ✓ |
| Task 4.4 | 全量测试 + PBT 行为等价性 | 保留路径重构后 ✓ |
| Task 5.5 | 全量测试 + 测试数量确认 | 断言反转 + 配置更新后 ✓ |
| Task 6.6 | 集成验证 + composer + 残留扫描 | 最终验证 ✓ |

每个关键阶段都有 checkpoint，覆盖充分。

### Q5: 两条路径的 IF 条件标注是否清晰？

- Task 3 整体标注 `[IF 移除路径]` ✓
- Task 4 整体标注 `[IF 保留路径]` ✓
- Task 5.1 标注 `[IF 移除路径]`，Task 5.2 标注 `[IF 保留路径]` ✓
- Task 5.3 标注 `[IF 移除路径]`，Task 5.4 标注 `[IF 保留路径]` ✓
- Task 6.4 标注 `[IF 移除路径]`，Task 6.5 标注 `[IF 保留路径]` ✓
- Notes 中明确说明了互斥关系 ✓

路径条件标注清晰，无歧义。

### Q6: requirements 覆盖是否完整？

| Requirement | 对应 Task |
|-------------|-----------|
| Req 1 (ORM 升级) | Task 1.1, 6.3 ✓ |
| Req 2 (消除 abandoned) | Task 1.1, 6.3 ✓ |
| Req 3 (TestEnv 适配) | Task 1.2 ✓ |
| Req 4 (ORM 3 验证) | Task 2.1 ✓ |
| Req 5 (移除路径) | Task 3.1, 3.2, 3.3, 6.4 ✓ |
| Req 6 (保留路径) | Task 4.1, 4.2, 4.3, 4.4 ✓ |
| Req 7 (断言反转) | Task 5.1, 5.2 ✓ |
| Req 8 (源码适配) | Task 1.4 ✓ |
| Req 9 (CLI 适配) | Task 1.3 ✓ |
| Req 10 (phpunit.xml) | Task 5.3, 5.4 ✓ |
| Req 11 (全量测试) | Task 1.5, 5.5, 6.2 ✓ |
| Req 12 (问题修复) | 被 Task 1.5, 5.5, 6.2 隐含覆盖 ✓ |

全部 12 条 requirement 均被覆盖，无遗漏。

---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [结构] Task 7 是独立的 checkpoint top-level task，违反 steering 要求（checkpoint 应作为每个 top-level task 的最后一个 sub-task）。已将原 Task 7 的全量测试验证合并到 Task 5.5 checkpoint 中，消除独立 checkpoint top-level task
- [结构] Task 3（移除路径）、Task 4（保留路径）、Task 5（断言反转）、Task 6（phpunit.xml）均缺少 checkpoint sub-task。已为 Task 3 补充 3.4 checkpoint（移除完整性扫描）、Task 4 补充 4.4 checkpoint（全量测试 + PBT 行为等价性验证）；Task 5 和 Task 6 合并为新 Task 5（断言反转 + PHPUnit 配置更新），补充 5.5 checkpoint
- [结构] 原 Task 5（断言反转）和 Task 6（phpunit.xml 更新）拆为两个独立 top-level task，但两者紧密关联（都是路径特定的后续清理），且原 Task 6 仅含 1 个 sub-task 粒度过细。已合并为新 Task 5，同时补充保留路径下 phpunit.xml 无需变更的确认 sub-task（覆盖 Req 10 AC2）
- [内容] Req 6 AC3（行为等价性）和 AC4（自维护状态跟踪需确认）未被任何 task 引用。已在 Task 4.4 checkpoint 中补充引用和说明（现有 PBT 验证等价性；design 已确认无需自维护状态跟踪）
- [内容] Req 10 AC2（保留路径下 phpunit.xml 保持不变）未被引用。已新增 Task 5.4 sub-task 显式覆盖
- [内容] 所有 checkpoint 缺少 commit 动作描述（steering 要求 checkpoint 包含 commit）。已为每个 checkpoint 补充 commit message 参考，与 release-3.0 格式一致
- [格式] Checkpoint 中混用英文描述（"Ensure all tests pass, ask the user if questions arise"），已统一为中文表述
- [内容] 合并后重新编号：原 Task 8（手工测试）→ 新 Task 6，原 Task 9（Code Review）→ 新 Task 7，序号连续无跳号

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
- [x] top-level task 有序号（1–7），sub-task 有层级序号，序号连续无跳号
- [x] 每个实现类 sub-task 引用了具体的 requirements 条款
- [x] requirements.md 中的每条 requirement（12/12）至少被一个 task 引用，无遗漏
- [x] 无悬空引用（所有引用的 requirement 编号和 AC 编号在 requirements.md 中存在）
- [x] top-level task 按依赖关系排序，无循环依赖
- [x] 已对核心模块执行 graphify 依赖查询（TestEnv、CascadeRemoveTrait、CascadeRemovableInterface、cli-config.php）
- [x] task 排序与 graphify 揭示的模块依赖一致，无遗漏的隐含跨模块依赖（TestEnv 与 CascadeRemoveTrait 无直接路径，cli-config.php 无下游依赖）
- [x] 每个 top-level task 的最后一个 sub-task 是 checkpoint
- [x] checkpoint 包含具体验证命令和 commit 动作
- [x] 每个 sub-task 足够具体，可独立执行
- [x] 无过粗或过细的 task
- [x] 所有 task 均为 mandatory（条件 task 通过 IF 标注而非 optional 标注）
- [x] 手工测试 top-level task 存在（Task 6），覆盖关键验证场景
- [x] Code Review 是最后一个 top-level task（Task 7），委托给 code-reviewer
- [x] Code Review task 未展开 review checklist
- [x] `## Notes` section 存在
- [x] Notes 明确提到遵循 `spec-execution.md`
- [x] Notes 明确 commit 随 checkpoint 一起执行
- [x] Notes 包含当前 spec 特有的执行要点（CR 决策、路径互斥说明、测试命令）
- [x] Socratic Review 存在且覆盖充分（6 个维度）
- [x] Design CR 决策已全部体现在 tasks 编排中（Q1 合并基础适配、Q2 验证独立、Q3 两条路径共存、Q4 state 文档排除）
- [x] Design 全覆盖：所有变更维度均有对应 task
- [x] 每个 sub-task 可独立执行，无需猜测上下文
- [x] 验收闭环完整：checkpoint + 手工测试 + code review
- [x] 执行路径无歧义，依赖关系清晰
