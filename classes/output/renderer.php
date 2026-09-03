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

namespace gradereport_quizanalytics\output;

/**
 * Renderer for the quizanalytics gradebook report.
 *
 * @package   gradereport_quizanalytics
 * @copyright 2026 DualCube (https://dualcube.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Returns which top-level tabs are enabled, keyed by tab id.
     *
     * Each one can be turned off via the "Visible analytics" admin settings.
     *
     * @return bool[]
     */
    private function get_tab_visibility(): array {
        global $CFG;

        return [
            'attempt-summary' => !isset($CFG->gradereport_quizanalytics_showattemptsummarytab)
                || $CFG->gradereport_quizanalytics_showattemptsummarytab,
            'my-progress' => !isset($CFG->gradereport_quizanalytics_showmyprogresstab)
                || $CFG->gradereport_quizanalytics_showmyprogresstab,
            'question-categories' => !isset($CFG->gradereport_quizanalytics_showquestioncategorytab)
                || $CFG->gradereport_quizanalytics_showquestioncategorytab,
            'question-stats' => !isset($CFG->gradereport_quizanalytics_showquestionstatstab)
                || $CFG->gradereport_quizanalytics_showquestionstatstab,
        ];
    }

    /**
     * Returns the nav-link label for each top-level tab, keyed by tab id.
     *
     * @return string[]
     */
    private function get_tab_nav_labels(): array {
        return [
            'attempt-summary' => '<span class="last-attempt">Last </span>'
                . get_string('attemptsummary', 'gradereport_quizanalytics'),
            'my-progress' => get_string('myprogress', 'gradereport_quizanalytics'),
            'question-categories' => get_string('questioncategory', 'gradereport_quizanalytics'),
            'question-stats' => get_string('questionstats', 'gradereport_quizanalytics'),
        ];
    }

    /**
     * Returns the pane markup for each top-level tab, keyed by tab id.
     *
     * @return string[]
     */
    private function get_tab_panes(): array {
        return [
            'attempt-summary' => $this->get_attempt_summary_pane(),
            'my-progress' => $this->get_my_progress_pane(),
            'question-categories' => $this->get_question_category_pane(),
            'question-stats' => $this->get_question_stats_pane(),
        ];
    }

    /**
     * Returns the "Attempt Summary" pane markup (attempt-summary).
     *
     * @return string
     */
    private function get_attempt_summary_pane(): string {
        return '
                                <div class="tab-pane mobile-overflow fade" id="attempt-summary" role="tabpanel"
                                     aria-labelledby="attempt-summary-tab">
                                    <div class="canvas-wrap"><label style="width:850px;">
                                        <canvas id="lastAttempt"></canvas>
                                    </label></div>
                                    <p class="last-attempt-des">'
                                        . get_string('lastattemptsummarydes', 'gradereport_quizanalytics') . '</p>
                                    <p class="attempt-des">'
                                        . get_string('attemptsummarydes', 'gradereport_quizanalytics') . '</p>
                                </div>';
    }

    /**
     * Returns the "My Progress and Predictions" pane markup (my-progress).
     *
     * @return string
     */
    private function get_my_progress_pane(): string {
        return '
                                <div class="tab-pane mobile-overflow fade" id="my-progress" role="tabpanel"
                                     aria-labelledby="my-progress-tab">
                                    <div class="tabbable">
                                        <ul class="nav nav-tabs" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link active" id="improvement-curve-tab" data-bs-toggle="tab"
                                                   href="#improvement-curve" role="tab" aria-controls="improvement-curve"
                                                   aria-selected="true">
                                                <span class="improvementcurve">'
                                                    . get_string('improvementcurve', 'gradereport_quizanalytics') . '</span>
                                                <span class="peerperformance">'
                                                    . get_string('peerperformance', 'gradereport_quizanalytics') . '</span>
                                            </a></li>
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link" id="hardest-question-tab" data-bs-toggle="tab"
                                                   href="#hardest-question" role="tab" aria-controls="hardest-question"
                                                   aria-selected="false">'
                                                . get_string('hardestquestion', 'gradereport_quizanalytics') . '</a></li>
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link" id="attempt-snapshot-tab" data-bs-toggle="tab"
                                                   href="#attempt-snapshot" role="tab" aria-controls="attempt-snapshot"
                                                   aria-selected="false">'
                                                . get_string('attemptsnapshot', 'gradereport_quizanalytics') . '</a></li>
                                        </ul>
                                        <div class="tab-content">
                                            <div id="improvement-curve" class="tab-pane fade mobile-overflow active show"
                                                 role="tabpanel" aria-labelledby="improvement-curve-tab">
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
                                            <div id="hardest-question" class="tab-pane fade mobile-overflow"
                                                 role="tabpanel" aria-labelledby="hardest-question-tab">
                                                <div class="canvas-wrap"><label style="width:700px;">
                                                    <canvas id="hardest-questions"></canvas>
                                                </label></div>
                                                <p>' . get_string('hardestquesdes', 'gradereport_quizanalytics') . '</p>
                                            </div>
                                            <div id="attempt-snapshot" class="tab-pane fade mobile-overflow"
                                                 role="tabpanel" aria-labelledby="attempt-snapshot-tab">
                                                <div class=" attemptssnapshot"></div>
                                                <p>' . get_string('attemptssnapshotdes', 'gradereport_quizanalytics') . '</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>';
    }

    /**
     * Returns the "Question Categories' Analysis" pane markup (question-categories).
     *
     * @return string
     */
    private function get_question_category_pane(): string {
        return '
                                <div class="tab-pane mobile-overflow fade" id="question-categories" role="tabpanel"
                                     aria-labelledby="question-categories-tab">
                                    <div class="tabbable">
                                        <ul class="nav nav-tabs" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link active" id="question-per-category-tab" data-bs-toggle="tab"
                                                   href="#question-per-category" role="tab" aria-controls="question-per-category"
                                                   aria-selected="true">'
                                                    . get_string('questionpercategory', 'gradereport_quizanalytics') . '</a>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link" id="hard-categories-all-tab" data-bs-toggle="tab"
                                                   href="#hard-categories-all" role="tab" aria-controls="hard-categories-all"
                                                   aria-selected="false">'
                                                    . get_string('challengingcategoris', 'gradereport_quizanalytics') . '</a>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link" id="hard-categories-me-tab" data-bs-toggle="tab"
                                                   href="#hard-categories-me" role="tab" aria-controls="hard-categories-me"
                                                   aria-selected="false">'
                                                    . get_string('challengingcategorisforme', 'gradereport_quizanalytics') . '</a>
                                            </li>
                                        </ul>
                                        <div class="tab-content">
                                            <div id="question-per-category" class="tab-pane fade mobile-overflow active show"
                                                 role="tabpanel" aria-labelledby="question-per-category-tab">
                                                <label style="width:400px; margin: 0 auto;">
                                                <canvas id="questionpercategories"></canvas>
                                                <div id="js-legendqpc" class="chart-legend"></div></label>
                                                <p>' . get_string('questionpercatdes', 'gradereport_quizanalytics') . '</p>
                                            </div>
                                            <div id="hard-categories-all" class="tab-pane fade mobile-overflow"
                                                 role="tabpanel" aria-labelledby="hard-categories-all-tab">
                                               <div class="canvas-wrap"><label style="width:700px;"><canvas id="allusers"></canvas>
                                                </label></div>
                                                <p>' . get_string('allusersdes', 'gradereport_quizanalytics') . '</p>
                                            </div>
                                            <div id="hard-categories-me" class="tab-pane fade mobile-overflow"
                                                 role="tabpanel" aria-labelledby="hard-categories-me-tab">
                                                <div class="canvas-wrap"><label style="width:700px;">
                                                <canvas id="loggedinuser"></canvas></label></div>
                                                <p>' . get_string('loggedinuserdes', 'gradereport_quizanalytics') . '</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>';
    }

    /**
     * Returns the "Scores' & Questions' Stats" pane markup (question-stats).
     *
     * @return string
     */
    private function get_question_stats_pane(): string {
        return '
                                <div class="tab-pane mobile-overflow fade" id="question-stats" role="tabpanel"
                                     aria-labelledby="question-stats-tab">
                                    <div class="tabbable">
                                        <ul class="nav nav-tabs" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link active" id="grade-analysis-tab" data-bs-toggle="tab"
                                                   href="#grade-analysis" role="tab" aria-controls="grade-analysis"
                                                   aria-selected="true">'
                                                    . get_string('scorbrpercent', 'gradereport_quizanalytics') . '</a>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <a class="nav-link" id="question-analysis-tab" data-bs-toggle="tab"
                                                   href="#question-analysis" role="tab" aria-controls="question-analysis"
                                                   aria-selected="false">'
                                                    . get_string('quesanalysis', 'gradereport_quizanalytics') . '</a>
                                            </li>
                                        </ul>
                                        <div class="tab-content">
                                            <div id="grade-analysis" class="tab-pane fade mobile-overflow active show"
                                                 role="tabpanel" aria-labelledby="grade-analysis-tab">
                                                <label style="width:400px; margin: 0 auto;"><canvas id="gradeanalysis"></canvas>
                                                <div id="js-legendgrade" class="chart-legend"></div></label>
                                                <p>' . get_string('gradeanalysisdes', 'gradereport_quizanalytics') . '</p>
                                            </div>
                                            <div id="question-analysis" class="tab-pane fade mobile-overflow"
                                                 role="tabpanel" aria-labelledby="question-analysis-tab">
                                                <div class="canvas-wrap"><label style="width:700px;">
                                                <canvas id="questionanalysis"></canvas></label></div>
                                                <p>' . get_string('quesananalysisdes', 'gradereport_quizanalytics') . '</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>';
    }

    /**
     * Builds the tabbed analytics markup shared by the report page and the quiz review page embed.
     *
     * Each top-level tab can be turned off via the "Visible analytics" admin settings, so the tabs
     * actually present - and therefore which one ends up marked active - vary per site.
     *
     * @return string HTML, or an empty string if every tab has been turned off.
     */
    public function render_analytics_html(): string {
        $showtabs = $this->get_tab_visibility();
        if (!array_filter($showtabs)) {
            return '';
        }
        $navlabels = $this->get_tab_nav_labels();
        $panes = $this->get_tab_panes();

        $navhtml = '';
        $paneshtml = '';
        $first = true;
        foreach ($showtabs as $tabid => $visible) {
            if (!$visible) {
                continue;
            }
            $navlinkclass = $first ? ' active' : '';
            $selected = $first ? 'true' : 'false';
            $navhtml .= '
                                <li class="nav-item" role="presentation">
                                    <a class="nav-link' . $navlinkclass . '" id="' . $tabid . '-tab" data-bs-toggle="tab"
                                       href="#' . $tabid . '" role="tab" aria-controls="' . $tabid . '"
                                       aria-selected="' . $selected . '">' . $navlabels[$tabid] . '</a>
                                </li>';
            $panehtml = $panes[$tabid];
            if ($first) {
                $panehtml = str_replace(
                    'class="tab-pane mobile-overflow fade" id="' . $tabid . '"',
                    'class="tab-pane mobile-overflow fade show active" id="' . $tabid . '"',
                    $panehtml
                );
            }
            $paneshtml .= $panehtml;
            $first = false;
        }

        return '<div class="showanalytics">
                        <div class="tabbable parentTabs">
                            <ul class="nav nav-tabs" role="tablist">' . $navhtml . '
                            </ul>
                            <div class="tab-content">' . $paneshtml . '
                            </div>
                        </div>
                    </div>';
    }
}
