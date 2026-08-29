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
 * The gradebook quizanalytics report.
 *
 * @package   gradereport_quizanalytics
 * @author    DualCube <admin@dualcube.com>
 * @copyright Dualcube (https://dualcube.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->dirroot . '/grade/lib.php');

$courseid = required_param('id', PARAM_INT);
$userid = optional_param('userid', $USER->id, PARAM_INT);

$PAGE->set_url(new moodle_url($CFG->wwwroot . '/grade/report/quizanalytics/index.php', ['id' => $courseid]));
$PAGE->requires->css('/grade/report/quizanalytics/css/frontend.css', true);
$PAGE->requires->css('/grade/report/quizanalytics/css/datatables.css', true);
$PAGE->requires->js('/grade/report/quizanalytics/js/Chart.js', true);
$PAGE->requires->js_call_amd('gradereport_quizanalytics/analytic', 'analytic');
$PAGE->requires->js_call_amd('gradereport_quizanalytics/analytic', 'init');

// Basic access checks.
if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    throw new moodle_exception('nocourseid');
}
require_login($course);
$PAGE->set_pagelayout('report');
$context = context_course::instance($course->id);
require_capability('gradereport/quizanalytics:view', $context);

if (empty($userid)) {
    require_capability('moodle/grade:viewall', $context);
} else if (!$DB->get_record('user', ['id' => $userid, 'deleted' => 0]) || isguestuser($userid)) {
    throw new moodle_exception('invaliduser');
}

$access = false;
if (has_capability('moodle/grade:viewall', $context)) {
    // Ok - can view all course grades.
    $access = true;
} else if ($userid == $USER->id && has_capability('moodle/grade:view', $context) && $course->showgrades) {
    // Ok - can view own grades.
    $access = true;
} else if (has_capability('moodle/grade:viewall', context_user::instance($userid)) && $course->showgrades) {
    // Ok - can view grades of this quizanalytics - parent most probably.
    $access = true;
}
if (!$access) {
    // No access to grades!
    throw new moodle_exception('nopermissiontoviewgrades', 'error', $CFG->wwwroot . '/course/view.php?id=' . $courseid);
}

$roles = get_user_roles($context, $userid, false);
$role = reset($roles);
$isstudent = $role && $role->shortname === 'student';

$gpr = new grade_plugin_return(
    ['type' => 'report', 'plugin' => 'overview', 'courseid' => $course->id, 'userid' => $userid]
);
if (!isset($USER->grade_last_report)) {
    $USER->grade_last_report = [];
}
$USER->grade_last_report[$course->id] = 'overview';

// First make sure we have proper final grades - this must be done before constructing of the grade tree.
grade_regrade_final_grades($courseid);

// Print the page.
print_grade_page_head(
    $courseid,
    'report',
    'quizanalytics',
    get_string('pluginname', 'gradereport_quizanalytics') . ' - ' . $USER->firstname . ' ' . $USER->lastname
);

$quizzes = $DB->get_records('quiz', ['course' => $courseid]);

