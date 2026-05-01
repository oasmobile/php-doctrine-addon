<?php
declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-11
 * Time: 14:48
 */

namespace Oasis\Mlib\Doctrine\Ut\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    protected ?Category $category = null;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'articles')]
    #[ORM\JoinTable(name: 'article_tags')]
    #[ORM\JoinColumn(name: '`article`', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'tag', referencedColumnName: 'id', onDelete: 'CASCADE')]
    protected Collection $tags;

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
