# **oasis/doctrine-addon** component

[Doctrine] is the most popular vendor for PHP database components. The core projects under this name are a [Object Relational Mapper (ORM)](http://doctrine-project.org/projects/orm.html) and the [Database Abstraction Layer (DBAL)](http://doctrine-project.org/projects/dbal.html) it is built upon.

The **oasis/doctrine-addon** component provides a few useful features to extend the [doctrine/orm] component:

- a trait to ease declaration of auto generated id field
- a trait/mechanism to make cache invalidating more flexible, during object removal phase

## Installation

Install the latest version with command below:

```bash
$ composer require oasis/doctrine-addon
```

## AutoIdTrait

The `AutoIdTrait` defines an `id` property and the getter method `getId()`. It can be used in any entity class that uses `id` as its primary index.

## Cascade Removal Solution

When cache is presented for an ORM-enabled application, cache invalidation has always been an interesting topic to deal with. One most common exception in ORM is when you try to access a collection that contains out-dated entity. This problem is commonly referred to as the _cascade removal problem_. An exmaple is like below:

> You have a Tean entity that holds a collection of its members, which are User entities. When you remove a User entity, without proper invalidation process, accessing the Team that holds reference to this User will throw an exception.

By default, [doctrine/orm] provides two ways to solve this problem:

- Manual invalidation upon each removal
- Use the `cascade={"remove"}` annotation

However, either of the two solutions are not optimal. Needless to say, the manual removal is complex and will become a nightmare when you have a reference chain. On the other hand, using the `cascade` annotation is very effecient development wise, but extremely slow in performance especially when the relation map is complicated and dataset is large.

**oasis/doctrine-addon** introduces another solution to the cascade removal problem, by providing the `CascadeRemoveTrait`. Any entity that wants to utilise the `CascadeRemoveTrait` must:

- declare with the `ORM\HasLifecycleCallbacks` annotation
- implement the `CascadeRemovableInterface`
- use the `CascadeRemoveTrait`
- use database provided schema to delete **strongly related entities**

Implementing the `CascadeRemovableInterface` further requires implementation of the following two methods:

- `getCascadeRemoveableEntities()`, which takes no arguments and returns an array of **strongly related entities**
- `getDirtyEntitiesOnInvalidation()`, which takes no arguments and returns an array of **loosely related entities**

> A **strongly related entity** is an entity that should also be removed when the current entity is removed.

> A **loosely related entity** is an entity that holds a reference to the current entity, either directly (To-One relation) or through a collection (To-Many relation). This reference should be invalidated when the current entity is removed.

The real removal of **strongly related entity** is achieved by database constraint, which must be `ON DELETE CASCADE`.

## Code Sample (very simple CMS)

Imagine we have a simple CMS that has 3 types of entities: Categroy, Article and Tag. The system must meet the following requirements:

- An Article may belong to a Category
- An Article and can have more than one Tag
- Different Articles can share Tags

Below is the implementation:

```php
<?php
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Oasis\Mlib\Doctrine\AutoIdTrait;
use Oasis\Mlib\Doctrine\CascadeRemovableInterface;
use Oasis\Mlib\Doctrine\CascadeRemoveTrait;

/**
 * Class Article
 *
 * @ORM\Entity()
 * @ORM\Table(name="articles")
 *
 * @ORM\HasLifecycleCallbacks()
 */
class Article implements CascadeRemovableInterface
{
    use CascadeRemoveTrait;
    use AutoIdTrait;

    /**
     * @var Category
     * @ORM\ManyToOne(targetEntity="Category", inversedBy="articles")
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    protected $category;
    /**
     * @var ArrayCollection
     * @ORM\ManyToMany(targetEntity="Tag", inversedBy="articles")
     * @ORM\JoinTable(name="article_tags",
     *     inverseJoinColumns={@ORM\JoinColumn(name="tag", referencedColumnName="id", onDelete="CASCADE")},
     *     joinColumns={@ORM\JoinColumn(name="`article`", referencedColumnName="id", onDelete="CASCADE")})
     */
    protected $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
    }

    function __toString()
    {
        return sprintf("Article #%s", $this->getId());
    }

    public function addTag(Tag $tag)
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
            /** @noinspection PhpInternalEntityUsedInspection */
            $tag->addArticle($this);
        }
    }

    /**
     * @return array an array of entities which will also be removed when the calling entity is remvoed
     */
    public function getCascadeRemoveableEntities()
    {
        return [];
    }

    /**
     * @return array an array of entities asscociated to the calling entity, which should be detached when calling
     *               entity is removed.
     */
    public function getDirtyEntitiesOnInvalidation()
    {
        return $this->tags->toArray();
    }

    /**
     * @return Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * @param Category $category
     */
    public function setCategory($category)
    {
        if ($this->category == $category) {
            return;
        }
        if ($this->category) {
            /** @noinspection PhpInternalEntityUsedInspection */
            $this->category->removeArticle($this);
        }
        $this->category = $category;
        if ($category) {
            /** @noinspection PhpInternalEntityUsedInspection */
            $category->addArticle($this);
        }
    }

    /**
     * @return ArrayCollection
     */
    public function getTags()
    {
        return $this->tags;
    }
}
```

```php
<?php

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Oasis\Mlib\Doctrine\AutoIdTrait;
use Oasis\Mlib\Doctrine\CascadeRemovableInterface;
use Oasis\Mlib\Doctrine\CascadeRemoveTrait;

/**
 * Class Category
 *
 * @ORM\Entity()
 * @ORM\Table(name="categories")
 *
 * @ORM\HasLifecycleCallbacks()
 */
class Category implements CascadeRemovableInterface
{
    use CascadeRemoveTrait;
    use AutoIdTrait;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="Article", mappedBy="category")
     */
    protected $articles;

    /**
     * @var Category
     * @ORM\ManyToOne(targetEntity="Category", inversedBy="children")
     * @ORM\JoinColumn(onDelete="SET NULL");
     */
    protected $parent;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="Category", mappedBy="parent")
     */
    protected $children;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
        $this->children = new ArrayCollection();
    }

    function __toString()
    {
        return '111';
    }

    /**
     * @param Article $article
     *
     * @internal
     */
    public function addArticle($article)
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
        }
    }

    /**
     * @param $child
     *
     * @internal
     */
    public function addChild($child)
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
        }
    }

    /**
     * @param Article $article
     *
     * @internal
     */
    public function removeArticle($article)
    {
        if ($this->articles->contains($article)) {
            $this->articles->remove($article);
        }
    }

    /**
     * @param $child
     *
     * @internal
     */
    public function removeChild($child)
    {
        if ($this->children->contains($child)) {
            $this->children->remove($child);
        }
    }

    /**
     * @return array an array of entities which will also be removed when the calling entity is remvoed
     */
    public function getCascadeRemoveableEntities()
    {
        return $this->articles->toArray();
    }

    /**
     * @return array an array of entities asscociated to the calling entity, which should be detached when calling
     *               entity is removed.
     */
    public function getDirtyEntitiesOnInvalidation()
    {
        return [];
    }

    /**
     * @return ArrayCollection
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @return Category
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param Category $parent
     */
    public function setParent($parent)
    {
        if ($this->parent) {
            $this->parent->removeChild($this);
        }
        $this->parent = $parent;
        if ($parent) {
            $parent->addChild($this);
        }
    }
}

```

```php
<?php

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Oasis\Mlib\Doctrine\AutoIdTrait;
use Oasis\Mlib\Doctrine\CascadeRemovableInterface;
use Oasis\Mlib\Doctrine\CascadeRemoveTrait;

/**
 * Class Tag
 *
 * @ORM\Entity()
 *
 * @ORM\HasLifecycleCallbacks()
 */
class Tag implements CascadeRemovableInterface
{
    use CascadeRemoveTrait;
    use AutoIdTrait;

    /**
     * @var ArrayCollection
     *
     * @ORM\ManyToMany(targetEntity="Article", mappedBy="tags")
     */
    protected $articles;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    function __toString()
    {
        return sprintf("Tag #%s", $this->getId());
    }

    /**
     * @param Article $article
     * @internal
     */
    public function addArticle(Article $article)
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
        }
    }

    /**
     * @return ArrayCollection
     */
    public function getArticles()
    {
        return $this->articles;
    }

    /**
     * @return array an array of entities which will also be removed when the calling entity is remvoed
     */
    public function getCascadeRemoveableEntities()
    {
        return [];
    }

    /**
     * @return array an array of entities asscociated to the calling entity, which should be detached when calling
     *               entity is removed.
     */
    public function getDirtyEntitiesOnInvalidation()
    {
        return $this->articles->toArray();
    }
}

```

> **NOTE**: there is always one side and only one side in a bidirectional relation, that should be accessed by the outside users. In this example, you `setCategory()` on an Article, and you never call `addArticle()` directly on a Category. To further ensure this behavior and let the IDE helps us locate potential bug, we should decalre the _hidden_ method `@internal` so that every call from outside will trigger a warning.

> **HOMEWORK**: there is only `addTag()` method on an Article. You can try to write your implementation of `removeTag()` method to futher familiarize yourself with the ORM tool.


[Doctrine]: http://doctrine-project.org/
[doctrine/orm]: http://doctrine-project.org/projects/orm.html
