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
 * Defines site settings for the quizanalytics gradebook report.
 *
 * @package   gradereport_quizanalytics
 * @author    DualCube <admin@dualcube.com>
 * @copyright 2026 DualCube (https://dualcube.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (is_siteadmin()) {
    $settings->add(
        new admin_setting_configtext(
            'gradereport_quizanalytics_cutoff',
            get_string('setcutoff', 'gradereport_quizanalytics'),
            get_string('cutoffdes', 'gradereport_quizanalytics'),
            40,
            PARAM_INT
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'gradereport_quizanalytics_globalboundary',
            get_string('setglobal', 'gradereport_quizanalytics'),
            get_string('setglobaldes', 'gradereport_quizanalytics'),
            1
        )
    );
    $settings->add(
        new admin_setting_configtextarea(
            'gradereport_quizanalytics_gradeboundary',
            get_string('gradeboundary', 'gradereport_quizanalytics'),
            get_string('gradeboundarydes', 'gradereport_quizanalytics'),
            '0-60, 61-70, 71-80, 81-90, 91-100'
        )
    );

    $settings->add(
        new admin_setting_heading(
            'gradereport_quizanalytics_visibleheading',
            get_string('visibleanalytics', 'gradereport_quizanalytics'),
            ''
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'gradereport_quizanalytics_showattemptsummarytab',
            get_string('showattemptsummarytab', 'gradereport_quizanalytics'),
            get_string('showattemptsummarytabdes', 'gradereport_quizanalytics'),
            1
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'gradereport_quizanalytics_showmyprogresstab',
            get_string('showmyprogresstab', 'gradereport_quizanalytics'),
            get_string('showmyprogresstabdes', 'gradereport_quizanalytics'),
            1
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'gradereport_quizanalytics_showquestioncategorytab',
            get_string('showquestioncategorytab', 'gradereport_quizanalytics'),
            get_string('showquestioncategorytabdes', 'gradereport_quizanalytics'),
            1
        )
    );
    $settings->add(
        new admin_setting_configcheckbox(
            'gradereport_quizanalytics_showquestionstatstab',
            get_string('showquestionstatstab', 'gradereport_quizanalytics'),
            get_string('showquestionstatstabdes', 'gradereport_quizanalytics'),
            1
        )
    );

    $settings->add(
        new admin_setting_configcheckbox(
            'gradereport_quizanalytics_showonreviewpage',
            get_string('showonreviewpage', 'gradereport_quizanalytics'),
            get_string('showonreviewpagedes', 'gradereport_quizanalytics'),
            0
        )
    );

    $settings->add(
        new admin_setting_configtextarea(
            'gradereport_quizanalytics_customcss',
            get_string('customcss', 'gradereport_quizanalytics'),
            get_string('customcssdes', 'gradereport_quizanalytics'),
            ''
        )
    );
}
