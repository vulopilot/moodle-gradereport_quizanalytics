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
 * Unit tests for gradereport_quizanalytics\local\chart_builder.
 *
 * @package   gradereport_quizanalytics
 * @author    DualCube <admin@dualcube.com>
 * @copyright 2026 DualCube (https://dualcube.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_quizanalytics\local;

/**
 * Unit tests for the chart-data shaping helpers.
 *
 * These are pure functions (no database access), so they are tested directly against
 * hand-computed expected output rather than via generators/fixtures.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(chart_builder::class)]
final class chart_builder_test extends \advanced_testcase {
    /**
     * questionpercategories() should just pass its arguments straight through into the chart
     * data/legend shape, unchanged.
     */
    public function test_questionpercategories(): void {
        $result = chart_builder::questionpercategories(
            ['Algebra', 'Geometry'],
            [4, 6],
            ['#ff0000', '#00ff00']
        );

        $this->assertSame(['Algebra', 'Geometry'], $result['data']['labels']);
        $this->assertSame([4, 6], $result['data']['datasets'][0]['data']);
        $this->assertSame(['#ff0000', '#00ff00'], $result['data']['datasets'][0]['backgroundColor']);
    }

    /**
     * A category whose wrong-answer count is too small relative to the previous (harder) category
     * in the ranking - here, less than 20% of it - is dropped from the chart entirely.
     */
    public function test_hardness_filters_out_categories_far_behind_the_worst(): void {
        $hardness = ['catA' => 90, 'catB' => 70, 'catC' => 5];
        $wrongcounts = ['catA' => 50, 'catB' => 40, 'catC' => 1];
        $categoryname = ['catA' => 'Category A', 'catB' => 'Category B', 'catC' => 'Category C'];

        $result = chart_builder::hardness($hardness, $wrongcounts, $categoryname, 'hardcatalluser');

        // CatC's wrong count (1) is well under 20% of catB's (40 * 0.2 = 8), so it is excluded.
        $this->assertSame(['Category A', 'Category B'], $result['data']['labels']);
        $this->assertSame([90, 70], $result['data']['datasets'][0]['data']);
        $this->assertCount(2, $result['data']['datasets'][0]['backgroundColor']);
    }

    /**
     * A category with zero wrong answers is always excluded, regardless of its hardness
     * percentage, since there is nothing to chart.
     */
    public function test_hardness_excludes_categories_with_no_wrong_answers(): void {
        $hardness = ['catA' => 90, 'catB' => 0];
        $wrongcounts = ['catA' => 10, 'catB' => 0];
        $categoryname = ['catA' => 'Category A', 'catB' => 'Category B'];

        $result = chart_builder::hardness($hardness, $wrongcounts, $categoryname, 'hardcatalluser');

        $this->assertSame(['Category A'], $result['data']['labels']);
        $this->assertSame([90], $result['data']['datasets'][0]['data']);
    }

    /**
     * hardest_questions() applies the same "at least 20% as bad as the previous question" cutoff,
     * and pairs each surviving question with the quiz's total attempt count.
     */
    public function test_hardest_questions_filters_and_pairs_totals(): void {
        $stats = [
            'queshardness' => ['q1' => 90, 'q2' => 60, 'q3' => 5],
            'negativeattemptd' => ['q1' => 18, 'q2' => 10, 'q3' => 0],
            'questionlabels' => ['q1' => 'Q1', 'q2' => 'Q2', 'q3' => 'Q3'],
        ];

        $result = chart_builder::hardest_questions($stats, 20);

        // Q3 has zero wrong/unattempted responses, so it is excluded outright.
        $this->assertSame(['Q1', 'Q2'], $result['data']['labels']);
        $this->assertSame([20, 20], $result['data']['datasets'][0]['data']);
        $this->assertSame([18, 10], $result['data']['datasets'][1]['data']);
    }

    /**
     * question_analysis() should place each response-type array into its own dataset, in a fixed
     * order, without altering the values.
     */
    public function test_question_analysis_maps_each_response_type_to_its_own_dataset(): void {
        $stats = [
            'questionlabels' => ['Q1', 'Q2'],
            'correctresponse' => [5, 3],
            'incorrectresponse' => [2, 4],
            'partialresponse' => [1, 0],
            'userunattempted' => [0, 1],
        ];

        $result = chart_builder::question_analysis($stats);

        $this->assertSame(['Q1', 'Q2'], $result['data']['labels']);
        $this->assertSame([5, 3], $result['data']['datasets'][0]['data']);
        $this->assertSame([2, 4], $result['data']['datasets'][1]['data']);
        $this->assertSame([1, 0], $result['data']['datasets'][2]['data']);
        $this->assertSame([0, 1], $result['data']['datasets'][3]['data']);
    }
}
