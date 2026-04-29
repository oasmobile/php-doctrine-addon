# Spec Goal: PHP 8.5 Upgrade (Release 3.0)

## 来源

- 分支: `release/3.0`
- 需求文档: `docs/proposals/PRP-001-php85-upgrade.md`

## 背景摘要

`oasis/doctrine-addon` 是一个 Doctrine ORM 扩展库，提供 AutoIdTrait（自增主键）和 CascadeRemove 机制（级联删除缓存失效）。当前工具链锁定在较旧版本：PHPUnit ^8.5 无法在 PHP 8.5 上正常运行，`doctrine/annotations` 已被标记 abandoned，`symfony/cache` ^5.4 已 EOL，且项目缺少 property-based testing 能力。

PHP 8.5 已正式发布，项目实际运行环境已是 8.5，但 `composer.json` 未显式声明 PHP 版本约束，各依赖版本约束也过于宽松或过旧。本次 release 的目标是全面升级工具链，使项目正式支持 PHP 8.5 运行时，同时引入 PBT 工具补充测试覆盖。

## 目标

- 显式声明 PHP `^8.4` 版本约束
- 升级 PHPUnit 至 `^13.0`，适配所有 API 变更
- 升级 `doctrine/orm` 约束至 `^2.20`
- 将 Doctrine Annotation 迁移到 PHP Attribute，移除 `doctrine/annotations` 依赖
- 升级 `symfony/cache` 至 `^7.2`
- 升级 `oasis/logging` 至最新版本
- 引入 `giorgiosironi/eris` `^1.0` 作为 PBT 工具
- 适配 `phpunit.xml` 配置格式
- 替换 `TestEnv.php` 中已 deprecated 的 Doctrine API 调用
- 新增 Eris PBT 用例，覆盖 `AutoIdTrait` 和 `CascadeRemoveTrait`
- 确保所有现有测试在 PHP 8.5 + PHPUnit 13 下通过

## 不做的事情（Non-Goals）

- 不迁移到 Doctrine ORM 3.x（ORM 2.x 维护延长至 2027-02）
- 不新增业务功能

## Clarification 记录

### Q1: oasis/logging 兼容性策略

PRP-001 Notes 中提到 `oasis/logging` ^1.1 依赖 `monolog/monolog` 1.x，需要验证 PHP 8.5 兼容性。如果不兼容，处理方式会影响 scope。

- 选项: A) 本次 release 中一并解决 / B) 仅验证，不兼容则记录 known issue / C) 不验证，假设兼容 / D) 补充说明
- 回答: D — oasis 的各种依赖库都有 PHP 8.5 的升级版本，不存在兼容性障碍。

### Q2: Doctrine Annotation 的处理边界

升级 `doctrine/orm` 到 ^2.20 后，annotation driver API 可能有变化。对 deprecated Doctrine API 的处理策略。

- 选项: A) 仅替换无法运行的 API / B) 替换所有 deprecated API 但不做 Annotation → Attribute 迁移 / C) 补充说明
- 回答: C — 改变 PRP 的 Non-Goal，如果 Attribute 更好，不限制升级到 Attribute 模式。

### Q3: Eris PBT 用例的范围

PRP-001 Scope 中提到"新增至少一组 Eris PBT 用例，验证工具链集成"，覆盖期望不明确。

- 选项: A) 最小验证，证明集成正常即可 / B) 针对 CascadeRemoveTrait 递归逻辑写 PBT / C) 同时覆盖 AutoIdTrait 和 CascadeRemoveTrait / D) 补充说明
- 回答: C — 同时覆盖两个模块，用 PBT 补充现有测试的盲区。

### Q4: oasis/logging 版本约束

当前 `composer.json` 中 `oasis/logging` 约束是 `^1.1`，是否需要调整。

- 选项: A) 保持 ^1.1 不变 / B) 收窄到实际兼容的最低版本 / C) 补充说明
- 回答: C — 升到最新版本。

## 约束与决策

- **Annotation → Attribute 迁移纳入 scope**：PRP-001 原 Non-Goal 已修改，允许迁移到 PHP Attribute 并移除 `doctrine/annotations` 依赖
- **oasis/logging 升级到最新**：不仅收窄约束，而是直接升级到最新版本
- **PBT 覆盖两个模块**：Eris PBT 用例需同时覆盖 `AutoIdTrait` 和 `CascadeRemoveTrait`，不仅仅是工具链验证
- **不做 ORM 3.x 迁移**：保持 ORM 2.x，仅收窄版本约束
- **不使用独立 feature 分支**：PRP-001 的实现直接在 `release/3.0` 分支上进行
