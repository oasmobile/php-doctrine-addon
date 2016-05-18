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
    
    /**
     * @return array an array of entities asscociated to the calling entity, which should be detached when calling
     *               entity is removed.
     */
    public function getCascadeRemovableEntities()
    {
        return array_merge(
            $this->articles->toArray(),
            //$this->children->toArray(),
            []
        );
    }
    
    /**
     * @param Article $article
     */
    public function addArticle($article)
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
        }
    }
    
    /**
     * @param Article $article
     */
    public function removeArticle($article)
    {
        if ($this->articles->contains($article)) {
            $this->articles->remove($article);
        }
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
    
    /**
     * @return ArrayCollection
     */
    public function getChildren()
    {
        return $this->children;
    }
    
    /**
     * @param $child
     */
    public function addChild($child)
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
        }
    }
    
    /**
     * @param $child
     */
    public function removeChild($child)
    {
        if ($this->children->contains($child)) {
            $this->children->remove($child);
        }
    }

    function __toString()
    {
        return '111';
    }

}
