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
     *  local_userenrols
     *
     *  This plugin will import user enrollments and group assignments
     *  from a delimited text file. It does not create new user accounts
     *  in Moodle, it will only enroll existing users in a course.
     *
     * @author      Fred Woolard <woolardfa@appstate.edu>
     * @copyright   (c) 2013 Appalachian State Universtiy, Boone, NC
     * @license     GNU General Public License version 3
     * @package     local_userenrols
     */

    require_once($CFG->libdir.'/formslib.php');



    /**
     * Form definition for the plugin
     *
     */
    class local_userenrols_index_form extends moodleform {

        /**
         * Define the form's contents
         * @see moodleform::definition()
         */
        public function definition()
        {

            // Want to know if there are any meta enrol plugin
            // instances in this course.

            $metacourse = $this->_customdata['data']->metacourse;
            $this->_form->addElement('hidden', local_userenrols_plugin::FORMID_METACOURSE, $metacourse ? '1' : '0');
            $this->_form->setType(local_userenrols_plugin::FORMID_METACOURSE, PARAM_INT);

            if ($metacourse) {
                $this->_form->addElement('warning', null, null, get_string('INF_METACOURSE_WARN', local_userenrols_plugin::PLUGIN_NAME));
            }

            // File picker
            $this->_form->addElement('header', 'identity', get_string('LBL_FILE_OPTIONS', local_userenrols_plugin::PLUGIN_NAME));
            $this->_form->addElement('filemanager', local_userenrols_plugin::FORMID_FILES, null, null, $this->_customdata['options']);
            $this->_form->addHelpButton(local_userenrols_plugin::FORMID_FILES, 'LBL_FILE_OPTIONS', local_userenrols_plugin::PLUGIN_NAME);
            $this->_form->addRule(local_userenrols_plugin::FORMID_FILES, null, 'required', null, 'client');

            $this->add_action_buttons(true, get_string('LBL_IMPORT', local_userenrols_plugin::PLUGIN_NAME));

        } // definition



        /**
         * Validate the submitted form data
         * @see moodleform::validation()
         */
        public function validation($data, $files)
        {
            global $USER;

            $result = array();

            // File is not in the $files var, rather the itemid is in
            // $data, but we can get to it through file api. At this
            // stage, the file should be in the user's draft area
            $area_files = get_file_storage()->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $data[local_userenrols_plugin::FORMID_FILES], false, false);
            $import_file = array_shift($area_files);
            if (null == $import_file) {
                $result[local_userenrols_plugin::FORMID_FILES] = get_string('VAL_NO_FILES', local_userenrols_plugin::PLUGIN_NAME);
            }

            return $result;

        } // validation


    } // class
