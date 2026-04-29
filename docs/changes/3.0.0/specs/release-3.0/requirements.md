# Requirements Document

`<spec-dir>` — Release 3.0 (PHP 8.5 Upgrade) 需求规格。

---

## Introduction

`oasis/doctrine-addon` 当前工具链锁定在较旧版本（PHPUnit ^8.5、`doctrine/annotations` ^1.13、`symfony/cache` ^5.4），无法在 PHP 8.5 运行时正常工作。本次 Release 3.0 的目标是全面升级依赖与工具链，将 Doctrine Annotation 迁移到 PHP Attribute，并引入 Eris property-based testing 补充测试覆盖。

**不涉及的内容**：不迁移到 Doctrine ORM 3.x（ORM 2.x 维护延长至 2027-02）；不新增业务功能。

---

## Glossary

- **Composer_Config**: `composer.json` 文件，声明项目依赖与版本约束
- **PHPUnit_Config**: `phpunit.xml` 文件，PHPUnit 测试运行器配置
- **Test_Suite**: 项目全量测试集合，包含 `ut/Test/` 下所有测试用例
- **TestEnv**: `ut/TestEnv.php`，测试环境初始化类，负责创建 EntityManager 和 SQLite in-memory 数据库
- **Entity_Fixture**: `ut/Entity/` 下的 Doctrine entity 类，用于测试
- **Source_Component**: `src/` 下的库源码组件（`AutoIdTrait`、`CascadeRemovableInterface`、`CascadeRemoveTrait`）
- **Annotation**: Doctrine 基于 docblock 注释的 metadata 声明方式（`@ORM\Entity()` 等）
- **Attribute**: PHP 8.0+ 原生属性语法的 metadata 声明方式（`#[ORM\Entity]` 等）
- **Eris**: `giorgiosironi/eris` 库，PHP property-based testing 框架
- **PBT_Suite**: 使用 Eris 编写的 property-based test 用例集合
- **Second_Level_Cache**: Doctrine ORM 二级缓存，用于跨请求缓存 entity 数据

---

## Requirements

### Requirement 1: PHP 版本约束声明

**User Story:** 作为库维护者，我希望在 Composer_Config 中显式声明 PHP 版本约束，以便使用者明确知道本库支持的 PHP 版本范围。

#### Acceptance Criteria

1. THE Composer_Config SHALL 在 `require` 段声明 `php` 约束为 `^8.4`
2. WHEN 在 PHP 8.4 或 8.5 环境执行 `composer validate` 时，THE Composer_Config SHALL 通过验证且无 error

---

### Requirement 2: PHPUnit 升级

**User Story:** 作为库维护者，我希望将 PHPUnit 升级到 ^13.0，以便测试框架能在 PHP 8.5 上正常运行。

#### Acceptance Criteria

1. THE Composer_Config SHALL 在 `require-dev` 段声明 `phpunit/phpunit` 约束为 `^13.0`
2. WHEN Test_Suite 中的测试用例使用了 PHPUnit 8 已移除的 API 时，THE Test_Suite SHALL 适配为 PHPUnit 13 兼容的 API 调用
3. WHEN 执行 `vendor/bin/phpunit` 时，THE Test_Suite SHALL 全部通过且无 deprecation warning

---

### Requirement 3: PHPUnit 配置格式适配

**User Story:** 作为库维护者，我希望 PHPUnit_Config 符合 PHPUnit 13 的 schema 要求，以便测试运行器能正确解析配置。

#### Acceptance Criteria

1. THE PHPUnit_Config SHALL 使用 PHPUnit 13 兼容的 XML schema 声明
2. THE PHPUnit_Config SHALL 保留现有 test suite 定义（`ut/Test/` 下所有测试文件）
3. THE PHPUnit_Config SHALL 保留 `bootstrap` 属性指向 `ut/bootstrap.php`

---

### Requirement 4: Doctrine ORM 版本约束收窄

**User Story:** 作为库维护者，我希望将 `doctrine/orm` 版本约束收窄到 ^2.20，以便约束与实际安装版本匹配并确保 Attribute mapping 支持。

