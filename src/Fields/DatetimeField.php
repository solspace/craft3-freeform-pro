<?php

namespace Solspace\FreeformPro\Fields;

use Craft\DateTime;
use Solspace\Freeform\Library\Composer\Components\Fields\Interfaces\InitialValueInterface;
use Solspace\Freeform\Library\Composer\Components\Fields\TextField;
use Solspace\Freeform\Library\Composer\Components\Fields\Traits\InitialValueTrait;
use Solspace\Freeform\Library\Composer\Components\Validation\Constraints\DateTimeConstraint;
use Solspace\Freeform\Library\Composer\Components\Validation\Constraints\MaxDateConstraint;
use Solspace\Freeform\Library\Composer\Components\Validation\Constraints\MinDateConstraint;

class DatetimeField extends TextField implements InitialValueInterface
{
    const DATETIME_TYPE_BOTH = 'both';
    const DATETIME_TYPE_DATE = 'date';
    const DATETIME_TYPE_TIME = 'time';

    use InitialValueTrait;

    /** @var string */
    protected $dateTimeType;

    /** @var bool */
    protected $generatePlaceholder;

    /** @var string */
    protected $dateOrder;

    /** @var bool */
    protected $date4DigitYear;

    /** @var bool */
    protected $dateLeadingZero;

    /** @var string */
    protected $dateSeparator;

    /** @var bool */
    protected $clock24h;

    /** @var bool */
    protected $lowercaseAMPM;

    /** @var string */
    protected $clockSeparator;

    /** @var string */
    protected $clockAMPMSeparate;

    /** @var bool */
    protected $useDatepicker;

    /** @var string */
    protected $minDate;

    /** @var string */
    protected $maxDate;

    /**
     * @return string
     */
    public static function getFieldTypeName(): string
    {
        return 'Date & Time';
    }

    /**
     * Return the field TYPE
     *
     * @return string
     */
    public function getType(): string
    {
        return self::TYPE_DATETIME;
    }

    /**
     * @return string
     */
    public function getDateTimeType(): string
    {
        return $this->dateTimeType;
    }

    /**
     * @return bool
     */
    public function isGeneratePlaceholder(): bool
    {
        return $this->generatePlaceholder;
    }

    /**
     * @return string
     */
    public function getDateOrder(): string
    {
        return $this->dateOrder;
    }

    /**
     * @return bool
     */
    public function isDate4DigitYear(): bool
    {
        return $this->date4DigitYear;
    }

    /**
     * @return bool
     */
    public function isDateLeadingZero(): bool
    {
        return $this->dateLeadingZero;
    }

    /**
     * @return string
     */
    public function getDateSeparator(): string
    {
        return $this->dateSeparator;
    }

    /**
     * @return bool
     */
    public function isClock24h(): bool
    {
        return $this->clock24h;
    }

    /**
     * @return bool
     */
    public function isLowercaseAMPM(): bool
    {
        return $this->lowercaseAMPM;
    }

    /**
     * @return string
     */
    public function getClockSeparator(): string
    {
        return $this->clockSeparator;
    }

    /**
     * @return bool
     */
    public function isClockAMPMSeparate(): bool
    {
        return $this->clockAMPMSeparate;
    }

    /**
     * @return bool
     */
    public function isUseDatepicker(): bool
    {
        return $this->useDatepicker;
    }

    /**
     * @return string|null
     */
    public function getMinDate()
    {
        return $this->minDate;
    }

    /**
     * @return string|null
     */
    public function getMaxDate()
    {
        return $this->maxDate;
    }

    /**
     * @param string|null $format
     *
     * @return string|null
     */
    public function getGeneratedMinDate(string $format = null)
    {
        if (!$this->minDate) {
            return null;
        }

        return date($format ?? 'Y-m-d', strtotime($this->minDate));
    }

    /**
     * @param string|null $format
     *
     * @return null|string
     */
    public function getGeneratedMaxDate(string $format = null)
    {
        if (!$this->maxDate) {
            return null;
        }

        return date($format ?? 'Y-m-d', strtotime($this->maxDate));
    }

