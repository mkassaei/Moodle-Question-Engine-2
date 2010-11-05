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
 * Script to upgrade the attempts at a particular quiz, after confirmation.
 *
 * @package report_quizupgrade
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->libdir . '/adminlib.php');

$quizid = required_param('quizid', PARAM_INT);
$confirmed = optional_param('confirmed', false, PARAM_BOOL);

require_login();
require_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM));

$baseurl = $CFG->wwwroot . '/' . $CFG->admin . '/report/quizupgrade/';
$indexurl = $baseurl . 'index.php';
$quizsummary = report_quizupgrade_get_quiz($quizid);
if (!$quizsummary) {
    print_error('invalidquizid', 'report_quizupgrade', $indexurl);
}
$quizsummary->name = format_string($quizsummary->name);

admin_externalpage_setup('reportquizupgrade');

if ($confirmed && data_submitted() && confirm_sesskey()) {
    // Actually do the conversion.
    admin_externalpage_print_header();
    print_heading(get_string('upgradingquizattempts', 'report_quizupgrade', $quizsummary));

    $upgrader = new report_quizupgrade_attempt_upgrader($quizsummary->id, $quizsummary->numtoconvert);
    $upgrader->convert_all_quiz_attempts();

    print_heading(get_string('done', 'report_quizupgrade'));
    print_continue($indexurl);

    admin_externalpage_print_footer();
    exit;
}

// Print an are-you-sure page.
admin_externalpage_print_header();
print_heading(get_string('areyousure', 'report_quizupgrade'));

$message = get_string('areyousuremessage', 'report_quizupgrade', $quizsummary);
$params = array('quizid' => $quizsummary->id, 'confirmed' => 1, 'sesskey' => sesskey());
notice_yesno($message, $baseurl . 'convertquiz.php', $indexurl,
        $params, null, 'post', 'get');

admin_externalpage_print_footer();