<?php

declare(strict_types=1);

namespace App\Http;

use App\Entity\ImportJob;
use App\Entity\Product;
use DateTimeInterface;

/** Преобразование сущностей в JSON-структуры API. */
final class Presenter
{
    public function __construct(private readonly string $imageBaseUrl)
    {
    }

    /** @return array<string, mixed> */
    public function productListItem(Product $p, ?string $thumbnailPath): array
    {
        return [
            'id' => $p->getId(),
            'externalCode' => $p->getExternalCode(),
            'name' => $p->getName(),
            'price' => (float) $p->getPrice(),
            'discount' => (float) $p->getDiscount(),
            'thumbnailUrl' => $thumbnailPath !== null ? $this->imageUrl($thumbnailPath) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function productDetail(Product $p): array
    {
        $attributes = [];
        foreach ($p->getAttributes() as $a) {
            $attributes[] = ['key' => $a->getKey(), 'value' => $a->getValue()];
        }
        $images = [];
        foreach ($p->getImages() as $i) {
            $images[] = ['url' => $i->getUrl(), 'path' => $i->getPath(), 'publicUrl' => $this->imageUrl($i->getPath())];
        }

        return [
            'id' => $p->getId(),
            'externalCode' => $p->getExternalCode(),
            'name' => $p->getName(),
            'description' => $p->getDescription(),
            'price' => (float) $p->getPrice(),
            'discount' => (float) $p->getDiscount(),
            'attributes' => $attributes,
            'images' => $images,
        ];
    }

    /** @return array<string, mixed> */
    public function importJob(ImportJob $j): array
    {
        $total = $j->getTotalRows();
        $progress = $total > 0 ? (int) floor($j->getProcessedRows() / $total * 100) : ($j->isFinished() ? 100 : 0);

        return [
            'id' => $j->getId(),
            'status' => $j->getStatus(),
            'originalName' => $j->getOriginalName(),
            'totalRows' => $total,
            'processedRows' => $j->getProcessedRows(),
            'succeededRows' => $j->getSucceededRows(),
            'failedRows' => $j->getFailedRows(),
            'progress' => min(100, $progress),
            'report' => $j->getReport(),
            'errorMessage' => $j->getErrorMessage(),
            'createdAt' => $j->getCreatedAt()->format(DateTimeInterface::ATOM),
            'startedAt' => $j->getStartedAt()?->format(DateTimeInterface::ATOM),
            'finishedAt' => $j->getFinishedAt()?->format(DateTimeInterface::ATOM),
        ];
    }

    private function imageUrl(string $path): string
    {
        return rtrim($this->imageBaseUrl, '/') . '/' . ltrim($path, '/');
    }
}
