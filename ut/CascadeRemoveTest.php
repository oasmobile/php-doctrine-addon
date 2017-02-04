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
        system("../vendor/bin/doctrine orm:clear-cache:meta");
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
        $grandchildrenCategory = new Category();
        $grandchildrenCategory->setParent($subCategory);

        $this->entityManger->persist($category);
        $this->entityManger->persist($article);
        $this->entityManger->persist($subCategory);
        $this->entityManger->persist($grandchildrenCategory);
        $this->entityManger->flush();

        $categoryId = $category->getId();
        $articleId  = $article->getId();
        $subId      = $subCategory->getId();

        $this->entityManger->remove($category);
        $this->entityManger->flush();

        $article2 = $this->entityManger->find(":Article", $articleId);
        $this->assertNull($article2);
        /** @var Category $subCategory */
        $subCategory2 = $this->entityManger->find(":Category", $subId);
        $this->assertNotNull($subCategory2);
        $this->assertNotNull(
            $categoryId,
            $subCategory2->getParent()
        ); // the parent is still a Category object, this should be null if we cascade remove all children

        $this->entityManger->detach($article);
        $this->entityManger->detach($subCategory);
        $this->entityManger->detach($category);
        $this->entityManger->detach($grandchildrenCategory);
        $this->entityManger->flush();
    }

    public function testCircularCascadeRemove()
    {
        $a1 = new Article();
        $a2 = new Article();
        $a3 = new Article();
        $t1 = new Tag();
        $t2 = new Tag();
        $t3 = new Tag();

        $a1->addTag($t1);
        $a1->addTag($t2);
        $a2->addTag($t3);
        $a2->addTag($t1);
        $a3->addTag($t2);
        $a3->addTag($t3);

        $this->entityManger->persist($a1);
        $this->entityManger->persist($a2);
        $this->entityManger->persist($a3);
        $this->entityManger->persist($t1);
        $this->entityManger->persist($t2);
        $this->entityManger->persist($t3);
        $this->entityManger->flush();
        $this->entityManger->flush();

        //echo sprintf(
        //    "Created %s, %s, %s, %s, %s, %s",
        //    $a1,
        //    $a2,
        //    $a3,
        //    $t1,
        //    $t2,
        //    $t3
        //);

        $a3id = $a3->getId(); // for later usage

        $this->entityManger->remove($t1);
        $this->entityManger->flush();
        $this->entityManger->flush();

        /** @var Article $a1 */
        $a1 = $this->entityManger->find(':Article', $a1->getId());
        $this->assertEquals(1, sizeof($a1->getTags()));

        $a3 = $this->entityManger->find(':Article', $a3id);
        $this->entityManger->remove($a3);
        $this->entityManger->flush();
        $this->entityManger->flush();

        /** @var Tag $t2 */
        $t2 = $this->entityManger->find(':Tag', $t2->getId());
        $this->assertEquals(1, sizeof($t2->getArticles()));

    }
    
    public function testRemovalOfBothSidesOfManyToManyRelation()
    {
        $article = new Article();
        $tag = new Tag();
        $this->entityManger->persist($article);
        $this->entityManger->persist($tag);
        $article->addTag($tag);
        $this->entityManger->flush();
        
        $this->entityManger->remove($article);
        $this->entityManger->remove($tag);
        $this->entityManger->flush();
    
    }

}
