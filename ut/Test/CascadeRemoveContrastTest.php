<?php

namespace Oasis\Mlib\Doctrine\Ut\Test;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Oasis\Mlib\Doctrine\Ut\Entity\Article;
use Oasis\Mlib\Doctrine\Ut\Entity\Category;
use Oasis\Mlib\Doctrine\Ut\Entity\PlainArticle;
use Oasis\Mlib\Doctrine\Ut\Entity\PlainCategory;
use Oasis\Mlib\Doctrine\Ut\Entity\Tag;
use Oasis\Mlib\Doctrine\Ut\TestEnv;
use PHPUnit\Framework\TestCase;

/**
 * Contrast tests: demonstrate the exact problem CascadeRemoveTrait solves.
 *
 * Each scenario is tested twice:
 *   - "Without trait" (Plain* entities): shows the stale-data bug
 *   - "With trait" (Article/Category entities): shows the bug is fixed
 */
class CascadeRemoveContrastTest extends TestCase
{
    /** @var EntityManager */
    protected $em;

    protected function setUp(): void
    {
        parent::setUp();
        // Each test gets a fresh EM + schema to avoid cross-test contamination
        TestEnv::resetEntityManager();
        $this->em = TestEnv::getEntityManager();
        $tool = new SchemaTool($this->em);
        $tool->dropDatabase();
        $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    // ---------------------------------------------------------------
    //  Scenario 1: Identity map holds stale reference after DB cascade
    // ---------------------------------------------------------------

    /**
     * WITHOUT the trait: after removing a parent, the child entity that was
     * cascade-deleted by the DB is still returned by EM from the identity map.
     */
    public function testWithoutTrait_IdentityMapReturnsStaleEntity()
    {
        $category = new PlainCategory();
        $article  = new PlainArticle();
        $article->setCategory($category);

        $this->em->persist($category);
        $this->em->persist($article);
        $this->em->flush();

        $articleId = $article->getId();

        $this->em->remove($category);
        $this->em->flush();

        // The DB has cascade-deleted the article, but EM doesn't know.
        // find() returns the stale object from the identity map.
        $stale = $this->em->find(PlainArticle::class, $articleId);
        $this->assertNotNull($stale, 'BUG: EM still returns the cascade-deleted entity from identity map');
    }

    /**
     * WITH the trait: after removing a parent, the child entity is properly
     * detached from the EM, so find() returns null.
     */
    public function testWithTrait_IdentityMapIsClean()
    {
        $category = new Category();
        $article  = new Article();
        $article->setCategory($category);

        $this->em->persist($category);
        $this->em->persist($article);
        $this->em->flush();

        $articleId = $article->getId();

        $this->em->remove($category);
        $this->em->flush();

        // The trait detached the article from EM, so find() goes to DB and gets null.
        $result = $this->em->find(Article::class, $articleId);
        $this->assertNull($result, 'With trait: cascade-deleted entity should not be found');
    }

    // ---------------------------------------------------------------
    //  Scenario 2: Second-level cache serves stale data after DB cascade
    // ---------------------------------------------------------------

    /**
     * WITHOUT the trait: even after clearing the EM, the second-level cache
     * still contains the cascade-deleted entity.
     */
    public function testWithoutTrait_SecondLevelCacheIsStale()
    {
        $cache = $this->em->getCache();
        $this->assertNotNull($cache);

        $category = new PlainCategory();
        $article  = new PlainArticle();
        $article->setCategory($category);

        $this->em->persist($category);
        $this->em->persist($article);
        $this->em->flush();

        $articleId = $article->getId();

        // Warm up the second-level cache
        $this->em->clear();
        $this->em->find(PlainArticle::class, $articleId);
        $this->assertTrue($cache->containsEntity(PlainArticle::class, $articleId));

        // Re-fetch parent so we can remove it
        $category = $this->em->find(PlainCategory::class, $category->getId());
        $this->em->remove($category);
        $this->em->flush();

        // DB has cascade-deleted the article, but the cache still has it.
        $this->assertTrue(
            $cache->containsEntity(PlainArticle::class, $articleId),
            'BUG: second-level cache still contains the cascade-deleted entity'
        );
    }

    /**
     * WITH the trait: after removing a parent, the cascade-deleted child
     * is evicted from the second-level cache.
     */
    public function testWithTrait_SecondLevelCacheIsEvicted()
    {
        $cache = $this->em->getCache();

        $category = new Category();
        $article  = new Article();
        $article->setCategory($category);

        $this->em->persist($category);
        $this->em->persist($article);
        $this->em->flush();

        $articleId = $article->getId();

        // Warm up the second-level cache
        $this->em->clear();
        $this->em->find(Article::class, $articleId);
        $this->assertTrue($cache->containsEntity(Article::class, $articleId));

        // Re-fetch parent so we can remove it
        $category = $this->em->find(Category::class, $category->getId());
        $this->em->remove($category);
        $this->em->flush();

        // The trait evicted the article from cache.
        $this->assertFalse(
            $cache->containsEntity(Article::class, $articleId),
            'With trait: cascade-deleted entity should be evicted from cache'
        );
    }

    // ---------------------------------------------------------------
    //  Scenario 3: Dirty entities (ManyToMany inverse side) not refreshed
    // ---------------------------------------------------------------

    /**
     * WITHOUT the trait: after removing a tag, the article's tag collection
     * in the identity map still contains the removed tag.
     *
     * (PlainArticle/PlainCategory don't have ManyToMany, so we simulate
     *  the "dirty" concept with the OneToMany side: after removing a category,
     *  a PlainArticle that was loaded before the remove still references it.)
     */
    public function testWithoutTrait_StaleCollectionReference()
    {
        $category = new PlainCategory();
        $article  = new PlainArticle();
        $article->setCategory($category);

        $this->em->persist($category);
        $this->em->persist($article);
        $this->em->flush();

        // Load the category's articles collection into memory
        $category->getArticles()->toArray();

        $articleId = $article->getId();

        $this->em->remove($category);
        $this->em->flush();

        // The article object in PHP still has a reference to the deleted category.
        // Without the trait, nobody refreshes or detaches it.
        $this->assertNotNull(
            $article->getCategory(),
            'BUG: article still holds a reference to the deleted category'
        );
    }

    /**
     * WITH the trait: after removing a tag (which declares articles as dirty),
     * the associated articles are refreshed and their collections are up-to-date.
     */
    public function testWithTrait_DirtyEntitiesAreRefreshed()
    {
        $article = new Article();
        $tag1    = new Tag();
        $tag2    = new Tag();
        $article->addTag($tag1);
        $article->addTag($tag2);

        $this->em->persist($article);
        $this->em->persist($tag1);
        $this->em->persist($tag2);
        $this->em->flush();

        $articleId = $article->getId();

        // Remove tag1 — article is declared as a dirty entity by Tag
        $this->em->remove($tag1);
        $this->em->flush();

        // The trait refreshed the article, so its tags collection is up-to-date.
        /** @var Article $refreshed */
        $refreshed = $this->em->find(Article::class, $articleId);
        $this->assertCount(1, $refreshed->getTags(), 'With trait: article should have only 1 tag after removal');
    }
}
