<?php

namespace Solspace\FreeformPro\Library\Rules;

use Solspace\Commons\Helpers\ComparisonHelper;
use Solspace\Freeform\Library\Composer\Components\AbstractField;
use Solspace\Freeform\Library\Composer\Components\Form;
use Solspace\Freeform\Library\Composer\Components\Properties;
use Solspace\Freeform\Library\Composer\Components\Properties\AbstractProperties;
use Solspace\Freeform\Library\Composer\Components\Properties\FieldProperties;
use Solspace\Freeform\Library\Exceptions\Composer\ComposerException;
use Solspace\Freeform\Library\Translations\TranslatorInterface;
use Solspace\FreeformPro\Rules\BaseRule;
use Solspace\FreeformPro\Rules\GotoRule;

class RuleProperties extends AbstractProperties
{
    const DEFAULT_SHOW      = false;
    const DEFAULT_MATCH_ALL = false;

        /** @var array */
    protected $list;

    /** @var array */
    private $compiledFieldRules;

    /** @var array */
    private $compiledPageRules;

    /** @var Properties */
    private $propertyCollection;

    /**
     * AbstractProperties constructor.
     *
     * @param array               $properties
     * @param TranslatorInterface $translator
     * @param Properties          $propertyCollection
     *
     * @throws ComposerException
     */
    public function __construct(array $properties, TranslatorInterface $translator, Properties $propertyCollection)
    {
        $this->propertyCollection = $propertyCollection;

        parent::__construct($properties, $translator);
    }

    /**
     * @param int    $pageIndex
     * @param string $hash
     *
     * @return null|FieldRule
     */
    public function getFieldRule(int $pageIndex, string $hash)
    {
        $rules = $this->getFieldRules($pageIndex);

        return $rules[$hash] ?? null;
    }

    /**
     * @param int $pageIndex
     *
     * @return bool
     */
    public function hasActiveFieldRules(int $pageIndex): bool
    {
        return \count($this->getFieldRules($pageIndex)) > 0;
    }

    /**
     * @param int $pageIndex
     *
     * @return bool
     */
    public function hasActiveGotoRules(int $pageIndex): bool
    {
        return \count($this->getGotoRules($pageIndex)) > 0;
    }

    /**
     * @param AbstractField $field
     * @param Form          $form
     *
     * @return bool
     */
    public function isHidden(AbstractField $field, Form $form): bool
    {
        static $cache;

        if (null === $cache) {
            $cache = new \SplObjectStorage();
        }

        if (!isset($cache[$form])) {
            $cache[$form] = new \SplObjectStorage();
        }

        if (!isset($cache[$form][$field])) {
            if (!$this->hasActiveFieldRules($field->getPageIndex())) {
                return false;
            }

            $rule = $this->getFieldRule($field->getPageIndex(), $field->getHash());
            if (!$rule) {
                return false;
            }

            $triggersRule = $this->triggersRule($rule, $form);

            $cache[$form][$field] = ($rule->isHidden() && $triggersRule) || ($rule->isShown() && !$triggersRule);
        }

        return $cache[$form][$field];
    }

    /**
     * @param Form $form
     *
     * @return int|null
     */
    public function getPageJumpIndex(Form $form)
    {
        $pageIndex = $form->getCurrentPage()->getIndex();
        $gotoRules = $this->getGotoRules($pageIndex);

        foreach ($gotoRules as $rule) {
            if ($this->triggersRule($rule, $form, true)) {
                return $rule->getTargetPageIndex();
            }
        }

        return null;
    }

