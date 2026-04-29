<?php

namespace Oasis\Mlib\Doctrine\Ut\Test;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Oasis\Mlib\Doctrine\CascadeRemoveTrait;
use Oasis\Mlib\Doctrine\Ut\Entity\Article;
use Oasis\Mlib\Doctrine\Ut\Entity\Category;
use Oasis\Mlib\Doctrine\Ut\Entity\Tag;
use Oasis\Mlib\Doctrine\Ut\TestEnv;
use PHPUnit\Framework\TestCase;

/**
 * A dummy class that uses CascadeRemoveTrait but does NOT implement CascadeRemovableInterface.
 * Used to test the LogicException guard.
 */
class BadEntity
{
    use CascadeRemoveTrait;
}

class CascadeRemoveTraitTest extends TestCase
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

    /**
     * When a class uses CascadeRemoveTrait but does not implement CascadeRemovableInterface,
     * onPreRemove must throw a LogicException.
     */
    public function testOnPreRemoveThrowsWhenInterfaceNotImplemented()
    {
        $bad = new BadEntity();

        $eventArgs = $this->createStub(\Doctrine\Persistence\Event\LifecycleEventArgs::class);
        $eventArgs->method('getObjectManager')->willReturn($this->entityManger);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must implement');
        $bad->onPreRemove($eventArgs);
    }

    /**
     * Removing an entity with no cascade-removeable entities and no dirty entities
     * should succeed without errors.
     */
    public function testRemoveEntityWithEmptyAssociations()
    {
        // Article has no cascade-removeable entities and no tags (empty dirty list)
        $article = new Article();
        $this->entityManger->persist($article);
        $this->entityManger->flush();

        $id = $article->getId();
        $this->assertNotNull($id);

        $this->entityManger->remove($article);
        $this->entityManger->flush();

        $found = $this->entityManger->find(Article::class, $id);
        $this->assertNull($found);
    }

    /**
     * Removing a Tag that has associated Articles should refresh those Articles
     * (dirty entities) rather than removing them.
     */
    public function testDirtyEntitiesAreRefreshedAfterRemove()
    {
        $article = new Article();
        $tag1 = new Tag();
        $tag2 = new Tag();
        $article->addTag($tag1);
        $article->addTag($tag2);

        $this->entityManger->persist($article);
        $this->entityManger->persist($tag1);
        $this->entityManger->persist($tag2);
        $this->entityManger->flush();

        $articleId = $article->getId();
        $tag2Id    = $tag2->getId();

        // Remove tag1 — article is a dirty entity, should be refreshed, not removed
        $this->entityManger->remove($tag1);
        $this->entityManger->flush();

        /** @var Article $refreshedArticle */
        $refreshedArticle = $this->entityManger->find(Article::class, $articleId);
        $this->assertNotNull($refreshedArticle, 'Article should still exist after removing one of its tags');

        // tag2 should still be associated
        /** @var Tag $remainingTag */
        $remainingTag = $this->entityManger->find(Tag::class, $tag2Id);
        $this->assertNotNull($remainingTag);
    }

    /**
     * Category.getCascadeRemoveableEntities returns its articles.
     * Removing a category should cascade-detach its articles from the EM.
     */
    public function testCascadeRemoveDetachesAssociatedEntities()
    {
        $category = new Category();
        $article1 = new Article();
        $article2 = new Article();
        $article1->setCategory($category);
        $article2->setCategory($category);

        $this->entityManger->persist($category);
        $this->entityManger->persist($article1);
        $this->entityManger->persist($article2);
        $this->entityManger->flush();

        $a1Id = $article1->getId();
        $a2Id = $article2->getId();

        $this->entityManger->remove($category);
        $this->entityManger->flush();

        // Articles should have been cascade-deleted by DB (onDelete=CASCADE)
        // and detached from EM by CascadeRemoveTrait
        $this->assertNull($this->entityManger->find(Article::class, $a1Id));
        $this->assertNull($this->entityManger->find(Article::class, $a2Id));
    }

    /**
     * Deep cascade: Category -> Articles -> (tags as dirty).
     * Removing a category with articles that have tags should handle the full chain.
     */
    public function testDeepCascadeRemoveWithDirtyEntities()
    {
        $category = new Category();
        $article  = new Article();
        $tag      = new Tag();

        $article->setCategory($category);
        $article->addTag($tag);

        $this->entityManger->persist($category);
        $this->entityManger->persist($article);
        $this->entityManger->persist($tag);
        $this->entityManger->flush();

        $tagId = $tag->getId();

        // Remove category -> article is cascade-removed (DB level) and detached (trait level)
        // -> tag is a dirty entity of article, should be refreshed
        $this->entityManger->remove($category);
        $this->entityManger->flush();

        // Tag should still exist (it's dirty, not cascade-removed)
        /** @var Tag $refreshedTag */
        $refreshedTag = $this->entityManger->find(Tag::class, $tagId);
        $this->assertNotNull($refreshedTag, 'Tag should survive as a dirty entity, not be removed');
    }

    /**
     * When a dirty entity is also scheduled for deletion, the trait should skip
     * refreshing it in onPostRemove (isScheduledForDelete branch).
     */
    public function testDirtyEntityScheduledForDeleteIsSkipped()
    {
        $article = new Article();
        $tag = new Tag();
        $article->addTag($tag);

        $this->entityManger->persist($article);
        $this->entityManger->persist($tag);
        $this->entityManger->flush();

        $articleId = $article->getId();
        $tagId     = $tag->getId();

        // Remove both article and tag in the same flush.
        // Tag is a dirty entity of article, but also scheduled for delete.
        // The trait should skip refreshing it.
        $this->entityManger->remove($article);
        $this->entityManger->remove($tag);
        $this->entityManger->flush();

        $this->assertNull($this->entityManger->find(Article::class, $articleId));
        $this->assertNull($this->entityManger->find(Tag::class, $tagId));
    }

    /**
     * After removing a category (which cascade-removes its articles at DB level),
     * the second-level cache must no longer contain the removed entities.
     * This verifies that onPostRemove actually calls evictEntity on the cache.
     */
    public function testSecondLevelCacheIsEvictedAfterRemove()
    {
        $cache = $this->entityManger->getCache();
        $this->assertNotNull($cache, 'Second-level cache must be enabled for this test');

        $category = new Category();
        $article  = new Article();
        $article->setCategory($category);

        $this->entityManger->persist($category);
        $this->entityManger->persist($article);
        $this->entityManger->flush();

        $categoryId = $category->getId();
        $articleId  = $article->getId();

        // Warm up the cache by loading entities
        $this->entityManger->clear();
        $this->entityManger->find(Category::class, $categoryId);
        $this->entityManger->find(Article::class, $articleId);

        // Confirm entities are now in the second-level cache
        $this->assertTrue(
            $cache->containsEntity(Category::class, $categoryId),
            'Category should be in second-level cache before removal'
        );
        $this->assertTrue(
            $cache->containsEntity(Article::class, $articleId),
            'Article should be in second-level cache before removal'
        );

        // Remove category — article is cascade-removed at DB level,
        // and CascadeRemoveTrait.onPostRemove should evict both from cache
        /** @var Category $cat */
        $cat = $this->entityManger->find(Category::class, $categoryId);
        $this->entityManger->remove($cat);
        $this->entityManger->flush();

        $this->assertFalse(
            $cache->containsEntity(Category::class, $categoryId),
            'Category must be evicted from second-level cache after removal'
        );
        $this->assertFalse(
            $cache->containsEntity(Article::class, $articleId),
            'Cascade-removed article must be evicted from second-level cache'
        );
    }
}
