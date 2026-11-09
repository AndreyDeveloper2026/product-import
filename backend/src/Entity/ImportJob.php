<?php

declare(strict_types=1);

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'import_jobs')]
class ImportJob
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(name: 'original_name', type: Types::STRING, length: 255)]
    private string $originalName;

    #[ORM\Column(name: 'file_path', type: Types::STRING, length: 512, nullable: true)]
    private ?string $filePath;

    #[ORM\Column(name: 'total_rows', type: Types::INTEGER)]
    private int $totalRows = 0;

    #[ORM\Column(name: 'processed_rows', type: Types::INTEGER)]
    private int $processedRows = 0;

    #[ORM\Column(name: 'succeeded_rows', type: Types::INTEGER)]
    private int $succeededRows = 0;

    #[ORM\Column(name: 'failed_rows', type: Types::INTEGER)]
    private int $failedRows = 0;

    /** @var list<array{row: int, level: string, message: string}> */
    #[ORM\Column(name: 'report', type: Types::JSON)]
    private array $report = [];

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $finishedAt = null;

    public function __construct(string $originalName, string $filePath)
    {
        $this->originalName = $originalName;
        $this->filePath = $filePath;
        $this->createdAt = new DateTimeImmutable();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function getTotalRows(): int
    {
        return $this->totalRows;
    }

    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    public function getSucceededRows(): int
    {
        return $this->succeededRows;
    }

    public function getFailedRows(): int
    {
        return $this->failedRows;
    }

    /** @return list<array{row: int, level: string, message: string}> */
    public function getReport(): array
    {
        return $this->report;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }
}
