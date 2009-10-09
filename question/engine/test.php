<?php

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir . '/simpletestlib.php');
require_once($CFG->dirroot . '/question/engine/simpletest/testquestionengine.php');
require_once($CFG->dirroot . '/question/engine/simpletest/testquestionattemptstep.php');
require_once($CFG->dirroot . '/question/engine/simpletest/testquestionattempt.php');
require_once($CFG->dirroot . '/question/engine/simpletest/testquestionattemptstepiterator.php');
require_once($CFG->dirroot . '/question/interaction/deferredfeedback/simpletest/testwalkthrough.php');
require_once($CFG->dirroot . '/question/interaction/manualgraded/simpletest/testwalkthrough.php');

define('QUESTION_FLAGSHIDDEN', 0);
define('QUESTION_FLAGSSHOWN', 1);
define('QUESTION_FLAGSEDITABLE', 2);

class question_truefalse_qtype {
    public function name() {
        return 'truefalse';
    }
}

class question_essay_qtype {
    public function name() {
        return 'essay';
    }
}

global $QTYPES;
$QTYPES = array(
    'essay' => new question_essay_qtype(),
    'truefalse' => new question_truefalse_qtype(),
);

$reporter = new HtmlReporter();
$test = new TestSuite();
$test->addTestClass('question_engine_test');
$test->addTestClass('question_attempt_step_test');
$test->addTestClass('question_attempt_step_iterator_test');
$test->addTestClass('question_attempt_test');
$test->addTestClass('question_attempt_with_steps_test');
$test->addTestClass('question_deferredfeedback_model_walkthrough_test');
$test->addTestClass('question_manualgraded_model_walkthrough_test');
$test->run($reporter);

function format_backtrace($callers, $plaintext = false) {
    // do not use $CFG->dirroot because it might not be available in desctructors
    $dirroot = dirname(dirname(__FILE__));
 
    if (empty($callers)) {
        return '';
    }

    $from = $plaintext ? '' : '<ul style="text-align: left">';
    foreach ($callers as $caller) {
        if (!isset($caller['line'])) {
            $caller['line'] = '?'; // probably call_user_func()
        }
        if (!isset($caller['file'])) {
            $caller['file'] = 'unknownfile'; // probably call_user_func()
        }
        $from .= $plaintext ? '* ' : '<li>';
        $from .= 'line ' . $caller['line'] . ' of ' . str_replace($dirroot, '', $caller['file']);
        if (isset($caller['function'])) {
            $from .= ': call to ';
            if (isset($caller['class'])) {
                $from .= $caller['class'] . $caller['type'];
            }
            $from .= $caller['function'] . '()';
        } else if (isset($caller['exception'])) {
            $from .= ': '.$caller['exception'].' thrown';
        }
        $from .= $plaintext ? "\n" : '</li>';
    }
    $from .= $plaintext ? '' : '</ul>';

    return $from;
}

function prepare_error_message($errorcode, $module, $link, $a) {
    global $SESSION;

    // Be careful, no guarantee moodlelib.php is loaded.
    if (empty($module) || $module == 'moodle' || $module == 'core') {
        $module = 'error';
    }
    if (function_exists('get_string')) {
        $message = get_string($errorcode, $module, $a);
        if ($module === 'error' and strpos($message, '[[') === 0) {
            // Search in moodle file if error specified - needed for backwards compatibility
            $message = get_string($errorcode, 'moodle', $a);
        }
    } else {
        $message = $module . '/' . $errorcode;
    }

    // Be careful, no guarantee weblib.php is loaded.
    if (function_exists('clean_text')) {
        $message = clean_text($message);
    } else {
        $message = htmlspecialchars($message);
    }

    if (!empty($CFG->errordocroot)) {
        $errordocroot = $CFG->errordocroot;
    } else if (!empty($CFG->docroot)) {
        $errordocroot = $CFG->docroot;
    } else {
        $errordocroot = 'http://docs.moodle.org';
    }
    if ($module === 'error') {
        $modulelink = 'moodle';
    } else {
        $modulelink = $module;
    }
    $moreinfourl = $errordocroot . '/en/error/' . $modulelink . '/' . $errorcode;

    if (empty($link)) {
        if (!empty($SESSION->fromurl)) {
            $link = $SESSION->fromurl;
            unset($SESSION->fromurl);
        } else {
            $link = $CFG->wwwroot .'/';
        }
    }

    return array($message, $moreinfourl, $link);
}

function default_exception_handler($ex) {
    $backtrace = $ex->getTrace();
    $place = array('file'=>$ex->getFile(), 'line'=>$ex->getLine(), 'exception'=>get_class($ex));
    array_unshift($backtrace, $place);

    if ($ex instanceof moodle_exception) {
        $errorcode = $ex->errorcode;
        $module = $ex->module;
        $a = $ex->a;
        $link = $ex->link;
        $debuginfo = $ex->debuginfo;
    } else {
        $errorcode = 'generalexceptionmessage';
        $module = 'error';
        $a = $ex->getMessage();
        $link = '';
        $debuginfo = null;
    }

    list($message, $moreinfourl, $link) = prepare_error_message($errorcode, $module, $link, $a);

    $content = '<div style="margin-top: 6em; margin-left:auto; margin-right:auto; color:#990000; text-align:center; font-size:large; border-width:1px;
    border-color:black; background-color:#ffffee; border-style:solid; border-radius: 20px; border-collapse: collapse;
    width: 80%; -moz-border-radius: 20px; padding: 15px">
' . $message . '
</div>';
    if (!empty($debuginfo)) {
        $content .= '<div class="notifytiny">' . $debuginfo . '</div>';
    }
    if (!empty($backtrace)) {
        $content .= '<div class="notifytiny">Stack trace: ' . format_backtrace($backtrace, false) . '</div>';
    }

    echo $content;
    exit(1); // General error code
}
set_exception_handler('default_exception_handler');
