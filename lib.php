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
 * Library functions for the quizanalytics gradebook report.
 *
 * @package   gradereport_quizanalytics
 * @author    DualCube <admin@dualcube.com>
 * @copyright Dualcube (https://dualcube.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Embeds a compact "Quiz Analytics" panel on the quiz attempt review page, if the site admin has
 * enabled the "Show on the quiz attempt review page" setting and the viewer is allowed to see it.
 *
 * @return string HTML to inject before the page footer, or an empty string.
 */
function gradereport_quizanalytics_before_footer() {
    global $CFG, $PAGE, $DB, $USER;

    if (empty($CFG->gradereport_quizanalytics_showonreviewpage)) {
        return '';
    }
    if ($PAGE->pagetype !== 'mod-quiz-review') {
        return '';
    }

    $attemptid = optional_param('attempt', 0, PARAM_INT);
    if (!$attemptid) {
        return '';
    }
    $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
    if (!$attempt || $attempt->state !== 'finished' || $attempt->sumgrades === null) {
        return '';
    }
    $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
    if (!$quiz) {
        return '';
    }

    $coursecontext = context_course::instance($quiz->course);
    if (!has_capability('gradereport/quizanalytics:view', $coursecontext)) {
        return '';
    }
    $isownattempt = $attempt->userid == $USER->id;
    if ($isownattempt) {
        if (!has_capability('moodle/grade:view', $coursecontext)) {
            return '';
        }
    } else if (!has_capability('moodle/grade:viewall', $coursecontext)) {
        return '';
    }

    $renderer = $PAGE->get_renderer('gradereport_quizanalytics');
    $tabshtml = $renderer->render_analytics_html();
    if ($tabshtml === '') {
        return '';
    }

    $PAGE->requires->css('/grade/report/quizanalytics/css/frontend.css', true);
    $PAGE->requires->css('/grade/report/quizanalytics/css/datatables.css', true);
    $PAGE->requires->js_call_amd('gradereport_quizanalytics/analytic', 'analytic');

    $link = '<a href="#" id="viewanalytic" class="viewanalytic" data-url="' . $CFG->wwwroot
        . '" data-quiz_id="' . $quiz->id . '" data-course_id="' . $quiz->course
        . '" data-user_id="' . $attempt->userid . '">'
        . get_string('viewanalytics', 'gradereport_quizanalytics') . '</a>';

    return '
        <div id="gradereport-quizanalytics-review" class="gradereport-quizanalytics-review">
            <h4>' . get_string('pluginname', 'gradereport_quizanalytics') . '</h4>
            <p>' . $link . '</p>
            ' . $tabshtml . '
        </div>';
}
