<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Chart-data shaping helpers for the quizanalytics gradebook report.
 *
 * @package   gradereport_quizanalytics
 * @author    DualCube <admin@dualcube.com>
 * @copyright 2026 DualCube (https://dualcube.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_quizanalytics\local;

/**
 * Shapes already-computed analytics numbers into the data/opt pairs the report's charts expect.
 *
 * Kept separate from \gradereport_quizanalytics\external\get_analytics, which owns fetching and
 * computing the underlying numbers, so neither class ends up doing both jobs at once.
 */
class chart_builder {
    /**
     * Returns a short random hex colour component, e.g. "3f".
     *
     * @return string
     */
    private static function random_color_part() {
        return str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Returns a random hex colour, e.g. "3fa27b".
     *
     * @return string
     */
    private static function random_color() {
        return self::random_color_part() . self::random_color_part() . self::random_color_part();
    }

    /**
     * Builds the "questions per category" pie chart.
     *
     * @param array $categoryname Category display name per category.
     * @param array $chartdata Question count per category.
     * @param array $randomcolor Slice colour per category.
     * @return array 'data'/'opt' pair.
     */
    public static function questionpercategories($categoryname, $chartdata, $randomcolor): array {
        return [
            'data' => ['labels' => $categoryname, 'datasets' => [[
                'label' => get_string('questionspercategory', 'gradereport_quizanalytics'),
                'backgroundColor' => $randomcolor, 'data' => $chartdata,
            ]]],
            'opt' => ['legend' => [
                'display' => false,
                'position' => 'bottom', 'labels' => ['boxWidth' => 13],
            ], 'title' => [
                'display' => true,
                'position' => 'bottom', 'text' => get_string('questionspercategory', 'gradereport_quizanalytics'),
            ]],
        ];
    }

    /**
     * Builds a "top 10 hardest categories" bar chart: the categories with the highest wrong-answer
     * rate, filtered down to only those at least 20% as bad as the single worst category. Used for
     * both the "all users" and "logged-in user" hardness charts, which share this exact algorithm.
     *
     * @param array $hardness Hardness percentage per category (same order as $categoryname).
     * @param array $wrongcounts Wrong-answer count per category (same order as $categoryname).
     * @param array $categoryname Category display name per category.
     * @param string $titlestringkey Language string key for the chart title.
     * @return array 'data'/'opt' pair.
     */
    public static function hardness($hardness, $wrongcounts, $categoryname, $titlestringkey): array {
        arsort($hardness);
        $maxhardnesskeys = array_keys($hardness, max($hardness));
        $previous = reset($maxhardnesskeys);

        $count = 0;
        $randomcolor = $chartdata = $chartlabels = [];
        foreach ($hardness as $key => $val) {
            if ($wrongcounts[$key] > 0 && $wrongcounts[$key] >= (($wrongcounts[$previous] * 20) / 100) && $count < 10) {
                $chartdata[] = $val;
                $chartlabels[] = $categoryname[$key];
                $randomcolor[] = "#" . self::random_color();
                $count++;
            }
            $previous = $key;
        }
        return [
            'data' => [
                'labels' => $chartlabels, 'datasets' => [
                    [
                        'label' => get_string('hardness', 'gradereport_quizanalytics'),
                        'backgroundColor' => $randomcolor,
                        'data' => $chartdata,
                    ],
                ],
            ],
            'opt' => ['legend' => [
                'display' => false,
                'position' => 'bottom',
            ], 'title' => [
                'display' => false,
                'position' => 'bottom', 'text' => get_string($titlestringkey, 'gradereport_quizanalytics'),
            ]],
        ];
    }

    /**
     * Builds the "hardest questions" bar chart: the top ten questions with the highest
     * wrong-or-unattempted rate, filtered down to only those at least 20% as bad as the worst one.
     *
     * @param array $stats From get_analytics::compute_question_response_stats().
     * @param int $totalattemptscount Count of all finished, graded attempts at this quiz.
     * @return array 'data'/'opt' pair.
     */
    public static function hardest_questions($stats, $totalattemptscount): array {
        $queshardness = $stats['queshardness'];
        $negativeattemptd = $stats['negativeattemptd'];
        $questionlabels = $stats['questionlabels'];

        arsort($queshardness);
        $maxhardness = array_keys($queshardness, max($queshardness));
        $previous = reset($maxhardness);

        $count = 0;
        $labels = $totals = $negatives = [];
        foreach (array_keys($queshardness) as $key) {
            $isbadenough = $negativeattemptd[$key] >= (($negativeattemptd[$previous] * 20) / 100);
            if ($negativeattemptd[$key] > 0 && $isbadenough && $count < 10) {
                $labels[] = $questionlabels[$key];
                $totals[] = $totalattemptscount;
                $negatives[] = $negativeattemptd[$key];
                $count++;
            }
            $previous = $key;
        }

        return [
            'data' => ['labels' => $labels, 'datasets' => [
                [
                    'label' => get_string('totalquizattempt', 'gradereport_quizanalytics'),
                    'backgroundColor' => "#8e5ea2", 'data' => $totals,
                ],
                [
                    'label' => get_string('wrongandunattemptd', 'gradereport_quizanalytics'),
                    'backgroundColor' => "#EB2838", 'data' => $negatives,
                ],
            ]],
            'opt' => [
                'title' => [
                    'display' => false,
                    'text' => get_string('hardestquestionschart', 'gradereport_quizanalytics'),
                ],
                'legend' => ['display' => true, 'position' => 'bottom', 'labels' => ['boxWidth' => 13]],
                'barPercentage' => 1.0,
                'categoryPercentage' => 1.0,
            ],
        ];
    }

    /**
     * Builds the "question analysis" line chart: correct / incorrect / partial / unattempted
     * counts across all fixed questions in the quiz.
     *
     * @param array $stats From get_analytics::compute_question_response_stats().
     * @return array 'data'/'opt' pair.
     */
    public static function question_analysis($stats): array {
        return [
            'data' => ['labels' => $stats['questionlabels'], 'datasets' => [
                [
                    'data' => $stats['correctresponse'], 'borderColor' => "#3e95cd", 'fill' => false,
                    'label' => get_string('correct', 'gradereport_quizanalytics'),
                ],
                [
                    'data' => $stats['incorrectresponse'], 'borderColor' => "#8e5ea2", 'fill' => false,
                    'label' => get_string('incorrect', 'gradereport_quizanalytics'),
                ],
                [
                    'data' => $stats['partialresponse'], 'borderColor' => "#3cba9f",
                    'fill' => false, 'label' => get_string('partialcorrect', 'gradereport_quizanalytics'),
                ],
                [
                    'data' => $stats['userunattempted'], 'borderColor' => "#c45850", 'fill' => false,
                    'label' => get_string('unattempted', 'gradereport_quizanalytics'),
                ],
            ]],
            'opt' => [
                'title' => ['display' => false],
                'legend' => ['display' => true, 'position' => 'bottom', 'labels' => ['boxWidth' => 13]],
            ],
        ];
    }
}
