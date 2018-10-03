<?php

/**
 *
 * @package    local_userenrols
 * @copyright  
 * @license    
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) { // needs this condition or there is error on login page
        $settings = new admin_settingpage(
                'local_userenrols',
                get_string('userenrols_config_title', 'local_userenrols')
            );

        $settings->add(new admin_setting_configtext(
        	    'local_userenrols/shortnamefilter', 
        	    get_string('shortnamefilter', 'local_userenrols'), 
        	    get_string('shortnamefilter_desc', 'local_userenrols'), 
        	    '', PARAM_TEXT));
        
        $ADMIN->add('localplugins', $settings);

}


