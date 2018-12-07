<?php

namespace Solspace\FreeformPro\Services;

use craft\base\Component;
use Solspace\Freeform\Events\Forms\FormRenderEvent;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Services\SettingsService;

class ProFormsService extends Component
{
    /**
     * @param FormRenderEvent $event
     */
    public function addDateTimeJavascript(FormRenderEvent $event)
    {
        $freeformPath = \Yii::getAlias('@freeform');
        $form         = $event->getForm();

        if ($form->getLayout()->hasDatepickerEnabledFields()) {
            static $datepickerLoaded;

            if (null === $datepickerLoaded) {
                $locale     = \Craft::$app->locale->id;
                $localePath = $freeformPath . "/Resources/js/lib/flatpickr/i10n/$locale.js";
                if (!file_exists($localePath)) {
                    $localePath = $freeformPath . '/Resources/js/lib/flatpickr/i10n/default.js';
                }

                $flatpickrCss      = file_get_contents($freeformPath . '/Resources/css/form-frontend/fields/datepicker.css');
                $flatpickrJs       = file_get_contents($freeformPath . '/Resources/js/lib/flatpickr/flatpickr.js');
                $flatpickrLocaleJs = file_get_contents($localePath);

                $event->appendCssToOutput($flatpickrCss);
                $event->appendJsToOutput($flatpickrJs);
                $event->appendJsToOutput($flatpickrLocaleJs);

                $datepickerLoaded = true;
            }

            foreach ($form->getLayout()->getDatepickerFields() as $field) {
                $datepickerJs = file_get_contents($freeformPath . '/Resources/js/cp/form-frontend/fields/datepicker.js');
                $event->appendJsToOutput($datepickerJs, ['id' => $field->getIdAttribute()]);
            }
        }
    }

    /**
     * @param FormRenderEvent $event
     */
    public function addPhonePatternJavascript(FormRenderEvent $event)
    {
        $freeformPath = \Yii::getAlias('@freeform');
        $form         = $event->getForm();

        if ($form->getLayout()->hasPhonePatternFields()) {
            static $imaskLoaded;

            if (null === $imaskLoaded) {
                $imaskMainJs = file_get_contents($freeformPath . '/Resources/js/lib/imask/imask.3.4.0.min.js');

                $event->appendJsToOutput($imaskMainJs);

                $imaskLoaded = true;
            }

            foreach ($form->getLayout()->getPhoneFields() as $field) {
                $imaskJs = file_get_contents($freeformPath . '/Resources/js/cp/form-frontend/fields/input-mask.js');
                $event->appendJsToOutput($imaskJs, ['id' => $field->getIdAttribute()]);
            }
        }
    }

    /**
     * @return SettingsService
     */
    private function getSettingsService(): SettingsService
    {
        return Freeform::getInstance()->settings;
    }
}
