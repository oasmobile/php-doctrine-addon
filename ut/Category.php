<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-11
 * Time: 14:49
 */

namespace Oasis\Mlib\Doctrine\Ut;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Oasis\Mlib\Doctrine\AutoIdTrait;
use Oasis\Mlib\Doctrine\CascadeRemovableInterface;
use Oasis\Mlib\Doctrine\CascadeRemoveTrait;

/**
 * Class Category
 *
 * @package Oasis\Mlib\Doctrine\Ut
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
