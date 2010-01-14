<?php // $Id$
/**
 * Page for configuring the list Opaque question engines we can connect to.
 *
 * @copyright &copy; 2006 The Open University
 * @author T.J.Hunt@open.ac.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package opaquequestiontype
 *//** */

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');
include_once($CFG->libdir . '/validateurlsyntax.php');
require_once(dirname(__FILE__) . '/locallib.php');

// Check the user is logged in.
require_login();
if (!has_capability('moodle/question:config', get_context_instance(CONTEXT_SYSTEM, SITEID))) {
    print_error('restricteduser');
}

/** Form definition class. */
class opaque_engine_edit_form extends moodleform {
    /** Form definition. */
    function definition() {
        $mform =& $this->_form;
        $renderer =& $mform->defaultRenderer();

        $mform->addElement('text', 'enginename', get_string('enginename', 'qtype_opaque'));
        $mform->addRule('enginename', get_string('missingenginename', 'qtype_opaque'), 'required', null, 'client');
        $mform->setType('enginename', PARAM_MULTILANG);

        $mform->addElement('textarea', 'questionengineurls', get_string('questionengineurls', 'qtype_opaque'),
                'rows="5" cols="80"');
        $mform->addRule('questionengineurls', get_string('missingengineurls', 'qtype_opaque'), 'required', null, 'client');
        $mform->setType('questionengineurls', PARAM_RAW);

        $mform->addElement('textarea', 'questionbankurls', get_string('questionbankurls', 'qtype_opaque'),
                'rows="5" cols="80"');
        $mform->setType('questionbankurls', PARAM_RAW);

        $mform->addElement('text', 'passkey', get_string('passkey', 'qtype_opaque'));
        $mform->setType('passkey', PARAM_MULTILANG);
        $mform->setHelpButton('passkey', array('passkey', get_string('passkey', 'qtype_opaque'), 'qtype_opaque'));

        $mform->addElement('hidden', 'engineid');
        $mform->setType('engineid', PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * Validate the contents of a textarea field, which should be a newline-separated list of URLs.
     *
     * @param $data the form data.
     * @param $field the field to validate.
     * @param $errors any error messages are added to this array.
     */
    function validateurllist(&$data, $field, &$errors) {
        $urls = preg_split('/[\r\n]+/', $data[$field]);
        foreach ($urls as $url) {
            $url = trim($url);
            if ($url && !validateUrlSyntax($url, 's?H?S?u-P-a?I?p?f?q?r?')) {
                $errors[$field] = get_string('urlsinvalid', 'qtype_opaque');
            }
        }
    }

    /**
     * Extract the contents of a textarea field, which should be a newline-separated list of URLs.
     *
     * @param $data the form data.
     * @param $field the field to extract.
     * @param @return array those lines from the form field that are valid URLs.
     */
    function extracturllist($data, $field) {
        $rawurls = preg_split('/[\r\n]+/', $data->$field);
        $urls = array();
        foreach ($rawurls as $url) {
            $url = clean_param(trim($url), PARAM_URL);
            if ($url) {
                $urls[] = $url;
            }
        }
        return $urls;
    }

    /**
     * Validate the submitted data.
     *
     * @param $data the submitted data.
     * @return true if valid, or an array of error messages if not.
     */
    function validation($data) {
        $errors = array();
        $this->validateurllist($data, 'questionengineurls', $errors);
        $this->validateurllist($data, 'questionbankurls', $errors);

        if ($errors) {
            return $errors;
        } else {
            return true;
        }
    }
}

$mform = new opaque_engine_edit_form('editengine.php');

if ($mform->is_cancelled()){
    redirect('engines.php');
} else if ($data = $mform->get_data()){
    // Update the database.
    global $db;

    $engine = new stdClass;
    if (!empty($data->engineid)) {
        $engine->id = $data->engineid;
    }
    $engine->name = $data->enginename;
    $engine->passkey = trim($data->passkey);
    $engine->questionengines = $mform->extracturllist($data, 'questionengineurls');
    $engine->questionbanks = $mform->extracturllist($data, 'questionbankurls');
    save_engine_def($engine);
    redirect('engines.php');

} else {
    // Prepare defaults.
    $defaults = new stdClass;
    $engineid = optional_param('engineid', '0', PARAM_INT);
    $defaults->engineid = $engineid;
    if ($engineid) {
        $engine = load_engine_def($engineid);
        if (is_string($engine)) {
            print_error('unknownengine', 'qtype_opaque', 'engines.php', $engine);
        }
        $defaults->enginename = $engine->name;
        $defaults->questionengineurls = implode("\n", $engine->questionengines);
        $defaults->questionbankurls = implode("\n", $engine->questionbanks);
        $defaults->passkey = $engine->passkey;
    }

    // Display the form.
    $strtitle = get_string('editquestionengine', 'qtype_opaque');
    $navlinks[] = array('name' => get_string('configuredquestionengines', 'qtype_opaque'), 'link' => "$CFG->wwwroot/question/type/opaque/engines.php", 'type' => 'misc');
    $navlinks[] = array('name' => $strtitle, 'link' => '', 'type' => 'title');
    print_header_simple($strtitle, '', build_navigation($navlinks));
    print_heading_with_help($strtitle, 'editengine', 'qtype_opaque');
    $mform->set_data($defaults);
    $mform->display();
    print_footer();
}
?>