#### Acceptance Criteria

1. THE Composer_Config SHALL 在 `require` 段声明 `doctrine/orm` 约束为 `^2.20`
2. WHEN 执行 `composer install` 时，THE Composer_Config SHALL 成功解析依赖且无冲突


---

### Requirement 5: Doctrine Annotation 迁移到 PHP Attribute

**User Story:** 作为库维护者，我希望将所有 Doctrine Annotation 迁移到 PHP Attribute 语法，以便移除已 abandoned 的 `doctrine/annotations` 依赖。

#### Acceptance Criteria

1. THE Source_Component SHALL 使用 PHP Attribute 语法声明所有 ORM mapping metadata，不再包含任何 Annotation 形式的 mapping 声明
2. THE Entity_Fixture SHALL 使用 PHP Attribute 语法声明所有 ORM mapping metadata，不再包含任何 Annotation 形式的 mapping 声明
3. THE Composer_Config SHALL 从 `require` 段移除 `doctrine/annotations` 依赖
4. THE TestEnv SHALL 使用 Attribute-based metadata driver 替代 Annotation-based metadata driver
5. WHEN 迁移完成后执行 Test_Suite 时，THE Test_Suite SHALL 全部通过

---

### Requirement 6: symfony/cache 升级

**User Story:** 作为库维护者，我希望将 `symfony/cache` 升级到 ^7.2，以便使用活跃维护的版本并获得安全修复。

#### Acceptance Criteria

1. THE Composer_Config SHALL 在 `require-dev` 段声明 `symfony/cache` 约束为 `^7.2`
2. WHEN TestEnv 使用 `ArrayAdapter` 创建 Second_Level_Cache 时，THE TestEnv SHALL 与 `symfony/cache` ^7.2 的 API 兼容

---

### Requirement 7: oasis/logging 升级

**User Story:** 作为库维护者，我希望将 `oasis/logging` 升级到最新版本，以便确保 PHP 8.5 兼容性。

#### Acceptance Criteria

1. THE Composer_Config SHALL 在 `require` 段将 `oasis/logging` 约束更新为最新版本
2. WHEN 执行 `composer install` 时，THE Composer_Config SHALL 成功解析 `oasis/logging` 依赖且无冲突

---

### Requirement 8: TestEnv deprecated API 替换

**User Story:** 作为库维护者，我希望替换 TestEnv 中已 deprecated 的 Doctrine API 调用，以便消除 deprecation warning 并使用推荐的 API。

#### Acceptance Criteria

1. THE TestEnv SHALL 使用 Doctrine ORM 当前推荐的 API 创建 metadata configuration，不再使用已 deprecated 的 API
2. THE TestEnv SHALL 使用 Doctrine ORM 当前推荐的方式创建 EntityManager 实例，不再使用已 deprecated 的工厂方法
3. WHEN 执行 Test_Suite 时，THE TestEnv SHALL 不产生 Doctrine deprecated API 相关的 deprecation warning

---

### Requirement 9: 引入 Eris PBT 工具

**User Story:** 作为库维护者，我希望引入 `giorgiosironi/eris` 作为 property-based testing 工具，以便对复杂逻辑进行更充分的测试覆盖。

#### Acceptance Criteria

1. THE Composer_Config SHALL 在 `require-dev` 段声明 `giorgiosironi/eris` 约束为 `^1.0`
2. WHEN 执行 `composer install` 时，THE Composer_Config SHALL 成功安装 Eris 且无冲突


---

### Requirement 10: AutoIdTrait PBT 覆盖

**User Story:** 作为库维护者，我希望使用 Eris 对 AutoIdTrait 编写 property-based test，以便验证自增 ID 在各种场景下的正确性。

#### Acceptance Criteria

