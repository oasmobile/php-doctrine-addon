# Spec Goal: Doctrine ORM 3 / DBAL 4 Upgrade

## 来源

- 分支: `release/3.1`
- 需求文档: `docs/proposals/PRP-002-doctrine-orm3-dbal4-upgrade.md`

## 背景摘要

`oasis/doctrine-addon` 当前依赖 Doctrine ORM 2.20 + DBAL 3.10，间接引入了已 abandoned 的 `doctrine/cache`，每次 `composer install` 产生 warning。ORM 2.x 维护窗口至 2027-02，之后仅安全修复。

库的核心组件 `CascadeRemoveTrait` 解决的是"数据库 `ON DELETE CASCADE` 删除了行，但 EntityManager identity map 和 Second Level Cache 中仍残留脏数据"的问题。该 trait 的实现直接调用了 `UnitOfWork` 的 internal API（`getEntityIdentifier`、`isScheduledForDelete`、`isInIdentityMap`），在 ORM 3 中 `UnitOfWork` 已被标记为 `@internal`，存在长期维护风险。

Doctrine ORM 3 对 identity map 和 L2 cache 管理做了改进，需要验证 ORM 3 + DBAL 4 环境下脏数据问题是否仍然存在。如果已原生解决，CascadeRemoveTrait 可以移除。

## 目标

- 升级 `doctrine/orm` 至 `^3.6`，`doctrine/dbal` 至 `^4.4`
- 消除 `doctrine/cache` 和 `doctrine/common` 间接依赖
- 消除 `CascadeRemoveTrait` 对 `UnitOfWork` internal API 的直接依赖，改用 EntityManager public API 实现等价逻辑
- 验证 ORM 3 + DBAL 4 环境下，不使用 CascadeRemoveTrait 时 identity map / L2 cache 脏数据问题是否仍然存在
  - 如果 ORM 3 已原生解决：直接移除 `CascadeRemoveTrait`、`CascadeRemovableInterface` 及相关测试 entity 和测试用例
  - 如果问题仍然存在：保留 trait 但确保实现不依赖 internal API
- 对照组测试（`CascadeRemoveContrastTest`）根据验证结果反转断言，作为回归保护
- 修复升级过程中发现的所有问题，不区分新旧
- 所有测试在升级后通过（含 PBT）

## 不做的事情（Non-Goals）

- 不新增业务功能
- 不迁移到 Doctrine ORM 4.x（目前仍为 dev 状态）
- 不变更库的公共 API 签名（除非验证结果表明 trait 可移除）

## Clarification 记录

### Q1: CascadeRemoveTrait 的处置策略

- 选项: A) 保留但标记 deprecated / B) 直接移除，3.1 作为 breaking change / C) 保留 interface 但清空实现 / D) 补充说明
- 回答: B — 如果 ORM 3 已原生解决问题，直接移除 trait 和 interface，3.1 作为 breaking change 发布

### Q2: UnitOfWork internal API 的替代方案

- 选项: A) 改用 EntityManager public API 组合 / B) 继续用 UnitOfWork 加注释 / C) 自维护 pendingRemovals 集合 / D) 补充说明
- 回答: 倾向 A，如果 design 阶段发现需要走 C 路线需先确认

### Q3: 对照组测试的处置

- 选项: A) 删除整个 ContrastTest / B) 保留但反转断言作为回归保护 / C) 保留 With trait 用例删除 Without trait / D) 补充说明
- 回答: B — 保留对照组测试但反转断言

### Q4: 库的大版本号策略

- 选项: A) 3.1 允许 breaking change / B) 改为 4.0 / C) 先按 3.1 推进视结果定 / D) 补充说明
- 回答: A — 本项目 minor 版本允许 breaking change

## 约束与决策

- **处置策略**：验证通过则直接移除 trait/interface，不做 deprecation 过渡
- **API 替代**：优先使用 EntityManager public API 替代 UnitOfWork internal API；如需自维护状态跟踪方案须与用户确认
- **对照组测试**：保留 `CascadeRemoveContrastTest` 并反转断言，作为 ORM 3 行为的回归保护
- **版本号**：3.1 允许 breaking change，不需要升到 4.0
- **问题修复**：升级过程中发现的所有问题一并修复，不区分是否为升级引入
