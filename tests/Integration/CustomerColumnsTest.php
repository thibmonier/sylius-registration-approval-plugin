<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusRegistrationApprovalPlugin\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CustomerColumnsTest extends KernelTestCase
{
    public function testTheMigrationAddedTheColumnsAndExistingCustomersStayApproved(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        $table = $connection->createSchemaManager()->introspectTable('sylius_customer');

        self::assertTrue($table->getColumn('approval_status')->getNotnull());
        self::assertSame('approved', $table->getColumn('approval_status')->getDefault());
        foreach (['approval_decided_at', 'approval_decided_by', 'approval_reason'] as $nullable) {
            self::assertFalse($table->getColumn($nullable)->getNotnull(), $nullable);
        }
    }
}
