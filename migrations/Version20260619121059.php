<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260619121059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE customer_order (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, shipped_at DATETIME DEFAULT NULL, cancelled_at DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE order_item (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, customer_order_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_52EA1F09A15A2E17 (customer_order_id), INDEX IDX_52EA1F094584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE product (id INT AUTO_INCREMENT NOT NULL, sku VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_D34A04ADF9038C4 (sku), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, customer_order_id INT NOT NULL, UNIQUE INDEX UNIQ_42C84955A15A2E17 (customer_order_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation_item (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, reservation_id INT NOT NULL, warehouse_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_922E876B83297E7 (reservation_id), INDEX IDX_922E8765080ECDE (warehouse_id), INDEX IDX_922E8764584665A (product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE warehouse (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_ECB38BFC77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE warehouse_stock (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, warehouse_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_CA572AAD5080ECDE (warehouse_id), INDEX IDX_CA572AAD4584665A (product_id), UNIQUE INDEX unique_warehouse_product (warehouse_id, product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F09A15A2E17 FOREIGN KEY (customer_order_id) REFERENCES customer_order (id)');
        $this->addSql('ALTER TABLE order_item ADD CONSTRAINT FK_52EA1F094584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955A15A2E17 FOREIGN KEY (customer_order_id) REFERENCES customer_order (id)');
        $this->addSql('ALTER TABLE reservation_item ADD CONSTRAINT FK_922E876B83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
        $this->addSql('ALTER TABLE reservation_item ADD CONSTRAINT FK_922E8765080ECDE FOREIGN KEY (warehouse_id) REFERENCES warehouse (id)');
        $this->addSql('ALTER TABLE reservation_item ADD CONSTRAINT FK_922E8764584665A FOREIGN KEY (product_id) REFERENCES product (id)');
        $this->addSql('ALTER TABLE warehouse_stock ADD CONSTRAINT FK_CA572AAD5080ECDE FOREIGN KEY (warehouse_id) REFERENCES warehouse (id)');
        $this->addSql('ALTER TABLE warehouse_stock ADD CONSTRAINT FK_CA572AAD4584665A FOREIGN KEY (product_id) REFERENCES product (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F09A15A2E17');
        $this->addSql('ALTER TABLE order_item DROP FOREIGN KEY FK_52EA1F094584665A');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955A15A2E17');
        $this->addSql('ALTER TABLE reservation_item DROP FOREIGN KEY FK_922E876B83297E7');
        $this->addSql('ALTER TABLE reservation_item DROP FOREIGN KEY FK_922E8765080ECDE');
        $this->addSql('ALTER TABLE reservation_item DROP FOREIGN KEY FK_922E8764584665A');
        $this->addSql('ALTER TABLE warehouse_stock DROP FOREIGN KEY FK_CA572AAD5080ECDE');
        $this->addSql('ALTER TABLE warehouse_stock DROP FOREIGN KEY FK_CA572AAD4584665A');
        $this->addSql('DROP TABLE customer_order');
        $this->addSql('DROP TABLE order_item');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE reservation_item');
        $this->addSql('DROP TABLE warehouse');
        $this->addSql('DROP TABLE warehouse_stock');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
