<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Fixtures\ProductFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;

require __DIR__ . '/../vendor/autoload.php';

$container = Bootstrap::container();
$em = $container->get(EntityManagerInterface::class);

$loader = new Loader();
$loader->addFixture(new ProductFixtures());

(new ORMExecutor($em, new ORMPurger($em)))->execute($loader->getFixtures());

echo "Fixtures loaded\n";