    /**
     * Determines if the given criteria trigger a rule, or not
     *
     * @param BaseRule $rule
     * @param Form     $form
     * @param bool     $checkHidden
     *
     * @return bool
     */
    private function triggersRule(BaseRule $rule, Form $form, bool $checkHidden = false): bool
    {
        $criteriaList = $rule->getCriteria();
        $compiledList = [];
        foreach ($criteriaList as $criteria) {
            $criteriaTarget = $form->get($criteria->getHash());
            if (!$criteriaTarget) {
                continue;
            }

            // Bypass the check for hidden fields
            // If the check should be bypassed
            if ($checkHidden && $this->isHidden($criteriaTarget, $form)) {
                $compiledList[] = false;
                continue;
            }

            $value       = strtolower($criteria->getValue());
            $postedValue = $criteriaTarget->getValue();

            if (\is_array($postedValue)) {
                $postedValue  = array_map('strtolower', $postedValue);
                $valueMatches = false;
                foreach ($postedValue as $val) {
                    if (ComparisonHelper::stringMatchesWildcard($value, $val)) {
                        $valueMatches =  true;
                    }
                }
            } else {
                $valueMatches = ComparisonHelper::stringMatchesWildcard($value, strtolower($postedValue));
            }

            $valueMatches = $criteria->isEquals() ? $valueMatches : !$valueMatches;

            $compiledList[] = $valueMatches;
        }

        $triggersRule = $rule->isMatchAll();
        foreach ($compiledList as $itemMatches) {
            if ($rule->isMatchAny() && $itemMatches) {
                $triggersRule = true;
                break;
            }

            if ($rule->isMatchAll() && !$itemMatches) {
                $triggersRule = false;
            }
        }

        return $triggersRule;
    }

    /**
     * @param int $pageIndex
     *
     * @return FieldRule[]
     */
    private function getFieldRules(int $pageIndex): array
    {
        if (null === $this->compiledFieldRules) {
            $propertyCollection = $this->getPropertyCollection();
            $ruleList           = [];

            if ($this->list) {

                foreach ($this->list as $pageHash => $rules) {
                    $ruleList[$pageHash] = [];

                    foreach ($rules['fieldRules'] as $item) {
                        if (!isset($item['hash'], $item['criteria']) || empty($item['criteria'])) {
                            continue;
                        }

                        $ruleList[$pageHash][$item['hash']] = new FieldRule(
                            $item['hash'],
                            $item['show'] ?? self::DEFAULT_SHOW,
                            $item['matchAll'] ?? self::DEFAULT_MATCH_ALL,
                            $item['criteria'] ?? [],
                            function (string $hash) use ($propertyCollection): FieldProperties {
                                return $propertyCollection->getFieldProperties($hash);
                            }
                        );
                    }
                }
            }

            $this->compiledFieldRules = $ruleList;
        }

        return $this->compiledFieldRules[Properties\PageProperties::getKey($pageIndex)] ?? [];
    }

    /**
     * @param int $pageIndex
     *
     * @return GotoRule[]
     */
    private function getGotoRules(int $pageIndex): array
    {
        if (null === $this->compiledPageRules) {
            $propertyCollection = $this->getPropertyCollection();
            $ruleList           = [];

            if ($this->list) {

                foreach ($this->list as $pageHash => $rules) {
                    $ruleList[$pageHash] = [];

                    foreach ($rules['gotoRules'] as $item) {
                        if (!isset($item['targetPageHash'], $item['matchAll']) || empty($item['criteria'])) {
                            continue;
                        }

                        $index = Properties\PageProperties::getIndex($item['targetPageHash']);

                        $ruleList[$pageHash][$index] = new GotoRule(
                            $item['targetPageHash'] ?? '',
                            $item['matchAll'] ?? self::DEFAULT_MATCH_ALL,
                            $item['criteria'] ?? [],
                            function (string $hash) use ($propertyCollection): FieldProperties {
                                return $propertyCollection->getFieldProperties($hash);
                            }
                        );
                    }
                }
            }

            $this->compiledPageRules = $ruleList;
        }

        return $this->compiledPageRules[Properties\PageProperties::getKey($pageIndex)] ?? [];
    }

    /**
     * @inheritDoc
     */
    protected function getPropertyManifest(): array
    {
        return [
            'list' => self::TYPE_ARRAY,
        ];
    }

    /**
     * @return Properties
     */
    private function getPropertyCollection(): Properties
    {
        return $this->propertyCollection;
    }
}
