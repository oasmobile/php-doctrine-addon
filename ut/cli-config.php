<?php
declare(strict_types=1);

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-11
 * Time: 15:06
 */
namespace Oasis\Mlib\Doctrine\Ut;

use Doctrine\ORM\Tools\Console\ConsoleRunner;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

require_once __DIR__ . '/bootstrap.php';

$entityManager = TestEnv::getEntityManager();

ConsoleRunner::run(new SingleManagerProvider($entityManager));