$table = new html_table();
if (!$quizzes) {
    echo $OUTPUT->heading(get_string('noquizfound', 'gradereport_quizanalytics'));
    $table = null;
} else {
    $table->head = [];
    $table->head[] = get_string('quizname', 'gradereport_quizanalytics');
    $table->head[] = get_string('noofattempts', 'gradereport_quizanalytics');
    if (!$isstudent) {
        $table->head[] = get_string('student_select', 'gradereport_quizanalytics');
    }
    $table->head[] = get_string('action', 'gradereport_quizanalytics');

    foreach ($quizzes as $quiz) {
        if (!$isstudent) {
            $attemptsnotgraded = $DB->get_records_sql(
                "SELECT * FROM {quiz_attempts} WHERE state = 'finished' AND sumgrades IS NULL AND quiz = ?",
                [$quiz->id]
            );
            $attempts = $DB->get_records('quiz_attempts', ['quiz' => $quiz->id, 'state' => 'finished']);
        } else {
            $attemptsnotgraded = $DB->get_records_sql(
                "SELECT * FROM {quiz_attempts} WHERE state = 'finished' AND sumgrades IS NULL AND quiz = ? AND userid = ?",
                [$quiz->id, $USER->id]
            );
            $attempts = $DB->get_records('quiz_attempts', [
                'quiz' => $quiz->id,
                'userid' => $USER->id,
                'state' => 'finished',
            ]);
        }

        $cm = $DB->get_record_sql(
            "SELECT cm.id
               FROM {course_modules} cm, {modules} m, {quiz} q
              WHERE m.name = 'quiz' AND cm.module = m.id AND cm.course = q.course
                AND cm.instance = q.id AND q.id = ?",
            [$quiz->id]
        );
        if ($cm) {
            $quizviewurl = $CFG->wwwroot . '/mod/quiz/view.php?id=' . $cm->id;
        } else {
            $quizviewurl = '#';
        }

        $row = [];
        $row[] = '<a href="' . $quizviewurl . '">' . format_text($quiz->name, FORMAT_MOODLE) . '</a>';
        $row[] = count($attempts);

        if (!$isstudent) {
            $attemptedusers = $DB->get_records_sql(
                "SELECT * FROM {quiz_attempts}
                  WHERE state = 'finished' AND sumgrades IS NOT NULL AND attempt = 1 AND quiz = ?",
                [$quiz->id]
            );
            $select = '<select id="userSelect"><option value="-1">'
                . get_string('user_select', 'gradereport_quizanalytics') . '</option>';
            foreach ($attemptedusers as $attempteduser) {
                $select .= '<option value="' . $attempteduser->userid . '">'
                    . get_complete_user_data('id', $attempteduser->userid)->username . '</option>';
            }
            $select .= '</select>';
            $row[] = $select;
        }

        if (count($attemptsnotgraded) == count($attempts)) {
            $row[] = get_string('notgraded', 'gradereport_quizanalytics');
        } else {
            $row[] = '<a ' . ($isstudent ? '' : 'style="pointer-events: none; color: #999" ')
                . 'href="#" id="viewanalytic" class="viewanalytic" data-url="' . $CFG->wwwroot
                . '" data-quiz_id="' . $quiz->id . '" data-course_id="' . $courseid . '">'
                . get_string('viewanalytics', 'gradereport_quizanalytics') . '</a>';
        }
        $table->data[] = $row;
    }
}

if (!empty($table)) {
    echo html_writer::start_tag('div', ['class' => 'no-overflow display-table']);
    echo html_writer::table($table);
    echo html_writer::end_tag('div');
}

