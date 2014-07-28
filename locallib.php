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

require_once($CFG->libdir.'/csvlib.class.php');
require_once("$CFG->libdir/accesslib.php");
require_once("$CFG->libdir/enrollib.php");
require_once("$CFG->libdir/grouplib.php");

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
            $data[$udi]->groups = array();
            $data[$udi]->groups[] = $gid;
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
    global $COURSE;

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
        $enrolled_users = get_enrolled_users($coursecontext);
        foreach ($enrolled_users as $enrolled_user) {
            if (!array_key_exists($enrolled_user->id, $csvdata)) {
                if ($missing_act == 'suspend') {
                    $system_manual_enroll->update_user($manual_enrol_instance, $uid, 1);
                } else {
                    $system_manual_enroll->unenrol_user($manual_enrol_instance, $uid);
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
        if ($autogroupwithdraw) {
            // Get current groups that the user already in
            $sql = "SELECT g.id, g.name
                    FROM {groups} g JOIN {groups_members} gm ON gm.groupid = g.id
                        WHERE gm.userid = ? AND g.courseid = ?";
            $params = array($uid, $COURSE->id);
            $currentgroups = $DB->get_records_sql($sql, $params);

            // Iterate
            foreach ($currentgroups as $gid=>$gname) {
                if (! in_array($gname, $userdata->groups)) {
                    groups_remove_member($gid, $uid);
                }
            }
        }
    }
}

// The following is for reference.
 /**
  80   * Returns the groupid of a group with the name specified for the course.
  81   * Group names should be unique in course
  82   *
  83   * @category group
  84   * @param int $courseid The id of the course
  85   * @param string $name name of group (without magic quotes)
  86   * @return int $groupid
  87   *
  88  function groups_get_group_by_name($courseid, $name) {

/**
 222   * Add a new group
 223   *
 224   * @param stdClass $data group properties
 225   * @param stdClass $editform
 226   * @param array $editoroptions
 227   * @return id of group or false if error
 228   *
 229  function groups_create_group($data, $editform = false, $editoroptions = false) {

 /**
  32   * Adds a specified user to a group
  33   *
  34   * @param mixed $grouporid  The group id or group object
  35   * @param mixed $userorid   The user id or user object
  36   * @param string $component Optional component name e.g. 'enrol_imsenterprise'
  37   * @param int $itemid Optional itemid associated with component
  38   * @return bool True if user added successfully or the user is already a
  39   * member of the group, false otherwise.
  40   *
  41  function groups_add_member($grouporid, $userorid, $component=null, $itemid=0) {

   /**
 348   * Determines if the user is a member of the given group.
 349   *
 350   * If $userid is null, use the global object.
 351   *
 352   * @category group
 353   * @param int $groupid The group to check for membership.
 354   * @param int $userid The user to check against the group.
 355   * @return bool True if the user is a member, false otherwise.
 356   *
 357  function groups_is_member($groupid, $userid=null) {

 /**
 174   * Deletes the link between the specified user and group.
 175   *
 176   * @param mixed $grouporid  The group id or group object
 177   * @param mixed $userorid   The user id or user object
 178   * @return bool True if deletion was successful, false otherwise
 179   *
 180  function groups_remove_member($grouporid, $userorid) {
 */