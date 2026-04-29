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

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    use AutoIdTrait;

    // 其他字段...
}
```

---

## CascadeRemove 机制

### 问题背景

当使用数据库 `ON DELETE CASCADE` 约束来级联删除关联记录时，Doctrine ORM 对此一无所知。删除父 entity 后：

1. **Identity map 脏数据**：`$em->find()` 仍然从 identity map 返回已被数据库删除的子 entity
2. **Second Level Cache 脏数据**：即使 `$em->clear()` 后，二级缓存仍然持有已删除 entity 的数据
3. **关联集合不一致**：持有被删除 entity 引用的其他 entity，其集合未被刷新

Doctrine 原生方案：

- 手动逐一失效：复杂且易遗漏
- `cascade={"remove"}`：开发简单但性能差，ORM 逐条发 DELETE 语句

### 本组件方案

通过 Doctrine 生命周期回调，在 `PreRemove` 阶段递归收集关联实体，在 `PostRemove` 阶段统一执行：

- **强关联实体**：从 EntityManager detach + 从二级缓存 evict
- **弱关联实体**：从二级缓存 evict + refresh（重新从数据库加载）

实际删除由数据库 `ON DELETE CASCADE` 约束完成，避免 ORM 层逐条删除的性能问题。

### 使用前提

- 使用方必须启用 Doctrine Second Level Cache（entity 声明 `#[ORM\Cache]` attribute + ORM 配置中开启二级缓存）
- 强关联实体的数据库外键必须设置 `ON DELETE CASCADE`

### 使用步骤

1. entity 添加 `#[ORM\HasLifecycleCallbacks]` attribute
2. entity 添加 `#[ORM\Cache]` attribute
3. 实现 `CascadeRemovableInterface`
4. `use CascadeRemoveTrait`
5. 数据库外键设置 `ON DELETE CASCADE`

```php
use Doctrine\ORM\Mapping as ORM;
use Oasis\Mlib\Doctrine\AutoIdTrait;
use Oasis\Mlib\Doctrine\CascadeRemovableInterface;
use Oasis\Mlib\Doctrine\CascadeRemoveTrait;

#[ORM\Entity]
#[ORM\Table(name: 'articles')]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
#[ORM\HasLifecycleCallbacks]
class Article implements CascadeRemovableInterface
{
    use CascadeRemoveTrait;
    use AutoIdTrait;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
    #[ORM\JoinTable(name: 'article_tags',
        inverseJoinColumns: [new ORM\JoinColumn(onDelete: 'CASCADE')],
        joinColumns: [new ORM\JoinColumn(onDelete: 'CASCADE')])]
    protected $tags;

    public function getCascadeRemoveableEntities()
    {
        // 返回删除当前实体时也应删除的实体（强关联）
        return [];
    }

    public function getDirtyEntitiesOnInvalidation()
    {
        // 返回持有当前实体引用、需要刷新缓存的实体（弱关联）
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
