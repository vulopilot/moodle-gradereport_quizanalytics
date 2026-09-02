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
 * Returns the finished, graded quiz attempt referenced by the current request's "attempt" param.
 *
 * @param moodle_database $db
 * @return stdClass|null The attempt record, or null if there isn't one worth showing analytics for.
 */
function gradereport_quizanalytics_get_review_attempt($db) {
    $attemptid = optional_param('attempt', 0, PARAM_INT);
    if (!$attemptid) {
        return null;
    }
    $attempt = $db->get_record('quiz_attempts', ['id' => $attemptid]);
    if (!$attempt || $attempt->state !== 'finished' || $attempt->sumgrades === null) {
        return null;
    }
    return $attempt;
}

/**
 * Checks whether the current user is allowed to see the analytics embed for this attempt.
 *
 * @param context_course $coursecontext
 * @param stdClass $attempt
 * @param int $userid The current user's id.
 * @return bool
 */
function gradereport_quizanalytics_can_view_review_embed($coursecontext, $attempt, $userid) {
    if (!has_capability('gradereport/quizanalytics:view', $coursecontext)) {
        return false;
    }
    if ($attempt->userid == $userid) {
        return has_capability('moodle/grade:view', $coursecontext);
    }
    return has_capability('moodle/grade:viewall', $coursecontext);
}

/**
 * Embeds a compact "Quiz Analytics" panel on the quiz attempt review page, if the site admin has
 * enabled the "Show on the quiz attempt review page" setting and the viewer is allowed to see it.
 *
 * @return string HTML to inject before the page footer, or an empty string.
 */
function gradereport_quizanalytics_before_footer() {
    global $CFG, $PAGE, $DB, $USER;

    if (empty($CFG->gradereport_quizanalytics_showonreviewpage) || $PAGE->pagetype !== 'mod-quiz-review') {
        return '';
    }

    $attempt = gradereport_quizanalytics_get_review_attempt($DB);
    if (!$attempt) {
        return '';
    }
    $quiz = $DB->get_record('quiz', ['id' => $attempt->quiz]);
    if (!$quiz) {
        return '';
    }

    $coursecontext = context_course::instance($quiz->course);
    if (!gradereport_quizanalytics_can_view_review_embed($coursecontext, $attempt, $USER->id)) {
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
