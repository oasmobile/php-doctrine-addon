<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-09
 * Time: 21:34
 */

namespace Oasis\Mlib\Doctrine;

use Doctrine\Common\Persistence\Event\LifecycleEventArgs;

interface CascadeRemovableInterface
{
    /**
     * @return array an array of entities asscociated to the calling entity, which should be detached when calling
     *               entity is removed.
     */
    public function getDirtyEntitiesOnInvalidation();

    /**
     * @return array an array of entities which will also be removed when the calling entity is remvoed
     */
    public function getCascadeRemoveableEntities();

    /**
     * This function has a default implementation in CascadeRemoveTrait, use the trait if you don't have an idea what
     * this is about.
     *
     * @param LifecycleEventArgs $eventArgs
     *
     * @return mixed
     */
    public function onPostRemove(LifecycleEventArgs $eventArgs);

}
