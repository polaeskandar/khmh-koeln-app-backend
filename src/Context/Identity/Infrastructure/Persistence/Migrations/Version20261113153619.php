<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261113153619 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.role_permission DROP CONSTRAINT fk_ea1fc9c7d60322ac');
        $this->addSql('ALTER TABLE identity.role_permission DROP CONSTRAINT fk_ea1fc9c7fed90cca');
        $this->addSql('ALTER TABLE identity.user_role DROP CONSTRAINT fk_d698b94da76ed395');
        $this->addSql('ALTER TABLE identity.user_role DROP CONSTRAINT fk_d698b94dd60322ac');
        $this->addSql('DROP TABLE identity.permission');
        $this->addSql('DROP TABLE identity.role');
        $this->addSql('DROP TABLE identity.role_permission');
        $this->addSql('DROP TABLE identity.user_role');
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE identity.permission (
                  id UUID NOT NULL,
                  action VARCHAR(6) NOT NULL,
                  resource VARCHAR(20) NOT NULL,
                  PRIMARY KEY (id)
                )
            SQL);
        $this->addSql(<<<'SQL'
                CREATE UNIQUE INDEX permission_resource_action_unique ON identity.permission (resource, action)
            SQL);
        $this->addSql(<<<'SQL'
                CREATE TABLE identity.role (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    name VARCHAR(80) NOT NULL,
                    name_key TEXT NOT NULL,
                    scope VARCHAR(14) DEFAULT 'church' NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX role_name_key_unique ON identity.role (name_key)');
        $this->addSql(<<<'SQL'
                CREATE TABLE identity.role_permission (
                  role_id UUID NOT NULL,
                  permission_id UUID NOT NULL,
                  PRIMARY KEY (role_id, permission_id)
                )
            SQL);
        $this->addSql('CREATE INDEX idx_ea1fc9c7d60322ac ON identity.role_permission (role_id)');
        $this->addSql('CREATE INDEX idx_ea1fc9c7fed90cca ON identity.role_permission (permission_id)');
        $this->addSql(<<<'SQL'
                CREATE TABLE identity.user_role (
                  user_id UUID NOT NULL,
                  role_id UUID NOT NULL,
                  PRIMARY KEY (user_id, role_id)
                )
            SQL);
        $this->addSql('CREATE INDEX idx_d698b94da76ed395 ON identity.user_role (user_id)');
        $this->addSql('CREATE INDEX idx_d698b94dd60322ac ON identity.user_role (role_id)');
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.role ADD CONSTRAINT role_name_check CHECK (btrim(name) <> '')
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.role ADD CONSTRAINT role_scope_check CHECK (scope IN ('church', 'assignedGroups'))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.permission ADD CONSTRAINT permission_action_check
                CHECK (action IN ('create', 'read', 'update', 'delete'))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.permission ADD CONSTRAINT permission_resource_check CHECK (resource IN (
                  'users', 'roles', 'services', 'stages', 'sessions', 'attendance', 'members', 'lessons', 'homework',
                  'parentPortal'
                ))
            SQL);
        $this->addSql(
            'INSERT INTO identity.permission (id, action, resource) SELECT id, action, resource FROM access.permission',
        );
        $this->addSql(<<<'SQL'
                INSERT INTO identity.role (id, name, name_key, scope, created_at, updated_at)
                SELECT id, name, name_key, scope, created_at, updated_at FROM access.role
            SQL);
        $this->addSql(<<<'SQL'
                INSERT INTO identity.role_permission (role_id, permission_id)
                SELECT role_id, permission_id FROM access.role_permission
            SQL);
        $this->addSql(<<<'SQL'
                INSERT INTO identity.user_role (user_id, role_id)
                SELECT user_id, role_id FROM access.user_role WHERE user_id IN (SELECT id FROM identity.app_user)
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.role_permission
                ADD
                  CONSTRAINT fk_ea1fc9c7d60322ac FOREIGN KEY (role_id) REFERENCES identity.role (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.role_permission
                ADD
                  CONSTRAINT fk_ea1fc9c7fed90cca FOREIGN KEY (permission_id) REFERENCES identity.permission (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.user_role
                ADD
                  CONSTRAINT fk_d698b94da76ed395 FOREIGN KEY (user_id) REFERENCES identity.app_user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.user_role
                ADD
                  CONSTRAINT fk_d698b94dd60322ac FOREIGN KEY (role_id) REFERENCES identity.role (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL);
    }
}
