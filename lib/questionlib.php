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
 * Code for handling and processing questions
 *
 * This is code that is module independent, i.e., can be used by any module that
 * uses questions, like quiz, lesson, ..
 * This script also loads the questiontype classes
 * Code for handling the editing of questions is in {@link question/editlib.php}
 *
 * TODO: separate those functions which form part of the API
 *       from the helper functions.
 *
 * @package moodlecore
 * @subpackage questionbank
 * @copyright 1999 onwards Martin Dougiamas and others {@link http://moodle.com}
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->dirroot . '/question/type/questiontype.php');


/// CONSTANTS ///////////////////////////////////

/**#@+
 * The core question types.
 */
define("SHORTANSWER",   "shortanswer");
define("TRUEFALSE",     "truefalse");
define("MULTICHOICE",   "multichoice");
define("RANDOM",        "random");
define("MATCH",         "match");
define("RANDOMSAMATCH", "randomsamatch");
define("DESCRIPTION",   "description");
define("NUMERICAL",     "numerical");
define("MULTIANSWER",   "multianswer");
define("CALCULATED",    "calculated");
define("ESSAY",         "essay");
/**#@-*/

/**
 * Constant determines the number of answer boxes supplied in the editing
 * form for multiple choice and similar question types.
 */
define("QUESTION_NUMANS", 10);

/**
 * Constant determines the number of answer boxes supplied in the editing
 * form for multiple choice and similar question types to start with, with
 * the option of adding QUESTION_NUMANS_ADD more answers.
 */
define("QUESTION_NUMANS_START", 3);

/**
 * Constant determines the number of answer boxes to add in the editing
 * form for multiple choice and similar question types when the user presses
 * 'add form fields button'.
 */
define("QUESTION_NUMANS_ADD", 3);

/**
 * The options used when popping up a question preview window in Javascript.
 */
define('QUESTION_PREVIEW_POPUP_OPTIONS', 'scrollbars=yes,resizable=yes,width=800,height=600');

/**#@+
 * options used in forms that move files.
 */
define('QUESTION_FILENOTHINGSELECTED', 0);
define('QUESTION_FILEDONOTHING', 1);
define('QUESTION_FILECOPY', 2);
define('QUESTION_FILEMOVE', 3);
define('QUESTION_FILEMOVELINKSONLY', 4);

/**#@-*/

/**
 * @global array holding question type objects
 * @deprecated
 */
global $QTYPES;
$QTYPES = question_bank::get_all_qtypes();


/**
 * An array of question type names translated to the user's language, suitable for use when
 * creating a drop-down menu of options.
 *
 * Long-time Moodle programmers will realise that this replaces the old $QTYPE_MENU array.
 * The array returned will only hold the names of all the question types that the user should
 * be able to create directly. Some internal question types like random questions are excluded.
 *
 * @return array an array of question type names translated to the user's language.
 */
function question_type_menu() {
    static $menu_options = null;
    if (is_null($menu_options)) {
        $menu_options = array();
        foreach (question_bank::get_all_qtypes() as $name => $qtype) {
            $menuname = $qtype->menu_name();
            if ($menuname) {
                $menu_options[$name] = $menuname;
            }
        }
    }
    return $menu_options;
}

/// FUNCTIONS //////////////////////////////////////////////////////

/**
 * Returns an array of names of activity modules that use this question
 *
 * @param object $questionid
 * @return array of strings
 */
function question_list_instances($questionid) {
    global $CFG;
    $instances = array();
    $modules = get_records('modules');
    foreach ($modules as $module) {
        $fullmod = $CFG->dirroot . '/mod/' . $module->name;
        if (file_exists($fullmod . '/lib.php')) {
            include_once($fullmod . '/lib.php');
            $fn = $module->name.'_question_list_instances';
            if (function_exists($fn)) {
                $instances = $instances + $fn($questionid);
            }
        }
    }
    return $instances;
}

/**
 * Determine whether there arey any questions belonging to this context, that is whether any of its
 * question categories contain any questions. This will return true even if all the questions are
 * hidden.
 *
 * @param mixed $context either a context object, or a context id.
 * @return boolean whether any of the question categories beloning to this context have
 *         any questions in them.
 */
function question_context_has_any_questions($context) {
    global $CFG;
    if (is_object($context)) {
        $contextid = $context->id;
    } else if (is_numeric($context)) {
        $contextid = $context;
    } else {
        print_error('invalidcontextinhasanyquestions', 'question');
    }
    return record_exists_sql('SELECT * FROM ' . $CFG->prefix . 'question q ' .
            'JOIN ' . $CFG->prefix . 'question_categories qc ON qc.id = q.category ' .
            "WHERE qc.contextid = $contextid AND q.parent = 0");
}

/**
 * Returns list of 'allowed' grades for grade selection
 * formatted suitably for dropdown box function
 * @return object ->gradeoptionsfull full array ->gradeoptions +ve only
 */
function get_grade_options() {
    // define basic array of grades. This list comprises all fractions of the form:
    // a. p/q for q <= 6, 0 <= p <= q
    // b. p/10 for 0 <= p <= 10
    // c. 1/q for 1 <= q <= 10
    // d. 1/20
    $grades = array(
        1.0000000,
        0.9000000,
        0.8333333,
        0.8000000,
        0.7500000,
        0.7000000,
        0.6666667,
        0.6000000,
        0.5000000,
        0.4000000,
        0.3333333,
        0.3000000,
        0.2500000,
        0.2000000,
        0.1666667,
        0.1428571,
        0.1250000,
        0.1111111,
        0.1000000,
        0.0500000,
        0.0000000);

    // iterate through grades generating full range of options
    $gradeoptionsfull = array();
    $gradeoptions = array();
    foreach ($grades as $grade) {
        $percentage = 100 * $grade;
        $neggrade = -$grade;
        $gradeoptions["$grade"] = "$percentage %";
        $gradeoptionsfull["$grade"] = "$percentage %";
        $gradeoptionsfull["$neggrade"] = -$percentage." %";
    }
    $gradeoptionsfull["0"] = $gradeoptions["0"] = get_string("none");

    // sort lists
    arsort($gradeoptions, SORT_NUMERIC);
    arsort($gradeoptionsfull, SORT_NUMERIC);

    // construct return object
    $grades = new stdClass;
    $grades->gradeoptions = $gradeoptions;
    $grades->gradeoptionsfull = $gradeoptionsfull;

    return $grades;
}

