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
 * Builds the tabbed analytics markup shared by the report page and the quiz review page embed.
 *
 * Each top-level tab can be turned off via the "Visible analytics" admin settings, so the tabs
 * actually present - and therefore which one ends up marked active - vary per site.
 *
 * @return string HTML, or an empty string if every tab has been turned off.
 */
function gradereport_quizanalytics_render_analytics_html() {
    global $CFG;

    $showtabs = [
        'tabs-1' => !isset($CFG->gradereport_quizanalytics_showattemptsummarytab)
            || $CFG->gradereport_quizanalytics_showattemptsummarytab,
        'tabs-2' => !isset($CFG->gradereport_quizanalytics_showmyprogresstab)
            || $CFG->gradereport_quizanalytics_showmyprogresstab,
        'tabs-3' => !isset($CFG->gradereport_quizanalytics_showquestioncategorytab)
            || $CFG->gradereport_quizanalytics_showquestioncategorytab,
        'tabs-4' => !isset($CFG->gradereport_quizanalytics_showquestionstatstab)
            || $CFG->gradereport_quizanalytics_showquestionstatstab,
    ];

    if (!array_filter($showtabs)) {
        return '';
    }

    $navlabels = [
        'tabs-1' => '<span class="last-attempt">Last </span>'
            . get_string('attemptsummary', 'gradereport_quizanalytics'),
        'tabs-2' => get_string('myprogress', 'gradereport_quizanalytics'),
        'tabs-3' => get_string('questioncategory', 'gradereport_quizanalytics'),
        'tabs-4' => get_string('questionstats', 'gradereport_quizanalytics'),
    ];

    $panes = [
        'tabs-1' => '
                            <div class="tab-pane mobile-overflow fade in" id="tabs-1">
                                <div class="canvas-wrap"><label style="width:850px;">
                                    <canvas id="lastAttempt"></canvas>
                                </label></div>
                                <p class="last-attempt-des">'
                                    . get_string('lastattemptsummarydes', 'gradereport_quizanalytics') . '</p>
                                <p class="attempt-des">'
                                    . get_string('attemptsummarydes', 'gradereport_quizanalytics') . '</p>
                            </div>',
        'tabs-2' => '
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
                            </div>',
        'tabs-3' => '
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
                            </div>',
        'tabs-4' => '
                            <div class="tab-pane mobile-overflow fade in" id="tabs-4">
                                <div class="tabbable">
                                    <ul class="nav nav-tabs  ">
                                        <li class="tab">
                                            <a class="active" href="#subtab41">'
                                                . get_string('scorbrpercent', 'gradereport_quizanalytics') . '</a>
                                        </li>
                                        <li class="tab">
                                            <a href="#subtab42">'
                                                . get_string('quesanalysis', 'gradereport_quizanalytics') . '</a>
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
                            </div>',
    ];

    $navhtml = '';
    $paneshtml = '';
    $first = true;
    foreach ($showtabs as $tabid => $visible) {
        if (!$visible) {
            continue;
        }
        $activeclass = $first ? ' class="active"' : '';
        $navhtml .= '
                            <li class="tab">
                                <a' . $activeclass . ' href="#' . $tabid . '">' . $navlabels[$tabid] . '</a>
                            </li>';
        $panehtml = $panes[$tabid];
        if ($first) {
            $panehtml = str_replace(
                'class="tab-pane mobile-overflow fade in" id="' . $tabid . '"',
                'class="tab-pane mobile-overflow active fade in" id="' . $tabid . '"',
                $panehtml
            );
        }
        $paneshtml .= $panehtml;
        $first = false;
    }

    return '<div class="showanalytics">
                    <div class="tabbable parentTabs">
                        <ul class="nav nav-tabs  ">' . $navhtml . '
                        </ul>
                        <div class="tab-content">' . $paneshtml . '
                        </div>
                    </div>
                </div>';
}

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

    $tabshtml = gradereport_quizanalytics_render_analytics_html();
    if ($tabshtml === '') {
        return '';
    }

    $PAGE->requires->css('/grade/report/quizanalytics/css/frontend.css', true);
    $PAGE->requires->css('/grade/report/quizanalytics/css/datatables.css', true);
    $PAGE->requires->js('/grade/report/quizanalytics/js/Chart.js', true);
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
