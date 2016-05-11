<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-11
 * Time: 15:06
 */
namespace Oasis\Mlib\Doctrine\Ut;

use Doctrine\ORM\Tools\Console\ConsoleRunner;

require_once __DIR__ . '/bootstrap.php';

$entityManager = TestEnv::getEntityManager();

return ConsoleRunner::createHelperSet($entityManager);
