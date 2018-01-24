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
use Solspace\Freeform\Library\Composer\Components\AbstractField;
use Solspace\FreeformPro\Bundles\ChartJsBundle;
use Solspace\FreeformPro\FreeformPro;
use Solspace\FreeformPro\Services\WidgetsService;
use yii\db\Exception;

class FieldValuesWidget extends AbstractWidget
{
    /** @var string */
    public $title;

    /** @var int */
    public $formId;

    /** @var string */
    public $fieldId;

    /** @var string */
    public $dateRange;

    /** @var int */
    public $chartHeight;

    /** @var string */
    public $chartType;

    /** @var bool */
    public $showEmpty;

    /**
     * @return string
     */
    public static function displayName(): string
    {
        return Freeform::getInstance()->name . ' ' . FreeformPro::t('Field Values');
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

        if (null === $this->dateRange) {
            $this->dateRange = WidgetsService::RANGE_LAST_30_DAYS;
        }

        if (null === $this->chartHeight) {
            $this->chartHeight = 100;
        }

        if (null === $this->chartType) {
            $this->chartType = WidgetsService::CHART_POLAR_AREA;
        }

        if (null === $this->showEmpty) {
            $this->showEmpty = true;
        }
    }

    /**
     * @inheritDoc
     */
    public function rules()
    {
        return [
            [['formId', 'fieldId'], 'required'],
        ];
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

        $fields    = $this->getFieldService()->getAllFields();
        $fieldList = [];
        foreach ($fields as $field) {
            if (null === $field) {
                continue;
            }

            if (\in_array($field->type, [AbstractField::TYPE_TEXTAREA, AbstractField::TYPE_FILE], true)) {
                continue;
            }

            $fieldList[$field->id] = $field->label;
        }

        return \Craft::$app->view->renderTemplate(
            'freeform-pro/_widgets/radial-charts/settings',
            [
                'settings'           => $this,
                'formOptions'        => $formsOptions,
                'fieldList'          => $fieldList,
                'dateRangeOptions'   => $this->getWidgetsService()->getDateRanges(),
                'fieldValueSettings' => true,
                'chartTypes'         => [
                    WidgetsService::CHART_PIE        => 'Pie',
                    WidgetsService::CHART_DONUT      => 'Donut',
                    WidgetsService::CHART_POLAR_AREA => 'Polar Area',
                ],
            ]
        );
    }

    /**
     * @return array
     */
    private function getChartData(): array
    {
        $widgets = $this->getWidgetsService();

        list($rangeStart, $rangeEnd) = $widgets->getRange($this->dateRange);

        $formId     = $this->formId;
        $fieldId    = $this->fieldId;
        $field      = $this->getFieldService()->getFieldById($fieldId);
        $columnName = Submission::getFieldColumnName($fieldId);

        $showEmpty = $this->showEmpty;

        try {
            $result = (new Query())
                ->select(
                    [
                        "$columnName as val",
                        'COUNT(id) as count',
                    ]
                )
                ->from(Submission::TABLE)
                ->where(['between', 'dateCreated', $rangeStart, $rangeEnd])
                ->andWhere(['formId' => $formId])
                ->groupBy([$columnName])
                ->all();
        } catch (Exception $e) {
            $result = [];
        }

        $cleanResults = [0 => ['val' => null, 'count' => 0]];
        foreach ($result as $item) {
            $value = $item['val'];
            if (!$value || $value === '[]') {
                $cleanResults[0]['count'] += (int) $item['count'];
            } else {
                $cleanResults[] = $item;
            }
        }

        if (!$showEmpty) {
            unset($cleanResults[0]);
        }

        $labels = $data = $backgroundColors = $hoverBackgroundColors = [];
        foreach ($cleanResults as $item) {
            $columnValue = $item['val'];
            if ($columnValue && \in_array(
                    $field->type,
                    [AbstractField::TYPE_CHECKBOX_GROUP, AbstractField::TYPE_EMAIL],
                    true
                )
            ) {
                $columnValue = implode(', ', json_decode($columnValue));
            }

            $count = (int) $item['count'];
            $color = $columnValue ? $widgets->getColor($columnValue) : [5, 148, 209];

            $labels[]                = $columnValue ?: 'Empty';
            $data[]                  = $count;
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
