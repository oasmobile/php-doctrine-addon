<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-11
 * Time: 15:01
 */

namespace Oasis\Mlib\Doctrine\Ut;

use Doctrine\ORM\EntityManager;

class CascadeRemoveTest extends \PHPUnit_Framework_TestCase
{
    /** @var  EntityManager */
    protected $entityManger;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        $olddir = getcwd();
        chdir(__DIR__);
        system("../vendor/bin/doctrine orm:schema-tool:drop -f");
        system("../vendor/bin/doctrine orm:schema-tool:create");
        chdir($olddir);
    }

    protected function setUp()
    {
        parent::setUp();

        $this->entityManger = TestEnv::getEntityManager();
    }

    public function testCascadeRemove()
    {
        $category = new Category();
        $article  = new Article();
        $article->setCategory($category);
        $subCategory = new Category();
        $subCategory->setParent($category);

        $this->entityManger->persist($category);
        $this->entityManger->persist($article);
        $this->entityManger->persist($subCategory);
        $this->entityManger->flush();

        $categoryId = $category->getId();
        $articleId  = $article->getId();
        $subId      = $subCategory->getId();

        $this->entityManger->remove($category);
        $this->entityManger->flush();

        $article = $this->entityManger->find(":Article", $articleId);
        $this->assertNull($article);
        /** @var Category $subCategory */
        $subCategory = $this->entityManger->find(":Category", $subId);
        $this->assertNotNull($subCategory);
        $this->assertNotNull(
            $categoryId,
            $subCategory->getParent()
        ); // the parent is still a Category object, this should be null if we cascade remove all children
    }

}