$html = '<div class="showanalytics">
                    <div class="tabbable parentTabs">
                        <ul class="nav nav-tabs  ">
                            <li class="tab">
                                <a class="active" href="#tabs-1"><span class="last-attempt">Last </span>
                                ' . get_string('attemptsummary', 'gradereport_quizanalytics') . '</a>
                            </li>
                            <li class="tab">
                                <a href="#tabs-2">' . get_string('myprogress', 'gradereport_quizanalytics') . '</a>
                            </li>
                            <li class="tab">
                                <a href="#tabs-3">' . get_string('questioncategory', 'gradereport_quizanalytics') . '</a>
                            </li>
                            <li class="tab">
                                <a href="#tabs-4">' . get_string('questionstats', 'gradereport_quizanalytics') . '</a>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane mobile-overflow active fade in" id="tabs-1">
                                <div class="canvas-wrap"><label style="width:850px;">
                                    <canvas id="lastAttempt"></canvas>
                                </label></div>
                                <p class="last-attempt-des">'
                                    . get_string('lastattemptsummarydes', 'gradereport_quizanalytics') . '</p>
                                <p class="attempt-des">' . get_string('attemptsummarydes', 'gradereport_quizanalytics') . '</p>
                            </div>
                            <div class="tab-pane mobile-overflow fade in" id="tabs-2">
                                <div class="tabbable">
                                    <ul class="nav nav-tabs  ">
                                        <li class="tab"><a class="active" href="#subtab21">
                                            <span class="improvementcurve">'
                                                . get_string('improvementcurve', 'gradereport_quizanalytics') . '</span>
                                            <span class="peerperformance">'
                                                . get_string('peerperformance', 'gradereport_quizanalytics') . '</span>
                                        </a></li>
                                        <li class="tab"><a href="#subtab22">'
                                            . get_string('hardestquestion', 'gradereport_quizanalytics') . '</a></li>
                                        <li class="tab"><a href="#subtab23">'
                                            . get_string('attemptsnapshot', 'gradereport_quizanalytics') . '</a></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div id="subtab21" class="tab-pane fade in mobile-overflow active show">
                                            <div class="subtabmix">
                                                <div class="canvas-wrap">
                                                    <label style="width:700px;">
                                                        <canvas id="mixchart"></canvas>
                                                    </label>
                                                </div>
                                                <p>' . get_string('mixchartdes', 'gradereport_quizanalytics') . '</p>
                                            </div>
                                            <div class="subtabtimechart1">
                                                <div class="canvas-wrap">
                                                    <label style="width:700px;">
                                                        <canvas id="timechart"></canvas>
                                                    </label>
                                                </div>
                                                <p>' . get_string('timechartdes', 'gradereport_quizanalytics') . '</p>
                                            </div>
                                        </div>
                                        <div id="subtab22" class="tab-pane fade in mobile-overflow">
                                            <div class="canvas-wrap"><label style="width:700px;">
                                                <canvas id="hardest-questions"></canvas>
                                            </label></div>
                                            <p>' . get_string('hardestquesdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                        <div id="subtab23" class="tab-pane fade in mobile-overflow">
                                            <div class=" attemptssnapshot"></div>
                                            <p>' . get_string('attemptssnapshotdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane mobile-overflow fade in" id="tabs-3">
                                <div class="tabbable">
                                    <ul class="nav nav-tabs  ">
                                        <li class="tab">
                                            <a class="active" href="#subtab31">'
                                                . get_string('questionpercategory', 'gradereport_quizanalytics') . '</a>
                                        </li>
                                        <li class="tab">
                                            <a href="#subtab32">'
                                                . get_string('challengingcategoris', 'gradereport_quizanalytics') . '</a>
                                        </li>
                                        <li class="tab">
                                            <a href="#subtab33">'
                                                . get_string('challengingcategorisforme', 'gradereport_quizanalytics') . '</a>
                                        </li>
                                    </ul>
                                    <div class="tab-content">
                                        <div id="subtab31" class="tab-pane fade in mobile-overflow active show">
                                            <label style="width:400px; margin: 0 auto;"><canvas id="questionpercategories"></canvas>
                                            <div id="js-legendqpc" class="chart-legend"></div></label>
                                            <p>' . get_string('questionpercatdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                        <div id="subtab32" class="tab-pane fade in mobile-overflow">
                                           <div class="canvas-wrap"><label style="width:700px;"><canvas id="allusers"></canvas>
                                            </label></div>
                                            <p>' . get_string('allusersdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                        <div id="subtab33" class="tab-pane fade in mobile-overflow">
                                            <div class="canvas-wrap"><label style="width:700px;">
                                            <canvas id="loggedinuser"></canvas></label></div>
                                            <p>' . get_string('loggedinuserdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane mobile-overflow fade in" id="tabs-4">
                                <div class="tabbable">
                                    <ul class="nav nav-tabs  ">
                                        <li class="tab">
                                            <a class="active" href="#subtab41">'
                                                . get_string('scorbrpercent', 'gradereport_quizanalytics') . '</a>
                                        </li>
                                        <li class="tab">
                                            <a href="#subtab42">' . get_string('quesanalysis', 'gradereport_quizanalytics') . '</a>
                                        </li>
                                    </ul>
                                    <div class="tab-content">
                                        <div id="subtab41" class="tab-pane fade in mobile-overflow active show">
                                            <label style="width:400px; margin: 0 auto;"><canvas id="gradeanalysis"></canvas>
                                            <div id="js-legendgrade" class="chart-legend"></div></label>
                                            <p>' . get_string('gradeanalysisdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                        <div id="subtab42" class="tab-pane fade in mobile-overflow">
                                            <div class="canvas-wrap"><label style="width:700px;">
                                            <canvas id="questionanalysis"></canvas></label></div>
                                            <p>' . get_string('quesananalysisdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
echo $html;
echo $OUTPUT->footer();
