<?php
declare(strict_types=1);

namespace Oasis\Mlib\Doctrine\Ut\Test;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Oasis\Mlib\Doctrine\Ut\Entity\Article;
use Oasis\Mlib\Doctrine\Ut\TestEnv;
use PHPUnit\Framework\TestCase;

class AutoIdTraitTest extends TestCase
{
    /** @var EntityManager */
    protected $entityManger;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        TestEnv::resetEntityManager();
        $em   = TestEnv::getEntityManager();
        $tool = new SchemaTool($em);
        $tool->dropDatabase();
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManger = TestEnv::getEntityManager();
    }

    public function testIdIsNullBeforePersist()
    {
        $article = new Article();
        $this->assertNull($article->getId());
    }

    public function testIdIsAssignedAfterPersistAndFlush()
    {
        $article = new Article();
        $this->entityManger->persist($article);
        $this->entityManger->flush();

        $this->assertNotNull($article->getId());
        $this->assertIsInt($article->getId());
        $this->assertGreaterThan(0, $article->getId());
    }

    public function testMultipleEntitiesGetDistinctIds()
    {
        $a1 = new Article();
        $a2 = new Article();
        $this->entityManger->persist($a1);
        $this->entityManger->persist($a2);
        $this->entityManger->flush();

        $this->assertNotEquals($a1->getId(), $a2->getId());
    }

    public function testIdSurvivesRetrievalFromDatabase()
    {
        $article = new Article();
        $this->entityManger->persist($article);
        $this->entityManger->flush();

        $id = $article->getId();
        $this->entityManger->clear();

        /** @var Article $found */
        $found = $this->entityManger->find(Article::class, $id);
        $this->assertNotNull($found);
        $this->assertEquals($id, $found->getId());
    }
}
