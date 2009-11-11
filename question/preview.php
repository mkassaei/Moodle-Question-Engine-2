<?php // $Id$
/**
 * This page displays a preview of a question
 *
 * The preview uses the option settings from the activity within which the question
 * is previewed or the default settings if no activity is specified. The question session
 * information is stored in the session as an array of subsequent states rather
 * than in the database.
 *
 * @author Alex Smith as part of the Serving Mathematics project
 *         {@link http://maths.york.ac.uk/serving_maths}
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questionbank
 */

require_once(dirname(__FILE__) . '/../config.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->libdir . '/formslib.php');
require_js('yui_dom-event');
require_js($CFG->httpswwwroot . '/question/preview.js');

/**
 * Settings form for the preview options.
 *
 * @copyright © 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class preview_options_form extends moodleform {
    function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'optionsheader', get_string('changeoptions', 'question'));

        $mform->addElement('select', 'model', get_string('howquestionsbehave', 'question'),
                question_engine::get_archetypal_interaction_models());
        $mform->setHelpButton('model', array('howquestionsbehave', get_string('howquestionsbehave', 'question'), 'question'));

        $mform->addElement('text', 'maxmark', get_string('markedoutof', 'question'), array('size' => '5'));
        $mform->setType('maxmark', PARAM_NUMBER);

        $mform->addElement('select', 'markdp', get_string('decimalplacesingrades', 'question'),
                question_engine::get_dp_options());

        // TODO Other fields from http://moodle.org/mod/forum/discuss.php?d=134156#p595000

        $mform->addElement('submit', 'submit', get_string('restartwiththeseoptions', 'question'));
    }
}

/**
 * Generate the URL for starting a new preview of a given question with the given options.
 * @param integer $questionid
 * @param string $preferredmodel
 * @param fload $maxmark
 * @return string the URL.
 */
function restart_url($questionid, $preferredmodel, $maxmark, $markdp) {
    global $CFG;
    return $CFG->wwwroot . '/question/preview.php?id=' . $questionid . '&model=' .
                $preferredmodel . '&maxmark=' . $maxmark . '&markdp=' . $markdp;
}

// Get and validate question id.
$id = required_param('id', PARAM_INT); // Question id
$question = question_engine::load_question($id);
require_login();
question_require_capability_on($question, 'use');
if (!$category = get_record("question_categories", "id", $question->category)) {
    print_error('unknownquestioncategory', 'question', $question->category);
}

// Get and validate display options.
$displayoptions = new question_display_options();
$displayoptions->set_review_options($CFG->quiz_review); // Quiz-specific, but a sensible source of defaults.
$displayoptions->markdp = optional_param('markdp', $CFG->quiz_decimalpoints, PARAM_INT);
// TODO various review options.
$displayoptions->flags = question_display_options::HIDDEN;
$displayoptions->manualcomment = question_display_options::HIDDEN;

// Get and validate exitsing preview, or start a new one.
$previewid = optional_param('previewid', 0, PARAM_ALPHANUM);
if ($previewid) {
    if (!isset($SESSION->question_previews[$previewid])) {
        print_error('notyourpreview', 'question');
    }
    $quba = question_engine::load_questions_usage_by_activity($previewid);
    $qnumber = $quba->get_first_question_number();
    $usedquestion = $quba->get_question($qnumber);
    if ($usedquestion->id != $question->id) {
        print_error('questionidmismatch', 'question');
    }
    $question = $usedquestion;

} else {
    $model = optional_param('model', 'deferredfeedback', PARAM_FORMAT);
    $maxmark = optional_param('maxmark', $question->defaultmark, PARAM_NUMBER);

    $quba = question_engine::make_questions_usage_by_activity('core_question_preview',
            get_context_instance_by_id($category->contextid));
    $quba->set_preferred_interaction_model($model);
    $question->maxmark = $maxmark;
    $qnumber = $quba->add_question($question);
    $quba->start_all_questions();
    question_engine::save_questions_usage_by_activity($quba);

    $SESSION->question_previews[$quba->get_id()] = true;
}

// Prepare a URL that is used in various places.
$actionurl = $CFG->wwwroot . '/question/preview.php?id=' . $question->id . '&amp;previewid=' . $quba->get_id();

// Create the settings form, and initialise the fields.
$optionsform = new preview_options_form($actionurl);
$currentoptions = new stdClass();
$currentoptions->model = $model;
$currentoptions->maxmark = $question->maxmark;
$currentoptions->markdp = $displayoptions->markdp;
$optionsform->set_data($currentoptions);

// Process change of settings, if that was requested.
if ($newoptions = $optionsform->get_submitted_data()) {
    redirect(restart_url($question->id, $newoptions->model, $newoptions->maxmark,
            $newoptions->markdp));
}

// Process any actions from the buttons at the bottom of the form.
if (data_submitted() && confirm_sesskey()) {
    if (optional_param('restart', false, PARAM_BOOL)) {
        redirect(restart_url($question->id, $quba->get_preferred_interaction_model(),
                $question->maxmark, $displayoptions->markdp));

    } else if (optional_param('fill', null, PARAM_BOOL)) {
        // TODO

    } else if (optional_param('finish', null, PARAM_BOOL)) {
        $quba->finish_all_questions();
        redirect($actionurl);
    }
}

// Output
$title = get_string('previewquestion', 'question', format_string($question->name));
$headtags = implode("\n", $quba->render_question_head_html($qnumber));
print_header($title, '', '', '', $headtags);
print_heading($title);

// Start the question form.
echo '<form method="post" action="' . $actionurl .
        '" enctype="multipart/form-data" id="responseform">', "\n";
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />', "\n";
echo '<input type="hidden" name="questionid" value="' . $quba->get_id() . '" />', "\n";

// Output the question.
echo $quba->render_question($qnumber, $displayoptions, '1');

// Finish the question form.
echo '<div id="previewcontrols" class="controls">';
echo '<input type="submit" name="restart" value="' . get_string('restart', 'question') . '" />', "\n";
// TODO Fill with correct button.
echo '<input type="submit" name="finish" value="' . get_string('submitandfinish', 'question') . '" />', "\n";
echo '</div>';
echo '<script type="text/javascript">question_preview_close_button("' .
        get_string('closepreview', 'question') . '", "previewcontrols");</script>', "\n";
echo '</form>';

// Display the settings form.
$optionsform->display();

// Finish output.
print_footer('empty');

