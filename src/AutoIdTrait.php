<?php
declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-11
 * Time: 15:00
 */

namespace Oasis\Mlib\Doctrine;

use Doctrine\ORM\Mapping as ORM;

trait AutoIdTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    protected $id;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
