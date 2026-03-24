<?php
require_once __DIR__ . '/bootstrap.php';

$db = \Novosga\Config\DatabaseConfig::getInstance();
$em = $db->createEntityManager();

return \Doctrine\ORM\Tools\Console\ConsoleRunner::createHelperSet($em);
