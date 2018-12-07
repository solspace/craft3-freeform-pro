<?php

namespace Solspace\FreeformPro\Fields;

use Solspace\Freeform\Freeform;
use Solspace\Freeform\Library\Composer\Components\AbstractField;
use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\InputOnlyInterface;
use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\NoStorageInterface;
use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\RecaptchaInterface;
use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\SingleValueInterface;
use Solspace\Freeform\Library\Composer\Components\Fields\Traits\SingleValueTrait;

class RecaptchaField extends AbstractField implements NoStorageInterface, SingleValueInterface, InputOnlyInterface, RecaptchaInterface
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
    public function getHandle()
    {
        return 'grecaptcha_' . $this->getHash();
    }

    /**
     * @inheritDoc
     */
    protected function getInputHtml(): string
    {
        $key = Freeform::getInstance()->getSettings()->recaptchaKey;

        $output = '<div class="g-recaptcha" data-sitekey="' . ($key ?: 'invalid') . '"></div>';
        $output .= '<input type="hidden" name="'
            . $this->getHandle()
            . $this->getInputAttributesString()
            . '" />';

        return $output;
    }
}
