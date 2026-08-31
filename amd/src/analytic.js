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
define(['jquery', 'core/ajax', 'core/str', 'gradereport_quizanalytics/datatables'], function ($, ajax, str) {
    'use strict';

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
            Chart.plugins.register({
                beforeDraw: function (chartInstance) {
                    var chartConvention = chartInstance.chart.ctx;
                    chartConvention.fillStyle = "white";
                    chartConvention.fillRect(0, 0, chartInstance.chart.width, chartInstance.chart.height);
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
                                        tooltips: {
                                            callbacks: {
                                                // use label callback to return the desired label
                                                label: function (tooltipItem, data) {
                                                    return " " + data.labels[tooltipItem.index] + " : " + data.datasets[0].data[tooltipItem.index];
                                                }
                                            }
                                        },
                                    };
                                    var Options = $.extend(totalData.attemptssnapshot.opt[key], option);
                                    $('.attemptssnapshot').append('<label><canvas id="attemptssnapshot' + key + '"></canvas><div id="js-legend' + key + '" class="chart-legend"></div></label><div class="download"><a class="download-canvas" data-canvas_id="attemptssnapshot' + key + '"></a></div>');
                                    var chartConvention = document.getElementById("attemptssnapshot" + key).getContext('2d');
                                    var attemptsSnapshot = new Chart(chartConvention, {
                                        type: 'doughnut',
                                        data: totalData.attemptssnapshot.data[key],
                                        options: Options,
                                    });
                                    document.getElementById('js-legend' + key).innerHTML = attemptsSnapshot.generateLegend();
                                    $('#js-legend' + key).find('ul').find('li').on("click", function (snaplegende) {
                                        var index = $(this).index();
                                        $(this).toggleClass("strike");
                                        function first(p) {
                                            for (var i in p) { return p[i] };
                                        }
                                        var currentTab = first(attemptsSnapshot.config.data.datasets[0]._meta).data[index];
                                        currentTab.hidden = !currentTab.hidden
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
                                    tooltips: {
                                        callbacks: {
                                            // use label callback to return the desired label
                                            label: function (tooltipItem, data) {
                                                return " " + data.labels[tooltipItem.index] + " : " + data.datasets[0].data[tooltipItem.index];
                                            }
                                        }
                                    },
                                };
                                var Options = $.extend(totalData.questionPerCategories.opt, option);
                                questionPerCategories = new Chart(chartConvention, {
                                    type: 'pie',
                                    data: totalData.questionPerCategories.data,
                                    options: Options,
                                });
                                document.getElementById('js-legendqpc').innerHTML = questionPerCategories.generateLegend();
                                $("#js-legendqpc > ul > li").on("click", function (legende) {
                                    var index = $(this).index();
                                    $(this).toggleClass("strike");
                                    function first(p) {
                                        for (var i in p) { return p[i] };
                                    }
                                    var currentTab = first(questionPerCategories.config.data.datasets[0]._meta).data[index];
                                    currentTab.hidden = !currentTab.hidden
                                    questionPerCategories.update();
                                });
                            }
                            var canvasAllUsers = document.getElementById("allusers");
                            if (canvasAllUsers) {
                                var option = {
                                    tooltips: {
                                        custom: function (tooltip) {
                                            if (!tooltip) return;
                                            // disable displaying the color box;
                                            tooltip.displayColors = false;
                                        }
                                    },
                                    scales: { xAxes: [{ scaleLabel: { display: true, labelString: s[1] } }], yAxes: [{ scaleLabel: { display: true, labelString: s[2] }, ticks: { beginAtZero: true, max: 100, callback: function (value) { if (Number.isInteger(value)) { return value; } } } }] }
                                };
                                var Options = $.extend(totalData.allUsers.opt, option);
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
                                    tooltips: {
                                        custom: function (tooltip) {
                                            if (!tooltip) return;
                                            // disable displaying the color box;
                                            tooltip.displayColors = false;
                                        }
                                    },
                                    scales: { xAxes: [{ scaleLabel: { display: true, labelString: s[1] } }], yAxes: [{ scaleLabel: { display: true, labelString: s[2] }, ticks: { beginAtZero: true, max: 100, callback: function (value) { if (Number.isInteger(value)) { return value; } } } }] }
                                };
                                var Options = $.extend(totalData.loggedInUser.opt, option);
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
                                        tooltips: {
                                            custom: function (tooltip) {
                                                if (!tooltip) return;
                                                // disable displaying the color box;
                                                tooltip.displayColors = false;
                                            },
                                            callbacks: {
                                                // use label callback to return the desired label
                                                label: function (tooltipItem, data) {
                                                    return tooltipItem.yLabel + " : " + tooltipItem.xLabel;
                                                },
                                                // remove title
                                                title: function (tooltipItem, data) {
                                                    return;
                                                }
                                            }
                                        }
                                    };
                                    var Options = $.extend(totalData.lastAttemptSummary.opt, option);
                                    lastAttemptSummary = new Chart(chartConvention1, {
                                        type: 'horizontalBar',
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
                                    tooltips: {
                                        custom: function (tooltip) {
                                            if (!tooltip) return;
                                            // disable displaying the color box;
                                            tooltip.displayColors = false;
                                        },
                                        callbacks: {
                                            // use label callback to return the desired label
                                            label: function (tooltipItem, data) {
                                                return tooltipItem.yLabel + " : " + tooltipItem.xLabel;
                                            },
                                            // remove title
                                            title: function (tooltipItem, data) {
                                                return;
                                            }
                                        }
                                    },
                                    scales: { xAxes: [{ scaleLabel: { display: true, labelString: s[5] }, ticks: { beginAtZero: true, callback: function (value) { if (Number.isInteger(value)) { return value; } } } }] }
                                };
                                var Options = $.extend(totalData.mixChart.opt, option);
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
                                    tooltips: {
                                        custom: function (tooltip) {
                                            if (!tooltip) return;
                                            // disable displaying the color box;
                                            tooltip.displayColors = false;
                                        },
                                        callbacks: {
                                            // use label callback to return the desired label
                                            label: function (tooltipItem, data) {
                                                return data.datasets[tooltipItem.datasetIndex].label + " : " + tooltipItem.yLabel;
                                            },
                                            // remove title
                                            title: function (tooltipItem, data) {
                                                return;
                                            }
                                        }
                                    },
                                    scales: { xAxes: [{ scaleLabel: { display: true, labelString: s[3] } }], yAxes: [{ scaleLabel: { display: true, labelString: s[4] }, ticks: { beginAtZero: true, callback: function (value) { if (Number.isInteger(value)) { return value; } } } }] }
                                };
                                var Options = $.extend(totalData.timeChart.opt, option);
                                var chartConvention = canvasTimechart.getContext('2d');
                                if (timeChart !== undefined) {
                                    timeChart.destroy();
                                }
                                timeChart = new Chart(chartConvention, {
                                    type: 'horizontalBar',
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
                                    tooltips: {
                                        custom: function (tooltip) {
                                            if (!tooltip) return;
                                            // disable displaying the color box;
                                            tooltip.displayColors = false;
                                        },
                                        callbacks: {
                                            // use label callback to return the desired label
                                            label: function (tooltipItem, data) {
                                                return "Percentage Scored (" + data.labels[tooltipItem.index] + ") : " + data.datasets[0].data[tooltipItem.index];
                                            }
                                        }
                                    }
                                };
                                var Options = $.extend(totalData.gradeAnalysis.opt, option);
                                gradeAnalysis = new Chart(chartConvention, {
                                    type: 'pie',
                                    data: totalData.gradeAnalysis.data,
                                    options: Options
                                });
                                document.getElementById('js-legendgrade').innerHTML = gradeAnalysis.generateLegend();
                                $("#js-legendgrade > ul > li").on("click", function (legendgrade) {
                                    var index = $(this).index();
                                    $(this).toggleClass("strike");
                                    function first(p) {
                                        for (var i in p) { return p[i] };
                                    }
                                    var currentTab = first(gradeAnalysis.config.data.datasets[0]._meta).data[index];
                                    currentTab.hidden = !currentTab.hidden
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
                                    tooltips: {
                                        custom: function (tooltip) {
                                            if (!tooltip) return;
                                            // disable displaying the color box;
                                            tooltip.displayColors = false;
                                        },
                                        callbacks: {
                                            // use label callback to return the desired label
                                            label: function (tooltipItem, data) {
                                                return [data.datasets[tooltipItem.datasetIndex].label + " : " + tooltipItem.yLabel, s[7]];

                                            }
                                        }
                                    },
                                    scales: { xAxes: [{ scaleLabel: { display: true, labelString: s[6] } }], yAxes: [{ scaleLabel: { display: true, labelString: s[3] }, ticks: { beginAtZero: true, callback: function (value) { if (Number.isInteger(value)) { return value; } } } }] }
                                };
                                var Options = $.extend(totalData.quesAnalysis.opt, option);

                                quesAnalysis = new Chart(chartConvention, {
                                    type: 'line',
                                    data: totalData.quesAnalysis.data,
                                    options: Options
                                });
                            }
                            var canvasHardestQuestionsChart = document.getElementById("hardest-questions");
                            if (canvasHardestQuestionsChart) {
                                var option = {
                                    tooltips: {
                                        custom: function (tooltip) {
                                            if (!tooltip) return;
                                            // disable displaying the color box;
                                            tooltip.displayColors = false;
                                        },
                                        callbacks: {
                                            // use label callback to return the desired label
                                            label: function (tooltipItem, data) {
                                                return [data.datasets[tooltipItem.datasetIndex].label + " : " + tooltipItem.yLabel, s[7]];

                                            },
                                            // remove title
                                            title: function (tooltipItem, data) {
                                                return;
                                            }
                                        }
                                    },
                                    scales: { xAxes: [{ scaleLabel: { display: true, labelString: s[1] } }], yAxes: [{ scaleLabel: { display: true, labelString: s[3] }, ticks: { beginAtZero: true, callback: function (value) { if (Number.isInteger(value)) { return value; } } } }] }
                                };
                                var Options = $.extend(totalData.hardestQuestions.opt, option);
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
                        var activePoints = quesAnalysis.getElementsAtEvent(questionevent);
                        var chartData = activePoints[0]['_chart'].config.data;
                        var idx = activePoints[0]['_index'];
                        var label = chartData.labels[idx];
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
                        var activePoints = hardestQuestions.getElementsAtEvent(questionevent);
                        var chartData = activePoints[0]['_chart'].config.data;
                        var idx = activePoints[0]['_index'];
                        var label = chartData.labels[idx];
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
