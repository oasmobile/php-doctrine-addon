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
    /** @var  array */
    private $removedEntities;
    /** @var  array */
    private $dirtyEntities;
    
    /**
     * @ORM\PreRemove()
     * @param LifecycleEventArgs $eventArgs
     */
    public function onPreRemove(LifecycleEventArgs $eventArgs)
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
        $this->removedEntities = $toRemove;
        $dirtyEntities         = $this->getDirtyEntitiesOnInvalidation();
        foreach ($this->removedEntities as $removedEntity) {
            if ($removedEntity instanceof CascadeRemovableInterface) {
                $dirtyEntities = array_merge(
                    $dirtyEntities,
                    $removedEntity->getDirtyEntitiesOnInvalidation()
                );
            }
        }
        $this->dirtyEntities = [];
        foreach ($dirtyEntities as $dirtyEntity) {
            $id  = $em->getUnitOfWork()->getEntityIdentifier($dirtyEntity);
            $key = serialize([get_class($dirtyEntity), $id]);
            if (array_key_exists($key, $this->removedEntities)) {
                continue;
            }
            $this->dirtyEntities[$key] = $dirtyEntity;
        }
    }
    
    /**
     * @ORM\PostRemove()
     * @param LifecycleEventArgs $eventArgs
     */
    public function onPostRemove(LifecycleEventArgs $eventArgs)
    {
        /** @var EntityManager $em */
        $em = $eventArgs->getObjectManager();
        foreach ($this->removedEntities as $key => $entity) {
            list(, $id) = unserialize($key);
            $em->detach($entity);
            $em->getCache()->evictEntity(get_class($entity), $id);
        }
        foreach ($this->dirtyEntities as $key => $entity) {
            if ($em->getUnitOfWork()->isScheduledForDelete($entity)
                || !$em->getUnitOfWork()->isInIdentityMap($entity)
            ) {
                //var_dump(get_class($entity));
                continue;
            }
            list(, $id) = unserialize($key);
            $em->getCache()->evictEntity(get_class($entity), $id);
            $em->refresh($entity);
        }
    }
    
    /**
     * Detaches associated entities from EntityManager and Cache. Normally these entities should either be deleted
     * or updated in database in post-remove phase.
     *
     * @param EntityManager             $em
     * @param CascadeRemovableInterface $entity
     * @param array                     $visited visited entities
     * @param int                       $depth
     */
    private function findCascadeDetachableEntities(EntityManager $em,
                                                   CascadeRemovableInterface $entity,
                                                   &$visited = [],
                                                   $depth = 0)
    {
        $id            = $em->getUnitOfWork()->getEntityIdentifier($entity);
        $key           = serialize([get_class($entity), $id]);
        $visited[$key] = $entity;
        
        $entities = $entity->getCascadeRemoveableEntities();
        foreach ($entities as $subEntity) {
            $id  = $em->getUnitOfWork()->getEntityIdentifier($subEntity);
            $key = serialize([get_class($subEntity), $id]);
            if (array_key_exists($key, $visited)) {
                //mdebug(
                //    "%sSkipping %s %d when detaching %s %d",
                //    str_repeat(' ', $depth * 4),
                //    get_class($subEntity),
                //    $subEntity->getId(),
                //    get_class($entity),
                //    $entity->getId()
                //);
                continue;
            }
            //mdebug(
            //    "%sCascade removing %s %d when removing %s %d",
            //    str_repeat(' ', $depth * 4),
            //    get_class($subEntity),
            //    $subEntity->getId(),
            //    get_class($entity),
            //    $entity->getId()
            //);
            
            if ($subEntity instanceof CascadeRemovableInterface) {
                $this->findCascadeDetachableEntities($em, $subEntity, $visited, $depth + 1);
            }
            else {
                $visited[$key] = $subEntity;
            }
        }
    }
}
