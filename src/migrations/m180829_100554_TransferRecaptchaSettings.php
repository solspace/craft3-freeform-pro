<?php

namespace Solspace\FreeformPro\migrations;

use craft\db\Migration;
use craft\db\Query;

/**
 * m180829_100554_TransferRecaptchaSettings migration.
 */
class m180829_100554_TransferRecaptchaSettings extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        if (version_compare(\Craft::$app->getVersion(), '3.1', '>=')) {
            return true;
        }

        $proSettings = (new Query())
            ->select('settings')
            ->from('{{%plugins}}')
            ->where(['handle' => 'freeform-pro'])
            ->scalar();

        $liteSettings = (new Query())
            ->select('settings')
            ->from('{{%plugins}}')
            ->where(['handle' => 'freeform'])
            ->scalar();

        if ($liteSettings) {
            $liteSettings = json_decode($liteSettings, true);
        } else {
            $liteSettings = [];
        }

        if ($proSettings) {
            $proSettings = json_decode($proSettings, true);
            if (isset($proSettings['recaptchaEnabled'])) {
                $liteSettings['recaptchaEnabled'] = 1;
                $liteSettings['recaptchaKey']     = $proSettings['recaptchaKey'];
                $liteSettings['recaptchaSecret']  = $proSettings['recaptchaSecret'];

                $this->update(
                    '{{%plugins}}',
                    ['settings' => json_encode($liteSettings)],
                    ['handle' => 'freeform']
                );

                $this->update(
                    '{{%plugins}}',
                    ['settings' => null],
                    ['handle' => 'freeform-pro']
                );
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m180829_100554_TransferRecaptchaSettings cannot be reverted.\n";

        return false;
    }
}