/**
 * match grade options
 * if no match return error or match nearest
 * @param array $gradeoptionsfull list of valid options
 * @param int $grade grade to be tested
 * @param string $matchgrades 'error' or 'nearest'
 * @return mixed either 'fixed' value or false if erro
 */
function match_grade_options($gradeoptionsfull, $grade, $matchgrades='error') {
    // if we just need an error...
    if ($matchgrades=='error') {
        foreach($gradeoptionsfull as $value => $option) {
            // slightly fuzzy test, never check floats for equality :-)
            if (abs($grade-$value)<0.00001) {
                return $grade;
            }
        }
        // didn't find a match so that's an error
        return false;
    }
    // work out nearest value
    else if ($matchgrades=='nearest') {
        $hownear = array();
        foreach($gradeoptionsfull as $value => $option) {
            if ($grade==$value) {
                return $grade;
            }
            $hownear[ $value ] = abs( $grade - $value );
        }
        // reverse sort list of deltas and grab the last (smallest)
        asort( $hownear, SORT_NUMERIC );
        reset( $hownear );
        return key( $hownear );
    }
    else {
        return false;
    }
}

/**
 * Tests whether a category is in use by any activity module
 *
 * @return boolean
 * @param integer $categoryid
 * @param boolean $recursive Whether to examine category children recursively
 */
