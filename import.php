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

    require_once(__DIR__ . '/../../config.php');
    require_once(__DIR__ . '/lib.php');
    require_once(__DIR__ . '/import_form.php');




    // No anonymous access for this page, and this will
    // handle bogus course id values as well
    require_login();

    // Get Config 
    $config = get_config('local_userenrols');

   // Access control.
    $canmanagegroups = has_capability('moodle/course:managegroups', $PAGE->context);

    $user_context = context_user::instance($USER->id);
    require_capability('local/userenrols:access', $user_context);
 
    // Want this for subsequent print_error() calls
    $course_url = new moodle_url("{$CFG->wwwroot}/course/view.php", array('id' => $COURSE->id));
    $groups_url = new moodle_url("{$CFG->wwwroot}/group/index.php", array('id' => $COURSE->id));
    $enrol_url  = new moodle_url("{$CFG->wwwroot}/enrol/users.php", array('id' => $COURSE->id));

    $page_head_title = get_string('LBL_IMPORT_TITLE', local_userenrols_plugin::PLUGIN_NAME) . ' : ' . $COURSE->shortname;

    $PAGE->set_title($page_head_title);
    $PAGE->set_heading($page_head_title);
    $PAGE->set_pagelayout('incourse');
    $PAGE->set_url(new moodle_url("{$CFG->wwwroot}/local/userenrols/import.php", array('id' => $COURSE->id)));
    $PAGE->set_cacheable(false);

    // Fix up the form. Have not determined yet whether this is a
    // GET or POST, but the form will be used in either case.

    // Fix up our customdata object to pass to the form constructor
    $data                   = new stdClass();
    $data->course           = $COURSE;
    $data->context          = $PAGE->context;
    $data->user_id_field_options
                            = local_userenrols_plugin::get_user_id_field_options();
    $data->metacourse       = false;
    $data->default_role_id  = 0;
    $data->canmanagegroups  = $canmanagegroups;
    $data->course_id_field_options = local_userenrols_plugin::get_course_id_field_options();

    // Iterate the list of active enrol plugins looking for
    // the manual course plugin, deal breaker if not found
    $manual_enrol_instance = null;
    $enrols_enabled = enrol_get_instances($COURSE->id, true);
    foreach($enrols_enabled as $enrol) {
        if ($enrol->enrol == 'manual') {
            $manual_enrol_instance = $enrol;
            $data->default_role_id = $enrol->roleid;
            break;
        }
    }
  
    // Iterate the list of active enrol plugins looking for
    // the meta course plugin
    reset($enrols_enabled);
    foreach($enrols_enabled as $enrol) {
        if ($enrol->enrol == 'meta') {
            $data->metacourse = true;
            $data->default_role_id = 0;
            break;
        }
    }

    // Set some options for the filepicker
    $file_picker_options = array(
		'accepted_types' => array('.csv','.txt'),
        'maxbytes'       => local_userenrols_plugin::MAXFILESIZE);

    $formdata = null;
    $mform    = new local_userenrols_index_form($PAGE->url->out(), array('data' => $data, 'options' => $file_picker_options));

    $courses_all_options = local_userenrols_plugin::get_courses_filtered($config);

    if ($mform->is_cancelled()) {

        // POST request, but cancel button clicked, or formdata not
        // valid. Either event, clear out draft file area to remove
        // unused uploads, then send back to course view
        get_file_storage()->delete_area_files($user_context->id, 'user', 'draft', file_get_submitted_draft_itemid(local_userenrols_plugin::FORMID_FILES));
        redirect($course_url);

    } elseif (!$mform->is_submitted() || null == ($formdata = $mform->get_data())) {
        // GET request, or POST request where data did not
        // pass validation, either case display the form
        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(get_string('LBL_IMPORT_TITLE', local_userenrols_plugin::PLUGIN_NAME), 'HELP_PAGE_IMPORT', local_userenrols_plugin::PLUGIN_NAME);

        // Display the form with a filepicker
        echo $OUTPUT->container_start();
        $mform->display();
        echo $OUTPUT->container_end();

        echo $OUTPUT->footer();

    } else {

        // POST request, submit button clicked and formdata
        // passed validation, first check session spoofing
        require_sesskey();

        // Collect the input

        $user_id_field        = 'username';
        $role_id = 0;
        $group_assign = 1;
        $group_id = 0;
        $group_create = 1;
        $courses_all_options = local_userenrols_plugin::get_courses_filtered($config);

        $area_files = get_file_storage()->get_area_files($user_context->id, 'user', 'draft', $formdata->{local_userenrols_plugin::FORMID_FILES}, null, false);
        
        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(get_string('LBL_IMPORT_TITLE', local_userenrols_plugin::PLUGIN_NAME), 'HELP_PAGE_IMPORT', local_userenrols_plugin::PLUGIN_NAME);
        $feedback = '';

        foreach($area_files as $file){
            $c = $file->get_filename();
            $c = str_replace(array('.csv','.txt'),'',$c);
            $result = $c.' Failed'."\n";

            foreach ($courses_all_options as $course_all_options){
                if ($c == $course_all_options->shortname) { 
                    $manual_enrol_instance = null;
                    $enrols_enabled = enrol_get_instances($course_all_options->id, true);
                
                    foreach ($enrols_enabled as $enrol) {
                        if ($enrol->enrol == 'manual') {
                            $manual_enrol_instance = $enrol;
                            $data->default_role_id = $enrol->roleid;
                            break;
                        }
                    }
    // Deal breaker
                if (null == $manual_enrol_instance) {
                print_error('ERR_NO_MANUAL_ENROL', local_userenrols_plugin::PLUGIN_NAME, $course_url);
                }
            
                $result = $course_all_options->shortname. ' '.local_userenrols_plugin::import_file($course_all_options->id, $manual_enrol_instance,
                $user_id_field, $role_id, $canmanagegroups ? (boolean)$group_assign : false,
                $group_id, (boolean)$group_create, $file)."\n";
                }
            }
        $feedback .= $result;

        }
        // Clean up the file area
        get_file_storage()->delete_area_files($user_context->id, 'user', 'draft', $formdata->{local_userenrols_plugin::FORMID_FILES});

        // Output the processing result
        echo $OUTPUT->box(nl2br($feedback));
        echo $OUTPUT->continue_button($CFG->wwwroot.'/local/userenrols/import.php');     
        echo $OUTPUT->footer();

    }
