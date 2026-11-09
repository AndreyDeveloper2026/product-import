<?php

declare(strict_types=1);

use App\Bootstrap;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Worker;

require __DIR__ . '/../vendor/autoload.php';

$container = Bootstrap::container();

$dispatcher = new EventDispatcher();

/** @var TransportInterface $transport */
$transport = $container->get('messenger.transport.async');
$logger = $container->get(LoggerInterface::class);

$logger->info('Messenger worker started');

$worker = new Worker(['async' => $transport], $container->get(MessageBusInterface::class), $dispatcher, $logger);
$worker->run(['sleep' => 1_000_000]);
