<?php
/**
 * Freeform for Craft
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2017, Solspace, Inc.
 * @link          https://solspace.com/craft/freeform
 * @license       https://solspace.com/software/license-agreement
 */

namespace Solspace\FreeformPro\Widgets;

use craft\db\Query;
use Solspace\Freeform\Elements\Submission;
use Solspace\Freeform\Freeform;
use Solspace\FreeformPro\Bundles\ChartJsBundle;
use Solspace\FreeformPro\FreeformPro;
use Solspace\FreeformPro\Services\WidgetsService;

class RadialChartsWidget extends AbstractWidget
{
    /** @var string */
    public $title;

    /** @var array */
    public $formIds;

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
        return Freeform::getInstance()->name . ' ' . FreeformPro::t('Radial Chart');
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

        if (null === $this->dateRange) {
            $this->dateRange = WidgetsService::RANGE_LAST_30_DAYS;
        }

        if (null === $this->chartHeight) {
            $this->chartHeight = 100;
        }

        if (null === $this->chartType) {
            $this->chartType = WidgetsService::CHART_DONUT;
        }
    }

    /**
     * @return string
     */
    public function getBodyHtml(): string
    {
        \Craft::$app->view->registerAssetBundle(ChartJsBundle::class);
        $data = $this->getChartData();

        return \Craft::$app->view->renderTemplate(
            'freeform-pro/_widgets/radial-charts/body',
            [
                'chartData' => $data,
                'settings'  => $this,
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
            'freeform-pro/_widgets/radial-charts/settings',
            [
                'settings'         => $this,
                'formOptions'      => $formsOptions,
                'chartTypes'       => [
                    WidgetsService::CHART_PIE        => 'Pie',
                    WidgetsService::CHART_DONUT      => 'Donut',
                    WidgetsService::CHART_POLAR_AREA => 'Polar Area',
                ],
                'dateRangeOptions' => $this->getWidgetsService()->getDateRanges(),
            ]
        );
    }

    /**
     * @return array
     */
    private function getChartData(): array
    {
        list($rangeStart, $rangeEnd) = $this->getWidgetsService()->getRange($this->dateRange);

        $forms = $this->getFormService()->getAllForms();

        $formIdList = $this->formIds;
        if ($formIdList === '*') {
            $formIdList = array_keys($forms);
        }


        $result = (new Query())
            ->select(
                [
                    'formId',
                    'COUNT(id) as count',
                ]
            )
            ->from(Submission::TABLE)
            ->where(['between', 'dateCreated', $rangeStart, $rangeEnd])
            ->andWhere(['IN', 'formId', $formIdList])
            ->groupBy(['formId'])
            ->all();

        $labels = $data = $backgroundColors = $hoverBackgroundColors = $formsWithResults = [];
        foreach ($result as $item) {
            $formId             = $item['formId'];
            $formsWithResults[] = $formId;

            $count = (int) $item['count'];
            $color = $this->getWidgetsService()->getColor($forms[$formId]->color);

            $labels[]                = $forms[$formId]->name;
            $data[]                  = $count;
            $backgroundColors[]      = sprintf('rgba(%s,0.8)', implode(',', $color));
            $hoverBackgroundColors[] = sprintf('rgba(%s,1)', implode(',', $color));
        }

        foreach ($formIdList as $formId) {
            if (\in_array($formId, $formsWithResults, false)) {
                continue;
            }

            $color = $this->getWidgetsService()->getColor($forms[$formId]->color);

            $labels[]                = $forms[$formId]->name;
            $data[]                  = 0;
            $backgroundColors[]      = sprintf('rgba(%s,0.8)', implode(',', $color));
            $hoverBackgroundColors[] = sprintf('rgba(%s,1)', implode(',', $color));
        }

        return [
            'type'    => $this->chartType,
            'data'    => [
                'labels'   => $labels,
                'datasets' => [
                    [
                        'data'                 => $data,
                        'backgroundColor'      => $backgroundColors,
                        'hoverBackgroundColor' => $hoverBackgroundColors,
                    ],
                ],
            ],
            'options' => [
                'tooltips'   => [
                    'backgroundColor' => 'rgba(250, 250, 250, 0.9)',
                    'titleFontColor'  => '#000',
                    'bodyFontColor'   => '#000',
                    'cornerRadius'    => 3,
                    'xPadding'        => 10,
                    'yPadding'        => 7,
                    'displayColors'   => false,
                ],
                'responsive' => true,
                'legend'     => [
                    'labels' => [
                        'padding'       => 15,
                        'usePointStyle' => true,
                    ],
                ],
            ],
        ];
    }
}
