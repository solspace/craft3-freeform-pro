<?php

namespace Solspace\FreeformPro\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m180507_171702_AddIPToExportProfiles migration.
 */
class m180507_171702_AddIPToExportProfiles extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $exportSettingsTable = '{{%freeform_export_settings}}';
        $data                = (new Query())
            ->select(['id', 'setting'])
            ->from($exportSettingsTable)
            ->all();

        foreach ($data as $item) {
            $settings = json_decode($item['setting'], true);
            $id       = $item['id'];

            foreach ($settings as $formId => $setting) {
                if (array_key_exists('ip', $settings[$formId])) {
                    continue;
                }

                $settings[$formId]['ip'] = [
                    'label'   => 'IP',
                    'checked' => true,
                ];
            }

            $this->update($exportSettingsTable, ['setting' => json_encode($settings)], ['id' => $id]);
        }

        $exportProfilesTable = '{{%freeform_export_profiles}}';
        $data                = (new Query())
            ->select(['id', 'fields'])
            ->from($exportProfilesTable)
            ->all();

        foreach ($data as $item) {
            $fields = json_decode($item['fields'], true);
            $id     = $item['id'];

            if (!array_key_exists('ip', $fields)) {
                $fields['ip'] = [
                    'label'   => 'IP',
                    'checked' => true,
                ];

                $this->update($exportProfilesTable, ['fields' => json_encode($fields)], ['id' => $id]);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        return true;
    }
}
