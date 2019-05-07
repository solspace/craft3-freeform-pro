<?php

namespace Solspace\FreeformPro\migrations;

use craft\db\Query;
use Solspace\Commons\Migrations\ForeignKey;
use Solspace\Commons\Migrations\KeepTablesAfterUninstallInterface;
use Solspace\Commons\Migrations\StreamlinedInstallMigration;
use Solspace\Commons\Migrations\Table;

/**
 * Install migration.
 */
class Install extends StreamlinedInstallMigration implements KeepTablesAfterUninstallInterface
{
    /**
     * @return Table[]
     */
    protected function defineTableData(): array
    {
        return [
            (new Table('freeform_export_profiles'))
                ->addField('id', $this->primaryKey())
                ->addField('formId', $this->integer()->notNull())
                ->addField('name', $this->string(255)->notNull()->unique())
                ->addField('limit', $this->integer())
                ->addField('dateRange', $this->string(255))
                ->addField('fields', $this->text()->notNull())
                ->addField('filters', $this->text())
                ->addField('statuses', $this->text()->notNull())
                ->addForeignKey('formId', 'freeform_forms', 'id', ForeignKey::CASCADE),

            (new Table('freeform_export_settings'))
                ->addField('id', $this->primaryKey())
                ->addField('userId', $this->integer()->notNull())
                ->addField('setting', $this->text())
                ->addForeignKey('userId', 'users', 'id', ForeignKey::CASCADE),
        ];
    }

    /**
     * Check if there are any Craft2 reliquaries left that need updating
     *
     * @inheritDoc
     */
    protected function afterInstall(): bool
    {
        // We pass the update for old Freeform Pro's for PgSQL users for now
        // Until a solution for this can be found
        if ($this->db->getDriverName() === 'pgsql') {
            return true;
        }

        try {
            (new Query())->select(['id'])->from('{{%freeform_export_profiles_backup}}')->one();
            $tableExists = true;
        } catch (\Exception $exception) {
            $tableExists = false;
        }

        if (!$tableExists) {
            return true;
        }

        $this->dropForeignKey('freeform_export_profiles_formId_fk', '{{%freeform_export_profiles}}');
        $this->dropForeignKey('freeform_export_settings_userId_fk', '{{%freeform_export_settings}}');

        $this->dropTable('{{%freeform_export_profiles}}');
        $this->dropTable('{{%freeform_export_settings}}');

        // Rename the saved export profiles
        $this->renameTable('{{%freeform_export_profiles_backup}}', '{{%freeform_export_profiles}}');
        $this->renameTable('{{%freeform_export_settings_backup}}', '{{%freeform_export_settings}}');

        return true;
    }
}
