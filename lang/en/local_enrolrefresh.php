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


$string['pluginname']               = 'Refresh enrolled users';

$string['rf_menulong']              = 'Refresh Enrolls';
$string['rf_menushort']             = 'Refresh';

$string['rf_title']                 = 'Refresh enrollment from a CSV file';

$string['rf_enrolloption']          = 'Enrollment Options';
$string['rf_enrolloption_help']     = 'Enrollment options require \'manual\' enrollemtn plugin';
$string['rf_roleid']                = 'Role for auto-enrollment:';
$string['rf_roleid_help']           = 'What role do you want the imported new users to have in the course. If \'No Enrollment\' then only group assignments will be made.';
$string['rf_noroleid']              = 'No Enrollments';
$string['rf_missing']               = 'Missing students:';
$string['rf_missing_help']          = 'What to do to the users, who already enrolled but do not present in the imported file';
$string['rf_missingnothing']        = 'Do nothing';
$string['rf_missingwithdraw']       = 'Withdraw';
$string['rf_missingsuspend']        = 'Suspend';

$string['rf_groupoptions']          = 'Group Options';
$string['rf_autogroupcreate']       = 'Auto create groups:';
$string['rf_autogroupcreate_help']  = 'If groups in import file do not exist, create new ones as needed, otherwise only assign users to groups if the group name specified already exists.';
$string['rf_autowithdraw']          = 'Auto withdraw users from unlisted groups:';
$string['rf_autowithdraw_help']     = 'Should users be withdrawn from groups that are not specified in the imported file.';

$string['rf_fileoptions']           = 'Import File';
$string['rf_csvdelimiter']          = 'CSV delimiter:';
$string['rf_encoding']              = 'File encoding:';
$string['rf_rowpreviewnum']         = 'Preview rows:';

$string['rf_import']                = 'Import';

$string['rf_helppage']              = 'Refresh enrollments';
$string['rf_helppage_help']         = '
<p>
Use this plugin to refresh user enrollments from a delimited text file.
New user accounts will not be created, so each of the users listed in
the input file must already have an account set up in the site.<br />
<br />
If a group name is include with any user record (line) then that user will be
added to that group if it exists. You can optionally create new groups as well as
enroll existing users if needed. The plugin has ability to withdraw or suspend
enrolled users whose name are not listed in the input file.
</p>

<ul>
<li>Each line of the import file represents a single record</li>
<li>Each record should at least contain one field with a userid value, whether it be a username or an internal idnumber.</li>
<li>Each record may contain an additional group name field, separated by a comma, semi-colon, or tab character.</li>
<li>The first line in the input file describes fields of the following records. It should be in the form of \"username,group\" or \"idnumber,group\"</li>
<li>The role to which these users are assigned can be selected, but should default to the course\'s default role.</li>
<li>If any students enrolled into more than 1 group, each group must be specified in a separate record.</li>
<li>Blank lines in the import file will be skipped</li>
<li>Note: If a user is already enrolled in the course, no changes will be made to that user\'s enrollment (i.e. no role change).</li>
</ul>

<h3>Examples</h3>

Internal idnumber value and group
<pre>
idnumber,group
5510500000,5
5510500001,5
5510500001,6
</pre>

Usernames
<pre>
username,group
b5510500000,5
b5510500001,5
b5510500001,6
</pre>';
