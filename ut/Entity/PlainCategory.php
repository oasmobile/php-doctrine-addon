<?php
declare(strict_types=1);

namespace Oasis\Mlib\Doctrine\Ut\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Oasis\Mlib\Doctrine\AutoIdTrait;

/**
 * A category entity that does NOT use CascadeRemoveTrait.
 * Used to demonstrate the problem this lib solves.
 */
#[ORM\Entity]
#[ORM\Table(name: 'plain_categories')]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
class PlainCategory
{
    use AutoIdTrait;

    #[ORM\OneToMany(targetEntity: PlainArticle::class, mappedBy: 'category')]
    protected Collection $articles;

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
