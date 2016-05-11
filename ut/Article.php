<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-11
 * Time: 14:48
 */

namespace Oasis\Mlib\Doctrine\Ut;

use Doctrine\ORM\Mapping as ORM;
use Oasis\Mlib\Doctrine\AutoIdTrait;

/**
 * Class Article
 *
 * @package Oasis\Mlib\Doctrine\Ut
 *
 * @ORM\Entity()
 * @ORM\Table(name="articles")
 */
class Article
{
    use AutoIdTrait;

    /**
     * @var Category
     * @ORM\ManyToOne(targetEntity="Category", inversedBy="articles")
     * @ORM\JoinColumn(onDelete="CASCADE")
     */
    protected $category;

    public function __construct()
    {
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
            $this->category->removeArticle($this);
        }
        $this->category = $category;
        if ($category) {
            $category->addArticle($this);
        }
    }
}
