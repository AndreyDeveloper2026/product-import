<?php

declare(strict_types=1);

namespace App\Message;

use App\Service\Import\ProductImportService;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;

final class ImportProductsHandler
{
    public function __construct(
        private readonly ProductImportService $service,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(ImportProductsMessage $message): void
    {
        // Воркер живёт долго: соединение с БД могло быть закрыто сервером по таймауту.
        $connection = $this->em->getConnection();
        try {
            $connection->executeQuery('SELECT 1');
        } catch (Throwable) {
            $connection->close();
        }
        $this->em->clear();

        $this->service->run($message->importJobId);
    }
}
