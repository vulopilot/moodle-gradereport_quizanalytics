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
        $totalnoofquestion = $DB->get_record_sql(
            "SELECT COUNT(q.id) as qnum
               FROM {quiz_slots} qs, {question} q, {question_references} qr,
                    {question_bank_entries} qbe, {question_versions} qv
              WHERE qr.component = 'mod_quiz' AND qr.questionarea = 'slot' AND qr.itemid = qs.id
                AND qbe.id = qr.questionbankentryid AND qv.questionbankentryid = qbe.id
                AND q.id = qv.questionid AND qs.quizid = ? AND q.qtype != ?",
            [$quizid, 'description']
        );
        $sql = "SELECT qc.id, COUNT(q.id) as qnum, qc.name
                  FROM {quiz_slots} qs, {question} q, {question_categories} qc, {question_references} qr,
                       {question_bank_entries} qbe, {question_versions} qv
                 WHERE qr.component = 'mod_quiz' AND qr.questionarea = 'slot' AND qr.itemid = qs.id
                   AND qbe.id = qr.questionbankentryid AND qv.questionbankentryid = qbe.id
                   AND q.id = qv.questionid AND qbe.questioncategoryid = qc.id
                   AND qs.quizid = ? AND q.qtype != ?
              GROUP BY qc.id";
        $categorys = $DB->get_records_sql($sql, [$quizid, 'description']);
        $sql = "SELECT qattstep.id as qattstepid, quizatt.id as quizattid, qatt.questionid,
                       qattstep.state, qattstep.sequencenumber
                  FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep,
                       {question} q, {question_categories} qc, {question_bank_entries} qbe
                 WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.questionattemptid = qatt.id
                   AND q.id = qatt.questionid AND qbe.questioncategoryid = qc.id AND qbe.id = q.id
                   AND quizatt.quiz = ? AND qattstep.questionattemptid = ? AND q.qtype != ?
                   AND qattstep.sequencenumber >= 2
                   AND (qattstep.state = 'gradedright' OR qattstep.state = 'mangrright')";
        $categoryname = $chartdata = $randomcolor = [];
        $wrongattemts = $userswrongattemts = $overallhardness = $userhardness = [];
        foreach ($categorys as $category) {
            $categoryname[] = empty($category->name) ? 'category' : $category->name;
            $chartdata[] = empty($category->qnum) ? 1 : ($category->qnum);
            $randomcolor[] = "#" . self::random_color();
            $correctattempts = $DB->get_records_sql($sql, [$quizid, $category->id, 'description']);
            $userscorrectattempts = $DB->get_records_sql(
                $sql . " AND quizatt.userid = ?",
                [$quizid, $category->id, 'description', $userid]
            );
            $categoryattempts = $category->qnum * count($totalquizattempted);
            $categoryuserattempts = $category->qnum * count($usersgradedattempts);
            $wrongattemts[] = ($categoryattempts - count($correctattempts));
            $userswrongattemts[] = ($categoryuserattempts - count($userscorrectattempts));
            $overallhardness[] = round(((($categoryattempts - count($correctattempts)) / $categoryattempts) * 100), 2);
            $userhardness[] = round(((($categoryuserattempts - count($userscorrectattempts)) / $categoryuserattempts) * 100), 2);
        }
        /* questionpercat */
        $questionpercategorydata = ['labels' => $categoryname, 'datasets' => [[
            'label' => get_string('questionspercategory', 'gradereport_quizanalytics'),
            'backgroundColor' => $randomcolor, 'data' => $chartdata,
        ]]];
        $questionpercategoryopt = ['legend' => [
            'display' => false,
            'position' => 'bottom', 'labels' => ['boxWidth' => 13],
        ], 'title' => [
            'display' => true,
            'position' => 'bottom', 'text' => get_string('questionspercategory', 'gradereport_quizanalytics'),
        ]];
        /* allusers */
        arsort($overallhardness);
        $maxhardnesskeys = array_keys($overallhardness, max($overallhardness));
        foreach ($maxhardnesskeys as $maxhardnesskey) {
            $previous = $maxhardnesskey;
            break;
        }
        $count = 0;
        $randomcolor = $chartdata = $chartlabels = [];
        foreach ($overallhardness as $key => $val) {
            if ($wrongattemts[$key] > 0) {
                if ($wrongattemts[$key] >= (($wrongattemts[$previous] * 20) / 100)) {
                    if ($count < 10) {
                        $chartdata[] = $val;
                        $chartlabels[] = $categoryname[$key];
                        $randomcolor[] = "#" . self::random_color();
                        $count++;
                    }
                }
            }
            $previous = $key;
        }
        $allusersdata = [
            'labels' => $chartlabels, 'datasets' => [
                [
                    'label' => get_string('hardness', 'gradereport_quizanalytics'),
                    'backgroundColor' => $randomcolor,
                    'data' => $chartdata,
                ],
            ],
        ];
        $allusersopt = ['legend' => [
            'display' => false,
            'position' => 'bottom',
        ], 'title' => [
            'display' => false,
            'position' => 'bottom', 'text' => get_string('hardcatalluser', 'gradereport_quizanalytics'),
        ]];
        /* loggedinuser */
        arsort($userhardness);
        $hardnesskeys = array_keys($userhardness, max($userhardness));
        foreach ($hardnesskeys as $hardnesskey) {
            $previouskey = $hardnesskey;
            break;
        }
        $count = 0;
        $randomcolor = $chartdata = $chartlabels = [];
        foreach ($userhardness as $key => $val) {
            if ($userswrongattemts[$key] > 0) {
                if ($userswrongattemts[$key] >= (($userswrongattemts[$previouskey] * 20) / 100)) {
                    if ($count < 10) {
                        $chartdata[] = $val;
                        $chartlabels[] = $categoryname[$key];
                        $randomcolor[] = "#" . self::random_color();
                        $count++;
                    }
                }
            }
            $previouskey = $key;
        }
        $loggedinuserdata = [
            'labels' => $chartlabels, 'datasets' => [
                [
                    'label' => get_string('hardness', 'gradereport_quizanalytics'),
                    'backgroundColor' => $randomcolor, 'data' => $chartdata,
                ],
            ],
        ];
        $loggedinuseropt = ['legend' => [
            'display' => false,
            'position' => 'bottom',
        ], 'title' => [
            'display' => false,
            'position' => 'bottom', 'text' => get_string('hardcatlogginuser', 'gradereport_quizanalytics'),
        ]];
        /* lastattemptsummary */
        $lastattemptid = $DB->get_record_sql(
            "SELECT quizatt.id
               FROM {quiz_attempts} quizatt
              WHERE quizatt.state = 'finished' AND quizatt.sumgrades IS NOT NULL
                AND quizatt.quiz = ? AND quizatt.userid= ?
           ORDER BY quizatt.id DESC LIMIT 1",
            [$quizid, $userid]
        );
        $sql = "SELECT qatt.questionid, qattstep.state, qattstep.fraction, qatt.maxmark
                  FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep
                 WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.questionattemptid = qatt.id
                   AND quizatt.userid = ? AND quizatt.id = ? AND quizatt.quiz = ? ";
        $totalattempted = $DB->get_records_sql($sql . " AND qattstep.sequencenumber = 2", [$userid, $lastattemptid->id, $quizid]);
        $rightattempt = $DB->get_records_sql(
            $sql . " AND (qattstep.state = 'gradedright' OR qattstep.state = 'mangrright')",
            [$userid, $lastattemptid->id, $quizid]
        );
        $partialcorrectattempt = $DB->get_records_sql(
            $sql . " AND (qattstep.state = 'gradedpartial' OR qattstep.state = 'mangrpartial')",
            [$userid, $lastattemptid->id, $quizid]
        );
        $count = $totaluserscores = $totalquesmarks = 0;
        if (!empty($partialcorrectattempt)) {
            foreach ($partialcorrectattempt as $partialcorrect) {
                $count++;
                $totaluserscores = $totaluserscores + $partialcorrect->fraction;
                $totalquesmarks = $totalquesmarks + $partialcorrect->maxmark;
            }
            $partiallycorrect = $count * ((($totaluserscores / $totalquesmarks) * 100) / 100);
        } else {
            $partiallycorrect = 0;
        }
        if (!empty($totalattempted)) {
            $accuracyrate = ((count($rightattempt) + round($partiallycorrect)) / count($totalattempted)) * 100;
        } else {
            $accuracyrate = 0;
        }
        if (count($totalattempted) != 0) {
            if (count($partialcorrectattempt) != 0) {
                $lastattemptsummarydata = [
                    'labels' => [
                        get_string('noofquestionattempt', 'gradereport_quizanalytics'),
                        get_string('noofrightans', 'gradereport_quizanalytics'),
                        get_string('noofpartialcorrect', 'gradereport_quizanalytics'),
                    ],
                    'datasets' => [[
                        'backgroundColor' => ["#2EA0EF", "#79D527", "#FF9827"],
                        'data' => [
                            count($totalattempted), count($rightattempt),
                            count($partialcorrectattempt),
                        ],
                    ]],
                ];
            } else {
                $lastattemptsummarydata = [
                    'labels' => [
                        get_string('noofquestionattempt', 'gradereport_quizanalytics'),
                        get_string('noofrightans', 'gradereport_quizanalytics'),
                    ],
                    'datasets' => [[
                        'backgroundColor' => ["#2EA0EF", "#79D527"],
                        'data' => [count($totalattempted), count($rightattempt)],
                    ]],
                ];
            }
            $lastattemptsummaryopt = [
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
            ];
        }
        /* attemptssnapshot */
        if (!empty($usersgradedattempts)) {
            $count = 1;
            $sql = "SELECT COUNT(qatt.questionid) as num
                      FROM {quiz_attempts} quizatt, {question_attempts} qatt,
                           {question_attempt_steps} qattstep, {question} q
                     WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.sequencenumber = 2
                       AND q.id = qatt.questionid AND qattstep.questionattemptid = qatt.id
                       AND quizatt.userid = ? AND quizatt.quiz= ? AND q.qtype != ?
                       AND quizatt.attempt = ? AND qattstep.state = ?";
            foreach ($usersgradedattempts as $attemptvalue) {
                $numofattempt = $DB->get_record_sql(
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
                $correct = $DB->get_record_sql($sql, [$userid, $quizid, 'description', $attemptvalue->attempt, 'gradedright']);
                $incorrect = $DB->get_record_sql($sql, [$userid, $quizid, 'description', $attemptvalue->attempt, 'gradedwrong']);
                $partialcorrect = $DB->get_record_sql(
                    $sql,
                    [$userid, $quizid, 'description', $attemptvalue->attempt, 'gradedpartial']
                );
                $snapdata[$count][0] = intval($unattempt);
                $snapdata[$count][1] = intval($correct->num);
                $snapdata[$count][2] = intval($incorrect->num);
                $snapdata[$count][3] = intval($partialcorrect->num);
                $snapshotdata[$count] = [
                    'labels' => [
                        get_string('unattempted', 'gradereport_quizanalytics'),
                        get_string('correct', 'gradereport_quizanalytics'),
                        get_string('incorrect', 'gradereport_quizanalytics'),
                        get_string('partialcorrect', 'gradereport_quizanalytics'),
                    ],
                    'datasets' => [[
                        'label' => 'Attempt' . $count,
                        'backgroundColor' => ['#3e95cd', '#8e5ea2', '#3cba9f', '#e8c3b9'],
                        'data' => $snapdata[$count],
                    ]],
                ];
                $snapshotopt[$count] = [
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
        } else {
            $snapshotdata[1] = [
                'labels' => [
                    get_string('unattempted', 'gradereport_quizanalytics'),
                    get_string('correct', 'gradereport_quizanalytics'),
                    get_string('incorrect', 'gradereport_quizanalytics'),
                    get_string('partialcorrect', 'gradereport_quizanalytics'),
                ],
                'datasets' => [[
                    'label' => 'Attempt1',
                    'backgroundColor' => ['#3e95cd', '#8e5ea2', '#3cba9f', '#e8c3b9'],
                    'data' => [0, 0, 0, 0],
                ]],
            ];
            $snapshotopt[1] = ['title' => [
                'display' => true,
                'position' => 'bottom', 'text' => 'Attempts Snapshot( timetaken: 0min )',
            ]];
        }
        /* timechart */
        if ($quiz->attempts == 1) {
            $scores = $scoredata = [];
            foreach ($totalquizattempted as $totalquizattempt) {
                $scores[] = ($totalquizattempt->sumgrades / $quiz->sumgrades) * 100;
            }
            $userscore = $DB->get_record('quiz_attempts', ['quiz' => $quizid, 'userid' => $userid]);
            $userscoredata = ($userscore->sumgrades / $quiz->sumgrades) * 100;
            $scoredata[0] = round($userscoredata, 2);
            $scoredata[1] = round(max($scores), 2);
            $scoredata[2] = round((array_sum($scores) / count($scores)), 2);
            $scoredata[3] = round(min($scores), 2);
            $timechartdata = [
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
            ];
            $timechartopt = [
                'showTooltips' => false,
                'legend' => ['display' => false],
                'title' => ['display' => true, 'text' => get_string('peerscores', 'gradereport_quizanalytics')],
            ];
        }
        /* mixchart */
        $attemptcutoff = $DB->get_records_sql(
            "SELECT * FROM {quiz_attempts}
              WHERE state = 'finished' AND sumgrades IS NOT NULL AND quiz = ? AND sumgrades >= ?
           GROUP BY userid",
            [$quizid, (($quiz->sumgrades * $CFG->gradereport_quizanalytics_cutoff) / 100)]
        );
        foreach ($attemptcutoff as $torichcutoff) {
            $attemptresult[] = $torichcutoff->attempt;
        }
        if (!empty($attemptresult)) {
            $averageattempt = array_sum($attemptresult) / count($attemptresult);
        } else {
            $averageattempt = 0;
        }
        $usersattempts = $DB->get_records_sql(
            "SELECT * FROM {quiz_attempts} WHERE state = 'finished' AND quiz = ? AND userid = ?",
            [$quizid, $userid]
        );
        $attemptnum = $scored = [0];
        $count = 1;
        foreach ($usersattempts as $usersattempt) {
            if (!empty($usersattempt->sumgrades)) {
                array_push($attemptnum, $count);
                array_push($scored, round($usersattempt->sumgrades, 2));
            } else {
                array_push($attemptnum, $count . '(NG)');
                array_push($scored, 0);
            }
            $count++;
        }
        for ($i = 0; $i < $count; $i++) {
            $cutoffarray[] = round((($quiz->sumgrades * $CFG->gradereport_quizanalytics_cutoff) / 100), 2);
        }
        if (round($averageattempt) >= $count) {
            for ($j = $count; $j <= round($averageattempt); $j++) {
                array_push($attemptnum, $j);
            }
        }
        $mixchartdata = [
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
        ];
        $mixchartopt = [
            'title' => [
                'display' => true, 'position' => 'bottom',
                'text' => get_string('impandpredicanalysis', 'gradereport_quizanalytics'),
            ],
            'legend' => ['display' => true, 'position' => 'bottom', 'labels' => ['boxWidth' => 13]],
        ];
        /* gradeanalysis */
        $chartdata = $chartlabels = [];
        $sql = "SELECT COUNT(qg.id) as numofstudents
                  FROM {quiz_grades} qg, {quiz} q
                 WHERE q.id = qg.quiz AND qg.quiz = ? AND qg.grade BETWEEN ? AND ?";
        if ($CFG->gradereport_quizanalytics_globalboundary == 1) {
            $gradeboundaries = explode(",", ($CFG->gradereport_quizanalytics_gradeboundary));
            if (!empty($gradeboundaries)) {
                foreach ($gradeboundaries as $gradeboundary) {
                    $grades = explode("-", $gradeboundary);
                    $mingrade = ($grades[0] * $quiz->grade) / 100;
                    $maxgrade = ($grades[1] * $quiz->grade) / 100;
                    $chartlabels[] = $mingrade . " - " . $maxgrade;
                    $randomcolor[] = "#" . self::random_color();
                    $userrecords = $DB->get_record_sql($sql, [$quizid, $mingrade, $maxgrade]);
                    $chartdata[] = $userrecords->numofstudents;
                }
            }
        } else {
            $feedbackrecs = $DB->get_records_sql("SELECT id, mingrade, maxgrade FROM {quiz_feedback} WHERE quizid = ?", [$quizid]);
            foreach ($feedbackrecs as $feedbackrec) {
                $mingrade = round($feedbackrec->mingrade);
                $maxgrade = round($feedbackrec->maxgrade) - 1;
                $chartlabels[] = $mingrade . " - " . $maxgrade;
                $randomcolor[] = "#" . self::random_color();
                $userrecords = $DB->get_record_sql($sql, [$quizid, $mingrade, $maxgrade]);
                $chartdata[] = $userrecords->numofstudents;
            }
        }
        $gradeanalysisdata = ['labels' => $chartlabels, 'datasets' => [
            [
                'label' => get_string('noofstudents', 'gradereport_quizanalytics'),
                'backgroundColor' => $randomcolor, 'data' => $chartdata,
            ],
        ]];
        $gradeanalysisopt = [
            'title' => [
                'display' => true,
                'text' => get_string('noofstudents', 'gradereport_quizanalytics'), 'position' => 'bottom',
            ],
            'legend' => ['display' => false, 'position' => 'bottom', 'labels' => ['boxWidth' => 13]],
        ];
        /* quesanalysis */
        $totalquestions = $DB->get_records_sql(
            "SELECT qs.id, q.qtype
               FROM {quiz_slots} qs, {question} q, {question_references} qr,
                    {question_bank_entries} qbe, {question_versions} qv
              WHERE qr.component = 'mod_quiz' AND qr.questionarea = 'slot' AND qr.itemid = qs.id
                AND qbe.id = qr.questionbankentryid AND qv.questionbankentryid = qbe.id
                AND q.id = qv.questionid AND qs.quizid= ? AND q.qtype != ?",
            [$quizid, 'description']
        );
        $count = 1;
        $sql = "SELECT COUNT(qatt.id) as qnum
                  FROM {question_attempts} qatt, {quiz_attempts} quizatt, {question_attempt_steps} qas
                 WHERE qas.questionattemptid = qatt.id AND quizatt.uniqueid = qatt.questionusageid
                   AND qas.sequencenumber = ? AND quizatt.sumgrades <> 'NULL' AND quizatt.quiz= ?
                   AND qatt.questionid = ? AND";
        $userunattempted = $correctresponse = $incorrectresponse = $partialresponse = [];
        $questionlabels = $negativeattemptd = $queshardness = $selectedquestionid = [];
        foreach ($totalquestions as $totalquestion) {
            if ($totalquestion->qtype == 'essay') {
                $sequencenumber = 3;
            } else {
                $sequencenumber = 2;
            }
            $usercorrectresponse = $DB->get_record_sql(
                $sql . " (qas.state = 'gradedright' OR qas.state = 'mangrright')",
                [$sequencenumber, $quizid, $totalquestion->id, $userid]
            );
            $userincorrectresponse = $DB->get_record_sql(
                $sql . " (qas.state = 'gradedwrong' OR qas.state = 'mangrwrong')",
                [$sequencenumber, $quizid, $totalquestion->id, $userid]
            );
            $userpartialresponse = $DB->get_record_sql(
                $sql . " (qas.state = 'gradedpartial' OR qas.state = 'mangrpartial')",
                [$sequencenumber, $quizid, $totalquestion->id, $userid]
            );
            $respondedcount = $usercorrectresponse->qnum + $userincorrectresponse->qnum + $userpartialresponse->qnum;
            $userunattempted[] = count($usersgradedattempts) - $respondedcount;
            $unattempted = count($totalquizattempted) - $respondedcount;
            $correctresponse[] = $usercorrectresponse->qnum;
            $incorrectresponse[] = $userincorrectresponse->qnum;
            $partialresponse[] = $userpartialresponse->qnum;
            $questionlabels[] = "Q" . $count;
            $negativeattemptd[] = $unattempted + $userincorrectresponse->qnum;
            $queshardness[] = round((($unattempted + $userincorrectresponse->qnum) / count($totalquizattempted)) * 100, 2);
            $selectedquestionid[] = "Q" . $count . "," . $totalquestion->id;
            $count++;
        }
        arsort($queshardness);
        $maxhardness = array_keys($queshardness, max($queshardness));
        foreach ($maxhardness as $maxhardnesskey) {
            $previous = $maxhardnesskey;
            break;
        }
        $count = 0;
        foreach ($queshardness as $key => $val) {
            if ($negativeattemptd[$key] > 0) {
                if ($negativeattemptd[$key] >= (($negativeattemptd[$previous] * 20) / 100)) {
                    if ($count < 10) {
                        $hardestquesdatalabel[] = $questionlabels[$key];
                        $totalquizattemptdata[] = count($totalquizattempted);
                        $negativeattemptdata[] = $negativeattemptd[$key];
                        $count++;
                    }
                }
            }
            $previous = $key;
        }
        $hardestquesdata = ['labels' => $hardestquesdatalabel, 'datasets' => [
            [
                'label' => get_string('totalquizattempt', 'gradereport_quizanalytics'),
                'backgroundColor' => "#8e5ea2", 'data' => $totalquizattemptdata,
            ],
            [
                'label' => get_string('wrongandunattemptd', 'gradereport_quizanalytics'),
                'backgroundColor' => "#EB2838", 'data' => $negativeattemptdata,
            ],
        ]];
        $hardestquesopt = [
            'title' => [
                'display' => false,
                'text' => get_string('hardestquestionschart', 'gradereport_quizanalytics'),
            ],
            'legend' => ['display' => true, 'position' => 'bottom', 'labels' => ['boxWidth' => 13]],
            'barPercentage' => 1.0,
            'categoryPercentage' => 1.0,
        ];
        /*Quesanalysis*/
        $quesanalysisdata = ['labels' => $questionlabels, 'datasets' => [
            [
                'data' => $correctresponse, 'borderColor' => "#3e95cd", 'fill' => false,
                'label' => get_string('correct', 'gradereport_quizanalytics'),
            ],
            [
                'data' => $incorrectresponse, 'borderColor' => "#8e5ea2", 'fill' => false,
                'label' => get_string('incorrect', 'gradereport_quizanalytics'),
            ],
            [
                'data' => $partialresponse, 'borderColor' => "#3cba9f",
                'fill' => false, 'label' => get_string('partialcorrect', 'gradereport_quizanalytics'),
            ],
            [
                'data' => $userunattempted, 'borderColor' => "#c45850", 'fill' => false,
                'label' => get_string('unattempted', 'gradereport_quizanalytics'),
            ],
        ]];
        $quesanalysisopt = [
            'title' => ['display' => false],
            'legend' => ['display' => true, 'position' => 'bottom', 'labels' => ['boxWidth' => 13]],
        ];
        $totalarray = [
            'questionPerCategories' => [
                'data' => $questionpercategorydata,
                'opt' => $questionpercategoryopt,
            ],
            'allUsers' => [
                'data' => $allusersdata,
                'opt' => $allusersopt,
            ],
            'loggedInUser' => [
                'data' => $loggedinuserdata,
                'opt' => $loggedinuseropt,
            ],
            'lastAttemptSummary' => [
                'data' => $lastattemptsummarydata,
                'opt' => $lastattemptsummaryopt,
            ],
            'attemptssnapshot' => [
                'data' => $snapshotdata,
                'opt' => $snapshotopt,
            ],
            'mixChart' => [
                'data' => $mixchartdata,
                'opt' => $mixchartopt,
            ],
            'timeChart' => [
                'data' => $timechartdata,
                'opt' => $timechartopt,
            ],
            'gradeAnalysis' => [
                'data' => $gradeanalysisdata,
                'opt' => $gradeanalysisopt,
            ],
            'quesAnalysis' => [
                'data' => $quesanalysisdata,
                'opt' => $quesanalysisopt,
            ],
            'hardestQuestions' => [
                'data' => $hardestquesdata,
                'opt' => $hardestquesopt,
            ],
            'userAttempts' => count($usersgradedattempts),
            'quizAttempt' => $quiz->attempts,
            'allQuestions' => $selectedquestionid,
            'quizid' => $quizid,
            'lastUserQuizAttemptID' => $lastattemptid->id,
            'url' => $CFG->wwwroot,
        ];
        return json_encode($totalarray);
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
