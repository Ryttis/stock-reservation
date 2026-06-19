<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619123314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique constraint on reservation_item and rename FK indexes to meaningful names';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX unique_reservation_warehouse_product ON reservation_item (reservation_id, warehouse_id, product_id)');
        $this->addSql('ALTER TABLE reservation_item RENAME INDEX idx_922e876b83297e7 TO idx_reservation_item_reservation');
        $this->addSql('ALTER TABLE reservation_item RENAME INDEX idx_922e8765080ecde TO idx_reservation_item_warehouse');
        $this->addSql('ALTER TABLE reservation_item RENAME INDEX idx_922e8764584665a TO idx_reservation_item_product');
        $this->addSql('ALTER TABLE warehouse_stock RENAME INDEX idx_ca572aad5080ecde TO idx_warehouse_stock_warehouse');
        $this->addSql('ALTER TABLE warehouse_stock RENAME INDEX idx_ca572aad4584665a TO idx_warehouse_stock_product');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX unique_reservation_warehouse_product ON reservation_item');
        $this->addSql('ALTER TABLE reservation_item RENAME INDEX idx_reservation_item_reservation TO IDX_922E876B83297E7');
        $this->addSql('ALTER TABLE reservation_item RENAME INDEX idx_reservation_item_warehouse TO IDX_922E8765080ECDE');
        $this->addSql('ALTER TABLE reservation_item RENAME INDEX idx_reservation_item_product TO IDX_922E8764584665A');
        $this->addSql('ALTER TABLE warehouse_stock RENAME INDEX idx_warehouse_stock_warehouse TO IDX_CA572AAD5080ECDE');
        $this->addSql('ALTER TABLE warehouse_stock RENAME INDEX idx_warehouse_stock_product TO IDX_CA572AAD4584665A');
    }
}
