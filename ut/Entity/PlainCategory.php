<?php

namespace Oasis\Mlib\Doctrine\Ut\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Oasis\Mlib\Doctrine\AutoIdTrait;

/**
 * A category entity that does NOT use CascadeRemoveTrait.
 * Used to demonstrate the problem this lib solves.
 *
 * @ORM\Entity()
 * @ORM\Table(name="plain_categories")
 * @ORM\Cache(usage="NONSTRICT_READ_WRITE")
 */
class PlainCategory
{
    use AutoIdTrait;

    /**
     * @var ArrayCollection
     * @ORM\OneToMany(targetEntity="PlainArticle", mappedBy="category")
     */
    protected $articles;

    public function __construct()
    {
        $this->articles = new ArrayCollection();
    }

    /** @internal */
    public function addArticle(PlainArticle $article)
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
        }
    }

    /** @internal */
    public function removeArticle(PlainArticle $article)
    {
        if ($this->articles->contains($article)) {
            $this->articles->removeElement($article);
        }
    }

    /** @return ArrayCollection */
    public function getArticles()
    {
        return $this->articles;
    }
}