function question_category_isused($categoryid, $recursive = false) {

    //Look at each question in the category
    if ($questions = get_records('question', 'category', $categoryid)) {
        foreach ($questions as $question) {
            if (count(question_list_instances($question->id))) {
                return true;
            }
        }
    }

    //Look under child categories recursively
    if ($recursive) {
        if ($children = get_records('question_categories', 'parent', $categoryid)) {
            foreach ($children as $child) {
                if (question_category_isused($child->id, $recursive)) {
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Deletes question and all associated data from the database
 *
 * It will not delete a question if it is used by an activity module
 * @param object $question  The question being deleted
 */
function delete_question($questionid) {
    global $QTYPES;

    if (!$question = get_record('question', 'id', $questionid)) {
        // In some situations, for example if this was a child of a
        // Cloze question that was previously deleted, the question may already
        // have gone. In this case, just do nothing.
        return;
    }

    // Do not delete a question if it is used by an activity module
    if (count(question_list_instances($questionid))) {
        return;
    }

    // delete questiontype-specific data
    question_require_capability_on($question, 'edit');
    if ($question) {
        if (isset($QTYPES[$question->qtype])) {
            $QTYPES[$question->qtype]->delete_question($questionid);
        }
    } else {
        echo "Question with id $questionid does not exist.<br />";
    }

    if ($states = get_records('question_states', 'question', $questionid)) {
        $stateslist = implode(',', array_keys($states));

        // delete questiontype-specific data
        foreach ($QTYPES as $qtype) {
            $qtype->delete_states($stateslist);
        }
    }

    // delete entries from all other question tables
    // It is important that this is done only after calling the questiontype functions
    delete_records("question_answers", "question", $questionid);
    delete_records("question_states", "question", $questionid);
    delete_records("question_sessions", "questionid", $questionid);

    // Now recursively delete all child questions
    if ($children = get_records('question', 'parent', $questionid)) {
        foreach ($children as $child) {
            if ($child->id != $questionid) {
                delete_question($child->id);
            }
        }
    }

    // Finally delete the question record itself
    delete_records('question', 'id', $questionid);

    return;
}

/**
 * All question categories and their questions are deleted for this course.
 *
 * @param object $mod an object representing the activity
 * @param boolean $feedback to specify if the process must output a summary of its work
 * @return boolean
 */
function question_delete_course($course, $feedback=true) {
    //To store feedback to be showed at the end of the process
    $feedbackdata   = array();

    //Cache some strings
    $strcatdeleted = get_string('unusedcategorydeleted', 'quiz');
    $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
    $categoriescourse = get_records('question_categories', 'contextid', $coursecontext->id, 'parent', 'id, parent, name');

    if ($categoriescourse) {

        //Sort categories following their tree (parent-child) relationships
        //this will make the feedback more readable
        $categoriescourse = sort_categories_by_tree($categoriescourse);

        foreach ($categoriescourse as $category) {

            //Delete it completely (questions and category itself)
            //deleting questions
            if ($questions = get_records("question", "category", $category->id)) {
                foreach ($questions as $question) {
                    delete_question($question->id);
                }
                delete_records("question", "category", $category->id);
            }
            //delete the category
            delete_records('question_categories', 'id', $category->id);

            //Fill feedback
            $feedbackdata[] = array($category->name, $strcatdeleted);
        }
        //Inform about changes performed if feedback is enabled
        if ($feedback) {
            $table = new stdClass;
            $table->head = array(get_string('category','quiz'), get_string('action'));
            $table->data = $feedbackdata;
            print_table($table);
        }
    }
    return true;
}

/**
 * Category is about to be deleted,
 * 1/ All question categories and their questions are deleted for this course category.
 * 2/ All questions are moved to new category
 *
 * @param object $category course category object
 * @param object $newcategory empty means everything deleted, otherwise id of category where content moved
 * @param boolean $feedback to specify if the process must output a summary of its work
 * @return boolean
 */
function question_delete_course_category($category, $newcategory, $feedback=true) {
    $context = get_context_instance(CONTEXT_COURSECAT, $category->id);
    if (empty($newcategory)) {
        $feedbackdata   = array(); // To store feedback to be showed at the end of the process
        $rescueqcategory = null; // See the code around the call to question_save_from_deletion.
        $strcatdeleted = get_string('unusedcategorydeleted', 'quiz');

        // Loop over question categories.
        if ($categories = get_records('question_categories', 'contextid', $context->id, 'parent', 'id, parent, name')) {
            foreach ($categories as $category) {

                // Deal with any questions in the category.
                if ($questions = get_records('question', 'category', $category->id)) {

                    // Try to delete each question.
                    foreach ($questions as $question) {
                        delete_question($question->id);
                    }

                    // Check to see if there were any questions that were kept because they are
                    // still in use somehow, even though quizzes in courses in this category will
                    // already have been deteted. This could happen, for example, if questions are
                    // added to a course, and then that course is moved to another category (MDL-14802).
                    $questionids = get_records_select_menu('question', 'category = ' . $category->id, '', 'id,1');
                    if (!empty($questionids)) {
                        if (!$rescueqcategory = question_save_from_deletion(implode(',', array_keys($questionids)),
                                get_parent_contextid($context), print_context_name($context), $rescueqcategory)) {
                            return false;
                       }
                       $feedbackdata[] = array($category->name, get_string('questionsmovedto', 'question', $rescueqcategory->name));
                    }
                }

                // Now delete the category.
                if (!delete_records('question_categories', 'id', $category->id)) {
                    return false;
                }
                $feedbackdata[] = array($category->name, $strcatdeleted);

            } // End loop over categories.
        }

        // Output feedback if requested.
        if ($feedback and $feedbackdata) {
            $table = new stdClass;
            $table->head = array(get_string('questioncategory','question'), get_string('action'));
            $table->data = $feedbackdata;
            print_table($table);
        }

    } else {
        // Move question categories ot the new context.
        if (!$newcontext = get_context_instance(CONTEXT_COURSECAT, $newcategory->id)) {
            return false;
        }
        if (!set_field('question_categories', 'contextid', $newcontext->id, 'contextid', $context->id)) {
            return false;
        }
        if ($feedback) {
            $a = new stdClass;
            $a->oldplace = print_context_name($context);
            $a->newplace = print_context_name($newcontext);
            notify(get_string('movedquestionsandcategories', 'question', $a), 'notifysuccess');
        }
    }

    return true;
}

/**
 * Enter description here...
 *
 * @param string $questionids list of questionids
 * @param object $newcontext the context to create the saved category in.
 * @param string $oldplace a textual description of the think being deleted, e.g. from get_context_name
 * @param object $newcategory
 * @return mixed false on
 */
function question_save_from_deletion($questionids, $newcontextid, $oldplace, $newcategory = null) {
    // Make a category in the parent context to move the questions to.
    if (is_null($newcategory)) {
        $newcategory = new object();
        $newcategory->parent = 0;
        $newcategory->contextid = $newcontextid;
        $newcategory->name = addslashes(get_string('questionsrescuedfrom', 'question', $oldplace));
        $newcategory->info = addslashes(get_string('questionsrescuedfrominfo', 'question', $oldplace));
        $newcategory->sortorder = 999;
        $newcategory->stamp = make_unique_id_code();
        if (!$newcategory->id = insert_record('question_categories', $newcategory)) {
            return false;
        }
    }

    // Move any remaining questions to the 'saved' category.
    if (!question_move_questions_to_category($questionids, $newcategory->id)) {
        return false;
    }
    return $newcategory;
}

/**
 * All question categories and their questions are deleted for this activity.
 *
 * @param object $cm the course module object representing the activity
 * @param boolean $feedback to specify if the process must output a summary of its work
 * @return boolean
 */
function question_delete_activity($cm, $feedback=true) {
    //To store feedback to be showed at the end of the process
    $feedbackdata   = array();

    //Cache some strings
    $strcatdeleted = get_string('unusedcategorydeleted', 'quiz');
    $modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
    if ($categoriesmods = get_records('question_categories', 'contextid', $modcontext->id, 'parent', 'id, parent, name')){
        //Sort categories following their tree (parent-child) relationships
        //this will make the feedback more readable
        $categoriesmods = sort_categories_by_tree($categoriesmods);

        foreach ($categoriesmods as $category) {

            //Delete it completely (questions and category itself)
            //deleting questions
            if ($questions = get_records("question", "category", $category->id)) {
                foreach ($questions as $question) {
                    delete_question($question->id);
                }
                delete_records("question", "category", $category->id);
            }
            //delete the category
            delete_records('question_categories', 'id', $category->id);

            //Fill feedback
            $feedbackdata[] = array($category->name, $strcatdeleted);
        }
        //Inform about changes performed if feedback is enabled
        if ($feedback) {
            $table = new stdClass;
            $table->head = array(get_string('category','quiz'), get_string('action'));
            $table->data = $feedbackdata;
            print_table($table);
        }
    }
    return true;
}

/**
 * This function should be considered private to the question bank, it is called from
 * question/editlib.php question/contextmoveq.php and a few similar places to to the work of
 * acutally moving questions and associated data. However, callers of this function also have to
 * do other work, which is why you should not call this method directly from outside the questionbank.
 *
 * @param string $questionids a comma-separated list of question ids.
 * @param integer $newcategory the id of the category to move to.
 */
function question_move_questions_to_category($questionids, $newcategory) {
    $result = true;

    // Move the questions themselves.
    $result = $result && set_field_select('question', 'category', $newcategory, "id IN ($questionids)");

    // Move any subquestions belonging to them.
    $result = $result && set_field_select('question', 'category', $newcategory, "parent IN ($questionids)");

    // TODO Deal with datasets.

    return $result;
}

/**
 * @param array $row tab objects
 * @param question_edit_contexts $contexts object representing contexts available from this context
 * @param string $querystring to append to urls
 * */
function questionbank_navigation_tabs(&$row, $contexts, $querystring) {
    global $CFG, $QUESTION_EDITTABCAPS;
    $tabs = array(
            'questions' =>array("$CFG->wwwroot/question/edit.php?$querystring", get_string('questions', 'quiz'), get_string('editquestions', 'quiz')),
            'categories' =>array("$CFG->wwwroot/question/category.php?$querystring", get_string('categories', 'quiz'), get_string('editqcats', 'quiz')),
            'import' =>array("$CFG->wwwroot/question/import.php?$querystring", get_string('import', 'quiz'), get_string('importquestions', 'quiz')),
            'export' =>array("$CFG->wwwroot/question/export.php?$querystring", get_string('export', 'quiz'), get_string('exportquestions', 'quiz')));
    foreach ($tabs as $tabname => $tabparams){
        if ($contexts->have_one_edit_tab_cap($tabname)) {
            $row[] = new tabobject($tabname, $tabparams[0], $tabparams[1], $tabparams[2]);
        }
    }
}

/**
 * Given a list of ids, load the basic information about a set of questions from the questions table.
 * The $join and $extrafields arguments can be used together to pull in extra data.
 * See, for example, the usage in mod/quiz/attemptlib.php, and
 * read the code below to see how the SQL is assembled. Throws exceptions on error.
 *
 * @global object
 * @global object
 * @param array $questionids array of question ids.
 * @param string $extrafields extra SQL code to be added to the query.
 * @param string $join extra SQL code to be added to the query.
 * @param array $extraparams values for any placeholders in $join.
 * You are strongly recommended to use named placeholder.
 *
 * @return array partially complete question objects. You need to call get_question_options
 * on them before they can be properly used.
 */
function question_preload_questions($questionids, $extrafields = '', $join = '') {
    global $CFG;
    if (empty($questionids)) {
        return array();
    }
    if ($join) {
        $join = ' JOIN '.$join;
    }
    if ($extrafields) {
        $extrafields = ', ' . $extrafields;
    }
    $sql = 'SELECT q.*' . $extrafields . " FROM {$CFG->prefix}question q" . $join .
            ' WHERE q.id IN (' . implode(',', $questionids) . ')';

    // Load the questions
    if (!$questions = get_records_sql($sql)) {
        return 'Could not load questions.';
    }

    foreach ($questions as $question) {
        $question->_partiallyloaded = true;
    }

    // Note, a possible optimisation here would be to not load the TEXT fields
    // (that is, questiontext and generalfeedback) here, and instead load them in
    // question_load_questions. That would add one DB query, but reduce the amount
    // of data transferred most of the time. I am not going to do this optimisation
    // until it is shown to be worthwhile.

    return $questions;
}

/**
 * Load a set of questions, given a list of ids. The $join and $extrafields arguments can be used
 * together to pull in extra data. See, for example, the usage in mod/quiz/attempt.php, and
 * read the code below to see how the SQL is assembled. Throws exceptions on error.
 *
 * @param array $questionids array of question ids.
 * @param string $extrafields extra SQL code to be added to the query.
 * @param string $join extra SQL code to be added to the query.
 * @param array $extraparams values for any placeholders in $join.
 * You are strongly recommended to use named placeholder.
 *
 * @return array question objects.
 */
function question_load_questions($questionids, $extrafields = '', $join = '') {
    $questions = question_preload_questions($questionids, $extrafields, $join);

    // Load the question type specific information
    if (!get_question_options($questions)) {
        return 'Could not load the question options';
    }

    return $questions;
}

/**
 * Private function to factor common code out of get_question_options().
 *
 * @param object $question the question to tidy.
 * @param boolean $loadtags load the question tags from the tags table. Optional, default false.
 * @return boolean true if successful, else false.
 */
function _tidy_question(&$question, $loadtags = false) {
    global $CFG, $QTYPES;
    if (!array_key_exists($question->qtype, $QTYPES)) {
        $question->qtype = 'missingtype';
        $question->questiontext = '<p>' . get_string('warningmissingtype', 'quiz') . '</p>' . $question->questiontext;
    }
    if ($success = $QTYPES[$question->qtype]->get_question_options($question)) {
        if (isset($question->_partiallyloaded)) {
            unset($question->_partiallyloaded);
        }
    }
    if ($loadtags && !empty($CFG->usetags)) {
        require_once($CFG->dirroot . '/tag/lib.php');
        $question->tags = tag_get_tags_array('question', $question->id);
    }
    return $success;
}

/**
 * Updates the question objects with question type specific
 * information by calling {@link get_question_options()}
 *
 * Can be called either with an array of question objects or with a single
 * question object.
 *
 * @param mixed $questions Either an array of question objects to be updated
 *         or just a single question object
 * @param boolean $loadtags load the question tags from the tags table. Optional, default false.
 * @return bool Indicates success or failure.
 */
function get_question_options(&$questions, $loadtags = false) {
    if (is_array($questions)) { // deal with an array of questions
        foreach ($questions as $i => $notused) {
            if (!_tidy_question($questions[$i], $loadtags)) {
                return false;
            }
        }
        return true;
    } else { // deal with single question
        return _tidy_question($questions, $loadtags);
    }
}

/**
 * Returns the html for question feedback image.
 * @param float   $fraction  value representing the correctness of the user's
 *                           response to a question.
 * @param boolean $selected  whether or not the answer is the one that the
 *                           user picked.
 * @return string
 */
function question_get_feedback_image($fraction, $selected=true) {

    global $CFG;

    if ($fraction > 0.9999999) {
        if ($selected) {
            $feedbackimg = '<img src="'.$CFG->pixpath.'/i/tick_green_big.gif" '.
                            'alt="'.get_string('correct', 'quiz').'" class="icon" />';
        } else {
            $feedbackimg = '<img src="'.$CFG->pixpath.'/i/tick_green_small.gif" '.
                            'alt="'.get_string('correct', 'quiz').'" class="icon" />';
        }
    } else if ($fraction >= 0.0000001) {
        if ($selected) {
            $feedbackimg = '<img src="'.$CFG->pixpath.'/i/tick_amber_big.gif" '.
                            'alt="'.get_string('partiallycorrect', 'quiz').'" class="icon" />';
        } else {
            $feedbackimg = '<img src="'.$CFG->pixpath.'/i/tick_amber_small.gif" '.
                            'alt="'.get_string('partiallycorrect', 'quiz').'" class="icon" />';
        }
    } else {
        if ($selected) {
            $feedbackimg = '<img src="'.$CFG->pixpath.'/i/cross_red_big.gif" '.
                            'alt="'.get_string('incorrect', 'quiz').'" class="icon" />';
        } else {
            $feedbackimg = '<img src="'.$CFG->pixpath.'/i/cross_red_small.gif" '.
                            'alt="'.get_string('incorrect', 'quiz').'" class="icon" />';
        }
    }
    return $feedbackimg;
}


/**
 * Returns the class name for question feedback.
 * @param float  $fraction  value representing the correctness of the user's
 *                          response to a question.
 * @return string
 */
function question_get_feedback_class($fraction) {

    global $CFG;

    if ($fraction > 0.9999999) {
        $class = 'correct';
    } else if ($fraction >= 0.0000001) {
        $class = 'partiallycorrect';
    } else {
        $class = 'incorrect';
    }
    return $class;
}

/**
* Print the icon for the question type
*
* @param object $question  The question object for which the icon is required
* @param boolean $return   If true the functions returns the link as a string
*/
function print_question_icon($question, $return = false) {
    global $QTYPES, $CFG;

    if (array_key_exists($question->qtype, $QTYPES)) {
        $namestr = $QTYPES[$question->qtype]->menu_name();
    } else {
        $namestr = 'missingtype';
    }
    $html = '<img src="' . $CFG->wwwroot . '/question/type/' .
            $question->qtype . '/icon.gif" alt="' .
            $namestr . '" title="' . $namestr . '" />';
    if ($return) {
        return $html;
    } else {
        echo $html;
    }
}

/**
 * Creates a stamp that uniquely identifies this version of the question
 *
 * In future we want this to use a hash of the question data to guarantee that
 * identical versions have the same version stamp.
 *
 * @param object $question
 * @return string A unique version stamp
 */
function question_hash($question) {
    return make_unique_id_code();
}


/// FUNCTIONS THAT SIMPLY WRAP QUESTIONTYPE METHODS //////////////////////////////////
/**
 * Get anything that needs to be included in the head of the question editing page
 * for a particular question type. This function is called by question/question.php.
 *
 * @param $question A question object. Only $question->qtype is used.
 * @return string some HTML code that can go inside the head tag.
 */
function get_editing_head_contributions($question) {
    global $QTYPES;
    $contributions = $QTYPES[$question->qtype]->get_editing_head_contributions();
    return implode("\n", array_unique($contributions));
}

/**
 * Saves question options
 *
 * Simply calls the question type specific save_question_options() method.
 */
function save_question_options($question) {
    global $QTYPES;

    $QTYPES[$question->qtype]->save_question_options($question);
}

/// CATEGORY FUNCTIONS /////////////////////////////////////////////////////////////////

/**
 * returns the categories with their names ordered following parent-child relationships
 * finally it tries to return pending categories (those being orphaned, whose parent is
 * incorrect) to avoid missing any category from original array.
 */
function sort_categories_by_tree(&$categories, $id = 0, $level = 1) {
    $children = array();
    $keys = array_keys($categories);

    foreach ($keys as $key) {
        if (!isset($categories[$key]->processed) && $categories[$key]->parent == $id) {
            $children[$key] = $categories[$key];
            $categories[$key]->processed = true;
            $children = $children + sort_categories_by_tree($categories, $children[$key]->id, $level+1);
        }
    }
    //If level = 1, we have finished, try to look for non processed categories (bad parent) and sort them too
    if ($level == 1) {
        foreach ($keys as $key) {
            //If not processed and it's a good candidate to start (because its parent doesn't exist in the course)
            if (!isset($categories[$key]->processed) && !record_exists('question_categories', 'course', $categories[$key]->course, 'id', $categories[$key]->parent)) {
                $children[$key] = $categories[$key];
                $categories[$key]->processed = true;
                $children = $children + sort_categories_by_tree($categories, $children[$key]->id, $level+1);
            }
        }
    }
    return $children;
}

/**
 * Private method, only for the use of add_indented_names().
 *
 * Recursively adds an indentedname field to each category, starting with the category
 * with id $id, and dealing with that category and all its children, and
 * return a new array, with those categories in the right order.
 *
 * @param array $categories an array of categories which has had childids
 *          fields added by flatten_category_tree(). Passed by reference for
 *          performance only. It is not modfied.
 * @param int $id the category to start the indenting process from.
 * @param int $depth the indent depth. Used in recursive calls.
 * @return array a new array of categories, in the right order for the tree.
 */
function flatten_category_tree(&$categories, $id, $depth = 0, $nochildrenof = -1) {

    // Indent the name of this category.
    $newcategories = array();
    $newcategories[$id] = $categories[$id];
    $newcategories[$id]->indentedname = str_repeat('&nbsp;&nbsp;&nbsp;', $depth) . $categories[$id]->name;

    // Recursively indent the children.
    foreach ($categories[$id]->childids as $childid) {
        if ($childid != $nochildrenof){
            $newcategories = $newcategories + flatten_category_tree($categories, $childid, $depth + 1, $nochildrenof);
        }
    }

    // Remove the childids array that were temporarily added.
    unset($newcategories[$id]->childids);

    return $newcategories;
}

/**
 * Format categories into an indented list reflecting the tree structure.
 *
 * @param array $categories An array of category objects, for example from the.
 * @return array The formatted list of categories.
 */
function add_indented_names($categories, $nochildrenof = -1) {

    // Add an array to each category to hold the child category ids. This array will be removed
    // again by flatten_category_tree(). It should not be used outside these two functions.
    foreach (array_keys($categories) as $id) {
        $categories[$id]->childids = array();
    }

    // Build the tree structure, and record which categories are top-level.
    // We have to be careful, because the categories array may include published
    // categories from other courses, but not their parents.
    $toplevelcategoryids = array();
    foreach (array_keys($categories) as $id) {
        if (!empty($categories[$id]->parent) && array_key_exists($categories[$id]->parent, $categories)) {
            $categories[$categories[$id]->parent]->childids[] = $id;
        } else {
            $toplevelcategoryids[] = $id;
        }
    }

    // Flatten the tree to and add the indents.
    $newcategories = array();
    foreach ($toplevelcategoryids as $id) {
        $newcategories = $newcategories + flatten_category_tree($categories, $id, 0, $nochildrenof);
    }

    return $newcategories;
}

/**
 * Output a select menu of question categories.
 *
 * Categories from this course and (optionally) published categories from other courses
 * are included. Optionally, only categories the current user may edit can be included.
 *
 * @param integer $courseid the id of the course to get the categories for.
 * @param integer $published if true, include publised categories from other courses.
 * @param integer $only_editable if true, exclude categories this user is not allowed to edit.
 * @param integer $selected optionally, the id of a category to be selected by default in the dropdown.
 */
function question_category_select_menu($contexts, $top = false, $currentcat = 0, $selected = "", $nochildrenof = -1) {
    $categoriesarray = question_category_options($contexts, $top, $currentcat, false, $nochildrenof);
    if ($selected) {
        $nothing = '';
    } else {
        $nothing = 'choose';
    }
    choose_from_menu_nested($categoriesarray, 'category', $selected, $nothing);
}

/**
* Gets the default category in the most specific context.
* If no categories exist yet then default ones are created in all contexts.
*
* @param array $contexts  The context objects for this context and all parent contexts.
* @return object The default category - the category in the course context
*/
function question_make_default_categories($contexts) {
    static $preferredlevels = array(
        CONTEXT_COURSE => 4,
        CONTEXT_MODULE => 3,
        CONTEXT_COURSECAT => 2,
        CONTEXT_SYSTEM => 1,
    );
    $toreturn = null;
    $preferredness = 0;
    // If it already exists, just return it.
    foreach ($contexts as $key => $context) {
        if (!$categoryrs = get_recordset_select("question_categories", "contextid = '{$context->id}'", 'sortorder, name', '*', '', 1)) {
            error('error getting category record');
        } else {
            if (!$category = rs_fetch_record($categoryrs)){
                // Otherwise, we need to make one
                $category = new stdClass;
                $contextname = print_context_name($context, false, true);
                $category->name = addslashes(get_string('defaultfor', 'question', $contextname));
                $category->info = addslashes(get_string('defaultinfofor', 'question', $contextname));
                $category->contextid = $context->id;
                $category->parent = 0;
                $category->sortorder = 999; // By default, all categories get this number, and are sorted alphabetically.
                $category->stamp = make_unique_id_code();
                if (!$category->id = insert_record('question_categories', $category)) {
                    error('Error creating a default category for context '.print_context_name($context));
                }
            }
        }
        if ($preferredlevels[$context->contextlevel] > $preferredness &&
                has_any_capability(array('moodle/question:usemine', 'moodle/question:useall'), $context)) {
            $toreturn = $category;
            $preferredness = $preferredlevels[$context->contextlevel];
        }
    }

    if (!is_null($toreturn)) {
        $toreturn = clone($toreturn);
    }
    return $toreturn;
}

/**
 * Get all the category objects, including a count of the number of questions in that category,
 * for all the categories in the lists $contexts.
 *
 * @param mixed $contexts either a single contextid, or a comma-separated list of context ids.
 * @param string $sortorder used as the ORDER BY clause in the select statement.
 * @return array of category objects.
 */
function get_categories_for_contexts($contexts, $sortorder = 'parent, sortorder, name ASC') {
    global $CFG;
    return get_records_sql("
            SELECT c.*, (SELECT count(1) FROM {$CFG->prefix}question q
                    WHERE c.id = q.category AND q.hidden='0' AND q.parent='0') as questioncount
            FROM {$CFG->prefix}question_categories c
            WHERE c.contextid IN ($contexts)
            ORDER BY $sortorder");
}

/**
 * Output an array of question categories.
 */
function question_category_options($contexts, $top = false, $currentcat = 0, $popupform = false, $nochildrenof = -1) {
    global $CFG;
    $pcontexts = array();
    foreach($contexts as $context){
        $pcontexts[] = $context->id;
    }
    $contextslist = join($pcontexts, ', ');

    $categories = get_categories_for_contexts($contextslist);

    $categories = question_add_context_in_key($categories);

    if ($top){
        $categories = question_add_tops($categories, $pcontexts);
    }
    $categories = add_indented_names($categories, $nochildrenof);

    //sort cats out into different contexts
    $categoriesarray = array();
    foreach ($pcontexts as $pcontext){
        $contextstring = print_context_name(get_context_instance_by_id($pcontext), true, true);
        foreach ($categories as $category) {
            if ($category->contextid == $pcontext){
                $cid = $category->id;
                if ($currentcat!= $cid || $currentcat==0) {
                    $countstring = (!empty($category->questioncount))?" ($category->questioncount)":'';
                    $categoriesarray[$contextstring][$cid] = $category->indentedname.$countstring;
                }
            }
        }
    }
    if ($popupform){
        $popupcats = array();
        foreach ($categoriesarray as $contextstring => $optgroup){
            $popupcats[] = '--'.$contextstring;
            $popupcats = array_merge($popupcats, $optgroup);
            $popupcats[] = '--';
        }
        return $popupcats;
    } else {
        return $categoriesarray;
    }
}

function question_add_context_in_key($categories){
    $newcatarray = array();
    foreach ($categories as $id => $category) {
        $category->parent = "$category->parent,$category->contextid";
        $category->id = "$category->id,$category->contextid";
        $newcatarray["$id,$category->contextid"] = $category;
    }
    return $newcatarray;
}

function question_add_tops($categories, $pcontexts){
    $topcats = array();
    foreach ($pcontexts as $context){
        $newcat = new object();
        $newcat->id = "0,$context";
        $newcat->name = get_string('top');
        $newcat->parent = -1;
        $newcat->contextid = $context;
        $topcats["0,$context"] = $newcat;
    }
    //put topcats in at beginning of array - they'll be sorted into different contexts later.
    return array_merge($topcats, $categories);
}

/**
 * Returns a comma separated list of ids of the category and all subcategories
 */
function question_categorylist($categoryid) {
    // returns a comma separated list of ids of the category and all subcategories
    $categorylist = $categoryid;
    if ($subcategories = get_records('question_categories', 'parent', $categoryid, 'sortorder ASC', 'id, 1 AS notused')) {
        foreach ($subcategories as $subcategory) {
            $categorylist .= ','. question_categorylist($subcategory->id);
        }
    }
    return $categorylist;
}


//===========================
// Import/Export Functions
//===========================

/**
 * Get list of available import or export formats
 * @param string $type 'import' if import list, otherwise export list assumed
 * @return array sorted list of import/export formats available
**/
function get_import_export_formats( $type ) {

    global $CFG;
    $fileformats = get_list_of_plugins("question/format");

    $fileformatname=array();
    require_once( "{$CFG->dirroot}/question/format.php" );
    foreach ($fileformats as $key => $fileformat) {
        $format_file = $CFG->dirroot . "/question/format/$fileformat/format.php";
        if (file_exists( $format_file ) ) {
            require_once( $format_file );
        }
        else {
            continue;
        }
        $classname = "qformat_$fileformat";
        $format_class = new $classname();
        if ($type=='import') {
            $provided = $format_class->provide_import();
        }
        else {
            $provided = $format_class->provide_export();
        }
        if ($provided) {
            $formatname = get_string($fileformat, 'quiz');
            if ($formatname == "[[$fileformat]]") {
                $formatname = get_string($fileformat, 'qformat_'.$fileformat);
                if ($formatname == "[[$fileformat]]") {
                    $formatname = $fileformat;  // Just use the raw folder name
                }
            }
            $fileformatnames[$fileformat] = $formatname;
        }
    }
    natcasesort($fileformatnames);

    return $fileformatnames;
}

/**
* Create default export filename
*
* @return string   default export filename
* @param object $course
* @param object $category
*/
function default_export_filename($course,$category) {
    //Take off some characters in the filename !!
    $takeoff = array(" ", ":", "/", "\\", "|");
    $export_word = str_replace($takeoff,"_",moodle_strtolower(get_string("exportfilename","quiz")));
    //If non-translated, use "export"
    if (substr($export_word,0,1) == "[") {
        $export_word= "export";
    }

    //Calculate the date format string
    $export_date_format = str_replace(" ","_",get_string("exportnameformat","quiz"));
    //If non-translated, use "%Y%m%d-%H%M"
    if (substr($export_date_format,0,1) == "[") {
        $export_date_format = "%%Y%%m%%d-%%H%%M";
    }

    //Calculate the shortname
    $export_shortname = clean_filename($course->shortname);
    if (empty($export_shortname) or $export_shortname == '_' ) {
        $export_shortname = $course->id;
    }

    //Calculate the category name
    $export_categoryname = clean_filename($category->name);

    //Calculate the final export filename
    //The export word
    $export_name = $export_word."-";
    //The shortname
    $export_name .= moodle_strtolower($export_shortname)."-";
    //The category name
    $export_name .= moodle_strtolower($export_categoryname)."-";
    //The date format
    $export_name .= userdate(time(),$export_date_format,99,false);
    //Extension is supplied by format later.

    return $export_name;
}

class context_to_string_translator{
    /**
     * @var array used to translate between contextids and strings for this context.
     */
    var $contexttostringarray = array();

    function context_to_string_translator($contexts){
        $this->generate_context_to_string_array($contexts);
    }

    function context_to_string($contextid){
        return $this->contexttostringarray[$contextid];
    }

    function string_to_context($contextname){
        $contextid = array_search($contextname, $this->contexttostringarray);
        return $contextid;
    }

    function generate_context_to_string_array($contexts){
        if (!$this->contexttostringarray){
            $catno = 1;
            foreach ($contexts as $context){
                switch  ($context->contextlevel){
                    case CONTEXT_MODULE :
                        $contextstring = 'module';
                        break;
                    case CONTEXT_COURSE :
                        $contextstring = 'course';
                        break;
                    case CONTEXT_COURSECAT :
                        $contextstring = "cat$catno";
                        $catno++;
                        break;
                    case CONTEXT_SYSTEM :
                        $contextstring = 'system';
                        break;
                }
                $this->contexttostringarray[$context->id] = $contextstring;
            }
        }
    }

}

/**
 * Check capability on category
 * @param mixed $question object or id
 * @param string $cap 'add', 'edit', 'view', 'use', 'move'
 * @param integer $cachecat useful to cache all question records in a category
 * @return boolean this user has the capability $cap for this question $question?
 */
function question_has_capability_on($question, $cap, $cachecat = -1){
    global $USER;
    // nicolasconnault@gmail.com In some cases I get $question === false. Since no such object exists, it can't be deleted, we can safely return true
    if ($question === false) {
        return true;
    }

    // these are capabilities on existing questions capabilties are
    //set per category. Each of these has a mine and all version. Append 'mine' and 'all'
    $question_questioncaps = array('edit', 'view', 'use', 'move');
    static $questions = array();
    static $categories = array();
    static $cachedcat = array();
    if ($cachecat != -1 && (array_search($cachecat, $cachedcat)===FALSE)){
        $questions += get_records('question', 'category', $cachecat);
        $cachedcat[] = $cachecat;
    }
    if (!is_object($question)){
        if (!isset($questions[$question])){
            if (!$questions[$question] = get_record('question', 'id', $question)){
                print_error('questiondoesnotexist', 'question');
            }
        }
        $question = $questions[$question];
    }
    if (!isset($categories[$question->category])){
        if (!$categories[$question->category] = get_record('question_categories', 'id', $question->category)){
            print_error('invalidcategory', 'quiz');
        }
    }
    $category = $categories[$question->category];

    if (array_search($cap, $question_questioncaps)!== FALSE){
        if (!has_capability('moodle/question:'.$cap.'all', get_context_instance_by_id($category->contextid))){
            if ($question->createdby == $USER->id){
                return has_capability('moodle/question:'.$cap.'mine', get_context_instance_by_id($category->contextid));
            } else {
                return false;
            }
        } else {
            return true;
        }
    } else {
        return has_capability('moodle/question:'.$cap, get_context_instance_by_id($category->contextid));
    }

}

/**
 * Require capability on question.
 */
function question_require_capability_on($question, $cap){
    if (!question_has_capability_on($question, $cap)){
        print_error('nopermissions', '', '', $cap);
    }
    return true;
}

function question_file_links_base_url($courseid){
    global $CFG;
    $baseurl = preg_quote("$CFG->wwwroot/file.php", '!');
    $baseurl .= '('.preg_quote('?file=', '!').')?';//may or may not
                                     //be using slasharguments, accept either
    $baseurl .= "/$courseid/";//course directory
    return $baseurl;
}

/*
 * Find all course / site files linked to in a piece of html.
 * @param string html the html to search
 * @param int course search for files for courseid course or set to siteid for
 *              finding site files.
 * @return array files with keys being files.
 */
function question_find_file_links_from_html($html, $courseid){
    global $CFG;
    $baseurl = question_file_links_base_url($courseid);
    $searchfor = '!'.
                   '(<\s*(a|img)\s[^>]*(href|src)\s*=\s*")'.$baseurl.'([^"]*)"'.
                   '|'.
                   '(<\s*(a|img)\s[^>]*(href|src)\s*=\s*\')'.$baseurl.'([^\']*)\''.
                  '!i';
    $matches = array();
    $no = preg_match_all($searchfor, $html, $matches);
    if ($no){
        $rawurls = array_filter(array_merge($matches[5], $matches[10]));//array_filter removes empty elements
        //remove any links that point somewhere they shouldn't
        foreach (array_keys($rawurls) as $rawurlkey){
            if (!$cleanedurl = question_url_check($rawurls[$rawurlkey])){
                unset($rawurls[$rawurlkey]);
            } else {
                $rawurls[$rawurlkey] = $cleanedurl;
            }

        }
        $urls = array_flip($rawurls);// array_flip removes duplicate files
                                            // and when we merge arrays will continue to automatically remove duplicates
    } else {
        $urls = array();
    }
    return $urls;
}
/*
 * Check that url doesn't point anywhere it shouldn't
 *
 * @param $url string relative url within course files directory
 * @return mixed boolean false if not OK or cleaned URL as string if OK
 */
function question_url_check($url){
    global $CFG;
    if ((substr(strtolower($url), 0, strlen($CFG->moddata)) == strtolower($CFG->moddata)) ||
            (substr(strtolower($url), 0, 10) == 'backupdata')){
        return false;
    } else {
        return clean_param($url, PARAM_PATH);
    }
}

/*
 * Find all course / site files linked to in a piece of html.
 * @param string html the html to search
 * @param int course search for files for courseid course or set to siteid for
 *              finding site files.
 * @return array files with keys being files.
 */
function question_replace_file_links_in_html($html, $fromcourseid, $tocourseid, $url, $destination, &$changed){
    global $CFG;
    require_once($CFG->libdir .'/filelib.php');
    $tourl = get_file_url("$tocourseid/$destination");
    $fromurl = question_file_links_base_url($fromcourseid).preg_quote($url, '!');
    $searchfor = array('!(<\s*(a|img)\s[^>]*(href|src)\s*=\s*")'.$fromurl.'(")!i',
                   '!(<\s*(a|img)\s[^>]*(href|src)\s*=\s*\')'.$fromurl.'(\')!i');
    $newhtml = preg_replace($searchfor, '\\1'.$tourl.'\\5', $html);
    if ($newhtml != $html){
        $changed = true;
    }
    return $newhtml;
}

function get_filesdir_from_context($context){
    switch ($context->contextlevel){
        case CONTEXT_COURSE :
            $courseid = $context->instanceid;
            break;
        case CONTEXT_MODULE :
            $courseid = get_field('course_modules', 'course', 'id', $context->instanceid);
            break;
        case CONTEXT_COURSECAT :
        case CONTEXT_SYSTEM :
            $courseid = SITEID;
            break;
        default :
            error('Unsupported contextlevel in category record!');
    }
    return $courseid;
}
