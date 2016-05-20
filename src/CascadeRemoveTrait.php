<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-09
 * Time: 21:36
 */

namespace Oasis\Mlib\Doctrine;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class CascadeRemoveTrait
 *
 * This trait aims to solve the cache problem when removing entities who have associated entities having onDelete
 * CASCADE property set. Without using this class (and not using cascade={"remove"}), the asscociated entities will be
 * removed from database but still exist in EntityManager and Cache.
 *
 * We strongly discourage the use of cascade remove in relation mapping (e.g. cascade={"remove"}) because of the heavy
 * performance hit it will bring.
 *
 * @example To use this trait, a class must also implement the CascadeRemovableInterface as well as declare the
 *          HasLifecycleCallbacks annotation
 *
 * @package Oasis\Mlib\Doctrine
 */
trait CascadeRemoveTrait
{
    /**
     * @ORM\PostRemove()
     * @param LifecycleEventArgs $eventArgs
     */
    public function onPostRemove(LifecycleEventArgs $eventArgs)
    {
        if (!$this instanceof CascadeRemovableInterface) {
            throw new \LogicException(
                __CLASS__ . " must implement " . CascadeRemovableInterface::class . " to enable CascadeRemoveTrait"
            );
        }
        /** @var CascadeRemovableInterface|CascadeRemoveTrait $this */
        /** @var EntityManager $em */
        $em = $eventArgs->getObjectManager();
        $this->findCascadeDetachableEntities($em, $this, $toRemove);
        foreach ($toRemove as $key => $entity) {
            list(, $id) = unserialize($key);
            $em->detach($entity);
            $em->getCache()->evictEntity(get_class($entity), $id);
        }
    }

    /**
     * Detaches associated entities from EntityManager and Cache. Normally these entities should either be deleted
     * or updated in database in post-remove phase.
     *
     * @param EntityManager             $em
     * @param CascadeRemovableInterface $entity
     * @param array                     $visited visited entities
     */
    private function findCascadeDetachableEntities(EntityManager $em, CascadeRemovableInterface $entity, &$visited = [])
    {
        $id            = $em->getUnitOfWork()->getEntityIdentifier($entity);
        $key           = serialize([get_class($entity), $id]);
        $visited[$key] = $entity;

        $entities = $entity->getDirtyEntitiesOnInvalidation();
        foreach ($entities as $subEntity) {
            //mdebug("Cascade detaching %s when detaching %s", get_class($subEntity), get_class($entity));
            $id  = $em->getUnitOfWork()->getEntityIdentifier($subEntity);
            $key = serialize([get_class($subEntity), $id]);
            if (array_key_exists($key, $visited)) {
                continue;
            }

            if ($subEntity instanceof CascadeRemovableInterface) {
                $this->findCascadeDetachableEntities($em, $subEntity, $visited);
            }
            else {
                $visited[$key] = $subEntity;
            }
        }
    }
}