1. THE PBT_Suite SHALL 包含针对 AutoIdTrait 的 property-based test
2. FOR ALL 批量 persist 的 entity 集合（数量由 Eris generator 生成），THE AutoIdTrait SHALL 为每个 entity 分配唯一的正整数 ID（invariant: ID 唯一性与正整数约束）
3. FOR ALL 已 persist 并 flush 的 entity，WHEN 从数据库重新加载该 entity 时，THE AutoIdTrait SHALL 返回与 persist 时相同的 ID（round-trip property: persist → flush → clear → find 保持 ID 不变）
4. WHEN 执行 PBT_Suite 中 AutoIdTrait 相关用例时，THE PBT_Suite SHALL 全部通过

---

### Requirement 11: CascadeRemoveTrait PBT 覆盖

**User Story:** 作为库维护者，我希望使用 Eris 对 CascadeRemoveTrait 编写 property-based test，以便验证级联删除缓存失效机制在各种实体拓扑下的正确性。

#### Acceptance Criteria

1. THE PBT_Suite SHALL 包含针对 CascadeRemoveTrait 的 property-based test
2. FOR ALL 随机生成的 entity 拓扑（包含 Category、Article、Tag 的不同关联组合），WHEN 删除一个实现 CascadeRemovableInterface 的 entity 时，THE CascadeRemoveTrait SHALL 确保所有强关联实体从 EntityManager identity map 中被 detach（invariant: identity map 清洁性）
3. FOR ALL 随机生成的 entity 拓扑，WHEN 删除一个实现 CascadeRemovableInterface 的 entity 时，THE CascadeRemoveTrait SHALL 确保所有强关联实体从 Second_Level_Cache 中被 evict（invariant: 二级缓存一致性）
4. FOR ALL 随机生成的 entity 拓扑，WHEN 删除一个实现 CascadeRemovableInterface 的 entity 时，THE CascadeRemoveTrait SHALL 确保所有弱关联实体（dirty entity）被 refresh 且其缓存被 evict，除非该弱关联实体已被调度删除或不在 identity map 中（invariant: dirty entity 刷新正确性）
5. WHEN 执行 PBT_Suite 中 CascadeRemoveTrait 相关用例时，THE PBT_Suite SHALL 全部通过

---

### Requirement 12: 全量测试通过

**User Story:** 作为库维护者，我希望所有现有测试和新增 PBT 用例在 PHP 8.5 + PHPUnit 13 环境下全部通过，以确保升级不引入回归。

#### Acceptance Criteria

1. WHEN 在 PHP 8.5 环境执行 `vendor/bin/phpunit` 时，THE Test_Suite SHALL 全部通过（exit code 0）
2. WHEN 在 PHP 8.5 环境执行 `vendor/bin/phpunit` 时，THE Test_Suite SHALL 不产生 PHP deprecated warning
3. THE Test_Suite SHALL 包含所有现有测试用例和新增 PBT_Suite 用例

---

## Socratic Review

### Q1: 需求是否完整覆盖了 goal.md 中的所有目标？

逐项对照：

| Goal | 对应 Requirement |
|------|-----------------|
| 声明 PHP `^8.4` 版本约束 | Req 1 |
| 升级 PHPUnit 至 `^13.0` | Req 2 |
| 升级 `doctrine/orm` 至 `^2.20` | Req 4 |
| Annotation → Attribute 迁移 | Req 5 |
| 升级 `symfony/cache` 至 `^7.2` | Req 6 |
| 升级 `oasis/logging` 至最新 | Req 7 |
| 引入 Eris `^1.0` | Req 9 |
| 适配 `phpunit.xml` 格式 | Req 3 |
| 替换 TestEnv deprecated API | Req 8 |
| AutoIdTrait PBT | Req 10 |
| CascadeRemoveTrait PBT | Req 11 |
| 全量测试通过 | Req 12 |

全部覆盖，无遗漏。

### Q2: 每条 AC 是否遵循 EARS 模式？

