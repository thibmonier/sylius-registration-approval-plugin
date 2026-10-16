<?php

declare(strict_types=1);

namespace Tmonier\SyliusRegistrationApprovalPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Additive: four columns on the customer table, existing customers stay approved. Schema API (not
 * raw SQL) so that it runs on MySQL, MariaDB and PostgreSQL.
 */
final class Version20261016300000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the approval status and decision to the customers (tmonier/sylius-registration-approval-plugin).';
    }

    public function up(Schema $schema): void
    {
        $customer = $schema->getTable('sylius_customer');
        $customer->addColumn('approval_status', Types::STRING, ['length' => 20, 'default' => 'approved', 'notnull' => true]);
        $customer->addColumn('approval_decided_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $customer->addColumn('approval_decided_by', Types::STRING, ['length' => 255, 'notnull' => false]);
        $customer->addColumn('approval_reason', Types::STRING, ['length' => 500, 'notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $customer = $schema->getTable('sylius_customer');
        foreach (['approval_status', 'approval_decided_at', 'approval_decided_by', 'approval_reason'] as $column) {
            $customer->dropColumn($column);
        }
    }
}
