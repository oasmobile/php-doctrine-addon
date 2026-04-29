<?php

namespace Oasis\Mlib\Doctrine\Ut\Test;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Eris\TestTrait;
use Oasis\Mlib\Doctrine\Ut\Entity\Article;
use Oasis\Mlib\Doctrine\Ut\TestEnv;
use PHPUnit\Framework\TestCase;

use function Eris\Generator\choose;

class AutoIdTraitPbtTest extends TestCase
{
    use TestTrait;

    private EntityManager $em;

    private function resetSchema(): void
    {
        TestEnv::resetEntityManager();
        $this->em = TestEnv::getEntityManager();
        $tool = new SchemaTool($this->em);
        $tool->dropDatabase();
        $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    /**
     * Feature: release-3.0, Property 1: AutoIdTrait 批量 ID 唯一性与正整数约束
     *
     * For any batch size N (1 ≤ N ≤ 100), after persisting and flushing N
     * entities using AutoIdTrait, all IDs must be unique positive integers.
     *
     * Validates: Requirements 10.2
     */
    public function testBatchIdUniquenessAndPositiveIntegerConstraint(): void
    {
        $this->forAll(
            choose(1, 100)
        )->then(function (int $batchSize): void {
            $this->resetSchema();

            $articles = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $article = new Article();
                $this->em->persist($article);
                $articles[] = $article;
            }
            $this->em->flush();

            $ids = [];
            foreach ($articles as $article) {
                $id = $article->getId();
                $this->assertIsInt($id, "ID must be an integer");
                $this->assertGreaterThan(0, $id, "ID must be a positive integer");
                $ids[] = $id;
            }

            $this->assertCount(
                $batchSize,
                array_unique($ids),
                "All $batchSize IDs must be unique, got duplicates: " . implode(', ', $ids)
            );
        });
    }

    /**
     * Feature: release-3.0, Property 2: AutoIdTrait ID 持久化 round-trip
     *
     * For any batch size N (1 ≤ N ≤ 100), after persist → flush → clear → find,
     * each entity's ID must remain identical to the one assigned at flush time.
     *
     * Validates: Requirements 10.3
     */
    public function testIdPersistenceRoundTrip(): void
    {
        $this->forAll(
            choose(1, 100)
        )->then(function (int $batchSize): void {
            $this->resetSchema();

            $articles = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $article = new Article();
                $this->em->persist($article);
                $articles[] = $article;
            }
            $this->em->flush();

            // Record IDs before clearing
            $originalIds = [];
            foreach ($articles as $article) {
                $originalIds[] = $article->getId();
            }

            // Clear identity map, forcing reload from DB
            $this->em->clear();

            // Reload each entity and verify ID consistency
            foreach ($originalIds as $originalId) {
                $found = $this->em->find(Article::class, $originalId);
                $this->assertNotNull($found, "Entity with ID $originalId must be found after clear");
                $this->assertSame($originalId, $found->getId(), "ID must survive round-trip");
            }
        });
    }
}
