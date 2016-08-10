<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-18
 * Time: 14:03
 */

namespace Oasis\Mlib\Doctrine\Ut;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Oasis\Mlib\Doctrine\AutoIdTrait;
use Oasis\Mlib\Doctrine\CascadeRemovableInterface;
use Oasis\Mlib\Doctrine\CascadeRemoveTrait;

/**
 * Class Tag
 *
 * @package Oasis\Mlib\Doctrine\Ut
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
