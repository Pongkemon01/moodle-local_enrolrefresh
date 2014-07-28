<?php
// This file is a part of Kasetsart Moodle Kit - https://github.com/Pongkemon01/moodle-quizaccess_studentident
//
// Kasetsart Moodle Kit is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Kasetsart Moodle Kit is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Enrollment refresh is use to synchronize student enrollment with registration office.
 * As the registration office use internal custom database scheme, we can refresh
 * student enrollment statuses through CSV file only.
 *
 * @package     quizaccess_studentident
 * @author      Akrapong Patchararungruang
 * @copyright   2014 Kasetsart University
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/lib/accesslib.php");
require_once("$CFG->dirroot/lib/enrollib.php");
require_once("$CFG->dirroot/lib/grouplib.php");
require_once("$CFG->dirroot/lib/navigationlib.php");
require_once("$CFG->dirroot/group/lib.php");


/**
 * Hook to insert a link in global navigation menu block
 * @param global_navigation $navigation
 */
/*
function local_enrolrefresh_extends_navigation(global_navigation $navigation)
{
}
*/


/**
 * Hook to insert a link in settings navigation menu block
 *
 * @param settings_navigation $navigation
 * @param course_context      $context
 * @return void
 */
function local_enrolrefresh_extends_settings_navigation(settings_navigation $navigation, $context)
{
    global $CFG;

    // If not in a course context, then leave
    if ($context == null || $context->contextlevel != CONTEXT_COURSE) {
        return;
    }

    // When on front page there is 'frontpagesettings' node, other
    // courses will have 'courseadmin' node
    if (null == ($courseadmin_node = $navigation->get('courseadmin'))) {
        // Keeps us off the front page
        return;
    }
    if (null == ($useradmin_node = $courseadmin_node->get('users'))) {
        return;
    }

    $modurl = $CFG->wwwroot . '/local/enrolrefresh/refresh.php';
    if (!empty($context->instanceid)) {
        $modurl = $modurl . '?id=' . $context->instanceid;
    }

    // Add our links
    $useradmin_node->add(
        get_string('rf_menulong', 'local_enrolrefresh'),
        $modurl,
        navigation_node::TYPE_SETTING,
        get_string('rf_menushort', 'local_enrolrefresh'),
        null, new pix_icon('i/import', 'import'));
}
