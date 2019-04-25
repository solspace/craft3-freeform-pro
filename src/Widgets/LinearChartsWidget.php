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

use Solspace\Freeform\Freeform;
use Solspace\Freeform\Library\Charts\LinearChartData;
use Solspace\Freeform\Resources\Bundles\ChartJsBundle;
use Solspace\FreeformPro\FreeformPro;
use Solspace\FreeformPro\Services\WidgetsService;

class LinearChartsWidget extends AbstractWidget
{
    /** @var string */
    public $title;

    /** @var array */
    public $formIds;

    /** @var bool */
    public $aggregate;

    /** @var string */
    public $dateRange;

    /** @var int */
    public $chartHeight;

    /** @var string */
    public $chartType;

    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Freeform::getInstance()->name . ' ' . FreeformPro::t('Linear Chart');
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

        if (null === $this->aggregate) {
            $this->aggregate = false;
        }

        if (null === $this->dateRange) {
            $this->dateRange = WidgetsService::RANGE_LAST_30_DAYS;
        }

        if (null === $this->chartHeight) {
            $this->chartHeight = 50;
        }

        if (null === $this->chartType) {
            $this->chartType = WidgetsService::CHART_LINE;
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
        \Craft::$app->view->registerAssetBundle(ChartJsBundle::class);
        $data = $this->getChartData();

        switch ($this->dateRange) {
            case WidgetsService::RANGE_LAST_7_DAYS:
                $incrementSkip = 1;
                break;

            case WidgetsService::RANGE_LAST_30_DAYS:
                $incrementSkip = 3;
                break;

            case WidgetsService::RANGE_LAST_60_DAYS:
                $incrementSkip = 6;
                break;

            case WidgetsService::RANGE_LAST_90_DAYS:
                $incrementSkip = 10;
                break;

            case WidgetsService::RANGE_LAST_24_HOURS:
            default:
                $incrementSkip = 1;
                break;
        }

        return \Craft::$app->view->renderTemplate(
            'freeform-pro/_widgets/linear-charts/body',
            [
                'chartData'     => $data,
                'settings'      => $this,
                'incrementSkip' => $incrementSkip,
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
            'freeform-pro/_widgets/linear-charts/settings',
            [
                'settings'         => $this,
                'formOptions'      => $formsOptions,
                'dateRangeOptions' => FreeformPro::getInstance()->widgets->getDateRanges(),
                'chartTypes'       => [
                    WidgetsService::CHART_LINE => 'Line',
                    WidgetsService::CHART_BAR  => 'Bar',
                ],
            ]
        );
    }

    /**
     * @return LinearChartData
     * @throws \Solspace\Freeform\Library\Exceptions\FreeformException
     */
    private function getChartData(): LinearChartData
    {
        list($rangeStart, $rangeEnd) = $this->getWidgetsService()->getRange($this->dateRange);

        $formIds = $this->formIds;
        if ($formIds === '*') {
            $formIds = array_keys($this->getFormService()->getAllForms());
        }

        $chartData = $this->getChartsService()->getLinearSubmissionChartData(
            $rangeStart,
            $rangeEnd,
            $formIds,
            (bool) $this->aggregate
        );

        $chartData->setChartType($this->chartType);

        return $chartData;
    }
}
