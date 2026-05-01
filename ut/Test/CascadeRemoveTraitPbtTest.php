<?php
declare(strict_types=1);

namespace Oasis\Mlib\Doctrine\Ut\Test;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\SchemaTool;
use Eris\TestTrait;
use Oasis\Mlib\Doctrine\Ut\Entity\Article;
use Oasis\Mlib\Doctrine\Ut\Entity\Category;
use Oasis\Mlib\Doctrine\Ut\Entity\Tag;
use Oasis\Mlib\Doctrine\Ut\TestEnv;
use PHPUnit\Framework\TestCase;

use function Eris\Generator\choose;
use function Eris\Generator\constant;
use function Eris\Generator\oneOf;

class CascadeRemoveTraitPbtTest extends TestCase
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

    // ---------------------------------------------------------------
    // Topology builders
    // ---------------------------------------------------------------

    /**
     * Build an entity graph based on the given topology mode.
     *
     * @return array{
     *     target: object,
     *     strong: list<object>,
     *     dirty:  list<object>
     * }
     */
    private function buildTopology(string $mode, int $n, int $m): array
    {
        return match ($mode) {
            'single-parent'    => $this->buildSingleParent($n),
            'parent-with-tags' => $this->buildParentWithTags($n, $m),
            'tag-hub'          => $this->buildTagHub($n, $m),
            'deep-chain'       => $this->buildDeepChain($n, $m),
        };
    }

    /**
     * single-parent: 1 Category → N Articles.
     * Delete target = Category.
     * Strong = Articles (cascade-removed via DB onDelete=CASCADE + trait).
     * Dirty  = none (Category.getDirtyEntitiesOnInvalidation returns []).
     */
    private function buildSingleParent(int $n): array
    {
        $category = new Category();
        $this->em->persist($category);

        $articles = [];
        for ($i = 0; $i < $n; $i++) {
            $article = new Article();
            $article->setCategory($category);
            $this->em->persist($article);
            $articles[] = $article;
        }

        $this->em->flush();

        return [
            'target' => $category,
            'strong' => $articles,
            'dirty'  => [],
        ];
    }

    /**
     * parent-with-tags: 1 Category → N Articles, each Article associated with M Tags.
     * Delete target = Category.
     * Strong = Articles (cascade-removed).
     * Dirty  = Tags (dirty entities of Articles via getDirtyEntitiesOnInvalidation).
     */
    private function buildParentWithTags(int $n, int $m): array
    {
        $category = new Category();
        $this->em->persist($category);

        $tags = [];
        for ($j = 0; $j < $m; $j++) {
            $tag = new Tag();
            $this->em->persist($tag);
            $tags[] = $tag;
        }

        $articles = [];
        for ($i = 0; $i < $n; $i++) {
            $article = new Article();
            $article->setCategory($category);
            foreach ($tags as $tag) {
                $article->addTag($tag);
            }
            $this->em->persist($article);
            $articles[] = $article;
        }

        $this->em->flush();

        return [
            'target' => $category,
            'strong' => $articles,
            'dirty'  => $tags,
        ];
    }

    /**
     * tag-hub: N Articles share M Tags, delete one Tag.
     * Delete target = a Tag.
     * Strong = none (Tag.getCascadeRemoveableEntities returns []).
     * Dirty  = Articles (Tag.getDirtyEntitiesOnInvalidation returns its articles).
     */
    private function buildTagHub(int $n, int $m): array
    {
        $tags = [];
        for ($j = 0; $j < $m; $j++) {
            $tag = new Tag();
            $this->em->persist($tag);
            $tags[] = $tag;
        }

        $articles = [];
        for ($i = 0; $i < $n; $i++) {
            $article = new Article();
            foreach ($tags as $tag) {
                $article->addTag($tag);
            }
            $this->em->persist($article);
            $articles[] = $article;
        }

        $this->em->flush();

        // Delete the first tag
        return [
            'target' => $tags[0],
            'strong' => [],
            'dirty'  => $articles,
        ];
    }

    /**
     * deep-chain: 1 Category → N Articles → M Tags (each Article has all M Tags).
     * Delete target = Category.
     * Strong = Articles (cascade-removed).
     * Dirty  = Tags (dirty entities of Articles).
     */
    private function buildDeepChain(int $n, int $m): array
    {
        $category = new Category();
        $this->em->persist($category);

        $tags = [];
        for ($j = 0; $j < $m; $j++) {
            $tag = new Tag();
            $this->em->persist($tag);
            $tags[] = $tag;
        }

        $articles = [];
        for ($i = 0; $i < $n; $i++) {
            $article = new Article();
            $article->setCategory($category);
            foreach ($tags as $tag) {
                $article->addTag($tag);
            }
            $this->em->persist($article);
            $articles[] = $article;
        }

        $this->em->flush();

        return [
            'target' => $category,
            'strong' => $articles,
            'dirty'  => $tags,
        ];
    }

    // ---------------------------------------------------------------
    // Cache warm-up helper
    // ---------------------------------------------------------------

    /**
     * Warm up the second-level cache by clearing the EM and reloading all entities.
     * Returns the reloaded delete-target so that it can be used for removal.
     *
     * @param object        $target  The entity to delete (will be reloaded)
     * @param list<object>  $strong  Strong-associated entities
     * @param list<object>  $dirty   Dirty-associated entities
     *
     * @return object  The reloaded target entity
     */
    private function warmCacheAndReload(object $target, array $strong, array $dirty): object
    {
        $cache = $this->em->getCache();

        // Collect IDs before clearing
        $targetClass = get_class($target);
        $targetId    = $target->getId();

        $strongRefs = [];
        foreach ($strong as $entity) {
            $strongRefs[] = [get_class($entity), $entity->getId()];
        }

        $dirtyRefs = [];
        foreach ($dirty as $entity) {
            $dirtyRefs[] = [get_class($entity), $entity->getId()];
        }

        // Clear identity map, then reload everything to populate L2 cache
        $this->em->clear();

        foreach ($strongRefs as [$class, $id]) {
            $this->em->find($class, $id);
        }
        foreach ($dirtyRefs as [$class, $id]) {
            $this->em->find($class, $id);
        }

        // Verify cache is warm for strong entities
        foreach ($strongRefs as [$class, $id]) {
            $this->assertTrue(
                $cache->containsEntity($class, $id),
                "Cache should contain $class#$id after warm-up"
            );
        }

        // Reload and return the target
        $reloadedTarget = $this->em->find($targetClass, $targetId);
        $this->assertNotNull($reloadedTarget, "Target entity must be reloadable after warm-up");

        return $reloadedTarget;
    }

    // ---------------------------------------------------------------
    // Property tests
    // ---------------------------------------------------------------

    /**
     * Feature: release-3.0, Property 3: CascadeRemoveTrait 强关联实体清理（identity map + L2 cache）
     *
     * For any randomly generated entity topology (chosen from 4 predefined
     * association patterns), when a root entity implementing
     * CascadeRemovableInterface is removed, all strongly-associated entities
     * (returned by getCascadeRemoveableEntities and their recursive children)
     * must satisfy:
     *   (a) $em->find() returns null (not in identity map and deleted from DB)
     *   (b) $cache->containsEntity() returns false (evicted from L2 cache)
     *
     * Validates: Requirements 11.2, 11.3
     */
    public function testStrongAssociatedEntitiesCleanup(): void
    {
        $this->forAll(
            oneOf(constant('single-parent'), constant('parent-with-tags'), constant('tag-hub'), constant('deep-chain')),
            choose(1, 10),
            choose(1, 10)
        )->then(function (string $mode, int $n, int $m): void {
            $this->resetSchema();

            $topo = $this->buildTopology($mode, $n, $m);

            // Collect strong entity references before any EM manipulation
            $strongRefs = [];
            foreach ($topo['strong'] as $entity) {
                $strongRefs[] = [get_class($entity), $entity->getId()];
            }

            // Skip if no strong entities to verify (e.g. tag-hub mode)
            if (empty($strongRefs)) {
                return;
            }

            // Warm up L2 cache and get a fresh target reference
            $target = $this->warmCacheAndReload($topo['target'], $topo['strong'], $topo['dirty']);

            // Remove the target entity
            $this->em->remove($target);
            $this->em->flush();

            $cache = $this->em->getCache();

            // Verify all strong entities are cleaned up
            foreach ($strongRefs as [$class, $id]) {
                // (a) Not in identity map and deleted from DB
                $found = $this->em->find($class, $id);
                $this->assertNull(
                    $found,
                    "[$mode n=$n m=$m] Strong entity $class#$id must not be found after removal"
                );

                // (b) Evicted from L2 cache
                $this->assertFalse(
                    $cache->containsEntity($class, $id),
                    "[$mode n=$n m=$m] Strong entity $class#$id must be evicted from L2 cache"
                );
            }
        });
    }

    /**
     * Feature: release-3.0, Property 4: CascadeRemoveTrait 弱关联实体刷新正确性
     *
     * For any randomly generated entity topology (chosen from 4 predefined
     * association patterns), when a root entity implementing
     * CascadeRemovableInterface is removed, all weakly-associated entities
     * (returned by getDirtyEntitiesOnInvalidation) must satisfy:
     *   - If the entity was not simultaneously deleted, $em->find() returns
     *     non-null (entity still exists)
     *   - $cache->containsEntity() returns false (cache evicted)
     *
     * Validates: Requirements 11.4
     */
    public function testDirtyEntitiesRefreshCorrectness(): void
    {
        $this->forAll(
            oneOf(constant('single-parent'), constant('parent-with-tags'), constant('tag-hub'), constant('deep-chain')),
            choose(1, 10),
            choose(1, 10)
        )->then(function (string $mode, int $n, int $m): void {
            $this->resetSchema();

            $topo = $this->buildTopology($mode, $n, $m);

            // Collect dirty entity references before any EM manipulation
            $dirtyRefs = [];
            foreach ($topo['dirty'] as $entity) {
                $dirtyRefs[] = [get_class($entity), $entity->getId()];
            }

            // Skip if no dirty entities to verify (e.g. single-parent mode)
            if (empty($dirtyRefs)) {
                return;
            }

            // Warm up L2 cache and get a fresh target reference
            $target = $this->warmCacheAndReload($topo['target'], $topo['strong'], $topo['dirty']);

            // Remove the target entity
            $this->em->remove($target);
            $this->em->flush();

            $cache = $this->em->getCache();

            // Verify all dirty entities are correctly handled
            foreach ($dirtyRefs as [$class, $id]) {
                // Dirty entity should still exist (not cascade-deleted)
                $found = $this->em->find($class, $id);
                $this->assertNotNull(
                    $found,
                    "[$mode n=$n m=$m] Dirty entity $class#$id must still exist after removal"
                );

                // Cache must be evicted
                $this->assertFalse(
                    $cache->containsEntity($class, $id),
                    "[$mode n=$n m=$m] Dirty entity $class#$id must be evicted from L2 cache"
                );
            }
        });
    }
}
