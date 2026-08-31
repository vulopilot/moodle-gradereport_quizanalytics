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
 * Renders the quiz analytics charts and wires up the report's UI.
 *
 * @module     gradereport_quizanalytics/analytic
 * @copyright  Dualcube (https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(
    ['jquery', 'core/ajax', 'core/str', 'gradereport_quizanalytics/chart', 'gradereport_quizanalytics/datatables'],
    function ($, ajax, str, Chart) {
    'use strict';

    // Chart.js v4 moved several v2 option keys to new homes (legend/title now live under
    // plugins, axis config is keyed by x/y instead of xAxes/yAxes arrays). The server (see
    // get_analytics.php) still sends v2-shaped options, so this translates them to v4's shape
    // before they're deep-merged with this file's own v4-format per-chart overrides below.
    // Keeping the translation here, rather than reshaping every option array in PHP, keeps all
    // Chart.js-version-specific logic in the one file that actually loads the library.
    function toV4ChartOptions(v2opt) {
        var v4opt = $.extend(true, {}, v2opt || {});
        var plugins = v4opt.plugins || {};
        if (v4opt.legend !== undefined) {
            plugins.legend = v4opt.legend;
            delete v4opt.legend;
        }
        if (v4opt.title !== undefined) {
            plugins.title = v4opt.title;
            delete v4opt.title;
        }
        if (v4opt.showTooltips !== undefined) {
            plugins.tooltip = $.extend({enabled: v4opt.showTooltips}, plugins.tooltip);
            delete v4opt.showTooltips;
        }
        v4opt.plugins = plugins;

        if (v4opt.scales && (v4opt.scales.xAxes || v4opt.scales.yAxes)) {
            var scales = {};
            (v4opt.scales.xAxes || []).forEach(function (axis) {
                scales.x = toV4Axis(axis);
            });
            (v4opt.scales.yAxes || []).forEach(function (axis) {
                scales.y = toV4Axis(axis);
            });
            v4opt.scales = scales;
        }
        return v4opt;
    }

    function toV4Axis(axis) {
        axis = $.extend(true, {}, axis);
        if (axis.scaleLabel) {
            axis.title = {
                display: axis.scaleLabel.display,
                text: axis.scaleLabel.labelString,
            };
            delete axis.scaleLabel;
        }
        if (axis.ticks) {
            if (axis.ticks.beginAtZero !== undefined) {
                axis.beginAtZero = axis.ticks.beginAtZero;
                delete axis.ticks.beginAtZero;
            }
            if (axis.ticks.max !== undefined) {
                axis.max = axis.ticks.max;
                delete axis.ticks.max;
            }
            if (axis.ticks.min !== undefined) {
                axis.min = axis.ticks.min;
                delete axis.ticks.min;
            }
        }
        return axis;
    }

    // v3+ dropped Chart.prototype.generateLegend() entirely, so the clickable per-slice legend
    // under the doughnut/pie charts below is now built by hand from the chart's own data.
    function buildSliceLegendHtml(chart) {
        var dataset = chart.data.datasets[0];
        var colors = dataset.backgroundColor;
        var html = '<ul>';
        chart.data.labels.forEach(function (label, index) {
            var color = Array.isArray(colors) ? colors[index] : colors;
            html += '<li><span style="background-color:' + color + '"></span>' + label + '</li>';
        });
        html += '</ul>';
        return html;
    }

    return {
        init: function () {
            $("table").DataTable({
                "paging": true,
                "pageLength": 5
            });
        },
        analytic: function () {
            var userID, lastAttemptSummary, loggedInUser, mixChart, allUsers,questionPerCategories, timeChart, gradeAnalysis, quesAnalysis, hardestQuestions, allQuestions, rooturl, lastUserQuizAttemptID;
            var attemptsSnapshotArray = [];
            Chart.register({
                id: 'quizanalyticsCanvasBackground',
                beforeDraw: function (chart) {
                    var chartConvention = chart.ctx;
                    chartConvention.fillStyle = "white";
                    chartConvention.fillRect(0, 0, chart.width, chart.height);
                }
            });
            const userSelects = document.querySelectorAll('.userSelect');
            const viewAnalyticsLinks = document.querySelectorAll(".viewanalytic");
            userSelects.forEach((userSelect) => {
                const viewAnalyticsLink = userSelect.parentNode.parentNode.querySelector(".viewanalytic");
                // Dynamic styling for viewanalytics link based on .userSelect
                if (viewAnalyticsLink && userSelect) {
                    userSelect.addEventListener("change", function () {
                        if (userSelect.value === '-1') {
                            viewAnalyticsLink.style.pointerEvents = 'none';
                            viewAnalyticsLink.style.color = '#999';
                        }
                        else {
                            viewAnalyticsLink.style.pointerEvents = 'auto';
                            viewAnalyticsLink.style.color = '';
                        }
                    });
                }
            });
            $(".viewanalytic").click(function () {
                var quizid = $(this).data('quiz_id');
                const [viewAnalytics] = $(this);
                const userSelect = viewAnalytics.parentNode.parentNode.querySelector(".userSelect");
                const linkuserid = $(this).data('user_id');
                userID = userSelect ? userSelect.value : (linkuserid !== undefined ? linkuserid : -1);
                var promises = ajax.call([
                    {
                        methodname: 'moodle_quizanalytics_analytic',
                        args: {
                            quizid: quizid,
                            user_id: userID
                        },
                    }
                ]);
                promises[0].done(function (data) {
                    var totalData = JSON.parse(data);
                    if (totalData) {
                        var stringFetch =[
                                {key:'zeroattempt', component:'gradereport_quizanalytics'},
                                {key:'hardestcategories', component:'gradereport_quizanalytics'},
                                {key:'hardestcategoriespercentage', component:'gradereport_quizanalytics'},
                                {key:'numberofattempts', component:'gradereport_quizanalytics'},
                                {key:'cutOffscore', component:'gradereport_quizanalytics'},
                                {key:'score', component:'gradereport_quizanalytics'},
                                {key:'questionnumber', component:'gradereport_quizanalytics'},
                                {key:'questionreview', component:'gradereport_quizanalytics'},
                            ];
                        allQuestions = totalData.allQuestions.length == 0 ? console.log(totalData) : totalData.allQuestions;
                        if(totalData.quizid)
                        quizid = totalData.quizid;
                        if(totalData.url)
                        rooturl = totalData.url;
                        if(totalData.lastUserQuizAttemptID)
                        lastUserQuizAttemptID = totalData.lastUserQuizAttemptID;
                        $("#page-grade-report-quizanalytics-index").find(".btn-navbar").on("click",function() {
                            $(this).toggleClass("active-drop");
                            if ($(this).hasClass("active-drop")) {
                                $("#page-grade-report-quizanalytics-index").find(".nav-collapse").show();
                            } else {
                                $("#page-grade-report-quizanalytics-index").find(".nav-collapse").hide();
                            }
                        });
                        $("#page-grade-report-quizanalytics-index").find(".nav").find(".dropdown").on('click', function (event) {
                            $(this).toggleClass('open');
                        });
                        $("#page-grade-report-quizanalytics-index").find(".nav").find(".dropdown").find('.dropdown-menu').find('.dropdown-submenu ').on("click",function(event) {
                            event.preventDefault();
                            event.stopPropagation();
                            $(this).toggleClass('open');
                        });
                        $("#page-grade-report-quizanalytics-index").find(".nav").find(".dropdown").find('.dropdown-menu').find('.dropdown-submenu ').find('ul').find('li').find('a').on("click",function(event) {
                            event.preventDefault();
                            event.stopPropagation();
                            window.open($(this).attr('href'), '_self');
                        });
                        $(".showanalytics").find(".parentTabs").find("span.last-attempt").hide();
                        $(".showanalytics").find("#tabs-1").find("p.last-attempt-des").hide();
                        $(".showanalytics").find("#tabs-1").find("p.attempt-des").show();
                        if (totalData.userAttempts > 1) {
                            $(".showanalytics").find(".parentTabs").find("span.last-attempt").show();
                            $(".showanalytics").find("#tabs-1").find("p.last-attempt-des").show();
                            $(".showanalytics").find("#tabs-1").find("p.attempt-des").hide();
                        }
                        setTimeout(function () {
                            $(".showanalytics").find("ul.nav-tabs a").click(function () {
                                $(this).tab('show');
                                // Center scroll on mobile.
                                if ($(window).width() < 480) {
                                    var outerContent = $('.mobile-overflow');
                                    var innerContent = $('.canvas-wrap');
                                    if (outerContent.length > 0) {
                                        outerContent.scrollLeft((innerContent.width() - outerContent.width()) / 2);
                                    }
                                }
                            });
                        }, 100);
                        $(".showanalytics").css("display", "block");
                        if (totalData.quizAttempt != 1) {
                            $("#tabs-2").find("ul").find("li").find("span.improvementcurve").show();
                            $("#tabs-2").find("ul").find("li").find("span.peerperformance").hide();
                            $("#subtab21").find(".subtabmix").show();
                            $("#subtab21").find(".subtabtimechart").hide();
                        } else {
                            $("#tabs-2").find("ul").find("li").find("span.improvementcurve").hide();
                            $("#tabs-2").find("ul").find("li").find("span.peerperformance").show();
                            $("#subtab21").find(".subtabmix").hide();
                            $("#subtab21").find(".subtabtimechart").show();
                        }
                        if (attemptsSnapshotArray.length > 0) {
                            $.each(attemptsSnapshotArray, function (i, v) {
                                v.destroy();
                            });
                        }
                        str.get_strings(stringFetch).done(function(s){
                            // Every canvas below belongs to a tab that can be turned off via the plugin's
                            // "Visible analytics" settings, so each block only runs if its canvas exists.
                            if ($('.attemptssnapshot').length) {
                                $('.attemptssnapshot').html('');
                                $.each(totalData.attemptssnapshot.data, function (key, value) {
                                    var option = {
                                        plugins: {
                                            tooltip: {
                                                callbacks: {
                                                    // use label callback to return the desired label
                                                    label: function (context) {
                                                        return " " + context.label + " : " + context.parsed;
                                                    }
                                                }
                                            }
                                        },
                                    };
                                    var Options = $.extend(true, {}, toV4ChartOptions(totalData.attemptssnapshot.opt[key]), option);
                                    $('.attemptssnapshot').append('<label><canvas id="attemptssnapshot' + key + '"></canvas><div id="js-legend' + key + '" class="chart-legend"></div></label><div class="download"><a class="download-canvas" data-canvas_id="attemptssnapshot' + key + '"></a></div>');
                                    var chartConvention = document.getElementById("attemptssnapshot" + key).getContext('2d');
                                    var attemptsSnapshot = new Chart(chartConvention, {
                                        type: 'doughnut',
                                        data: totalData.attemptssnapshot.data[key],
                                        options: Options,
                                    });
                                    document.getElementById('js-legend' + key).innerHTML = buildSliceLegendHtml(attemptsSnapshot);
                                    $('#js-legend' + key).find('ul').find('li').on("click", function () {
                                        var index = $(this).index();
                                        $(this).toggleClass("strike");
                                        attemptsSnapshot.toggleDataVisibility(index);
                                        attemptsSnapshot.update();
                                    });
                                    attemptsSnapshotArray.push(attemptsSnapshot);
                                });
                            }
                            var canvasQuestionPerCategories = document.getElementById("questionpercategories");
                            if (canvasQuestionPerCategories) {
                                var chartConvention = canvasQuestionPerCategories.getContext('2d');
                                if (questionPerCategories !== undefined) {
                                    questionPerCategories.destroy();
                                }
                                var option = {
                                    plugins: {
                                        tooltip: {
                                            callbacks: {
                                                // use label callback to return the desired label
                                                label: function (context) {
                                                    return " " + context.label + " : " + context.parsed;
                                                }
                                            }
                                        }
                                    },
                                };
                                var Options = $.extend(true, {}, toV4ChartOptions(totalData.questionPerCategories.opt), option);
                                questionPerCategories = new Chart(chartConvention, {
                                    type: 'pie',
                                    data: totalData.questionPerCategories.data,
                                    options: Options,
                                });
                                document.getElementById('js-legendqpc').innerHTML = buildSliceLegendHtml(questionPerCategories);
                                $("#js-legendqpc > ul > li").on("click", function () {
                                    var index = $(this).index();
                                    $(this).toggleClass("strike");
                                    questionPerCategories.toggleDataVisibility(index);
                                    questionPerCategories.update();
                                });
                            }
                            var canvasAllUsers = document.getElementById("allusers");
                            if (canvasAllUsers) {
                                var option = {
                                    plugins: {
                                        tooltip: {
                                            // disable displaying the color box;
                                            displayColors: false
                                        }
                                    },
                                    scales: {
                                        x: { title: { display: true, text: s[1] } },
                                        y: {
                                            title: { display: true, text: s[2] }, beginAtZero: true, max: 100,
                                            ticks: { callback: function (value) { if (Number.isInteger(value)) { return value; } } }
                                        }
                                    }
                                };
                                var Options = $.extend(true, {}, toV4ChartOptions(totalData.allUsers.opt), option);
                                var chartConvention = canvasAllUsers.getContext('2d');
                                if (allUsers !== undefined) {
                                    allUsers.destroy();
                                }
                                allUsers = new Chart(chartConvention, {
                                    type: 'bar',
                                    data: totalData.allUsers.data,
                                    options: Options
                                });
                            }
                            var canvasLoggedInUser = document.getElementById("loggedinuser");
                            if (canvasLoggedInUser) {
                                var option = {
                                    plugins: {
                                        tooltip: {
                                            // disable displaying the color box;
                                            displayColors: false
                                        }
                                    },
                                    scales: {
                                        x: { title: { display: true, text: s[1] } },
                                        y: {
                                            title: { display: true, text: s[2] }, beginAtZero: true, max: 100,
                                            ticks: { callback: function (value) { if (Number.isInteger(value)) { return value; } } }
                                        }
                                    }
                                };
                                var Options = $.extend(true, {}, toV4ChartOptions(totalData.loggedInUser.opt), option);
                                var chartConvention = canvasLoggedInUser.getContext('2d');
                                if (loggedInUser !== undefined) {
                                    loggedInUser.destroy();
                                }
                                loggedInUser = new Chart(chartConvention, {
                                    type: 'bar',
                                    data: totalData.loggedInUser.data,
                                    options: Options
                                });
                            }
                            var canvasLastAttempt = document.getElementById("lastAttempt");
                            if (canvasLastAttempt) {
                                if (totalData.lastAttemptSummary.data != null && totalData.lastAttemptSummary.opt != null) {
                                    $(".showanalytics").find(".unattempted").hide();
                                    $(".showanalytics").find("#lastAttempt").show();
                                    canvasLastAttempt.height = 100;
                                    var chartConvention1 = canvasLastAttempt.getContext('2d');
                                    if (lastAttemptSummary !== undefined) {
                                        lastAttemptSummary.destroy();
                                    }
                                    var option = {
                                        // 'horizontalBar' was removed in Chart.js v3+ - a horizontal bar chart is
                                        // now a regular 'bar' chart with the index axis switched to 'y'.
                                        indexAxis: 'y',
                                        plugins: {
                                            tooltip: {
                                                // disable displaying the color box;
                                                displayColors: false,
                                                callbacks: {
                                                    // use label callback to return the desired label
                                                    label: function (context) {
                                                        return context.label + " : " + context.parsed.x;
                                                    },
                                                    // remove title
                                                    title: function () {
                                                        return '';
                                                    }
                                                }
                                            }
                                        }
                                    };
                                    var Options = $.extend(true, {}, toV4ChartOptions(totalData.lastAttemptSummary.opt), option);
                                    lastAttemptSummary = new Chart(chartConvention1, {
                                        type: 'bar',
                                        data: totalData.lastAttemptSummary.data,
                                        options: Options
                                    });
                                }
                                else {
                                    $(".showanalytics").find("#lastAttempt").hide();
                                    $(".showanalytics").find("#lastAttempt").parent().append('<p class="unattempted"><b>' + s[0] + '</b></p>');
                                }
                            }
                            var canvasMixchart = document.getElementById("mixchart");
                            if (canvasMixchart) {
                                var option = {
                                    plugins: {
                                        tooltip: {
                                            // disable displaying the color box;
                                            displayColors: false,
                                            callbacks: {
                                                // use label callback to return the desired label
                                                label: function (context) {
                                                    return context.parsed.y + " : " + context.label;
                                                },
                                                // remove title
                                                title: function () {
                                                    return '';
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            title: { display: true, text: s[5] }, beginAtZero: true,
                                            ticks: { callback: function (value) { if (Number.isInteger(value)) { return value; } } }
                                        }
                                    }
                                };
                                var Options = $.extend(true, {}, toV4ChartOptions(totalData.mixChart.opt), option);
                                var chartConvention = canvasMixchart.getContext('2d');
                                if (mixChart !== undefined) {
                                    mixChart.destroy();
                                }
                                mixChart = new Chart(chartConvention, {
                                    type: 'line',
                                    data: totalData.mixChart.data,
                                    options: Options
                                });
                            }
                            var canvasTimechart = document.getElementById("timechart");
                            if (canvasTimechart) {
                                var option = {
                                    // 'horizontalBar' was removed in Chart.js v3+ - a horizontal bar chart is
                                    // now a regular 'bar' chart with the index axis switched to 'y'.
                                    indexAxis: 'y',
                                    plugins: {
                                        tooltip: {
                                            // disable displaying the color box;
                                            displayColors: false,
                                            callbacks: {
                                                // use label callback to return the desired label
                                                label: function (context) {
                                                    return context.dataset.label + " : " + context.label;
                                                },
                                                // remove title
                                                title: function () {
                                                    return '';
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: { title: { display: true, text: s[3] } },
                                        y: {
                                            title: { display: true, text: s[4] }, beginAtZero: true,
                                            ticks: { callback: function (value) { if (Number.isInteger(value)) { return value; } } }
                                        }
                                    }
                                };
                                var Options = $.extend(true, {}, toV4ChartOptions(totalData.timeChart.opt), option);
                                var chartConvention = canvasTimechart.getContext('2d');
                                if (timeChart !== undefined) {
                                    timeChart.destroy();
                                }
                                timeChart = new Chart(chartConvention, {
                                    type: 'bar',
                                    data: totalData.timeChart.data,
                                    options: Options
                                });
                            }
                            var canvasGradeAnalysis = document.getElementById("gradeanalysis");
                            if (canvasGradeAnalysis) {
                                var chartConvention = canvasGradeAnalysis.getContext('2d');
                                if (gradeAnalysis !== undefined) {
                                    gradeAnalysis.destroy();
                                }
                                var option = {
                                    plugins: {
                                        tooltip: {
                                            // disable displaying the color box;
                                            displayColors: false,
                                            callbacks: {
                                                // use label callback to return the desired label
                                                label: function (context) {
                                                    return "Percentage Scored (" + context.label + ") : " + context.parsed;
                                                }
                                            }
                                        }
                                    }
                                };
                                var Options = $.extend(true, {}, toV4ChartOptions(totalData.gradeAnalysis.opt), option);
                                gradeAnalysis = new Chart(chartConvention, {
                                    type: 'pie',
                                    data: totalData.gradeAnalysis.data,
                                    options: Options
                                });
                                document.getElementById('js-legendgrade').innerHTML = buildSliceLegendHtml(gradeAnalysis);
                                $("#js-legendgrade > ul > li").on("click", function () {
                                    var index = $(this).index();
                                    $(this).toggleClass("strike");
                                    gradeAnalysis.toggleDataVisibility(index);
                                    gradeAnalysis.update();
                                });
                            }
                            var canvasQuestionAnalysisChart = document.getElementById("questionanalysis");
                            if (canvasQuestionAnalysisChart) {
                                var chartConvention = canvasQuestionAnalysisChart.getContext('2d');
                                if (quesAnalysis !== undefined) {
                                    quesAnalysis.destroy();
                                }
                                var option = {
                                    plugins: {
                                        tooltip: {
                                            // disable displaying the color box;
                                            displayColors: false,
                                            callbacks: {
                                                // use label callback to return the desired label
                                                label: function (context) {
                                                    return [context.dataset.label + " : " + context.parsed.y, s[7]];

                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: { title: { display: true, text: s[6] } },
                                        y: {
                                            title: { display: true, text: s[3] }, beginAtZero: true,
                                            ticks: { callback: function (value) { if (Number.isInteger(value)) { return value; } } }
                                        }
                                    }
                                };
                                var Options = $.extend(true, {}, toV4ChartOptions(totalData.quesAnalysis.opt), option);

                                quesAnalysis = new Chart(chartConvention, {
                                    type: 'line',
                                    data: totalData.quesAnalysis.data,
                                    options: Options
                                });
                            }
                            var canvasHardestQuestionsChart = document.getElementById("hardest-questions");
                            if (canvasHardestQuestionsChart) {
                                var option = {
                                    plugins: {
                                        tooltip: {
                                            // disable displaying the color box;
                                            displayColors: false,
                                            callbacks: {
                                                // use label callback to return the desired label
                                                label: function (context) {
                                                    return [context.dataset.label + " : " + context.parsed.y, s[7]];

                                                },
                                                // remove title
                                                title: function () {
                                                    return '';
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        x: { title: { display: true, text: s[1] } },
                                        y: {
                                            title: { display: true, text: s[3] }, beginAtZero: true,
                                            ticks: { callback: function (value) { if (Number.isInteger(value)) { return value; } } }
                                        }
                                    }
                                };
                                var Options = $.extend(true, {}, toV4ChartOptions(totalData.hardestQuestions.opt), option);
                                var chartConvention = canvasHardestQuestionsChart.getContext('2d');
                                if (hardestQuestions !== undefined) {
                                    hardestQuestions.destroy();
                                }
                                hardestQuestions = new Chart(chartConvention, {
                                    type: 'bar',
                                    data: totalData.hardestQuestions.data,
                                    options: Options
                                });
                            }
                        });
                    }
                })
                var canvasQuestionAnalysis = document.getElementById("questionanalysis");
                if (canvasQuestionAnalysis) {
                    canvasQuestionAnalysis.onclick = function (questionevent) {
                        // getElementsAtEvent() was removed in Chart.js v3+; getElementsAtEventForMode()
                        // with mode 'index' is the documented replacement for its "same index across
                        // datasets" behaviour, and elements now expose a plain .index property.
                        var activePoints = quesAnalysis.getElementsAtEventForMode(questionevent, 'index', {intersect: true}, false);
                        if (!activePoints.length) {
                            return;
                        }
                        var idx = activePoints[0].index;
                        var label = quesAnalysis.data.labels[idx];
                        if (allQuestions !== undefined) {
                            var quesPage = 0;
                            $.each(allQuestions, function (i, quesid) {
                                if (label == quesid.split(",")[0]) {
                                    var quesid = quesid.split(",")[1];
                                    var id = quizid;
                                    if (quesPage == 0) {
                                        var newwindow = window.open(rooturl + '/mod/quiz/review.php?attempt=' + lastUserQuizAttemptID + '&showall=' + 0, '', 'height=500,width=800');
                                    } else {
                                        var newwindow = window.open(rooturl + '/mod/quiz/review.php?attempt=' + lastUserQuizAttemptID + '&page=' + quesPage, '', 'height=500,width=800');
                                    }
                                    if (window.focus) {
                                        newwindow.focus();
                                    }
                                    return false;
                                }
                                quesPage++;
                            });
                        }
                    };
                }
                var canvasHardestQuestions = document.getElementById("hardest-questions");
                if (canvasHardestQuestions) {
                    canvasHardestQuestions.onclick = function (questionevent) {
                        // getElementsAtEvent() was removed in Chart.js v3+; getElementsAtEventForMode()
                        // with mode 'index' is the documented replacement for its "same index across
                        // datasets" behaviour, and elements now expose a plain .index property.
                        var activePoints = hardestQuestions.getElementsAtEventForMode(questionevent, 'index', {intersect: true}, false);
                        if (!activePoints.length) {
                            return;
                        }
                        var idx = activePoints[0].index;
                        var label = hardestQuestions.data.labels[idx];
                        if (allQuestions !== undefined) {
                            var quesPage = 0;
                            $.each(allQuestions, function (i, quesid) {
                                if (label == quesid.split(",")[0]) {
                                    var quesid = quesid.split(",")[1];
                                    var id = quizid;
                                    if (quesPage == 0) {
                                        var newwindow = window.open(rooturl + '/mod/quiz/review.php?attempt=' + lastUserQuizAttemptID + '&showall=' + 0, '','height=500,width=800');
                                    } else {
                                        var newwindow = window.open(rooturl + '/mod/quiz/review.php?attempt=' + lastUserQuizAttemptID + '&page=' + quesPage,'', 'height=500,width=800');
                                    }
                                    if (window.focus) {
                                        newwindow.focus();
                                    }
                                    return false;
                                }
                                quesPage++;
                            });
                        }
                    };
                }
            });
            $("#viewanalytic").one("click", function () {
                $(".showanalytics").find("canvas").each(function () {
                    var canvasid = $(this).attr("id");
                    $(this).parent().append('<div class="download"><a class="download-canvas" data-canvas_id="' + canvasid + '"></a></div>');
                });
            });
            $('body').on('click', '.download-canvas', function () {
                var canvasId = $(this).data('canvas_id');
                downloadCanvas(this, canvasId, canvasId + '.jpeg');
            });
            function downloadCanvas(link, canvasId, filename) {
                link.href = document.getElementById(canvasId).toDataURL("image/jpeg");
                link.download = filename;
            }
        }
    };
});
