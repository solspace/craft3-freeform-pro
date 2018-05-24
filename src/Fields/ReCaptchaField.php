<?php

namespace Solspace\FreeformPro\Fields;

use Solspace\Freeform\Library\Composer\Components\AbstractField;
use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\InputOnlyInterface;
use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\NoStorageInterface;
use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\SingleValueInterface;
use Solspace\Freeform\Library\Composer\Components\Fields\Traits\SingleValueTrait;
use Solspace\FreeformPro\FreeformPro;

class RecaptchaField extends AbstractField implements NoStorageInterface, SingleValueInterface, InputOnlyInterface
{
    use SingleValueTrait;

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return self::TYPE_RECAPTCHA;
    }

    /**
     * @inheritDoc
     */
    protected function getInputHtml(): string
    {
        $key = FreeformPro::getInstance()->getSettings()->recaptchaKey;

        $output = '<script src="https://www.google.com/recaptcha/api.js"></script>';
        $output .= '<div class="g-recaptcha" data-sitekey="' . ($key ?: 'invalid') . '"></div>';

        return $output;
    }
}
