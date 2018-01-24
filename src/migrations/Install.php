<?php

namespace Solspace\FreeformPro\migrations;

use craft\db\Migration;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        foreach ($this->getTableData() as $data) {
            $options               = $data['options'] ?? null;
            $fields                = $data['fields'];
            $fields['dateCreated'] = $this->dateTime()->notNull()->defaultExpression('NOW()');
            $fields['dateUpdated'] = $this
                    ->dateTime()
                    ->notNull()
                    ->defaultExpression('NOW()') . ' ON UPDATE CURRENT_TIMESTAMP';
            $fields['uid']         = $this->char(36)->defaultValue(0);

            $this->createTable($data['table'], $fields, $options);
        }

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
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropForeignKey('export_profiles_formId', 'freeform_export_profiles');
        $this->dropForeignKey('export_settings_userId', 'freeform_export_settings');

        foreach ($this->getTableData() as $data) {
            $this->dropTableIfExists($data['table']);
        }
    }

    /**
     * @return array
     */
    private function getTableData(): array
    {
        return [
            [
                'table'  => 'freeform_export_profiles',
                'fields' => [
                    'id'        => $this->primaryKey(),
                    'formId'    => $this->integer()->notNull(),
                    'name'      => $this->string(255)->notNull()->unique(),
                    'limit'     => $this->integer(),
                    'dateRange' => $this->string(255),
                    'fields'    => $this->text()->notNull(),
                    'filters'   => $this->text(),
                    'statuses'  => $this->text()->notNull(),
                ],
            ],
            [
                'table'  => 'freeform_export_settings',
                'fields' => [
                    'id'      => $this->primaryKey(),
                    'userId'  => $this->integer()->notNull(),
                    'setting' => $this->text(),
                ],
            ],
        ];
    }
}
