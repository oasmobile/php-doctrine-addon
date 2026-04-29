<?php
namespace Oasis\Mlib\Doctrine\Ut;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-11
 * Time: 14:41
 */
class TestEnv
{
    /** @var EntityManager|null */
    private static $entityManager = null;

    public static function getEntityManager()
    {
        if (self::$entityManager instanceof EntityManager && self::$entityManager->isOpen()) {
            return self::$entityManager;
        }

        $isDevMode = true;
        $config    = ORMSetup::createAttributeMetadataConfiguration(
            [__DIR__ . '/Entity'],
            $isDevMode
        );
        // Entity namespace aliases removed (not supported by doctrine/persistence 3.x)
        // Tests use FQCN instead of short aliases like ":Article"
        $cachePool = new ArrayAdapter();
        $regconfig = new RegionsConfiguration();
        $factory   = new DefaultCacheFactory($regconfig, $cachePool);
        $config->setSecondLevelCacheEnabled();
        $config->getSecondLevelCacheConfiguration()->setCacheFactory($factory);

        $connectionInfo = [
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ];
        $connection     = DriverManager::getConnection($connectionInfo);
        $connection->executeStatement('PRAGMA foreign_keys = ON');
        self::$entityManager = new EntityManager($connection, $config);

        return self::$entityManager;
    }

    /**
     * Reset the EntityManager (e.g. between tests that need a fresh DB).
     */
    public static function resetEntityManager()
    {
        if (self::$entityManager) {
            self::$entityManager->close();
            self::$entityManager = null;
        }
    }
}
