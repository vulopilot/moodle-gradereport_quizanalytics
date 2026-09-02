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
 * External API for retrieving the quiz analytics chart data.
 *
 * @package   gradereport_quizanalytics
 * @author    DualCube <admin@dualcube.com>
 * @copyright Dualcube (https://dualcube.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_quizanalytics\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use gradereport_quizanalytics\local\chart_builder;

/**
 * Builds the chart data consumed by the quiz analytics report.
 */
class get_analytics extends external_api {
    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'quizid' => new external_value(PARAM_INT, 'quiz id'),
                'user_id' => new external_value(PARAM_INT, 'user id'),
            ]
        );
    }

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
     * Builds the JSON payload consumed by the report's charts.
     *
     * @param int $quizid
     * @param int $userid
     * @return string JSON-encoded chart data.
     */
    public static function execute($quizid, $userid) {
        global $DB, $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'quizid' => $quizid,
            'user_id' => $userid,
        ]);
        $quizid = $params['quizid'];
        $userid = $params['user_id'];

        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $context = context_course::instance($quiz->course);
        self::validate_context($context);
        require_capability('gradereport/quizanalytics:view', $context);

        if ($userid < 0) {
            $userid = $USER->id;
        }
        if ($userid != $USER->id) {
            require_capability('moodle/grade:viewall', $context);
        }

        $sql = "SELECT * FROM {quiz_attempts} WHERE state = 'finished' AND sumgrades IS NOT NULL AND quiz = ?";
        $totalquizattempted = $DB->get_records_sql($sql, [$quizid]);
        $usersgradedattempts = $DB->get_records_sql($sql . " AND userid = ?", [$quizid, $userid]);

        [$slots, $totalnoofquestion, $realslots] = self::resolve_slots($quiz);
        $categorys = self::get_question_categories($DB, $realslots);
        $hardness = self::compute_category_hardness(
            $DB,
            $quizid,
            $userid,
            $categorys,
            count($totalquizattempted),
            count($usersgradedattempts)
        );

        $lastattemptid = $DB->get_record_sql(
            "SELECT quizatt.id
               FROM {quiz_attempts} quizatt
              WHERE quizatt.state = 'finished' AND quizatt.sumgrades IS NOT NULL
                AND quizatt.quiz = ? AND quizatt.userid= ?
           ORDER BY quizatt.id DESC LIMIT 1",
            [$quizid, $userid]
        );

        // Random slots are deliberately excluded here (unlike $realslots above): each attempt
        // draws a different concrete question for them, so there is no single "the question in
        // this slot" to break out individually the way this section does for fixed slots.
        $totalquestions = array_values(array_filter($slots, function ($slot) {
            return $slot->qtype !== 'description' && $slot->qtype !== 'random' && $slot->qtype !== 'missingtype';
        }));
        $responsestats = self::compute_question_response_stats($DB, $quizid, $totalquestions, count($totalquizattempted));

        $catchart = chart_builder::questionpercategories(
            $hardness['categoryname'],
            $hardness['chartdata'],
            $hardness['randomcolor']
        );
        $allusers = chart_builder::hardness(
            $hardness['overallhardness'],
            $hardness['wrongattemts'],
            $hardness['categoryname'],
            'hardcatalluser'
        );
        $loggedinuser = chart_builder::hardness(
            $hardness['userhardness'],
            $hardness['userswrongattemts'],
            $hardness['categoryname'],
            'hardcatlogginuser'
        );
        $snapshot = self::build_attempts_snapshot($DB, $quizid, $userid, $usersgradedattempts, $totalnoofquestion);

        $totalarray = [
            'questionPerCategories' => $catchart,
            'allUsers' => $allusers,
            'loggedInUser' => $loggedinuser,
            'lastAttemptSummary' => self::build_last_attempt_summary($DB, $quizid, $userid, $lastattemptid->id),
            'attemptssnapshot' => $snapshot,
            'mixChart' => self::build_mix_chart($DB, $CFG, $quiz, $quizid, $userid),
            'timeChart' => self::build_time_chart($DB, $quiz, $quizid, $userid, $totalquizattempted),
            'gradeAnalysis' => self::build_grade_analysis($DB, $CFG, $quiz, $quizid),
            'quesAnalysis' => chart_builder::question_analysis($responsestats),
            'hardestQuestions' => chart_builder::hardest_questions($responsestats, count($totalquizattempted)),
            'userAttempts' => count($usersgradedattempts),
            'quizAttempt' => $quiz->attempts,
            'allQuestions' => $responsestats['selectedquestionid'],
            'quizid' => $quizid,
            'lastUserQuizAttemptID' => $lastattemptid->id,
            'url' => $CFG->wwwroot,
        ];
        return json_encode($totalarray);
    }

    /**
     * Resolves the quiz's slots via qbank_helper, which is the core API mod_quiz itself uses to
     * correctly resolve both fixed slots (via question_references) and random slots (via
     * question_set_references, whose category lives in a JSON filtercondition) - hand-rolled joins
     * through question_references alone would silently miss every random slot.
     *
     * @param \stdClass $quiz
     * @return array [$slots, $totalnoofquestion, $realslots] - $realslots excludes description
     *     slots, which aren't real questions; $totalnoofquestion is (object) ['qnum' => count].
     */
    private static function resolve_slots($quiz): array {
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $quiz->course, false, MUST_EXIST);
        $quizcontext = \context_module::instance($cm->id);
        $slots = \mod_quiz\question\bank\qbank_helper::get_question_structure($quiz->id, $quizcontext);
        // Random slots (qtype 'random') draw a different concrete question per attempt, so unlike
        // fixed slots they can't be broken down individually later on - but they still count as
        // real questions here for the totals and the per-category breakdown.
        $realslots = array_filter($slots, function ($slot) {
            return $slot->qtype !== 'description';
        });
        return [$slots, (object) ['qnum' => count($realslots)], $realslots];
    }

    /**
     * Resolves each fixed/random slot's question_categories row into a per-category question
     * count, using qbank_helper's own category ids rather than re-deriving them.
     *
     * @param \moodle_database $db
     * @param array $realslots Non-description slots from resolve_slots().
     * @return array Objects with id/qnum/name.
     */
    private static function get_question_categories($db, $realslots): array {
        $categorycounts = [];
        foreach ($realslots as $slot) {
            $categorycounts[$slot->category] = ($categorycounts[$slot->category] ?? 0) + 1;
        }
        $categorynames = [];
        if ($categorycounts) {
            [$insql, $inparams] = $db->get_in_or_equal(array_keys($categorycounts));
            $categoryrecords = $db->get_records_select('question_categories', "id $insql", $inparams, '', 'id, name');
            foreach ($categoryrecords as $categoryrecord) {
                $categorynames[$categoryrecord->id] = $categoryrecord->name;
            }
        }
        $categorys = [];
        foreach ($categorycounts as $categoryid => $qnum) {
            $categorys[] = (object) [
                'id' => $categoryid,
                'qnum' => $qnum,
                'name' => $categorynames[$categoryid] ?? '',
            ];
        }
        return $categorys;
    }

    /**
     * Computes, for each question category, how many questions it has and how often those
     * questions were answered wrong (both across all users, and for the given user alone).
     *
     * @param \moodle_database $db
     * @param int $quizid
     * @param int $userid
     * @param array $categorys Objects with id/qnum/name, from get_question_categories().
     * @param int $totalattempts Count of all finished, graded attempts at this quiz.
     * @param int $userattempts Count of the given user's finished, graded attempts.
     * @return array
     */
    private static function compute_category_hardness($db, $quizid, $userid, $categorys, $totalattempts, $userattempts): array {
        // Qatt.questionid is the actual question a user was given (even for a random slot, this is
        // already resolved to one concrete question), and each question row belongs to exactly one
        // question_versions row, so its category can be looked up directly - no slot/version
        // resolution needed here, unlike qbank_helper::get_question_structure() in execute().
        //
        // COUNT(DISTINCT qatt.id), not a plain row count: sequencenumber >= 2 is a range, and a
        // single question attempt can pass through 'gradedright'/'mangrright' at more than one
        // step (e.g. an initial auto-grade step, then a later finish/regrade step landing on the
        // same state) - counting rows instead of distinct attempts double-counts those, which can
        // push the computed "wrong" count to zero or below and hide categories that really do have
        // wrong answers.
        $sql = "SELECT COUNT(DISTINCT qatt.id) as cnt
                  FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep,
                       {question} q, {question_categories} qc, {question_bank_entries} qbe,
                       {question_versions} qv
                 WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.questionattemptid = qatt.id
                   AND q.id = qatt.questionid AND qv.questionid = q.id AND qv.questionbankentryid = qbe.id
                   AND qbe.questioncategoryid = qc.id
                   AND quizatt.quiz = ? AND qc.id = ? AND q.qtype != ?
                   AND qattstep.sequencenumber >= 2
                   AND (qattstep.state = 'gradedright' OR qattstep.state = 'mangrright')";
        $categoryname = $chartdata = $randomcolor = [];
        $wrongattemts = $userswrongattemts = $overallhardness = $userhardness = [];
        foreach ($categorys as $category) {
            $categoryname[] = empty($category->name) ? 'category' : $category->name;
            $chartdata[] = empty($category->qnum) ? 1 : ($category->qnum);
            $randomcolor[] = "#" . self::random_color();
            $correctattempts = $db->get_record_sql($sql, [$quizid, $category->id, 'description'])->cnt;
            $userscorrectattempts = $db->get_record_sql(
                $sql . " AND quizatt.userid = ?",
                [$quizid, $category->id, 'description', $userid]
            )->cnt;
            $categoryattempts = $category->qnum * $totalattempts;
            $categoryuserattempts = $category->qnum * $userattempts;
            $wrongattemts[] = ($categoryattempts - $correctattempts);
            $userswrongattemts[] = ($categoryuserattempts - $userscorrectattempts);
            $overallhardness[] = round(((($categoryattempts - $correctattempts) / $categoryattempts) * 100), 2);
            $userhardness[] = round(((($categoryuserattempts - $userscorrectattempts) / $categoryuserattempts) * 100), 2);
        }
        return [
            'categoryname' => $categoryname,
            'chartdata' => $chartdata,
            'randomcolor' => $randomcolor,
            'wrongattemts' => $wrongattemts,
            'userswrongattemts' => $userswrongattemts,
            'overallhardness' => $overallhardness,
            'userhardness' => $userhardness,
        ];
    }

    /**
     * Builds the "last attempt" bar chart: how the user's most recent finished, graded attempt
     * broke down into correct / partially-correct / wrong / unattempted questions.
     *
     * @param \moodle_database $db
     * @param int $quizid
     * @param int $userid
     * @param int $lastattemptid Id of the user's most recent finished, graded attempt.
     * @return array 'data'/'opt' pair, both null if the user has no finished, graded attempt.
     */
    private static function build_last_attempt_summary($db, $quizid, $userid, $lastattemptid): array {
        $sql = "SELECT qatt.questionid, qattstep.state, qattstep.fraction, qatt.maxmark
                  FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep
                 WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.questionattemptid = qatt.id
                   AND quizatt.userid = ? AND quizatt.id = ? AND quizatt.quiz = ? ";
        $totalattempted = $db->get_records_sql($sql . " AND qattstep.sequencenumber = 2", [$userid, $lastattemptid, $quizid]);
        if (empty($totalattempted)) {
            return ['data' => null, 'opt' => null];
        }
        $rightattempt = $db->get_records_sql(
            $sql . " AND (qattstep.state = 'gradedright' OR qattstep.state = 'mangrright')",
            [$userid, $lastattemptid, $quizid]
        );
        $partialattempts = $db->get_records_sql(
            $sql . " AND (qattstep.state = 'gradedpartial' OR qattstep.state = 'mangrpartial')",
            [$userid, $lastattemptid, $quizid]
        );

        $partiallycorrect = 0;
        if (!empty($partialattempts)) {
            $count = $totaluserscores = $totalquesmarks = 0;
            foreach ($partialattempts as $partialcorrect) {
                $count++;
                $totaluserscores += $partialcorrect->fraction;
                $totalquesmarks += $partialcorrect->maxmark;
            }
            $partiallycorrect = $count * ((($totaluserscores / $totalquesmarks) * 100) / 100);
        }
        $accuracyrate = ((count($rightattempt) + round($partiallycorrect)) / count($totalattempted)) * 100;
        $noofwronganswers = count($totalattempted) - count($rightattempt) - count($partialattempts);

        $labels = [
            get_string('noofquestionattempt', 'gradereport_quizanalytics'),
            get_string('noofrightans', 'gradereport_quizanalytics'),
        ];
        $colors = ["#2EA0EF", "#79D527"];
        $values = [count($totalattempted), count($rightattempt)];
        if (!empty($partialattempts)) {
            $labels[] = get_string('noofpartialcorrect', 'gradereport_quizanalytics');
            $colors[] = "#FF9827";
            $values[] = count($partialattempts);
        }
        $labels[] = get_string('noofwronganswers', 'gradereport_quizanalytics');
        $colors[] = "#EB2838";
        $values[] = $noofwronganswers;

        return [
            'data' => [
                'labels' => $labels,
                'datasets' => [['backgroundColor' => $colors, 'data' => $values]],
            ],
            'opt' => [
                'legend' => ['display' => false],
                'title' => ['display' => false], 'scales' => [
                    'xAxes' => [[
                        'ticks' => ['min' => 0],
                        'scaleLabel' => [
                            'display' => true,
                            'labelString' => get_string('accuaracyrate', 'gradereport_quizanalytics')
                                . round($accuracyrate, 2) . "%",
                        ],
                    ]],
                    'yAxes' => [['barPercentage' => 0.4]],
                ],
            ],
        ];
    }

    /**
     * Builds one doughnut chart per finished, graded attempt, breaking down how many questions
     * were unattempted / correct / incorrect / partially correct in each.
     *
     * @param \moodle_database $db
     * @param int $quizid
     * @param int $userid
     * @param array $usersgradedattempts The user's finished, graded quiz_attempts records.
     * @param \stdClass $totalnoofquestion Object with a ->qnum property (the quiz's question count).
     * @return array 'data'/'opt' pairs, keyed by attempt number.
     */
    private static function build_attempts_snapshot($db, $quizid, $userid, $usersgradedattempts, $totalnoofquestion): array {
        $labels = [
            get_string('unattempted', 'gradereport_quizanalytics'),
            get_string('correct', 'gradereport_quizanalytics'),
            get_string('incorrect', 'gradereport_quizanalytics'),
            get_string('partialcorrect', 'gradereport_quizanalytics'),
        ];

        if (empty($usersgradedattempts)) {
            return [
                'data' => [1 => ['labels' => $labels, 'datasets' => [[
                    'label' => 'Attempt1',
                    'backgroundColor' => ['#3e95cd', '#3cba9f', '#8e5ea2', '#e8c3b9'],
                    'data' => [0, 0, 0, 0],
                ]]]],
                'opt' => [1 => ['title' => [
                    'display' => true,
                    'position' => 'bottom', 'text' => 'Attempts Snapshot( timetaken: 0min )',
                ]]],
            ];
        }

        $count = 1;
        $data = $opt = [];
        $sql = "SELECT COUNT(qatt.questionid) as num
                  FROM {quiz_attempts} quizatt, {question_attempts} qatt,
                       {question_attempt_steps} qattstep, {question} q
                 WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.sequencenumber = 2
                   AND q.id = qatt.questionid AND qattstep.questionattemptid = qatt.id
                   AND quizatt.userid = ? AND quizatt.quiz= ? AND q.qtype != ?
                   AND quizatt.attempt = ? AND qattstep.state = ?";
        foreach ($usersgradedattempts as $attemptvalue) {
            $numofattempt = $db->get_record_sql(
                "SELECT COUNT(qatt.questionid) as anum
                   FROM {quiz_attempts} quizatt, {question_attempts} qatt,
                        {question_attempt_steps} qattstep, {question} q
                  WHERE qatt.questionusageid = quizatt.uniqueid AND q.id = qatt.questionid
                    AND qattstep.questionattemptid = qatt.id AND qattstep.sequencenumber = 2
                    AND quizatt.userid = ? AND quizatt.quiz= ? AND quizatt.attempt = ? AND q.qtype != ?",
                [$userid, $quizid, $attemptvalue->attempt, 'description']
            );
            $timetaken = round((($attemptvalue->timefinish - $attemptvalue->timestart) / 60), 2);
            $unattempt = ($totalnoofquestion->qnum - $numofattempt->anum);
            $correct = $db->get_record_sql($sql, [$userid, $quizid, 'description', $attemptvalue->attempt, 'gradedright']);
            $incorrect = $db->get_record_sql($sql, [$userid, $quizid, 'description', $attemptvalue->attempt, 'gradedwrong']);
            $partialcorrect = $db->get_record_sql(
                $sql,
                [$userid, $quizid, 'description', $attemptvalue->attempt, 'gradedpartial']
            );
            $data[$count] = [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Attempt' . $count,
                    'backgroundColor' => ['#3e95cd', '#3cba9f', '#8e5ea2', '#e8c3b9'],
                    'data' => [
                        intval($unattempt), intval($correct->num),
                        intval($incorrect->num), intval($partialcorrect->num),
                    ],
                ]],
            ];
            $opt[$count] = [
                'title' => [
                    'display' => true,
                    'position' => 'bottom', 'text' => get_string(
                        'timetaken',
                        'gradereport_quizanalytics'
                    ) . $timetaken . 'min)',
                ],
                'legend' => [
                    'display' => false, 'position' => 'bottom',
                    'labels' => ['boxWidth' => 13],
                ],
            ];
            $count++;
        }
        return ['data' => $data, 'opt' => $opt];
    }

    /**
     * Builds the "peer scores" bar chart, shown instead of the improvement curve when the quiz
     * only allows a single attempt (so there is no per-attempt trend to plot).
     *
     * @param \moodle_database $db
     * @param \stdClass $quiz
     * @param int $quizid
     * @param int $userid
     * @param array $totalquizattempted Every finished, graded quiz_attempts record for this quiz.
     * @return array 'data'/'opt' pair, both null if the quiz allows more than one attempt.
     */
    private static function build_time_chart($db, $quiz, $quizid, $userid, $totalquizattempted): array {
        if ($quiz->attempts != 1) {
            return ['data' => null, 'opt' => null];
        }
        $scores = [];
        foreach ($totalquizattempted as $totalquizattempt) {
            $scores[] = ($totalquizattempt->sumgrades / $quiz->sumgrades) * 100;
        }
        $userscore = $db->get_record('quiz_attempts', ['quiz' => $quizid, 'userid' => $userid]);
        $userscoredata = ($userscore->sumgrades / $quiz->sumgrades) * 100;
        $scoredata = [
            round($userscoredata, 2),
            round(max($scores), 2),
            round((array_sum($scores) / count($scores)), 2),
            round(min($scores), 2),
        ];
        return [
            'data' => [
                'labels' => [
                    get_string('userscore', 'gradereport_quizanalytics'),
                    get_string('bestscore', 'gradereport_quizanalytics'),
                    get_string('avgscore', 'gradereport_quizanalytics'),
                    get_string('lowestscore', 'gradereport_quizanalytics'),
                ],
                'datasets' => [[
                    'label' => get_string('score', 'gradereport_quizanalytics'),
                    'backgroundColor' => "#3e95cd", 'data' => $scoredata,
                ]],
            ],
            'opt' => [
                'showTooltips' => false,
                'legend' => ['display' => false],
                'title' => ['display' => true, 'text' => get_string('peerscores', 'gradereport_quizanalytics')],
            ],
        ];
    }

    /**
     * Builds the "improvement curve" line chart: the user's score on every attempt against the
     * grade cut-off, extended out to the average number of attempts users need to reach it.
     *
     * @param \moodle_database $db
     * @param \stdClass $cfg
     * @param \stdClass $quiz
     * @param int $quizid
     * @param int $userid
     * @return array 'data'/'opt' pair.
     */
    private static function build_mix_chart($db, $cfg, $quiz, $quizid, $userid): array {
        $cutoffscore = ($quiz->sumgrades * $cfg->gradereport_quizanalytics_cutoff) / 100;
        $attemptcutoff = $db->get_records_sql(
            "SELECT userid, MIN(attempt) as attempt
               FROM {quiz_attempts}
              WHERE state = 'finished' AND sumgrades IS NOT NULL AND quiz = ? AND sumgrades >= ?
           GROUP BY userid",
            [$quizid, $cutoffscore]
        );
        $attemptresult = [];
        foreach ($attemptcutoff as $torichcutoff) {
            $attemptresult[] = $torichcutoff->attempt;
        }
        $averageattempt = empty($attemptresult) ? 0 : (array_sum($attemptresult) / count($attemptresult));

        $usersattempts = $db->get_records_sql(
            "SELECT * FROM {quiz_attempts} WHERE state = 'finished' AND quiz = ? AND userid = ?",
            [$quizid, $userid]
        );
        $attemptnum = $scored = [0];
        $count = 1;
        foreach ($usersattempts as $usersattempt) {
            if (!empty($usersattempt->sumgrades)) {
                $attemptnum[] = $count;
                $scored[] = round($usersattempt->sumgrades, 2);
            } else {
                $attemptnum[] = $count . '(NG)';
                $scored[] = 0;
            }
            $count++;
        }
        $cutoffarray = array_fill(0, $count, round($cutoffscore, 2));
        if (round($averageattempt) >= $count) {
            for ($j = $count; $j <= round($averageattempt); $j++) {
                $attemptnum[] = $j;
            }
        }
        return [
            'data' => [
                'labels' => $attemptnum,
                'datasets' => [
                    [
                        'label' => get_string('cutOffscore', 'gradereport_quizanalytics'),
                        'borderColor' => "#3e95cd",
                        'data' => $cutoffarray,
                        'fill' => true,
                    ],
                    [
                        'label' => get_string('score', 'gradereport_quizanalytics'),
                        'borderColor' => "#8e5ea2",
                        'data' => $scored,
                        'fill' => false,
                    ],
                ],
            ],
            'opt' => [
                'title' => [
                    'display' => true, 'position' => 'bottom',
                    'text' => get_string('impandpredicanalysis', 'gradereport_quizanalytics'),
                ],
                'legend' => ['display' => true, 'position' => 'bottom', 'labels' => ['boxWidth' => 13]],
            ],
        ];
    }

    /**
     * Builds the "scores by percentage" pie chart: how many users' grades fall into each
     * percentage band, using either the site-wide grade boundaries or this quiz's own feedback
     * bands, depending on the "Set Globally" setting.
     *
     * @param \moodle_database $db
     * @param \stdClass $cfg
     * @param \stdClass $quiz
     * @param int $quizid
     * @return array 'data'/'opt' pair.
     */
    private static function build_grade_analysis($db, $cfg, $quiz, $quizid): array {
        $sql = "SELECT COUNT(qg.id) as numofstudents
                  FROM {quiz_grades} qg, {quiz} q
                 WHERE q.id = qg.quiz AND qg.quiz = ? AND qg.grade BETWEEN ? AND ?";
        $chartdata = $chartlabels = $randomcolor = [];
        if ($cfg->gradereport_quizanalytics_globalboundary == 1) {
            $gradeboundaries = explode(",", ($cfg->gradereport_quizanalytics_gradeboundary));
            foreach ($gradeboundaries as $gradeboundary) {
                $grades = explode("-", $gradeboundary);
                $mingrade = ($grades[0] * $quiz->grade) / 100;
                $maxgrade = ($grades[1] * $quiz->grade) / 100;
                $chartlabels[] = $mingrade . " - " . $maxgrade;
                $randomcolor[] = "#" . self::random_color();
                $userrecords = $db->get_record_sql($sql, [$quizid, $mingrade, $maxgrade]);
                $chartdata[] = $userrecords->numofstudents;
            }
        } else {
            $feedbackrecs = $db->get_records_sql("SELECT id, mingrade, maxgrade FROM {quiz_feedback} WHERE quizid = ?", [$quizid]);
            foreach ($feedbackrecs as $feedbackrec) {
                $mingrade = round($feedbackrec->mingrade);
                $maxgrade = round($feedbackrec->maxgrade) - 1;
                $chartlabels[] = $mingrade . " - " . $maxgrade;
                $randomcolor[] = "#" . self::random_color();
                $userrecords = $db->get_record_sql($sql, [$quizid, $mingrade, $maxgrade]);
                $chartdata[] = $userrecords->numofstudents;
            }
        }
        return [
            'data' => ['labels' => $chartlabels, 'datasets' => [
                [
                    'label' => get_string('noofstudents', 'gradereport_quizanalytics'),
                    'backgroundColor' => $randomcolor, 'data' => $chartdata,
                ],
            ]],
            'opt' => [
                'title' => [
                    'display' => true,
                    'text' => get_string('noofstudents', 'gradereport_quizanalytics'), 'position' => 'bottom',
                ],
                'legend' => ['display' => false, 'position' => 'bottom', 'labels' => ['boxWidth' => 13]],
            ],
        ];
    }

    /**
     * Computes, for each fixed (non-random) question in the quiz, how many attempts answered it
     * correctly, incorrectly, or partially, and how many left it unattempted.
     *
     * @param \moodle_database $db
     * @param int $quizid
     * @param array $totalquestions Fixed-slot questions from qbank_helper::get_question_structure().
     * @param int $totalattemptscount Count of all finished, graded attempts at this quiz.
     * @return array
     */
    private static function compute_question_response_stats($db, $quizid, $totalquestions, $totalattemptscount): array {
        $sql = "SELECT COUNT(qatt.id) as qnum
                  FROM {question_attempts} qatt, {quiz_attempts} quizatt, {question_attempt_steps} qas
                 WHERE qas.questionattemptid = qatt.id AND quizatt.uniqueid = qatt.questionusageid
                   AND qas.sequencenumber = ? AND quizatt.sumgrades IS NOT NULL AND quizatt.quiz= ?
                   AND qatt.questionid = ? AND";
        $userunattempted = $correctresponse = $incorrectresponse = $partialresponse = [];
        $questionlabels = $negativeattemptd = $queshardness = $selectedquestionid = [];
        $count = 1;
        foreach ($totalquestions as $totalquestion) {
            $sequencenumber = $totalquestion->qtype == 'essay' ? 3 : 2;
            $correctcount = $db->get_record_sql(
                $sql . " (qas.state = 'gradedright' OR qas.state = 'mangrright')",
                [$sequencenumber, $quizid, $totalquestion->questionid]
            )->qnum;
            $incorrectcount = $db->get_record_sql(
                $sql . " (qas.state = 'gradedwrong' OR qas.state = 'mangrwrong')",
                [$sequencenumber, $quizid, $totalquestion->questionid]
            )->qnum;
            $partialcount = $db->get_record_sql(
                $sql . " (qas.state = 'gradedpartial' OR qas.state = 'mangrpartial')",
                [$sequencenumber, $quizid, $totalquestion->questionid]
            )->qnum;
            $unattempted = $totalattemptscount - ($correctcount + $incorrectcount + $partialcount);
            $userunattempted[] = $unattempted;
            $correctresponse[] = $correctcount;
            $incorrectresponse[] = $incorrectcount;
            $partialresponse[] = $partialcount;
            $questionlabels[] = "Q" . $count;
            $negativeattemptd[] = $unattempted + $incorrectcount;
            $queshardness[] = round((($unattempted + $incorrectcount) / $totalattemptscount) * 100, 2);
            $selectedquestionid[] = "Q" . $count . "," . $totalquestion->questionid;
            $count++;
        }
        return [
            'questionlabels' => $questionlabels,
            'correctresponse' => $correctresponse,
            'incorrectresponse' => $incorrectresponse,
            'partialresponse' => $partialresponse,
            'userunattempted' => $userunattempted,
            'negativeattemptd' => $negativeattemptd,
            'queshardness' => $queshardness,
            'selectedquestionid' => $selectedquestionid,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_value
     */
    public static function execute_returns(): external_value {
        return new external_value(PARAM_RAW, 'The updated JSON output');
    }
}
