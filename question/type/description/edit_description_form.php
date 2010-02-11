<?php  // $Id$
/**
 * Defines the editing form for the description question type.
 *
 * @copyright &copy; 2007 Jamie Pratt
 * @author Jamie Pratt me@jamiep.org
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questionbank
 * @subpackage questiontypes
 */

/**
 * description editing form definition.
 */
class question_edit_description_form extends question_edit_form {
    /**
     * Add question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function definition_inner($mform) {
        // We don't need this default element.
        $mform->removeElement('defaultgrade');
        $mform->addElement('hidden', 'defaultgrade', 0);
        $mform->setType('defaultgrade', PARAM_RAW);
    }

    public function qtype() {
        return 'description';
    }
}
