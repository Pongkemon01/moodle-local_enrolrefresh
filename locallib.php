<?php
// This file is a part of Kasetsart Moodle Kit - https://github.com/Pongkemon01/moodle-local_enrolrefresh
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

require_once($CFG->libdir.'/csvlib.class.php');
require_once("$CFG->libdir/accesslib.php");
require_once("$CFG->libdir/enrollib.php");
require_once("$CFG->libdir/grouplib.php");
require_once("$CFG->dirroot/group/lib.php");

/**
 * Validation callback function - verified the column line of csv file.
 * Converts standard column names to lowercase.
 * @param csv_import_reader $cir
 * @param moodle_url $returnurl return url in case of any error
 * @return array list of field names
 */
function validate_user_upload_columns(csv_import_reader $cir, moodle_url $returnurl) {
    $columns = $cir->get_columns(); // Get the first line (field names)

    if (empty($columns)) {
        $cir->close();
        $cir->cleanup();
        print_error('cannotreadtmpfile', 'error', $returnurl);
    }
    if (count($columns) != 2) {
        $cir->close();
        $cir->cleanup();
        print_error('csvwrongcolumns', 'error', $returnurl);
    }

    // test columns
    $processed = array();
    $allowfield = array('idnumber','username','group');

    foreach ($columns as $key=>$field) {
        $lcfield = core_text::strtolower($field);
        if (!in_array($lcfield, $allowfield)) {
            $cir->close();
            $cir->cleanup();
            print_error('invalidfieldname', 'error', $returnurl, $field);
        }

        if (in_array($lcfield, $processed)) {
            $cir->close();
            $cir->cleanup();
            print_error('duplicatefieldname', 'error', $returnurl, $newfield);
        }
        $processed[$key] = $lcfield;
    }

    if (! in_array('group', $processed)) {
        $cir->close();
        $cir->cleanup();
        print_error('csvnogroup', 'error', $returnurl);
    }

    return $processed;
}

/**
 * Parse csv file.
 * @param csv_import_reader $cir
 * @param array $csv_keys the first line of input file (header line)
 * @return associative array of indexed array of enrolled groups. The array is indexed by id field in user table.
 */
function parse_csv(csv_import_reader $cir, $csv_keys) {
    global $DB;

    $data = array();

    // init csv import helper
    $cir->init();

    // Get name of the key field
    if (in_array ('username', $csv_keys)) {
        $keyname = 'username';
    } else {
        $keyname = 'idnumber';
    }

    // Iterate from line 2 (Skip the header line)
    while ($line = $cir->next()) {
        // add fields to user object
        foreach ($line as $keynum => $value) {
            if (!isset($csv_keys[$keynum])) {
                // this should not happen
                continue;
            }
            if($csv_keys[$keynum] == 'group') {
                $gid = $value;
            } else {
                // csv_keys can have only 'group' or 'username' or 'idnumber'
                $key = $value;
            }
        }

        // Get user id
        $user = $DB->get_record('user', array($keyname=>$key));
        if (empty($user))
        {
            // User have not sign-up yet.
            continue;
        }
        $uid = $user->id;

        // Store id
        if (array_key_exists($uid, $data)) {
            // This $uid already exists, append
            $data[$uid]->group[] = $gid;
        } else {
            // First for this $uid, create new
            $data[$uid] = new stdClass();
            $data[$uid]->id = $uid;
            $data[$uid]->key = $key;
            $data[$uid]->groups = array();
            $data[$uid]->groups[] = $gid;
        }
    }

    return (count($data)>0) ? $data : false;
}

/**
 * Perform enrollment action.
 * @param array $csvdata
 * @param class $manual_enrol_instance - The instance of 'manual' enrollment plugin in class-context.
 * @param integer $role_id
 * @param string $missing_act - action for users missing from the imported data.
 * @return none.
 */
function enroll_action ($csvdata, $manual_enrol_instance, $role_id, $missing_act) {
    global $DB, $COURSE;

    $coursecontext = context_course::instance($COURSE->id);
    $system_manual_enroll = enrol_get_plugin('manual');

    // Enroll new users
    if ($role_id > 0) {
        foreach ($csvdata as $uid=>$unused) {
            if (!is_enrolled($coursecontext, $uid)) {
                $system_manual_enroll->enrol_user($manual_enrol_instance, $uid, $role_id);
            }
        }
    }

    // Withdraw/Suspend unlisted users
    if ($missing_act != 'nothing') {
        // Get 'student' roleid
        $student_roleid = $DB->get_record('role', array('shortname'=>'student'))->id;
        $enrolled_users = get_enrolled_users($coursecontext);
        foreach ($enrolled_users as $enrolled_user) {
            if (!array_key_exists($enrolled_user->id, $csvdata)) {
                if ($DB->count_records('role_assignments', array('userid'=>$enrolled_user->id, 
                						'roleid'=>$student_roleid, 'contextid'=>$coursecontext->id)) == 0) {
                    continue;  // Skip non-student users
                }
                if ($missing_act == 'suspend') {
                    $system_manual_enroll->update_user_enrol($manual_enrol_instance, $enrolled_user->id, 1);
                } else {
                    $system_manual_enroll->unenrol_user($manual_enrol_instance, $enrolled_user->id);
                }
            }
        }
    }
}

/**
 * Perform enrollment action.
 * @param array $csvdata
 * @param integer $autogroupcreate - Whether new group should be created if required?
 * @param integer $autogroupwithdraw - Whether users should be withdrawn from the group not specified in imported data?
 * @return none.
 */
function group_action($csvdata, $autogroupcreate, $autogroupwithdraw) {
    global $DB, $COURSE;

    $coursecontext = context_course::instance($COURSE->id);
    // Get 'student' roleid
    $student_roleid = $DB->get_record('role', array('shortname'=>'student'))->id;

    foreach ($csvdata as $uid=>$userdata) {
        if (!is_enrolled($coursecontext, $uid)) {
            // skip unenrolled users as enrol_action should finish all enrollment
            continue;
        }

        // Add to group
        foreach ($userdata->groups as $group) {

            // Prepare course group
            if (! ($DB->record_exists('groups', array('name'=>$group)))) {
                // If group not exists, should we create it?
                if ($autogroupcreate) {
                    // Create a group
                    $newgroupdata = new stdClass();
                    $newgroupdata->name = $group;
                    $newgroupdata->courseid = $COURSE->id;
                    $newgroupdata->description = '';
                    $gid = groups_create_group($newgroupdata);
                    if (!$gid) {
                        // Fail to create group
                        continue; // Skip
                    }
                } else {
                    continue;   // Skip this group
                }
            } else {
                $gid = groups_get_group_by_name($COURSE->id, $group);
            }

            // Add user to group
            if (! groups_is_member($gid, $uid)) {
                groups_add_member($gid, $uid);
            }
        }

        // Withdraw from unlisted groups if required
        if ($autogroupwithdraw && ($DB->count_records('role_assignments', array('userid'=>$uid, 
        											  'roleid'=>$student_roleid)) > 0)) {
            // Get current groups that the user already in
            $sql = "SELECT g.id, g.name
                    FROM {groups} g JOIN {groups_members} gm ON gm.groupid = g.id
                        WHERE gm.userid = ? AND g.courseid = ?";
            $params = array($uid, $COURSE->id);
            $currentgroups = $DB->get_records_sql($sql, $params);

            // Iterate
            foreach ($currentgroups as $gid=>$currentgroup) {
                if (! in_array($currentgroup->name, $userdata->groups)) {
                    groups_remove_member($gid, $uid);
                }
            }
        }
    }
}

