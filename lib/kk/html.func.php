<?php

namespace kk\OddsMaster;

use DateTime;

class HtmlFunc
{
    static function hexToRgba($hexColor, $opacityPercentage)
    {
        // Remove the hash if present
        $hexColor = ltrim($hexColor, '#');

        // Ensure the hex color is valid
        if (strlen($hexColor) === 3) {
            // Convert shorthand hex to full-length hex
            $hexColor = $hexColor[0] . $hexColor[0] .
                $hexColor[1] . $hexColor[1] .
                $hexColor[2] . $hexColor[2];
        }

        if (strlen($hexColor) !== 6) {
            throw new InvalidArgumentException('Invalid hex color provided.');
        }

        // Extract RGB values
        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));

        // Convert opacity percentage to a value between 0 and 1
        $alpha = max(0, min(100, $opacityPercentage)) / 100;

        // Return the rgba color string
        return sprintf('rgba(%d, %d, %d, %.2f)', $r, $g, $b, $alpha);
    }

    static function displayGroupedByDateQuery($data, $keyDateFormat = 'Ymd', $newKeyDateFormat = 'd/m/Y')
    {
        krsort($data);

        $ths = [];
        $trs = [];

        /// Find the max of the set 
        $_MAX_REPS = 0;

        // background coloring
        $baseColor = '#ff0000'; // Red color

        foreach ($data as $dateKey => $datarr) {
            $max_of_column = max(array_keys($datarr['occurancies']));
            if ($max_of_column > $_MAX_REPS) {
                $_MAX_REPS = $max_of_column;
            }
        }

        /// Add first column as standard
        $ths[] = '<th class="reps-header"></th>';
        for ($i = 1; $i <= $_MAX_REPS; $i++) {
            $trs[$i][] = '<td>' . $i . '</td>';
        }

        foreach ($data as $dateKey => $datarr) {
            $dateString = $dateKey;
            if ($keyDateFormat !== 'general') {
                $dateString = DateTime::createFromFormat($keyDateFormat, $dateKey);
                $dateString = $dateString->format($newKeyDateFormat);
            }

            $ths[] = "<th>$dateString</th>";

            for ($i = 1; $i <= $_MAX_REPS; $i++) {
                $str = '&nbsp;';

                $rgbaColor = $baseColor;
                if (array_key_exists($i, $datarr['occurancies'])) {
                    $str = $datarr['occurancies'][$i]['percentage'] . '% (' . $datarr['occurancies'][$i]['occurancies'] . ')';

                    $bgOpacity = $datarr['occurancies'][$i]['percentage'];
                    $rgbaColor = HtmlFunc::hexToRgba($baseColor, $bgOpacity);
                } else {
                    $rgbaColor = '#FFFFFF';
                }

                $trs[$i][] = "<td style=\"background-color: $rgbaColor\">$str</td>";
            }
        }

        $trsJoined = [];
        foreach ($trs as $i => $tdArr) {
            $trsJoined[$i] = '<tr>' . implode('', $tdArr) . '</tr>';
        }

        $html = '<table class="monospace table table-condensed text-small dataTable">
        <thead><tr>' . implode('', $ths) . '</tr></thead>
        <tbody>' . implode('', $trsJoined) . '</tbody>
        </table>';

        return $html;
    }
}
