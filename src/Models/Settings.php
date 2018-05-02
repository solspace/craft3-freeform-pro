<?php

namespace Solspace\FreeformPro\Models;

use craft\base\Model;

class Settings extends Model
{
    /** @var bool */
    public $recaptchaEnabled;

    /** @var string */
    public $recaptchaKey;

    /** @var string */
    public $recaptchaSecret;

    /**
     * @return array
     */
    public function rules(): array
    {
        return [
            [['recaptchaKey', 'recaptchaSecret'], 'required', 'when' => function (Settings $model) {
                return (bool) $model->recaptchaEnabled;
            }],
        ];
    }
}