<?php

declare(strict_types=1);

namespace App\Context\Identity\Infrastructure\Persistence\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Override;

final class Version20261101141600 extends AbstractMigration
{
    #[Override]
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
                CREATE TABLE identity.user_role (
                  user_id UUID NOT NULL,
                  role_id UUID NOT NULL,
                  PRIMARY KEY (user_id, role_id)
                )
            SQL);
        $this->addSql('CREATE INDEX IDX_D698B94DA76ED395 ON identity.user_role (user_id)');
        $this->addSql('CREATE INDEX IDX_D698B94DD60322AC ON identity.user_role (role_id)');
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
        $this->addSql('CREATE INDEX IDX_EA1FC9C7D60322AC ON identity.role_permission (role_id)');
        $this->addSql('CREATE INDEX IDX_EA1FC9C7FED90CCA ON identity.role_permission (permission_id)');
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
                ALTER TABLE
                  identity.user_role
                ADD
                  CONSTRAINT FK_D698B94DA76ED395 FOREIGN KEY (user_id) REFERENCES identity.app_user (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.user_role
                ADD
                  CONSTRAINT FK_D698B94DD60322AC FOREIGN KEY (role_id) REFERENCES identity.role (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.role_permission
                ADD
                  CONSTRAINT FK_EA1FC9C7D60322AC FOREIGN KEY (role_id) REFERENCES identity.role (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE
                  identity.role_permission
                ADD
                  CONSTRAINT FK_EA1FC9C7FED90CCA FOREIGN KEY (permission_id) REFERENCES identity.permission (id) ON DELETE CASCADE NOT DEFERRABLE
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.role ADD CONSTRAINT role_name_check CHECK ((btrim((name)::text) <> ''::text))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.permission ADD CONSTRAINT permission_resource_check CHECK (((resource)::text = ANY ((ARRAY['users'::character varying, 'roles'::character varying, 'services'::character varying, 'stages'::character varying, 'sessions'::character varying, 'attendance'::character varying, 'members'::character varying, 'lessons'::character varying, 'homework'::character varying, 'parentPortal'::character varying])::text[])))
            SQL);
        $this->addSql(<<<'SQL'
                ALTER TABLE identity.permission ADD CONSTRAINT permission_action_check CHECK (((action)::text = ANY ((ARRAY['create'::character varying, 'read'::character varying, 'update'::character varying, 'delete'::character varying])::text[])))
            SQL);
    }

    #[Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE identity.user_role DROP CONSTRAINT FK_D698B94DA76ED395');
        $this->addSql('ALTER TABLE identity.user_role DROP CONSTRAINT FK_D698B94DD60322AC');
        $this->addSql('ALTER TABLE identity.role_permission DROP CONSTRAINT FK_EA1FC9C7D60322AC');
        $this->addSql('ALTER TABLE identity.role_permission DROP CONSTRAINT FK_EA1FC9C7FED90CCA');
        $this->addSql('DROP TABLE identity.user_role');
        $this->addSql('DROP TABLE identity.role');
        $this->addSql('DROP TABLE identity.role_permission');
        $this->addSql('DROP TABLE identity.permission');
    }
}
