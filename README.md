# oasis/doctrine-addon

[Doctrine ORM](http://doctrine-project.org/projects/orm.html) 扩展组件，提供：

- **AutoIdTrait** — 简化自增主键声明
- **CascadeRemoveTrait** — 解决使用数据库 `ON DELETE CASCADE` 时，EntityManager identity map 和 Second Level Cache 中残留脏数据的问题

## Installation

```bash
composer require oasis/doctrine-addon
```

## AutoIdTrait

`use AutoIdTrait` 即可获得自增整数主键 `$id` 和 `getId()` 方法：

```php
/**
 * @ORM\Entity()
 */
class User
{
    use AutoIdTrait;
}
```

## The Cascade Removal Problem

当 ORM 启用 Second Level Cache 并使用数据库 `ON DELETE CASCADE` 约束时，删除父 entity 后：

1. **Identity map 脏数据** — `$em->find()` 仍然返回已被数据库删除的子 entity
2. **二级缓存脏数据** — 缓存中仍持有已删除 entity 的数据
3. **关联集合不一致** — 持有被删除 entity 引用的其他 entity，其集合未被刷新

> 例如：Team entity 持有 User 集合。删除一个 User 后，访问 Team 的 members 集合仍会拿到已删除的 User 引用，导致异常。

Doctrine 原生方案各有缺陷：

- **手动失效**：复杂，关联链越深越容易遗漏
- **`cascade={"remove"}`**：ORM 逐条发 DELETE 语句，关联复杂时性能极差

## CascadeRemoveTrait Solution

通过 Doctrine 生命周期回调，在 `PreRemove` 阶段递归收集关联实体，在 `PostRemove` 阶段统一 detach + evict + refresh。实际删除由数据库 `ON DELETE CASCADE` 完成。

### Prerequisites

- Entity 必须启用 Doctrine Second Level Cache（`@ORM\Cache` 注解）
- 强关联实体的数据库外键必须设置 `ON DELETE CASCADE`

### Usage

```php
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

    /** @ORM\OneToMany(targetEntity="Article", mappedBy="category") */
    protected $articles;

    public function getCascadeRemoveableEntities()
    {
        // 强关联：删除 Category 时，其下的 Article 也应删除
        return $this->articles->toArray();
    }

    public function getDirtyEntitiesOnInvalidation()
    {
        // 弱关联：无（Category 删除不需要刷新其他 entity 的缓存）
        return [];
    }
}
```

### Key Concepts

| 概念 | 说明 | `getCascadeRemoveableEntities()` 返回 |
|------|------|--------------------------------------|
| **强关联实体** | 当前实体删除时也应删除的实体 | Category → Article |
| **弱关联实体** | 持有当前实体引用、需刷新缓存的实体 | Article → Tag（通过 `getDirtyEntitiesOnInvalidation()`） |

### What the Trait Does

| 阶段 | 强关联实体 | 弱关联实体 |
|------|-----------|-----------|
| PreRemove | 递归收集 | 收集（排除已在强关联列表中的） |
| PostRemove | detach from EM + evict from L2 cache | evict from L2 cache + refresh from DB |

完整的 CMS 示例（Category / Article / Tag）见 `ut/Entity/` 目录。

## Development

```bash
# 安装依赖
composer install

# 运行测试（零外部依赖，使用 SQLite in-memory）
vendor/bin/phpunit

# 覆盖率报告（需要 pcov 扩展）
vendor/bin/phpunit --coverage-text --whitelist=src/
```

详见 `docs/manual/development.md`。

## License

MIT
