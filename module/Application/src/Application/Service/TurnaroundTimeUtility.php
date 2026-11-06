<?php

declare(strict_types=1);

namespace Application\Service;

use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Expression as WhereExpression;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;

/**
 * Builds the turnaround time (TAT) queries behind the VL and EID TAT reports.
 *
 * Follows vlsm's App\Utilities\TurnaroundTimeUtility, so the dashboard and
 * the LIS measure turnaround time the same way:
 *
 *  - A stage is measured in calendar days with DATEDIFF.
 *  - If a stage's dates are missing, out of order, or more than $skipDays
 *    apart, that stage is NULL for the sample and AVG() leaves it out. The
 *    sample's other stages still count. A missing date never counts as zero.
 *  - Test dates in the future or before collection are not plausible, so
 *    those samples are excluded.
 *
 * There is no registration stage. vlsm 5.3.2 (2025-09-15) dropped
 * sample_registered_at_lab, so Received to Tested is a single stage.
 *
 * Each module keeps its own access rules and passes the resulting lab list in.
 */
final class TurnaroundTimeUtility
{
    /** Result column => [start column, end column] */
    public const STAGES = [
        'Collection_Receive' => ['sample_collection_date', 'sample_received_at_lab_datetime'],
        'Receive_Analysis' => ['sample_received_at_lab_datetime', 'sample_tested_datetime'],
        'Analysis_Authorise' => ['sample_tested_datetime', 'result_approved_datetime'],
        'total' => ['sample_collection_date', 'result_approved_datetime'],
    ];

    public const BY_PROVINCE = 'province';
    public const BY_DISTRICT = 'district';
    public const BY_CLINIC = 'clinic';

    /**
     * The averaged stage columns, keyed by STAGES.
     *
     * @return array<string, Expression>
     */
    public static function stageColumns(int $skipDays, string $alias = 'vl'): array
    {
        $columns = [];
        foreach (self::STAGES as $name => [$from, $to]) {
            $diff = "DATEDIFF($alias.$to, $alias.$from)";
            $inRange = $skipDays > 0 ? "$diff BETWEEN 0 AND $skipDays" : "$diff >= 0";
            $columns[$name] = new Expression("ROUND(AVG(CASE WHEN $inRange THEN $diff END), 2)");
        }
        return $columns;
    }

    /**
     * The TAT query for one report level.
     *
     * Rows carry geo_id and geo_name when grouped by province or district, and
     * facility_id and facility_name when grouped by clinic.
     *
     * @param string     $groupBy   One of the BY_* constants
     * @param string     $startDate First test date to include (Y-m-d)
     * @param string     $endDate   Last test date to include (Y-m-d)
     * @param int[]|null $labIds    Labs to include. null applies no lab filter. An empty array matches nothing.
     */
    public static function select(
        Sql $sql,
        string $table,
        string $groupBy,
        string $startDate,
        string $endDate,
        ?array $labIds,
        int $skipDays
    ): Select {
        $select = $sql->select()
            ->from(['vl' => $table])
            ->columns(self::stageColumns($skipDays));

        if ($groupBy === self::BY_CLINIC) {
            $select->join(['f' => 'facility_details'], 'f.facility_id = vl.facility_id', ['facility_id', 'facility_name'])
                ->group('vl.facility_id');
        } else {
            $geoColumn = $groupBy === self::BY_DISTRICT ? 'facility_district' : 'facility_state';
            $select->join(['f' => 'facility_details'], 'f.facility_id = vl.facility_id', [])
                ->join(['g' => 'geographical_divisions'], "g.geo_id = f.$geoColumn", ['geo_id', 'geo_name'], Select::JOIN_LEFT)
                ->group('g.geo_id');
        }

        // Whole days: the date range picker sends dates without a time.
        $select->where->between(
            'vl.sample_tested_datetime',
            substr($startDate, 0, 10) . ' 00:00:00',
            substr($endDate, 0, 10) . ' 23:59:59'
        );
        $select->where([
            new WhereExpression("vl.sample_collection_date IS NOT NULL AND DATE(vl.sample_collection_date) > '1970-01-01'"),
            new WhereExpression('DATE(vl.sample_tested_datetime) <= CURDATE()'),
            new WhereExpression('DATE(vl.sample_tested_datetime) >= DATE(vl.sample_collection_date)'),
        ]);

        if ($labIds !== null) {
            if ($labIds === []) {
                $select->where(new WhereExpression('1 = 0'));
            } else {
                $select->where->in('vl.lab_id', array_values($labIds));
            }
        }

        return $select;
    }
}
