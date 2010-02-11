<?php  // $Id$
/**
 * Defines the editing form for the essay question type.
 *
 * @copyright &copy; 2007 Jamie Pratt
 * @author Jamie Pratt me@jamiep.org
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questionbank
 * @subpackage questiontypes
 */

/**
 * essay editing form definition.
 */
class question_edit_essay_form extends question_edit_form {
    public function qtype() {
        return 'essay';
    }
}
