# Data Model

`docs/state/` SSOT — 组件提供的数据模型与接口定义。

---

## AutoIdTrait

为 entity 提供自增整数主键。

### 提供的字段

| 字段 | 类型 | 注解 |
|------|------|------|
| `$id` | `integer` | `#[ORM\Id]`, `#[ORM\GeneratedValue(strategy: 'AUTO')]`, `#[ORM\Column(type: 'integer')]` |

### 提供的方法

| 方法 | 返回值 | 说明 |
|------|--------|------|
| `getId()` | `int` | 获取主键 |

---

## CascadeRemovableInterface

entity 实现此接口以启用级联删除缓存失效机制。

### 需实现的方法

| 方法 | 返回值 | 说明 |
|------|--------|------|
| `getCascadeRemoveableEntities()` | `array` | 返回强关联实体（当前实体删除时也应删除的实体） |
| `getDirtyEntitiesOnInvalidation()` | `array` | 返回弱关联实体（持有当前实体引用、需要刷新缓存的实体） |
| `onPostRemove(LifecycleEventArgs $eventArgs)` | `mixed` | PostRemove 生命周期回调（`CascadeRemoveTrait` 提供默认实现） |

---

## CascadeRemoveTrait

实现 `CascadeRemovableInterface` 的 `onPostRemove`，并提供 `onPreRemove` 回调。

### 使用前提

- entity 必须声明 `#[ORM\HasLifecycleCallbacks]` attribute
- entity 必须实现 `CascadeRemovableInterface`（否则 `onPreRemove` 抛出 `LogicException`）
- entity 必须声明 `#[ORM\Cache]` attribute 以启用 Second Level Cache
- 强关联实体的数据库外键必须设置 `ON DELETE CASCADE`

### 内部字段

| 字段 | 类型 | 说明 |
|------|------|------|
| `$removedEntities` | `array` | PreRemove 阶段收集的待移除实体 |
| `$dirtyEntities` | `array` | PreRemove 阶段收集的待刷新实体 |

### 生命周期回调

| 回调 | 阶段 | 行为 |
|------|------|------|
| `onPreRemove` | `#[ORM\PreRemove]` | 递归收集强关联实体和弱关联实体；校验接口实现 |
| `onPostRemove` | `#[ORM\PostRemove]` | detach 强关联实体并从二级缓存 evict；refresh 弱关联实体并从二级缓存 evict |

### onPostRemove 中的跳过逻辑

对于弱关联实体（dirty entity），以下情况跳过 refresh：

- 该实体不再被 EntityManager 管理（`!$em->contains($entity)`），即已被调度删除或不在 identity map 中

---

## 关联类型定义

- **强关联实体（strongly related）**：当前实体删除时也应被删除的实体，由数据库 `ON DELETE CASCADE` 约束实际执行删除
- **弱关联实体（loosely related）**：持有当前实体引用的实体（To-One 或 To-Many），删除后需刷新其缓存以避免引用失效