    /**
     * @return string|null
     */
    public function getPlaceholder()
    {
        if (!$this->isGeneratePlaceholder()) {
            return $this->placeholder;
        }

        return $this->getHumanReadableFormat();
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        $value = $this->value;

        if ($this->getValueOverride()) {
            $value = $this->getValueOverride();
        }

        if (empty($value)) {
            $value = $this->getInitialValue();

            if ($value) {
                try {
                    $date = new \DateTime($value);

                    return $date->format($this->getFormat());
                } catch (\Exception $e) {
                }
            }
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function getConstraints(): array
    {
        $constraints   = parent::getConstraints();
        $constraints[] = new DateTimeConstraint(
            $this->translate(
                '"{value}" does not conform to "{format}" format.',
                [
                    'value'  => $this->getValue(),
                    'format' => $this->getHumanReadableFormat(),
                ]
            ),
            $this->getFormat()
        );
        $constraints[] = new MinDateConstraint(
            $this->translate(
                'Date "{date}" must be after "{minDate}"',
                [
                    'date'    => $this->getValue(),
                    'minDate' => $this->getGeneratedMinDate($this->getDateFormat()),
                ]
            ),
            $this->getGeneratedMinDate()
        );
        $constraints[] = new MaxDateConstraint(
            $this->translate(
                'Date "{date}" must be before "{maxDate}"',
                [
                    'date'    => $this->getValue(),
                    'maxDate' => $this->getGeneratedMaxDate($this->getDateFormat()),
                ]
            ),
            $this->getGeneratedMaxDate()
        );

        return $constraints;
    }

    /**
     * @return string
     */
    protected function getInputHtml(): string
    {
        $attributes = $this->getCustomAttributes();
        $this->addInputClass('form-date-time-field');

        if ($this->isUseDatepicker()) {
            $this->addInputClass('form-datepicker');
            $this->addInputAttribute('autocomplete', 'off');
        }

        $hasTime = \in_array($this->getDateTimeType(), [self::DATETIME_TYPE_BOTH, self::DATETIME_TYPE_TIME], true);
        $hasDate = \in_array($this->getDateTimeType(), [self::DATETIME_TYPE_BOTH, self::DATETIME_TYPE_DATE], true);

        $this->addInputAttribute('class', $attributes->getClass() . ' ' . $this->getInputClassString());

        $locale       = \Craft::$app->locale->id;
        $freeformPath = \Yii::getAlias('@freeform');
        if (!file_exists($freeformPath . "/Resources/js/lib/flatpickr/i10n/$locale.js")) {
            $locale = '';
        }

        return '<input '
            . $this->getInputAttributesString()
            . $this->getAttributeString('name', $this->getHandle())
            . $this->getAttributeString('type', $this->getType())
            . $this->getAttributeString('id', $this->getIdAttribute())
            . $this->getAttributeString('data-datepicker-format', $this->getDatepickerFormat())
            . $this->getAttributeString('data-datepicker-enabletime', $hasTime ?: '')
            . $this->getAttributeString('data-datepicker-enabledate', $hasDate ?: '')
            . $this->getAttributeString('data-datepicker-clock_24h', $this->isClock24h() ?: '')
            . $this->getAttributeString('data-datepicker-locale', $locale)
            . $this->getAttributeString('data-datepicker-min-date', $this->getGeneratedMinDate())
            . $this->getAttributeString('data-datepicker-max-date', $this->getGeneratedMaxDate())
            . $this->getAttributeString(
                'placeholder',
                $this->translate($attributes->getPlaceholder() ?: $this->getPlaceholder())
            )
            . $this->getAttributeString('value', $this->getValue(), false)
            . $this->getRequiredAttribute()
            . $attributes->getInputAttributesAsString()
            . '/>';
    }

    /**
     * @return string
     */
    private function getDatepickerFormat(): string
    {
        $format = $this->getFormat();

        $datepickerFormat = str_replace(
            ['G', 'g', 'a', 'A'],
            ['H', 'h', 'K', 'K'],
            $format
        );

        return $datepickerFormat;
    }

    /**
     * Converts Y/m/d to YYYY/MM/DD, etc
     *
     * @return string
     */
    private function getHumanReadableFormat(): string
    {
        $format = $this->getFormat();

        $humanReadable = str_replace(
            ['Y', 'y', 'n', 'm', 'j', 'd', 'H', 'h', 'G', 'g', 'i', 'A', 'a'],
            ['YYYY', 'YY', 'M', 'MM', 'D', 'DD', 'HH', 'H', 'HH', 'H', 'MM', 'TT', 'TT'],
            $format
        );

        return $humanReadable;
    }

    /**
     * Gets the datetime format based on selected field settings
     *
     * @return string
     */
    private function getFormat(): string
    {
        $showDate = \in_array($this->getDateTimeType(), [self::DATETIME_TYPE_BOTH, self::DATETIME_TYPE_DATE], true);
        $showTime = \in_array($this->getDateTimeType(), [self::DATETIME_TYPE_BOTH, self::DATETIME_TYPE_TIME], true);

        $formatParts = [];
        if ($showDate) {
            $formatParts[] = $this->getDateFormat();
        }

        if ($showTime) {
            $formatParts[] = $this->getTimeFormat();
        }

        return implode(' ', $formatParts);
    }

    /**
     * @return string
     */
    private function getDateFormat(): string
    {
        $month = $this->isDateLeadingZero() ? 'm' : 'n';
        $day   = $this->isDateLeadingZero() ? 'd' : 'j';
        $year  = $this->isDate4DigitYear() ? 'Y' : 'y';

        $first = $second = $third = null;

        switch ($this->getDateOrder()) {
            case 'mdy':
                $first  = $month;
                $second = $day;
                $third  = $year;

                break;

            case 'dmy':
                $first  = $day;
                $second = $month;
                $third  = $year;

                break;

            case 'ymd':
                $first  = $year;
                $second = $month;
                $third  = $day;

                break;
        }

        return sprintf(
            '%s%s%s%s%s',
            $first,
            $this->getDateSeparator(),
            $second,
            $this->getDateSeparator(),
            $third
        );
    }

    /**
     * @return string
     */
    private function getTimeFormat(): string
    {
        $minutes = 'i';

        if ($this->isClock24h()) {
            $hours = 'H';
            $ampm  = '';
        } else {
            $hours = 'g';
            $ampm  = ($this->isClockAMPMSeparate() ? ' ' : '') . ($this->isLowercaseAMPM() ? 'a' : 'A');
        }

        return $hours . $this->getClockSeparator() . $minutes . $ampm;
    }
}
