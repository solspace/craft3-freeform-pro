<?php

namespace Solspace\FreeformPro\Fields;

use Solspace\Freeform\Library\Composer\Components\Fields\TextField;
use Solspace\Freeform\Library\Composer\Components\Validation\Constraints\PhoneConstraint;

class PhoneField extends TextField
{
    /** @var string */
    protected $pattern;

    /** @var bool */
    protected $useJsMask;

    /**
     * Return the field TYPE
     *
     * @return string
     */
    public function getType(): string
    {
        return self::TYPE_PHONE;
    }

    /**
     * @return bool
     */
    public function isUseJsMask(): bool
    {
        return (bool) $this->useJsMask;
    }

    /**
     * @return string|null
     */
    public function getPattern()
    {
        return !empty($this->pattern) ? $this->pattern : null;
    }

    /**
     * @inheritDoc
     */
    public function getConstraints(): array
    {
        $constraints   = parent::getConstraints();
        $constraints[] = new PhoneConstraint(
            $this->translate('Invalid phone number'),
            $this->getPattern()
        );

        return $constraints;
    }

    /**
     * @return string
     */
    public function getInputHtml(): string
    {
        if (!$this->isUseJsMask()) {
            return parent::getInputHtml();
        }

        $this->addInputClass('form-phone-pattern-field');

        $pattern = $this->getPattern();
        $pattern = str_replace('x', '0', $pattern);

        $output  = parent::getInputHtml();
        $output  = str_replace('/>', '', $output);
        $output  .= $this->getAttributeString('data-pattern', $pattern);
        $output  .= ' />';

        return $output;
    }
}
