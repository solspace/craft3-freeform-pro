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
     * @return array
     */
    private function getChartData(): array
    {
        list($rangeStart, $rangeEnd) = $this->getWidgetsService()->getRange($this->dateRange);
        $diff = date_diff(new \DateTime($rangeStart), new \DateTime($rangeEnd));

        $labels      = $dates = [];
        $dateContext = new \DateTime($rangeStart);
        for ($i = 0; $i <= $diff->days; $i++) {
            $labels[] = $dateContext->format('M j');
            $dates[]  = $dateContext->format('Y-m-d');
            $dateContext->add(new \DateInterval('P1D'));
        }

        $forms = $this->getFormService()->getAllForms();

        $aggregateData = $this->aggregate;
        $formIdList    = $this->formIds;
        if ($formIdList === '*') {
            $formIdList = array_keys($forms);
        }


        $formData = [];
        foreach ($formIdList as $formId) {
            if (null !== $formId && !isset($forms[$formId])) {
                continue;
            }

            $query = (new Query())
                ->select(
                    [
                        'DATE(dateCreated) as dt',
                        'COUNT(id) as count',
                    ]
                )
                ->from(Submission::TABLE)
                ->groupBy(['dt']);

            $query->where(['between', 'dateCreated', $rangeStart, $rangeEnd]);

            $form = null;
            if ($aggregateData) {
                $query->andWhere(['in', 'form', $formIdList]);
            } else {
                $form = $forms[$formId];
                $query->andWhere(['formId' => $formId]);
            }

            $result = $query->all();

            $data = [];
            foreach ($dates as $date) {
                $data[$date] = 0;
            }

            foreach ($result as $item) {
                $data[$item['dt']] = (int) $item['count'];
            }

            if ($form) {
                $color = $this->getWidgetsService()->getColor($form->color);
            } else {
                $color = [5, 148, 209];
            }

            $formData[] = [
                'label'                => $form ? $form->name : 'Submissions',
                'borderColor'          => sprintf('rgba(%s,1)', implode(',', $color)),
                'backgroundColor'      => sprintf('rgba(%s,1)', implode(',', $color)),
                'pointRadius'          => 3,
                'pointBackgroundColor' => sprintf('rgba(%s,1)', implode(',', $color)),
                'lineTension'          => 0.2,
                'fill'                 => false,
                'data'                 => array_values($data),
            ];

            if ($aggregateData) {
                break;
            }
        }

        $chartType = $this->chartType;

        return [
            'type'    => $chartType,
            'data'    => [
                'labels'   => $labels,
                'datasets' => $formData,
            ],
            'options' => [
                'tooltips'   => [
                    'backgroundColor' => 'rgba(250, 250, 250, 0.9)',
                    'titleFontColor'  => '#000',
                    'bodyFontColor'   => '#000',
                    'cornerRadius'    => 4,
                    'xPadding'        => 10,
                    'yPadding'        => 7,
                    'displayColors'   => false,
                ],
                'responsive' => true,
                'legend'     => [
                    'display' => !$this->aggregate,
                    'labels'  => [
                        'padding'       => 20,
                        'usePointStyle' => true,
                    ],
                ],
                'scales'     => [
                    'yAxes' => [
                        [
                            'stacked'     => $chartType === 'bar' ? true : null,
                            'beginAtZero' => true,
                        ],
                    ],
                    'xAxes' => [
                        [
                            'stacked'   => $chartType === 'bar' ? true : null,
                            'gridLines' => [
                                'display' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
