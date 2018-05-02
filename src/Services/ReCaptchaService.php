<?php

namespace Solspace\FreeformPro\Services;

use craft\base\Component;
use GuzzleHttp\Client;
use Solspace\Freeform\Events\Fields\ValidateEvent;
use Solspace\FreeformPro\Fields\ReCaptchaField;
use Solspace\FreeformPro\FreeformPro;

class ReCaptchaService extends Component
{
    public function validateReCaptcha(ValidateEvent $event)
    {
        $field = $event->getField();

        if ($field instanceof ReCaptchaField) {
            $response = \Craft::$app->request->post('g-recaptcha-response');
            if (!$response) {
                $field->addError(FreeformPro::t('Please verify that you are not a robot.'));
            } else {
                $secret = FreeformPro::getInstance()->getSettings()->recaptchaSecret;

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