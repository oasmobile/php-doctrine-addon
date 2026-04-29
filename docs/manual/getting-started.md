# Getting Started

`docs/manual/` — `oasis/doctrine-addon` 的安装与基本使用说明。

---

## 安装

```bash
composer require oasis/doctrine-addon
```

---

## AutoIdTrait

在 entity 中 `use AutoIdTrait` 即可获得自增整数主键 `$id` 和 `getId()` 方法。

```php
use Doctrine\ORM\Mapping as ORM;
use Oasis\Mlib\Doctrine\AutoIdTrait;

/**
 * @ORM\Entity()
 * @ORM\Table(name="users")
 */
class User
{
    use AutoIdTrait;

    // 其他字段...
}
```

---

## CascadeRemove 机制

### 问题背景

当 ORM 启用 Second Level Cache 时，删除一个 entity 后，与之关联的 entity 缓存可能未及时失效，导致访问时抛出异常。

Doctrine 原生方案：

- 手动逐一失效：复杂且易遗漏
- `cascade={"remove"}`：开发简单但性能差，尤其在关联链较深时

### 本组件方案

通过 Doctrine 生命周期回调，在 PreRemove 阶段递归收集关联实体，在 PostRemove 阶段统一 detach 和 refresh 缓存。实际删除由数据库 `ON DELETE CASCADE` 约束完成，避免 ORM 层逐条删除的性能问题。

### 使用步骤

1. entity 添加 `@ORM\HasLifecycleCallbacks` 注解
2. 实现 `CascadeRemovableInterface`
3. `use CascadeRemoveTrait`
4. 数据库外键设置 `ON DELETE CASCADE`

```php
use Doctrine\ORM\Mapping as ORM;
use Oasis\Mlib\Doctrine\CascadeRemovableInterface;
use Oasis\Mlib\Doctrine\CascadeRemoveTrait;

/**
 * @ORM\Entity()
 * @ORM\Table(name="articles")
 * @ORM\HasLifecycleCallbacks()
 */
class Article implements CascadeRemovableInterface
{
    use CascadeRemoveTrait;
    use AutoIdTrait;

    public function getCascadeRemoveableEntities()
    {
        // 返回删除当前实体时也应删除的实体
        return [];
    }

    public function getDirtyEntitiesOnInvalidation()
    {
        // 返回持有当前实体引用、需要刷新缓存的实体
        return $this->tags->toArray();
    }
}
```

### 关键概念

| 概念 | 说明 | 示例 |
|------|------|------|
| 强关联实体 | 当前实体删除时也应删除 | Category 删除时，其下的 Article 也删除 |
| 弱关联实体 | 持有当前实体引用，需刷新缓存 | Article 删除时，其关联的 Tag 需刷新 |

---

## 双向关联的 `@internal` 约定

双向关联中，只有一侧对外暴露写方法。另一侧的方法标记为 `@internal`，仅供关联维护方内部调用。

例如：外部调用 `$article->setCategory($category)`，而不直接调用 `$category->addArticle($article)`。
