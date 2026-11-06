<?php

namespace Application\View;

/**
 * Builds the table and chart series for the turnaround-time report views.
 *
 * The VL views under application/time and the EID views that render them
 * share this markup. Each row is one place from TurnaroundTimeUtility::select().
 */
final class TatReport
{
    private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;

    private const STAGES = ['collect_receive', 'receive_analysis', 'analysis_authorise'];

    /**
     * The report table as a JavaScript string literal, for getRow().
     *
     * $headings holds the translated Places, three stage and Total headings.
     * A row carries the "clickable" class only when $clickable is true.
     */
    public static function tableJs(
        array $rows,
        string $idTable,
        int $facilityCategory,
        ?string $labs,
        ?string $daterange,
        bool $clickable,
        array $headings
    ): string {
        $header = '';
        foreach ($headings as $heading) {
            $header .= '<th>' . self::html($heading) . '</th>';
        }

        $body = '';
        foreach (array_values($rows) as $i => $row) {
            $place = self::html($row['facility']);
            $body .= '<tr class="' . ($clickable ? 'clickable' : '') . '" id="' . self::html($row['facility_id'])
                . '" data-labs="' . self::html($labs) . '" data-date-range="' . self::html($daterange)
                . '" data-category="' . $facilityCategory . '" data-place="' . $place . '">'
                . '<td id="td-' . $i . '-0">' . $place . '</td>'
                . '<td id="td-' . $i . '-1">' . self::html($row['collect_receive']) . '</td>'
                . '<td id="td-' . $i . '-2">' . self::html($row['receive_analysis']) . '</td>'
                . '<td id="td-' . $i . '-3">' . self::html($row['analysis_authorise']) . '</td>'
                . '<td id="td-' . $i . '-4">' . self::html($row['total']) . '</td>'
                . '</tr>';
        }

        $html = '<div class="scrolltable">'
            . '<table class="' . self::html($idTable) . ' header"><thead>' . $header . '</thead></table>'
            . '<div class="body scrollbar style-3">'
            . '<table id="' . self::html($idTable) . '"><tbody>' . $body . '</tbody></table>'
            . '</div>'
            . '</div>';

        return json_encode($html, self::JSON_FLAGS);
    }

    /**
     * The three stacked Highcharts series as a JavaScript array literal.
     *
     * $names holds the translated stage names in STAGES order. A missing
     * stage average plots as 0.
     */
    public static function seriesJs(array $rows, array $names): string
    {
        $series = [];
        foreach (self::STAGES as $i => $stage) {
            $data = [];
            foreach ($rows as $row) {
                $data[] = [
                    'y' => (float) ($row[$stage] ?? 0),
                    'id' => (string) ($row['facility_id'] ?? 0),
                ];
            }
            $series[] = ['name' => $names[$i], 'data' => $data];
        }

        return json_encode($series, self::JSON_FLAGS);
    }

    /** A value escaped for HTML text and attribute values. */
    private static function html(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /** Any value as a JavaScript literal that is safe inside a script element. */
    public static function js(mixed $value): string
    {
        return json_encode($value, self::JSON_FLAGS);
    }
}
