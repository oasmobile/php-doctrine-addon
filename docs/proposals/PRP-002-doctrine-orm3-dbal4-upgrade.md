# Doctrine ORM 3 / DBAL 4 Upgrade

`docs/proposals/` — 升级 Doctrine ORM 至 3.x、DBAL 至 4.x，消除 abandoned 依赖，清理 UnitOfWork internal API 依赖，重新评估 CascadeRemoveTrait 的必要性。

---

## Status

`accepted`

---

## Background

PRP-001 将 Doctrine ORM 约束收窄到 `^2.20` 并完成了 PHP 8.5 + PHPUnit 13 升级，但明确将 ORM 3.x 迁移列为 Non-Goal。当前状态：

| 组件 | 当前版本 | 问题 |
|------|----------|------|
| `doctrine/orm` | 2.20.10 | ORM 2.x 维护窗口至 2027-02，之后仅安全修复 |
| `doctrine/dbal` | 3.10.5 | DBAL 3.x 同样进入维护尾期 |
| `doctrine/cache` | 2.2.0 | 已被官方标记 **abandoned**，每次 `composer install` 产生 warning |
| `doctrine/common` | 3.5.0 | ORM 3 不再依赖，可随升级移除 |

此外，`CascadeRemoveTrait` 核心逻辑直接调用 `UnitOfWork` 的 internal API（`getEntityIdentifier`、`isScheduledForDelete`、`isInIdentityMap`），这在 ORM 2.x 中可用但在 ORM 3.x 中 `UnitOfWork` 已被标记为 `@internal`，属于不稳定 API，存在长期维护风险。

同时，Doctrine ORM 在 2.x → 3.x 演进过程中对 identity map 和 Second Level Cache 的管理做了改进。CascadeRemoveTrait 的初衷是解决"数据库 `ON DELETE CASCADE` 删除了行，但 EntityManager identity map 和 L2 cache 中仍残留脏数据"的问题。需要验证 ORM 3 + DBAL 4 环境下，这个问题是否仍然存在——如果 ORM 3 已经原生解决了这个问题，CascadeRemoveTrait 可能可以简化甚至废弃。

---

## Problem

1. `doctrine/cache` abandoned warning 污染每次 `composer install` 输出，且该包不再接收维护
2. `CascadeRemoveTrait` 依赖 `UnitOfWork` internal API，ORM 3 中这些 API 虽然当前仍存在，但随时可能在后续小版本中变更或移除
3. ORM 2.x 进入维护尾期，越晚升级积累的 gap 越大
4. 尚未验证 ORM 3 是否已原生解决 identity map / L2 cache 脏数据问题——如果已解决，当前的 CascadeRemoveTrait 就是不必要的复杂度

---

## Goals

- 升级 `doctrine/orm` 至 `^3.6`，`doctrine/dbal` 至 `^4.4`
- 消除 `doctrine/cache` 和 `doctrine/common` 依赖
- 消除 `CascadeRemoveTrait` 对 `UnitOfWork` internal API 的直接依赖，改用 public API 或其他方式实现等价逻辑
- 验证 ORM 3 + DBAL 4 环境下，不使用 CascadeRemoveTrait 时 identity map / L2 cache 脏数据问题是否仍然存在
  - 如果 ORM 3 已原生解决：标记 CascadeRemoveTrait 为 deprecated，或直接移除
  - 如果问题仍然存在：保留 trait 但确保实现不依赖 internal API
- 所有现有测试在升级后通过（含 PBT）
- 修复升级过程中发现的所有问题，不区分新旧

---

## Non-Goals

- 不新增业务功能
- 不变更库的公共 API 签名（`CascadeRemovableInterface` 的方法签名保持不变，除非验证结果表明 trait 可废弃）
- 不迁移到 Doctrine ORM 4.x（目前仍为 dev 状态）

---

## Scope

### Composer 依赖变更

| 依赖 | 当前约束 | 目标约束 | 说明 |
|------|----------|----------|------|
| `doctrine/orm` | `^2.20` | `^3.6` | 大版本升级 |
| `doctrine/dbal` | （间接依赖）3.10.5 | `^4.4`（显式声明或随 ORM 3 拉入） | 大版本升级 |
| `doctrine/cache` | （间接依赖）2.2.0 | 移除 | ORM 3 不再依赖 |
| `doctrine/common` | （间接依赖）3.5.0 | 移除 | ORM 3 不再依赖 |

### 代码适配

#### `src/` 核心代码

- `CascadeRemoveTrait.php`：消除对 `UnitOfWork::getEntityIdentifier()`、`isScheduledForDelete()`、`isInIdentityMap()` 的直接调用，改用 EntityManager public API 或等价方案
- `CascadeRemovableInterface.php`：`LifecycleEventArgs` 的 import 路径确认（`Doctrine\Persistence\Event\LifecycleEventArgs` 在 ORM 3 中仍有效）

#### `ut/` 测试代码

- `TestEnv.php`：`new EntityManager()` 构造函数在 ORM 3 中不再 public，需改用 `EntityManager::create()` 或等价工厂方法
- `TestEnv.php`：DBAL 4 中 `'driver' => 'pdo_sqlite'` 需改为 `'driver' => 'sqlite3'`
- `cli-config.php`：`ConsoleRunner::createHelperSet()` 在 ORM 3 中已移除，需适配新 API
- `CascadeRemoveContrastTest.php`：对照组测试（"Without trait" 场景）需重新验证——如果 ORM 3 原生解决了脏数据问题，这些测试的预期结果会改变

### 验证计划

1. 升级依赖后运行全量测试，记录失败项
2. 逐项修复代码适配问题
3. 重点关注 `CascadeRemoveContrastTest` 中 "Without trait" 的测试结果：
   - 如果 `testWithoutTrait_IdentityMapReturnsStaleEntity` 不再返回 stale entity → ORM 3 已修复 identity map 问题
   - 如果 `testWithoutTrait_SecondLevelCacheIsStale` 不再返回 stale cache → ORM 3 已修复 L2 cache 问题
   - 如果 `testWithoutTrait_StaleCollectionReference` 不再持有 stale reference → ORM 3 已修复 collection 刷新问题
4. 根据验证结果决定 CascadeRemoveTrait 的处置方案

---

## References

- [Doctrine ORM 3 and DBAL 4 Released](https://www.doctrine-project.org/2024/02/03/doctrine-orm-3-and-dbal-4-released.html) — 官方发布公告
- [ORM 2 End of Life Update](https://www.doctrine-project.org/2025/10/08/an-update-on-the-orm-2-end-of-life.html) — ORM 2.x 维护计划
- `docs/state/architecture.md` — 当前系统架构
- `docs/state/data-model.md` — CascadeRemoveTrait 的数据模型与行为定义
- `docs/proposals/archive/PRP-001-php85-upgrade.md` — 前序升级 proposal

---

## Notes

- Composer dry-run 已确认 ORM 3.6.3 + DBAL 4.4.3 依赖解析无冲突，会自动移除 `doctrine/cache` 和 `doctrine/common`
- ORM 3 中 `UnitOfWork` 被标记为 `@internal`，即使当前方法仍存在，也应视为不稳定 API 并替换
- CascadeRemoveTrait 的废弃决策取决于验证结果，不在本 proposal 中预设结论
