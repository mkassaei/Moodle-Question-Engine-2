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
 * Web services admin UI forms
 *
 * @package   webservice
 * @copyright 2009 Moodle Pty Ltd (http://moodle.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once $CFG->libdir.'/formslib.php';

class external_service_form extends moodleform {
    function definition() {
        global $CFG, $USER;

        $mform = $this->_form;
        $service = $this->_customdata;

        $mform->addElement('header', 'extservice', get_string('externalservice', 'webservice'));

        $mform->addElement('text', 'name', get_string('name'));
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'webservice'));


        /// needed to select automatically the 'No required capability" option
        $currentcapabilityexist = false;
        if (empty($service->requiredcapability))
        {
          $service->requiredcapability = "norequiredcapability";
          $currentcapabilityexist = true;
        }

        // Prepare the list of capabilites to choose from
        $systemcontext = get_context_instance(CONTEXT_SYSTEM);
        $allcapabilities = fetch_context_capabilities($systemcontext);
        $capabilitychoices = array();
        $capabilitychoices['norequiredcapability'] = get_string('norequiredcapability', 'webservice');
        foreach ($allcapabilities as $cap) {
            $capabilitychoices[$cap->name] = $cap->name . ': ' . get_capability_string($cap->name);
            if (!empty($service->requiredcapability) && $service->requiredcapability == $cap->name) {
                $currentcapabilityexist = true;
            }
        }

        $mform->addElement('searchableselector', 'requiredcapability', get_string('requiredcapability', 'webservice'), $capabilitychoices, array('size' => 12));
       
        /// display notification error if the current requiredcapability doesn't exist anymore
        if(empty($currentcapabilityexist)) {
            global $OUTPUT;
            $mform->addElement('static', 'capabilityerror', '', $OUTPUT->notification(get_string('selectedcapabilitydoesntexit','webservice', $service->requiredcapability)));
            $service->requiredcapability = "norequiredcapability";
        }
        $mform->addElement('advcheckbox', 'restrictedusers', get_string('restrictedusers', 'webservice'));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true);
 
        $this->set_data($service);
    }

    function definition_after_data() {
        $mform = $this->_form;
        $service = $this->_customdata;

        if (!empty($service->component)) {
            // built-in components must not be modified except the enabled flag!!
            $mform->hardFreeze('name,requiredcapability,restrictedusers');
        }
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);     
        return $errors;
    }
}