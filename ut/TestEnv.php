<?php
namespace Oasis\Mlib\Doctrine\Ut;

use Doctrine\Common\Cache\MemcachedCache;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\Cache\DefaultCacheFactory;
use Doctrine\ORM\Cache\RegionsConfiguration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;

/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-11
 * Time: 14:41
 */
class TestEnv
{
    public static function getEntityManager()
    {
        static $entityManager = null;
        if ($entityManager instanceof EntityManager && $entityManager->isOpen()) {
            return $entityManager;
        }

        $memcached = new \Memcached();
        $memcached->addServer(self::getConfig('memcached.host'), self::getConfig('memcached.port'));
        $memcache = new MemcachedCache();
        $memcache->setMemcached($memcached);
        $isDevMode = true;
        $config    = Setup::createAnnotationMetadataConfiguration(
            [__DIR__],
            $isDevMode,
            null,
            $memcache,
            false
        );
        $config->addEntityNamespace("", "Oasis\\Mlib\\Doctrine\\Ut");
        //$config->setSQLLogger(new EchoSQLLogger());

        $regconfig = new RegionsConfiguration();
        $factory   = new DefaultCacheFactory($regconfig, $memcache);
        $config->setSecondLevelCacheEnabled();
        $config->getSecondLevelCacheConfiguration()->setCacheFactory($factory);

        $connectionInfo = self::getConfig('connection');
        $connection     = DriverManager::getConnection($connectionInfo);
        $entityManager  = EntityManager::create($connection, $config);

        return $entityManager;
    }

    public static function getConfig($key)
    {
        static $config = [
            "memcached.host" => "localhost",
            "memcached.port" => 11211,

            "connection" => [
                "driver"   => "pdo_mysql",
                "host"     => "localhost",
                "user"     => "doctrine_addon",
                "password" => "123456",
                "dbname"   => "doctrine_addon",
            ],
        ];

        return $config[$key];
    }
}
