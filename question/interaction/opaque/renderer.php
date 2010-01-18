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
 * Renderer for outputting parts of a question when the actual interaction model
 * used is not available.
 *
 * @package qim_opaque
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


class qim_opaque_renderer extends qim_renderer {
    public function get_state_string(question_attempt $qa) {
        $state = $qa->get_state();
        $omstate = $qa->get_last_im_var('_statestring');
        if (question_state::is_finished($state)) {
            return question_state::default_string($state);
        } else if ($omstate){
            return $omstate;
        } else {
            return get_string('submissionnotcorrect', 'qim_opaque');
        }
    }

    public function controls(question_attempt $qa, question_display_options $options) {
        if (question_state::is_gave_up($qa->get_state())) {
            return $this->output_tag('div', array('class' => 'question_aborted'),
                    get_string('notcompletedmessage', 'qtype_opaque'));
        }

        $opaquestate =& update_opaque_state($qa);
        if (is_string($opaquestate)) {
            return notify($opaquestate, '', '', true);
            // TODO
        }

        return $this->output_tag('div', array('class', opaque_browser_type()), $opaquestate->xhtml);
    }

    // TODO
    function get_html_head_contributions(question_attempt $qa, question_display_options $options) {
        $contributions = array('<link rel="stylesheet" type="text/css" href="' .
                    $this->plugin_baseurl() . '/styles.css" />');

        $opaquestate =& update_opaque_state($qa);

        $resourcecache = new opaque_resource_cache($question->options->engineid,
                $question->options->remoteid, $question->options->remoteversion);
        if (!empty($opaquestate->cssfilename) && $resourcecache->file_in_cache($opaquestate->cssfilename)) {
            $contributions[] = '<link rel="stylesheet" type="text/css" href="' .
                    $resourcecache->file_url($opaquestate->cssfilename) . '" />';
        }

        if(!empty($opaquestate->headXHTML)) {
            $contributions[] = $opaquestate->headXHTML;
        }

        return $contributions;
    }
}