<?php

namespace Solspace\FreeformPro\Services;

use craft\base\Component;
use craft\web\View;
use GuzzleHttp\Client;
use Solspace\Freeform\Events\Fields\ValidateEvent;
use Solspace\Freeform\Events\Forms\FormRenderEvent;
use Solspace\Freeform\Freeform;
use Solspace\FreeformPro\Fields\RecaptchaField;
use Solspace\FreeformPro\FreeformPro;

class RecaptchaService extends Component
{
    const RECAPTCHA_SCRIPT_URL = 'https://www.google.com/recaptcha/api.js';

    /**
     * @param ValidateEvent $event
     */
    public function validateRecaptcha(ValidateEvent $event)
    {
        $field = $event->getField();

        if ($field instanceof RecaptchaField) {
            $response = \Craft::$app->request->post('g-recaptcha-response');
            if (!$response) {
                $field->addError(FreeformPro::t('Please verify that you are not a robot.'));
            } else {
                $secret = Freeform::getInstance()->getSettings()->recaptchaSecret;

                $client = new Client();
                $response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
                    'form_params' => [
                        'secret'   => $secret,
                        'response' => $response,
                        'remoteip' => \Craft::$app->request->getRemoteIP(),
                    ],
                ]);

                $result = json_decode((string) $response->getBody(), true);

                if (!$result['success']) {
                    $field->addError(FreeformPro::t('Please verify that you are not a robot.'));
                }
            }
        }
    }

    /**
     * @param FormRenderEvent $event
     */
    public function addRecaptchaJavascriptToForm(FormRenderEvent $event)
    {
        static $scriptLoaded;

        if (null === $scriptLoaded && $event->getForm()->getLayout()->hasRecaptchaFields()) {
            $scriptJs = file_get_contents(\Yii::getAlias('@freeform-pro') . '/Resources/js/src/form/recaptcha.js');

            $recaptchaUrl = self::RECAPTCHA_SCRIPT_URL . '?render=explicit';
            if (Freeform::getInstance()->settings->isFooterScripts()) {
                \Craft::$app->view->registerJsFile($recaptchaUrl);
                \Craft::$app->view->registerJs($scriptJs, View::POS_END);
            } else {
                $event->appendExternalJsToOutput($recaptchaUrl);
                $event->appendJsToOutput($scriptJs);
            }
        }
    }
}
