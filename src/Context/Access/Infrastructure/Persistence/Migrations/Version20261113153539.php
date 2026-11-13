<?php

declare(strict_types=1);

namespace App\Context\Access\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261113153539 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE SCHEMA access');
        $this->addSql(<<<'SQL'
                CREATE TABLE access.role (
                  created_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    updated_at TIMESTAMP(0)
                  WITH
                    TIME ZONE NOT NULL,
                    id UUID NOT NULL,
                    scope VARCHAR(14) DEFAULT 'church' NOT NULL,
                    name VARCHAR(80) NOT NULL,
                    name_key TEXT NOT NULL,
                    PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX role_name_key_unique ON access.role (name_key)');
        $this->addSql(<<<'SQL'
                CREATE TABLE access.role_permission (
                  role_id UUID NOT NULL,
                  permission_id UUID NOT NULL,
                  PRIMARY KEY (role_id, permission_id)
                )
            SQL);
        $this->addSql('CREATE INDEX IDX_AB9F8ED5D60322AC ON access.role_permission (role_id)');
        $this->addSql('CREATE INDEX IDX_AB9F8ED5FED90CCA ON access.role_permission (permission_id)');
        $this->addSql(<<<'SQL'
                CREATE TABLE access.user_role (
                  user_id UUID NOT NULL,
                  role_id UUID NOT NULL,
                  PRIMARY KEY (user_id, role_id)
                )
            SQL);
        $this->addSql('CREATE INDEX IDX_65D03DFED60322AC ON access.user_role (role_id)');
        $this->addSql(<<<'SQL'
                CREATE TABLE access.permission (
                  id UUID NOT NULL,
                  action VARCHAR(6) NOT NULL,
                  resource VARCHAR(20) NOT NULL,
                  PRIMARY KEY (id)
                )
            SQL);
        $this->addSql('CREATE UNIQUE INDEX permission_resource_action_unique ON access.permission (resource, action)');
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  access.role_permission
                ADD
                  CONSTRAINT FK_AB9F8ED5D60322AC FOREIGN KEY (role_id) REFERENCES access.role (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  access.role_permission
                ADD
                  CONSTRAINT FK_AB9F8ED5FED90CCA FOREIGN KEY (permission_id) REFERENCES access.permission (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  access.user_role
                ADD
                  CONSTRAINT FK_65D03DFED60322AC FOREIGN KEY (role_id) REFERENCES access.role (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE access.role ADD CONSTRAINT role_name_check CHECK (btrim(name) <> '')
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE access.role ADD CONSTRAINT role_scope_check CHECK (scope IN ('church', 'assignedGroups'))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE access.permission ADD CONSTRAINT permission_action_check
                CHECK (action IN ('create', 'read', 'update', 'delete'))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE access.permission ADD CONSTRAINT permission_resource_check CHECK (resource IN (
                  'users', 'roles', 'services', 'stages', 'sessions', 'attendance', 'members', 'lessons', 'homework',
                  'parentPortal'
                ))
            SQL);
        $this->addSql(<<<'SQL'
                DO $$
                BEGIN
                  IF to_regclass('identity.role') IS NOT NULL THEN
                    INSERT INTO access.permission (id, action, resource)
                    SELECT id, action, resource FROM identity.permission;

                    INSERT INTO access.role (id, name, name_key, scope, created_at, updated_at)
                    SELECT id, name, name_key, scope, created_at, updated_at FROM identity.role;

                    INSERT INTO access.role_permission (role_id, permission_id)
                    SELECT role_id, permission_id FROM identity.role_permission;

                    INSERT INTO access.user_role (user_id, role_id)
                    SELECT user_id, role_id FROM identity.user_role;
                  ELSE
                    INSERT INTO access.permission (id, action, resource) VALUES
                      ('019a3f01-0000-7000-8000-000000000001', 'create', 'users'),
                      ('019a3f01-0000-7000-8000-000000000002', 'read', 'users'),
                      ('019a3f01-0000-7000-8000-000000000003', 'update', 'users'),
                      ('019a3f01-0000-7000-8000-000000000004', 'delete', 'users'),
                      ('019a3f01-0000-7000-8000-000000000005', 'create', 'roles'),
                      ('019a3f01-0000-7000-8000-000000000006', 'read', 'roles'),
                      ('019a3f01-0000-7000-8000-000000000007', 'update', 'roles'),
                      ('019a3f01-0000-7000-8000-000000000008', 'delete', 'roles'),
                      ('019a3f01-0000-7000-8000-000000000009', 'create', 'services'),
                      ('019a3f01-0000-7000-8000-000000000010', 'read', 'services'),
                      ('019a3f01-0000-7000-8000-000000000011', 'update', 'services'),
                      ('019a3f01-0000-7000-8000-000000000012', 'delete', 'services'),
                      ('019a3f01-0000-7000-8000-000000000013', 'create', 'stages'),
                      ('019a3f01-0000-7000-8000-000000000014', 'read', 'stages'),
                      ('019a3f01-0000-7000-8000-000000000015', 'update', 'stages'),
                      ('019a3f01-0000-7000-8000-000000000016', 'delete', 'stages'),
                      ('019a3f01-0000-7000-8000-000000000017', 'create', 'sessions'),
                      ('019a3f01-0000-7000-8000-000000000018', 'read', 'sessions'),
                      ('019a3f01-0000-7000-8000-000000000019', 'update', 'sessions'),
                      ('019a3f01-0000-7000-8000-000000000020', 'delete', 'sessions'),
                      ('019a3f01-0000-7000-8000-000000000021', 'create', 'attendance'),
                      ('019a3f01-0000-7000-8000-000000000022', 'read', 'attendance'),
                      ('019a3f01-0000-7000-8000-000000000023', 'update', 'attendance'),
                      ('019a3f01-0000-7000-8000-000000000024', 'delete', 'attendance'),
                      ('019a3f01-0000-7000-8000-000000000025', 'create', 'members'),
                      ('019a3f01-0000-7000-8000-000000000026', 'read', 'members'),
                      ('019a3f01-0000-7000-8000-000000000027', 'update', 'members'),
                      ('019a3f01-0000-7000-8000-000000000028', 'delete', 'members'),
                      ('019a3f01-0000-7000-8000-000000000029', 'create', 'lessons'),
                      ('019a3f01-0000-7000-8000-000000000030', 'read', 'lessons'),
                      ('019a3f01-0000-7000-8000-000000000031', 'update', 'lessons'),
                      ('019a3f01-0000-7000-8000-000000000032', 'delete', 'lessons'),
                      ('019a3f01-0000-7000-8000-000000000033', 'create', 'homework'),
                      ('019a3f01-0000-7000-8000-000000000034', 'read', 'homework'),
                      ('019a3f01-0000-7000-8000-000000000035', 'update', 'homework'),
                      ('019a3f01-0000-7000-8000-000000000036', 'delete', 'homework'),
                      ('019a3f01-0000-7000-8000-000000000037', 'create', 'parentPortal'),
                      ('019a3f01-0000-7000-8000-000000000038', 'read', 'parentPortal'),
                      ('019a3f01-0000-7000-8000-000000000039', 'update', 'parentPortal'),
                      ('019a3f01-0000-7000-8000-000000000040', 'delete', 'parentPortal');

                    INSERT INTO access.role (id, name, name_key, scope, created_at, updated_at) VALUES
                      ('019a3f00-0000-7000-8000-000000000001', 'Administrator', 'administrator', 'church', '2026-11-01 00:00:00+00', '2026-11-01 00:00:00+00'),
                      ('019a3f00-0000-7000-8000-000000000002', 'Service leader', 'service_leader', 'assignedGroups', '2026-11-01 00:00:00+00', '2026-11-01 00:00:00+00'),
                      ('019a3f00-0000-7000-8000-000000000003', 'Servant', 'servant', 'assignedGroups', '2026-11-01 00:00:00+00', '2026-11-01 00:00:00+00'),
                      ('019a3f00-0000-7000-8000-000000000004', 'Parent', 'parent', 'church', '2026-11-01 00:00:00+00', '2026-11-01 00:00:00+00');

                    INSERT INTO access.role_permission (role_id, permission_id)
                    SELECT r.id, p.id
                    FROM access.role r
                    JOIN access.permission p ON CASE r.name_key
                      WHEN 'administrator' THEN p.resource <> 'parentPortal'
                      WHEN 'parent' THEN p.resource = 'parentPortal' AND p.action = 'read'
                      WHEN 'servant' THEN p.resource IN ('attendance', 'homework', 'lessons', 'members')
                        OR (p.resource = 'services' AND p.action = 'read')
                      WHEN 'service_leader' THEN p.resource IN ('attendance', 'homework', 'lessons', 'members', 'sessions', 'stages')
                        OR (p.resource = 'services' AND p.action IN ('read', 'update'))
                      ELSE false
                    END;
                  END IF;
                END
                $$
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE access.role_permission DROP CONSTRAINT FK_AB9F8ED5D60322AC');
        $this->addSql('ALTER TABLE access.role_permission DROP CONSTRAINT FK_AB9F8ED5FED90CCA');
        $this->addSql('ALTER TABLE access.user_role DROP CONSTRAINT FK_65D03DFED60322AC');
        $this->addSql('DROP TABLE access.role');
        $this->addSql('DROP TABLE access.role_permission');
        $this->addSql('DROP TABLE access.user_role');
        $this->addSql('DROP TABLE access.permission');
        $this->addSql('DROP SCHEMA access');
    }
}
