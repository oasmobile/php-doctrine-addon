<?php

namespace Oasis\Mlib\Doctrine\Ut\Entity;

use Doctrine\ORM\Mapping as ORM;
use Oasis\Mlib\Doctrine\AutoIdTrait;

/**
 * An article entity that does NOT use CascadeRemoveTrait.
 * Used to demonstrate the problem this lib solves.
 */
#[ORM\Entity]
#[ORM\Table(name: 'plain_articles')]
#[ORM\Cache(usage: 'NONSTRICT_READ_WRITE')]
class PlainArticle
{
    use AutoIdTrait;

    #[ORM\ManyToOne(targetEntity: PlainCategory::class, inversedBy: 'articles')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    protected ?PlainCategory $category = null;

    /** @return PlainCategory */
    public function getCategory()
    {
        return $this->category;
    }

    /** @param PlainCategory $category */
    public function setCategory(PlainCategory $category)
    {
        if ($this->category === $category) {
            return;
        }
        if ($this->category) {
            $this->category->removeArticle($this);
        }
        $this->category = $category;
        $category->addArticle($this);
    }
}
