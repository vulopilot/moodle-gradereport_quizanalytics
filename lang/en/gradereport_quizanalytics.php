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
 * Strings for component 'gradereport_quizanalytics', language 'en'
 *
 * @package   gradereport_quizanalytics
 * @author DualCube <admin@dualcube.com>
 * @copyright Dualcube (https://dualcube.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Index.
$string['action'] = 'Action';
$string['attemptsummary'] = 'Attempt Summary';
$string['student_select'] = 'Student';
$string['user_select'] = 'Select User';
$string['myprogress'] = 'My Progress and Predictions';
$string['questioncategory'] = 'Question Categories&#39; Analysis';
$string['questionstats'] = 'Scores&#39; & Questions&#39; Stats';
$string['viewanalytics'] = 'View Analytics';
$string['quesanalysis'] = 'Question Analysis';
$string['noofstudents'] = 'Number of Students';
$string['quizname'] = 'Quiz Name';
$string['noofattempts'] = 'Number of Attempts';
$string['noquizfound'] = 'No Quiz Found!';
$string['notgraded'] = 'Not yet Graded';
$string['pluginname'] = 'Quiz Analytics';
$string['lastattemptsummarydes'] = 'This graph depicts how you fared in the last attempt.';
$string['attemptsummarydes'] = 'This graph depicts how you fared in your attempt.';
$string['improvementcurve'] = 'Improvement Curve';
$string['peerperformance'] = 'Peer Performance';
$string['hardestquestion'] = 'Hardest Question';
$string['attemptsnapshot'] = 'Attempt Snapshot';
$string['mixchartdes'] = 'This graph shows how you improved over all your attempts and the dark block represents the no of average attempts required to touch the score set as cut off (by site admin). The x-axis (Horizontal) represents the number of attempts.';
$string['timechartdes'] = 'This graph shows how your peers have scored in comparison to you.';
$string['hardestquesdes'] = '*Total Attempts = Total number of quiz attempts.
    *Unsuccessful Attempts = Total wrong attempts + Number of Not Answered cases.
    This graph represnts the toughness of questions depending on how many times the quiz was attempted and the times that particular question is left unattempted or incorrectly attempted. Clicking on the bars dedicated to each question will show the question itself along with explanation and correct answer.';
$string['attemptssnapshotdes'] = 'This section is like a recap, displaying the key figures of all your previous attempt.';
$string['questionpercategory'] = 'Question Per Category';
$string['challengingcategoris'] = 'Challenging Categories (Across All Users)';
$string['challengingcategorisforme'] = 'Challenging Categories for me';
$string['questionpercatdes'] = 'This graph tells you the number of questions present in the quiz from each category.';
$string['allusersdes'] = 'This section reports on the basis of wrong and unattempted cases, the categories that turned out to be most challenging ones across all the users who took the quiz.';
$string['loggedinuserdes'] = 'Lets have a look the categories you have fared not so well.';
$string['scorbrpercent'] = 'Scores by Percentage (All Users)';
$string['gradeanalysisdes'] = 'Number of users in each percentage (score percentage) group.';
$string['quesananalysisdes'] = 'The curves here depict how the users fared in each question. Clicking on the circles dedicated to each question will show the question itself along with explanation and correct answer.';
// Settings.
$string['gradeboundary'] = 'Grade Boundary';
$string['gradeboundarydes'] = "The grade boundaries are ranges set by the admin which will be displayed on the scores's stats graph.";
$string['setglobal'] = 'Set Globally';
$string['setglobaldes'] = 'Set Grade Boundary For All Quiz. This when checked rewrites the individual grade boundary settings for each quiz.';
$string['setcutoff'] = 'Set Cut Off';
$string['cutoffdes'] = 'Set Cut Off percentage (%) for all the quiz. This will be used for predictive analysis graph.';
$string['visibleanalytics'] = 'Visible analytics';
$string['showattemptsummarytab'] = 'Show Attempt Summary tab';
$string['showattemptsummarytabdes'] = 'Show the Attempt Summary / Last Attempt Summary tab.';
$string['showmyprogresstab'] = 'Show My Progress and Predictions tab';
$string['showmyprogresstabdes'] = 'Show the My Progress and Predictions tab '
    . '(Improvement Curve, Hardest Question, Attempt Snapshot).';
$string['showquestioncategorytab'] = 'Show Question Categories&#39; Analysis tab';
$string['showquestioncategorytabdes'] = 'Show the Question Categories&#39; Analysis tab.';
$string['showquestionstatstab'] = 'Show Scores&#39; & Questions&#39; Stats tab';
$string['showquestionstatstabdes'] = 'Show the Scores&#39; & Questions&#39; Stats tab.';
$string['customcss'] = 'Custom CSS';
$string['customcssdes'] = 'CSS rules added here are injected on every Quiz Analytics page, after the plugin&#39;s '
    . 'own stylesheet, so they can be used to restyle the report without editing the plugin files - changes made '
    . 'here survive future plugin updates.';
$string['showonreviewpage'] = 'Show on the quiz attempt review page';
$string['showonreviewpagedes'] = 'When enabled, a compact version of the analytics for the reviewed attempt is '
    . 'also shown on the quiz&#39;s own attempt review page, for users who have permission to view it.';
// Externallib.
$string['noofquestionattempt'] = 'Number of Questions Attempted';
$string['noofquestionunattempt'] = 'Number of Questions Unattempted';
$string['noofrightans'] = 'Number of Right Answers';
$string['noofpartialcorrect'] = 'Number of Partial Correct Answers';
$string['noofwronganswers'] = 'Number of Wrong Answers';
$string['accuaracyrate'] = 'Accuracy Rate : ';
$string['impandpredicanalysis'] = 'Improvement Curve & Predictive Analysis';
$string['peerscores'] = 'Peer Scores';
$string['userscore'] = 'User Score';
$string['bestscore'] = 'Best Score';
$string['avgscore'] = 'Average Score';
$string['lowestscore'] = 'Lowest Score';
$string['totalquizattempt'] = 'Total Attempts';
$string['wrongandunattemptd'] = 'Unsuccessful Attempts';
$string['hardestquestionschart'] = 'Hardest Questions';
$string['questionspercategory'] = 'Questions per Category';
$string['hardcatalluser'] = 'Hard Categories (All Users)%';
$string['hardness'] = 'Hardness (%)';
$string['hardcatlogginuser'] = 'Hard Categories (Logged-in User)%';
$string['correct'] = 'Correct';
$string['incorrect'] = 'Incorrect';
$string['partialcorrect'] = 'Partial Correct';
$string['unattempted'] = 'Unattempted';
$string['score'] = 'Score';
$string['timetaken'] = 'Attempts Snapshot (Time Taken: ';
// Analytic.js.
$string['zeroattempt'] = 'Please attempt at least one question.';
$string['hardestcategories'] = 'Hardest Categories';
$string['hardestcategoriespercentage'] = 'Hardness in percentage (%)';
$string['numberofattempts'] = 'Number of Attempts';
$string['cutOffscore'] = 'Cut Off Score';
$string['score'] = 'Score';
$string['questionnumber'] = 'Question Number';
$string['questionreview'] = "(Click to Review Question & Last Attempt)";
