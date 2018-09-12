<?php

namespace Solspace\FreeformPro\Services;

use craft\base\Component;
use GuzzleHttp\Client;
use Solspace\Freeform\Events\Fields\ValidateEvent;
use Solspace\Freeform\Freeform;
use Solspace\FreeformPro\Fields\RecaptchaField;
use Solspace\FreeformPro\FreeformPro;

class RecaptchaService extends Component
{
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
}