逐条检查：
- Req 1: AC1 ubiquitous (SHALL)，AC2 event-driven (WHEN...SHALL) ✓
- Req 2: AC1 ubiquitous，AC2 event-driven，AC3 event-driven ✓
- Req 3: AC1–AC3 ubiquitous ✓
- Req 4: AC1 ubiquitous，AC2 event-driven ✓
- Req 5: AC1–AC4 ubiquitous，AC5 event-driven ✓
- Req 6: AC1 ubiquitous，AC2 event-driven ✓
- Req 7: AC1 ubiquitous，AC2 event-driven ✓
- Req 8: AC1–AC2 ubiquitous，AC3 event-driven ✓
- Req 9: AC1 ubiquitous，AC2 event-driven ✓
- Req 10: AC1 ubiquitous，AC2–AC3 complex (FOR ALL...SHALL)，AC4 event-driven ✓
- Req 11: AC1 ubiquitous，AC2–AC4 complex (FOR ALL...WHEN...SHALL)，AC5 event-driven ✓
- Req 12: AC1–AC2 event-driven，AC3 ubiquitous ✓

### Q3: 是否存在模糊用语或不可测试的 AC？

- Req 7 AC1 中 "最新版本" 在实现时需确定具体版本号，但作为需求层面表达合理——实现阶段会锁定到具体版本
- Req 8 AC2 中 "推荐的工厂方法" 在实现时需查阅 Doctrine 文档确定具体 API，需求层面已指明方向
- 其余 AC 均有明确的可验证条件

### Q4: PBT 相关需求是否适合 property-based testing？

- Req 10 AC2（ID 唯一性）：行为随 entity 数量变化，测试自有代码逻辑，100 次迭代能发现更多边界 → 适合 PBT ✓
- Req 10 AC3（ID round-trip）：行为随 entity 状态变化，测试 persist/find 一致性 → 适合 PBT ✓
- Req 11 AC2–AC4（级联删除正确性）：行为随 entity 拓扑变化，测试自有 trait 逻辑，复杂拓扑组合多 → 适合 PBT ✓

### Q5: Non-Goal 是否被意外纳入？

- 未包含 Doctrine ORM 3.x 迁移相关需求 ✓
- 未包含新业务功能需求 ✓

### Q6: 是否有遗漏的错误路径或边界条件？

- Req 1–9 为依赖升级和配置变更，错误路径主要是依赖冲突，已通过 `composer install` / `composer validate` 的 AC 覆盖 ✓
- Req 10 PBT 覆盖了批量 persist 场景和 round-trip 场景，边界条件（如 0 个 entity）由 Eris generator 的范围决定，在 design 阶段明确 generator 参数即可 ✓
- Req 11 PBT 覆盖了不同关联组合的拓扑，AC4 已显式列出跳过条件（已调度删除、不在 identity map），关键边界已覆盖 ✓

### Q7: 各 requirement 之间是否存在矛盾或重叠？

- Req 2 AC3（Test_Suite 全部通过）与 Req 12 AC1（全量测试通过）存在重叠，但 Req 2 聚焦 PHPUnit 升级后的兼容性，Req 12 聚焦最终集成验证，两者视角不同，不构成矛盾
- Req 5 AC5（迁移后测试通过）同理，聚焦 Attribute 迁移的正确性验证
- 无实质矛盾 ✓

### Q8: 是否有隐含的前置假设未显式列出？

- 假设运行环境为 PHP 8.5（goal.md 已明确）
- 假设 `oasis/logging` 最新版本兼容 PHP 8.5（goal.md Q1 已确认无兼容性障碍）
- 假设 Doctrine ORM 2.20+ 支持 PHP Attribute mapping（ORM 2.14+ 已支持，2.20 满足）
- 假设 Eris ^1.0 兼容 PHPUnit 13（proposal 已确认 Eris 1.x 支持 PHPUnit 10–13）
- 所有前置假设均有据可查 ✓


---

## Gatekeep Log

**校验时间**: 2025-07-15
**校验结果**: ⚠️ 已修正后通过

