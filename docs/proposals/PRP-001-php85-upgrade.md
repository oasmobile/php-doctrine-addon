# PHP 8.5 Upgrade

`docs/proposals/` — 升级 PHP 运行时与测试工具链，引入 property-based testing。

---

## Status

`accepted`

---

## Background

当前项目锁定在较旧的工具链版本上：

| 组件 | 当前版本 | 状态 |
|------|----------|------|
| PHP | 7.4+（`composer.json` 未显式声明，实际运行环境已是 8.5） | 版本约束过旧 |
| `doctrine/orm` | ^2.7（实际安装 2.20.10） | 约束过宽 |
| `doctrine/annotations` | ^1.13 | 已被官方标记 abandoned |
| `phpunit/phpunit` | ^8.5 | PHP 8.5 下已不受支持 |
| `symfony/cache` | ^5.4 | Symfony 5.4 已 EOL |
| PBT 工具 | 无 | 缺少 property-based testing 能力 |

PHP 8.5 已正式发布，PHPUnit 13 要求 PHP ≥ 8.4，Eris 1.x 已支持 PHPUnit 10–13 和 PHP 8.1+。

---

## Problem

1. `phpunit/phpunit` ^8.5 无法在 PHP 8.5 上正常运行（deprecation 和 API 不兼容）
2. `doctrine/annotations` 已 abandoned，Doctrine ORM 2.x 后续仅接受 PHP 兼容性修复和安全修复
3. `symfony/cache` ^5.4 已 EOL，不再接收安全修复
4. 项目缺少 property-based testing，对 `CascadeRemoveTrait` 递归逻辑等复杂路径的覆盖不足

---

## Goals

- 支持 PHP 8.5 运行时
- 升级 PHPUnit 至 ^13（要求 PHP ≥ 8.4）
- 引入 `giorgiosironi/eris` ^1.0 作为 PBT 工具
- 升级 `symfony/cache` 至兼容 PHP 8.5 的版本
- 升级 `doctrine/orm` 版本约束以匹配实际安装版本
- 确保所有现有测试在新工具链下通过

---

## Non-Goals

- 不迁移到 Doctrine ORM 3.x（ORM 2.x 仍在维护窗口内，至少到 2027-02）
- 不将 Doctrine Annotation 迁移到 PHP Attribute（属于独立重构，可另开 proposal）
- 不变更 `oasis/logging` 依赖（需确认上游兼容性，但不在本 proposal 范围内主动升级其大版本）
- 不新增业务功能

---

## Scope

### Composer 依赖变更

| 依赖 | 当前约束 | 目标约束 | 说明 |
|------|----------|----------|------|
| `php` | 未声明 | `^8.4` | 显式声明最低 PHP 版本 |
| `doctrine/orm` | `^2.7` | `^2.20` | 收窄到实际兼容范围 |
| `doctrine/annotations` | `^1.13` | `^1.13`（暂保留） | ORM 2.x 仍依赖，待 Attribute 迁移时移除 |
| `phpunit/phpunit` | `^8.5` | `^13.0` | 跨大版本升级 |
| `symfony/cache` | `^5.4` | `^7.2` | 升级到活跃维护版本 |
| `giorgiosironi/eris` | 无 | `^1.0` | 新增 PBT 依赖 |

### 代码适配

- PHPUnit 8 → 13 的 API 变更（TestCase 方法签名、assertion 变更、`phpunit.xml` schema 等）
- `TestEnv.php` 中已 deprecated 的 Doctrine API 调用（`Setup::createAnnotationMetadataConfiguration`、`EntityManager::create`）
- `phpunit.xml` 配置格式升级

### 测试

- 所有现有测试在 PHP 8.5 + PHPUnit 13 下通过
- 新增至少一组 Eris PBT 用例，验证工具链集成

---

## References

- [PHPUnit 13 Release Announcement](https://phpunit.de/announcements/phpunit-13.html) — 要求 PHP ≥ 8.4
- [Eris 1.1.0 on Packagist](https://packagist.org/packages/giorgiosironi/eris) — 支持 PHPUnit 10–13，PHP ≥ 8.1
- [Doctrine ORM 2 EOL Update](https://www.doctrine-project.org/2025/10/08/an-update-on-the-orm-2-end-of-life.html) — ORM 2.x 维护延长至 2027-02

---

## Notes

- `oasis/logging` ^1.1 当前依赖 `monolog/monolog` 1.x，需在 spec 阶段验证其在 PHP 8.5 下的兼容性；若不兼容则需协调上游或 fork
- `doctrine/annotations` 虽已 abandoned，但 ORM 2.x 仍需要它；迁移到 Attribute 是独立工作，建议另开 proposal
- PHPUnit 8 → 13 跨越多个大版本，需关注 `setUp`/`tearDown` 返回类型、`assertContains` 行为变更、mock API 变更等
