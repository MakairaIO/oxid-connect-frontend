<?php

namespace Makaira\OxidConnect\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use OxidEsales\EshopCommunity\Internal\Framework\Database\QueryBuilderFactoryInterface;

class Version20241127114321 extends AbstractMigration
{
    private static ?array $columns = null;
    private function hasColumn(string $table, string $column): bool
    {
        if (null === self::$columns) {
            $result = $this->connection->executeQuery(
                'SELECT TABLE_NAME, COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = :schema',
                ['schema' => $this->connection->getDatabase()]
            );

            self::$columns = [];
            while (false !== ($row = $result->fetchAssociative())) {
                self::$columns[$row['TABLE_NAME']][$row['COLUMN_NAME']] = true;
            }
        }

        return isset(self::$columns[$table][$column]);
    }

    public function up(Schema $schema): void
    {
        if (!$this->hasColumn('oxobject2category', 'OXSHOPID')) {
            $this->addSql('ALTER TABLE oxobject2category ADD OXSHOPID INT(11) NOT NULL DEFAULT 1');
        }

        if (!$this->hasColumn('oxartextends', 'OXTAGS')) {
            $this->addSql(
                "ALTER TABLE oxartextends
                     ADD OXTAGS VARCHAR(255) NOT NULL COMMENT 'Tags (multilanguage)',
                     ADD OXTAGS_1 varchar(255) NOT NULL,
                     ADD OXTAGS_2 varchar(255) NOT NULL,
                     ADD OXTAGS_3 varchar(255) NOT NULL",
            );
        }
    }
}
