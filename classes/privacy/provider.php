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
 * Privacy provider for the quizanalytics gradebook report.
 *
 * @package   gradereport_quizanalytics
 * @author    DualCube <admin@dualcube.com>
 * @copyright 2026 DualCube (https://dualcube.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace gradereport_quizanalytics\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;

/**
 * This plugin has no database tables, user preferences, files, or logged events of its own - it
 * only reads quiz attempt data (owned by mod_quiz) and question response data (owned by the
 * core_question subsystem) to build its charts, in the same way mod_quiz's own report subplugins
 * do. It never stores, exports, or deletes any of that data itself, so it only needs to declare
 * metadata, not implement \core_privacy\local\request\plugin\provider.
 */
class provider implements metadata_provider {
    /**
     * Returns metadata about the personal data this plugin reads (but does not store) so it
     * shows up correctly on the site's Privacy Registry page.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        // Same subsystem link mod_quiz's own privacy provider uses for the same reason: this
        // plugin reads question_attempts/question_attempt_steps (via the question engine) to
        // report on correctness, response counts, and time taken - core_question remains
        // responsible for exporting/deleting that data.
        $collection->add_subsystem_link(
            'core_question',
            [],
            'privacy:metadata:core_question'
        );

        return $collection;
    }
}
