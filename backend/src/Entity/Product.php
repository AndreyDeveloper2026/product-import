<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\UniqueConstraint(name: 'uniq_products_external_code', columns: ['external_code'])]
#[ORM\Index(name: 'idx_products_name', columns: ['name'])]
#[ORM\Index(name: 'idx_products_price', columns: ['price'])]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'external_code', type: Types::STRING, length: 100)]
    private string $externalCode;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    // DECIMAL: MySQL отдаёт строку, SQLite (в тестах) — float, поэтому тип шире
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string|int|float $price = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 7, scale: 2)]
    private string|int|float $discount = '0.00';

    /** @var Collection<int, ProductAttribute> */
    #[ORM\OneToMany(targetEntity: ProductAttribute::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attributes;

    /** @var Collection<int, ProductImage> */
    #[ORM\OneToMany(targetEntity: ProductImage::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $images;

    public function __construct(string $externalCode)
    {
        $this->externalCode = $externalCode;
        $this->attributes = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function applyData(string $name, ?string $description, string $price, string $discount): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->discount = $discount;
    }

    /**
     * Полная замена доп. атрибутов (orphanRemoval удалит лишние).
     *
     * @param array<string, string> $attributes
     */
    public function replaceAttributes(array $attributes): void
    {
        $this->attributes->clear();
        foreach ($attributes as $key => $value) {
            $this->attributes->add(new ProductAttribute($this, (string) $key, $value));
        }
    }

    /**
     * Приводит набор изображений к переданному (url => path).
     * Существующие по url сохраняются, отсутствующие удаляются, новые добавляются.
     *
     * @param array<string, string> $urlToPath
     *
     * @return list<string> локальные пути удалённых изображений (файлы нужно удалить после commit)
     */
    public function syncImages(array $urlToPath): array
    {
        $removed = [];
        foreach ($this->images->toArray() as $image) {
            if (!array_key_exists($image->getUrl(), $urlToPath)) {
                $removed[] = $image->getPath();
                $this->images->removeElement($image);
            }
        }

        $existing = [];
        foreach ($this->images as $image) {
            $existing[$image->getUrl()] = true;
        }
        foreach ($urlToPath as $url => $path) {
            if (!isset($existing[(string) $url])) {
                $this->images->add(new ProductImage($this, (string) $url, $path));
            }
        }

        return $removed;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalCode(): string
    {
        return $this->externalCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrice(): string
    {
        return number_format((float) $this->price, 2, '.', '');
    }

    public function getDiscount(): string
    {
        return number_format((float) $this->discount, 2, '.', '');
    }

    /** @return Collection<int, ProductAttribute> */
    public function getAttributes(): Collection
    {
        return $this->attributes;
    }

    /** @return Collection<int, ProductImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }
}
