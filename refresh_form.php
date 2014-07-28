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

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->dirroot . '/user/editlib.php');
require_once($CFG->libdir.'/csvlib.class.php');


//require_once("$CFG->dirroot/lib/enrollib.php");
//require_once("$CFG->dirroot/lib/grouplib.php");

/**
 * Form definition for the plugin
 *
 */
class local_enrolrefresh_index_form extends moodleform {

    /**
     * @access private
     * @pluginname string
     */
    public static $pluginname = 'local_enrolrefresh';

    /**
     * Define the form's contents
     *
     */
    public function definition() {
        global $PAGE;

        $mform = $this->_form;
        $canenroll = ($this->_customdata['data']->manual_enroll_instance == null) ? false : true;

        // Enrollment Options
        $mform->addElement('header', 'identity', get_string('rf_enrolloption', self::$pluginname));
        $mform->addHelpButton('identity', 'rf_enrolloption', self::$pluginname);
        $mform->disabledIf('identity', $canenroll ? 1 : 0);

        // The role id drop down list. The get_assignable_roles returns an assoc. array
        // with integer keys (role id) and role name values, so it looks like a sparse
        // array. The php array functions tend to reorder the keys to remove the perceived
        // gaps, so have to merge manually with the 0 option.
        $roles = HTML_QuickForm::arrayMerge(array(0 => get_string('rf_noroleid', self::$pluginname)),
                                            get_assignable_roles($PAGE->context, ROLENAME_BOTH));
        $mform->addElement('select', 'role_id', get_string('rf_noroleid', self::$pluginname), $roles);
        $mform->setDefault('role_id', $this->_customdata['data']->default_role_id);
        $mform->addHelpButton('role_id', 'role_id', self::$pluginname);


        // Action for unlisted enrolled-users
        $choices = array (
            'nothing' => get_string('rf_missingnothing', self::$pluginname),
            'withdraw' => get_string('rf_missingwithdraw', self::$pluginname),
            'suspend' => get_string('rf_missingsuspend', self::$pluginname),
        );
        $mform->addElement('select', 'missing_act', get_string('rf_missing', self::$pluginname), $choices);
        $mform->setDefault('missing_act', 'nothing');
        $mform->addHelpButton('missing_act', 'rf_missing', self::$pluginname);

        // Group Options
        $mform->addElement('header', 'identity', get_string('rf_groupoptions', self::$pluginname));

        // Create new if needed
        $mform->addElement('selectyesno', 'autogroupcreate', get_string('rf_autogroupcreate', self::$pluginname));
        $mform->setDefault('autogroupcreate', 0);
        $mform->addHelpButton('autogroupcreate', 'rf_autogroupcreate', self::$pluginname);

        // Withdraw from unspecified groups?
        $mform->addElement('selectyesno', 'autogroupwithdraw', get_string('rf_autowithdraw', self::$pluginname));
        $mform->setDefault('autogroupwithdraw', 0);
        $mform->addHelpButton('autogroupwithdraw', 'rf_autowithdraw', self::$pluginname);

        // Import File
        $mform->addElement('header', 'identity', get_string('rf_fileoptions', self::$pluginname));

        // File picker
        // Set some options for the filepicker
        $file_picker_options = array(
            'accepted_types' => array('.csv','.txt'),
            'maxbytes'       => 51200
        );
        $mform->addElement('filepicker', 'csvfile', null, null, $file_picker_options);
        $mform->addRule('csvfile', null, 'required', null, 'client');

        $choices = csv_import_reader::get_delimiter_list();
        $mform->addElement('select', 'delimiter_name', get_string('rf_csvdelimiter', self::$pluginname), $choices);
        if (array_key_exists('cfg', $choices)) {
            $mform->setDefault('delimiter_name', 'cfg');
        } else if (get_string('listsep', 'langconfig') == ';') {
            $mform->setDefault('delimiter_name', 'semicolon');
        } else {
            $mform->setDefault('delimiter_name', 'comma');
        }

        $choices = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('rf_encoding', self::$pluginname), $choices);
        $mform->setDefault('encoding', 'UTF-8');

        //$choices = array('10'=>10, '20'=>20, '100'=>100, '1000'=>1000, '100000'=>100000);
        //$mform->addElement('select', 'previewrows', get_string('rf_rowpreviewnum', self::$pluginname), $choices);
        //$mform->setType('previewrows', PARAM_INT);

        $this->add_action_buttons(true, get_string('rf_import', self::$pluginname));

    } // definition

} // class
