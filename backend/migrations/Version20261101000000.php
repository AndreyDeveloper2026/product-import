<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create products, product_attributes, product_images, import_jobs';
    }

    public function up(Schema $schema): void
    {
        $opts = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB';

        $this->addSql(<<<SQL
            CREATE TABLE products (
                id INT AUTO_INCREMENT NOT NULL,
                external_code VARCHAR(100) NOT NULL,
                name VARCHAR(255) NOT NULL,
                description LONGTEXT DEFAULT NULL,
                price NUMERIC(12, 2) NOT NULL,
                discount NUMERIC(7, 2) NOT NULL DEFAULT 0,
                UNIQUE INDEX uniq_products_external_code (external_code),
                INDEX idx_products_name (name),
                INDEX idx_products_price (price),
                PRIMARY KEY (id)
            ) $opts
            SQL);

        $this->addSql(<<<SQL
            CREATE TABLE product_attributes (
                id INT AUTO_INCREMENT NOT NULL,
                product_id INT NOT NULL,
                `key` VARCHAR(255) NOT NULL,
                `value` TEXT NOT NULL,
                INDEX idx_product_attributes_product (product_id),
                PRIMARY KEY (id),
                CONSTRAINT fk_product_attributes_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
            ) $opts
            SQL);

        $this->addSql(<<<SQL
            CREATE TABLE product_images (
                id INT AUTO_INCREMENT NOT NULL,
                product_id INT NOT NULL,
                url VARCHAR(2048) NOT NULL,
                path VARCHAR(255) NOT NULL,
                INDEX idx_product_images_product (product_id),
                PRIMARY KEY (id),
                CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
            ) $opts
            SQL);

        $this->addSql(<<<SQL
            CREATE TABLE import_jobs (
                id INT AUTO_INCREMENT NOT NULL,
                status VARCHAR(20) NOT NULL,
                original_name VARCHAR(255) NOT NULL,
                file_path VARCHAR(512) DEFAULT NULL,
                total_rows INT NOT NULL DEFAULT 0,
                processed_rows INT NOT NULL DEFAULT 0,
                succeeded_rows INT NOT NULL DEFAULT 0,
                failed_rows INT NOT NULL DEFAULT 0,
                report JSON NOT NULL,
                error_message TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                started_at DATETIME DEFAULT NULL,
                finished_at DATETIME DEFAULT NULL,
                INDEX idx_import_jobs_created (created_at),
                PRIMARY KEY (id)
            ) $opts
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE import_jobs');
        $this->addSql('DROP TABLE product_images');
        $this->addSql('DROP TABLE product_attributes');
        $this->addSql('DROP TABLE products');
    }
}
