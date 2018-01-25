<?php

namespace Solspace\FreeformPro\migrations;

use craft\db\Migration;

class m180125_124339_UpdateForeignKeyNamesForPro extends Migration
{
    /**
     * @return bool|void
     */
    public function safeUp()
    {
        try {
            $this->dropForeignKey('export_profiles_formId', 'freeform_export_profiles');
            $this->dropForeignKey('export_settings_userId', 'freeform_export_settings');

            $this->addForeignKey(
                'freeform_export_profiles_formId_fk',
                'freeform_export_profiles',
                'formId',
                'freeform_forms',
                'id',
                'CASCADE'
            );
            $this->addForeignKey(
                'freeform_export_settings_userId_fk',
                'freeform_export_settings',
                'userId',
                'users',
                'id',
                'CASCADE'
            );
        } catch (\Exception $e) {
        }
    }

    /**
     * @return bool|void
     */
    public function safeDown()
    {
        try {
            $this->dropForeignKey('freeform_export_profiles_formId_fk', 'freeform_export_profiles');
            $this->dropForeignKey('freeform_export_settings_userId_fk', 'freeform_export_settings');

            $this->addForeignKey(
                'export_profiles_formId',
                'freeform_export_profiles',
                'formId',
                'freeform_forms',
                'id',
                'CASCADE'
            );
            $this->addForeignKey(
                'export_settings_userId',
                'freeform_export_settings',
                'userId',
                'users',
                'id',
                'CASCADE'
            );
        } catch (\Exception $e) {
        }
    }
}
