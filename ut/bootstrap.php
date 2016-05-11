<?php
/**
 * Created by PhpStorm.
 * User: minhao
 * Date: 2016-05-11
 * Time: 15:05
 */
use Oasis\Mlib\Logging\ConsoleHandler;

require_once __DIR__ . "/../vendor/autoload.php";

(new ConsoleHandler())->install();
