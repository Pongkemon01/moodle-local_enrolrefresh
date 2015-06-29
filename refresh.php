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

require_once('../../config.php');
require_once('./locallib.php');
require_once('./refresh_form.php');

// Fetch the course id from query string
$course_id = required_param('id', PARAM_INT);

// No anonymous access for this page, and this will
// handle bogus course id values as well
require_login($course_id);
// $PAGE, $USER, $COURSE, and other globals now set
// up, check the capabilities (we need Manual Enrollment module)
require_capability('enrol/manual:enrol', $PAGE->context);

$user_context = context_user::instance($USER->id);
$course_context = context_course::instance($COURSE->id);

// Want this for subsequent print_error() calls
$course_url = new moodle_url("{$CFG->wwwroot}/course/view.php", array('id' => $COURSE->id));
$groups_url = new moodle_url("{$CFG->wwwroot}/group/index.php", array('id' => $COURSE->id));

$page_head_title = get_string('rf_title', local_enrolrefresh_index_form::$pluginname) . ' : ' . $COURSE->shortname;

$PAGE->set_title($page_head_title);
$PAGE->set_heading($page_head_title);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url($CFG->wwwroot . '/local/enrolrefresh/refresh.php?id=' . $COURSE->id);
$PAGE->set_cacheable(false);

// Fix up the form. Have not determined yet whether this is a
// GET or POST, but the form will be used in either case.

// Iterate the list of active enrol plugins looking for
// the manual course plugin.
// The enrollment retrieved here is the one that can be seen by this course.
// Don't confuse with the enrollment plugins enabled in system level.
$data                  = new stdClass();
$data->default_role_id = 0;
$data->manual_enroll_instance = null;
$manual_enroll_instance = null;
$enrols_enabled = enrol_get_instances($COURSE->id, true);
foreach($enrols_enabled as $enrol) {
    if ($enrol->enrol == 'manual') {
        $manual_enroll_instance = $enrol;
        $data->manual_enroll_instance = $enrol;
        $data->default_role_id = $enrol->roleid;
        break;
    }
}

$formdata = null;
$mform    = new local_enrolrefresh_index_form($CFG->wwwroot . '/local/enrolrefresh/refresh.php?id=' . $COURSE->id, array('data' => $data));

if ($mform->is_cancelled()) {

    // You need this section if you have a cancel button on your form
    // here you tell php what to do if your user presses cancel
    // probably a redirect is called for!
    // PLEASE NOTE: is_cancelled() should be called before get_data().
    redirect($course_url);

} elseif ($formdata = $mform->get_data()) {

    // This branch is where you process validated data.
    // Do stuff ...

    // First, check session spoofing
    require_sesskey();

    // Collect the input
    $iid = csv_import_reader::get_new_iid(local_enrolrefresh_index_form::$pluginname);
    $cir = new csv_import_reader($iid, local_enrolrefresh_index_form::$pluginname);

    // Verify basic imput CSV file
    $content = $mform->get_file_content('csvfile');
    $csvlinecount = $cir->load_csv_content($content, $formdata->encoding, $formdata->delimiter_name);
    $csvloaderror = $cir->get_error();
    unset($content);

    if (!is_null($csvloaderror)) {
        print_error('csvloaderror', '', $course_url, $csvloaderror);
    }

    // test if columns ok
    $filecolumns = validate_user_upload_columns($cir, $course_url);
    if (in_array('username', $filecolumns)) {
        $uid = 'username';
    } else {
        $uid = 'idnumber';
    }

    // Parse csv file
    if (!($csvdata = parse_csv($cir, $filecolumns))) {
        print_error('csvparseerror', '', $course_url);
    }

    // Perform enrollment level action
    if ($manual_enroll_instance != null && ($formdata->role_id > 0 || $formdata->missing_act != 'nothing')) {
        enroll_action($csvdata, $manual_enroll_instance, $formdata->role_id, $formdata->missing_act);
    }

    group_action($csvdata, $formdata->autogroupcreate, $formdata->autogroupwithdraw);

    // Typically you finish up by redirecting to somewhere where the user
    // can see what they did.
    redirect($course_url);
}

// Form printing
echo $OUTPUT->header();
echo $OUTPUT->heading_with_help(get_string('rf_title', local_enrolrefresh_index_form::$pluginname), 'rf_helppage', local_enrolrefresh_index_form::$pluginname);

$mform->display();

echo $OUTPUT->footer();
