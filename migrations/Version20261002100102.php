<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002100102 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE daily_stat (id INT AUTO_INCREMENT NOT NULL, day DATE NOT NULL, visitors INT NOT NULL, page_views INT NOT NULL, registrations INT NOT NULL, comments INT NOT NULL, UNIQUE INDEX UNIQ_64BEE0B4E5A02990 (day), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE visit (id INT AUTO_INCREMENT NOT NULL, day DATE NOT NULL, visitor_hash VARCHAR(64) NOT NULL, page_views INT NOT NULL, UNIQUE INDEX visit_day_visitor (day, visitor_hash), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user ADD created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE daily_stat');
        $this->addSql('DROP TABLE visit');
        $this->addSql('ALTER TABLE user DROP created_at');
    }
}
