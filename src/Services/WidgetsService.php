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

namespace Solspace\FreeformPro\Services;

use Carbon\Carbon;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\DateTimeHelper;
use Solspace\Freeform\Elements\Submission;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Library\Exceptions\FreeformException;

class WidgetsService extends Component
{
    const CHART_LINE       = 'line';
    const CHART_BAR        = 'bar';
    const CHART_DONUT      = 'doughnut';
    const CHART_PIE        = 'pie';
    const CHART_POLAR_AREA = 'polarArea';

    const RANGE_LAST_24_HOURS = 'last_24_hours';
    const RANGE_LAST_7_DAYS   = 'last_7_days';
    const RANGE_LAST_30_DAYS  = 'last_30_days';
    const RANGE_LAST_60_DAYS  = 'last_60_days';
    const RANGE_LAST_90_DAYS  = 'last_90_days';

    /** @var array */
    private static $dateRanges = [
        self::RANGE_LAST_24_HOURS => 'Last 24 hours',
        self::RANGE_LAST_7_DAYS   => 'Last 7 days',
        self::RANGE_LAST_30_DAYS  => 'Last 30 days',
        self::RANGE_LAST_60_DAYS  => 'Last 60 days',
        self::RANGE_LAST_90_DAYS  => 'Last 90 days',
    ];

    /**
     * @return array
     */
    public function getDateRanges(): array
    {
        $dateRanges = self::$dateRanges;

        array_walk(
            $dateRanges,
            function (&$value) {
                $value = Freeform::t($value);
            }
        );

        return self::$dateRanges;
    }

    /**
     * @param string $rangeType
     *
     * @return array - [$dateRangeStart, $dateRangeEnd]
     * @throws FreeformException
     */
    public function getRange($rangeType): array
    {
        if (!array_key_exists($rangeType, self::$dateRanges)) {
            throw new FreeformException(sprintf("Range type '%s' not supported", $rangeType));
        }

        $rangeEnd = new Carbon(null, 'UTC');
        $rangeEnd->setTime(0, 0);

        switch ($rangeType) {
            case self::RANGE_LAST_24_HOURS:
                $rangeStart = new Carbon('24 hours ago', 'UTC');
                break;

            case self::RANGE_LAST_7_DAYS:
                $rangeStart = new Carbon('7 days ago', 'UTC');
                break;

            case self::RANGE_LAST_30_DAYS:
                $rangeStart = new Carbon('30 days ago', 'UTC');
                break;

            case self::RANGE_LAST_60_DAYS:
                $rangeStart = new Carbon('60 days ago', 'UTC');
                break;

            case self::RANGE_LAST_90_DAYS:
                $rangeStart = new Carbon('90 days ago', 'UTC');
                break;

            default:
                $rangeStart = new Carbon('-1 month', 'UTC');
                $rangeStart->setTime(0, 0);
                break;
        }

        return [$rangeStart, $rangeEnd];
    }
}