### 修正项
- [内容] Introduction 缺少 Non-scope 声明，已补充"不涉及的内容"段落（不迁移 ORM 3.x、不新增业务功能）
- [内容] Req 5 AC1–AC2 列举了具体 Attribute 名称（`#[ORM\Id]`、`#[ORM\Entity]` 等），属于实现细节，已改为描述外部可观察行为（"使用 PHP Attribute 语法声明所有 ORM mapping metadata，不再包含任何 Annotation 形式的 mapping 声明"）
- [内容] Req 8 AC1–AC2 引用了具体类名和方法签名（`Doctrine\ORM\ORMSetup`、`EntityManager::create()` 等），属于实现细节，已改为描述行为约束（"使用当前推荐的 API"、"不再使用已 deprecated 的工厂方法"）
- [内容] Socratic Review 缺少错误路径/边界条件、requirement 间矛盾/重叠、隐含前置假设等维度的审查，已补充 Q6–Q8

### 合规检查
- [x] 无 TBD / TODO / 待定 / 占位符
- [x] 无空 section 或不完整的列表
- [x] 内部引用一致（术语表术语在 AC 中使用，requirement 编号连续）
- [x] 无 markdown 格式错误
- [x] 一级标题存在且正确
- [x] Introduction 存在，描述了 feature 范围
- [x] Introduction 明确了不涉及的内容（Non-scope）
- [x] Glossary 存在且非空，术语格式正确
- [x] Glossary 中无孤立术语，AC 中无未定义的关键术语
- [x] Requirements section 存在且包含 12 条 requirement
- [x] 各 section 之间使用 `---` 分隔
- [x] 所有 AC 遵循 EARS 语体（SHALL / WHEN...SHALL / FOR ALL...SHALL）
- [x] AC 中不包含具体类名、方法签名等实现细节
- [x] User Story 使用中文行文
- [x] Goal CR 决策已体现在 requirements 中
- [x] Socratic Review 覆盖充分（目标覆盖、EARS 合规、模糊用语、PBT 适用性、Non-goal、错误路径、矛盾/重叠、前置假设）

### Clarification Round

**状态**: 已完成

**Q1:** Req 10 AC2 要求 AutoIdTrait 为批量 persist 的 entity 分配"唯一的正整数 ID"。在 design 阶段，PBT generator 需要确定批量 persist 的数量范围。批量大小的上界会影响测试执行时间和数据库压力。对于 PBT 的批量大小范围，你倾向哪种策略？
- A) 小范围（1–20），快速执行，聚焦逻辑正确性
- B) 中等范围（1–100），平衡覆盖度和执行时间
- C) 大范围（1–500+），压力测试风格，发现更多边界
- D) 其他（请说明）

**A:** B — 中等范围（1–100），平衡覆盖度和执行时间。

**Q2:** Req 11 AC2–AC4 要求对"随机生成的 entity 拓扑"进行 PBT。entity 拓扑的复杂度直接影响 design 方案——简单拓扑可以用固定模板 + 随机参数，复杂拓扑需要自定义 generator 生成任意关联图。对于 entity 拓扑的随机化程度，你倾向哪种？
- A) 固定拓扑模板（如 1 Category → N Articles → M Tags），仅随机化数量参数
- B) 半随机拓扑（预定义几种关联模式，随机选择并组合）
- C) 全随机拓扑（自定义 generator 生成任意合法的 entity 关联图）
- D) 其他（请说明）

**A:** B — 半随机拓扑，预定义几种关联模式，随机选择并组合。

**Q3:** Req 12 AC2 要求"不产生 PHP deprecated warning"。PHP 8.5 对一些旧语法和函数发出了新的 deprecation warning，这些 warning 可能来自项目自身代码，也可能来自第三方依赖（如 Doctrine ORM 2.x 内部）。对于第三方依赖产生的 deprecation warning，处理策略是什么？
- A) 仅要求项目自身代码无 deprecated warning，第三方依赖的 warning 不在 scope 内
- B) 通过 PHPUnit baseline 或 error handler 抑制已知的第三方 deprecation warning，确保测试输出干净
- C) 严格要求零 deprecated warning（包括第三方依赖），如有则降级或 patch
- D) 其他（请说明）

**A:** C — 严格要求零 deprecated warning（包括第三方依赖），如有则降级或 patch。
