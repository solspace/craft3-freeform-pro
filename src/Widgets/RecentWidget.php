<?php
/**
 * Freeform for Craft
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2019, Solspace, Inc.
 * @link          https://solspace.com/craft/freeform
 * @license       https://solspace.com/software/license-agreement
 */

namespace Solspace\FreeformPro\Widgets;

use Solspace\Freeform\Elements\Db\SubmissionQuery;
use Solspace\Freeform\Elements\Submission;
use Solspace\Freeform\Freeform;
use Solspace\FreeformPro\FreeformPro;

class RecentWidget extends AbstractWidget
{
    const DEFAULT_LIMIT = 5;

    /** @var string */
    public $title;

    /** @var array */
    public $formIds;

    /** @var int */
    public $limit;

    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Freeform::getInstance()->name . ' ' . FreeformPro::t('Recent');
    }

    /**
     * @return string
     */
    public static function iconPath(): string
    {
        return __DIR__ . '/../icon-mask.svg';
    }

    /**
     * @inheritDoc
     */
    public function init()
    {
        parent::init();

        if (null === $this->title) {
            $this->title = self::displayName();
        }

        if (null === $this->formIds) {
            $this->formIds = [];
        }

        if (null === $this->limit) {
            $this->limit = self::DEFAULT_LIMIT;
        }
    }

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return [
            [['formIds'], 'required'],
        ];
    }

    /**
     * @return string
     */
    public function getBodyHtml(): string
    {
        $forms      = $this->getFormService()->getAllForms();
        $formIdList = $this->formIds;
        if ($formIdList === '*') {
            $formIdList = array_keys($forms);
        }

        $submissions = Submission::find()
            ->formId($formIdList)
            ->orderBy(['id' => SORT_DESC])
            ->limit((int) $this->limit);

        return \Craft::$app->view->renderTemplate(
            'freeform-pro/_widgets/recent/body',
            [
                'submissions' => $submissions,
                'settings'    => $this,
            ]
        );
    }

    /**
     * @return string
     */
    public function getSettingsHtml(): string
    {
        $forms        = $this->getFormService()->getAllForms();
        $formsOptions = [];
        foreach ($forms as $form) {
            $formsOptions[$form->id] = $form->name;
        }

        return \Craft::$app->view->renderTemplate(
            'freeform-pro/_widgets/recent/settings',
            [
                'settings'         => $this,
                'formOptions'      => $formsOptions,
                'dateRangeOptions' => $this->getWidgetsService()->getDateRanges(),
            ]
        );
    }
}
