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
 * Unit tests for the OU multiple response question type class.
 *
 * @package qtype_oumultiresponse
 * @copyright 2008 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->dirroot . '/question/engine/simpletest/helpers.php');
require_once($CFG->dirroot . '/question/type/oumultiresponse/questiontype.php');


/**
 * Unit tests for (some of) question/type/oumultiresponse/questiontype.php.
 *
 * @copyright 2008 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_oumultiresponse_test extends UnitTestCase {
    private $tolerance = 0.0001;
    private $qtype;

    function setUp() {
        $this->qtype = new qtype_oumultiresponse();
    }

    function tearDown() {
        $this->qtype = null;
    }

    function test_name() {
        $this->assertEqual($this->qtype->name(), 'oumultiresponse');
    }

    function replace_char_at() {
        $this->assertEqual($this->qtype->replace_char_at('220', 0, '0'), '020');
    }

    function test_grade_computation() {
        $right = new stdClass;
        $right->fraction = 1.0;
        $wrong = new stdClass;
        $wrong->fraction = 0.0;

        $penalty = 0.333333;
        $answers = array($right, $right, $right, $wrong, $wrong, $wrong);

        $response_history = array('111', '000', '000', '000', '000', '000');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.33333, $this->tolerance);

        $response_history = array('111', '111', '000', '000', '000', '000');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.66667, $this->tolerance);

        $response_history = array('1', '1', '1', '0', '0', '0');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 1.0, $this->tolerance);

        $response_history = array('111', '111', '111', '111', '000', '000');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.66667, $this->tolerance);

        $response_history = array('111', '111', '111', '111', '111', '000');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.33333, $this->tolerance);

        $response_history = array('111', '111', '111', '111', '111', '111');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.0, $this->tolerance);

        $response_history = array('011', '000', '000', '100', '111', '111');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.22222, $this->tolerance);

        $response_history = array('001', '000', '000', '110', '111', '111');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.11111, $this->tolerance);

        $response_history = array('111', '111', '001', '100', '010', '000');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.77778, $this->tolerance);

        $response_history = array('100', '100', '001', '100', '011', '001');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.11111, $this->tolerance);

        $response_history = array('101', '101', '001', '110', '011', '111');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.11111, $this->tolerance);

        $response_history = array('011', '001', '001', '100', '110', '111');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.33333, $this->tolerance);

        $response_history = array('111', '111', '111', '110', '110', '100');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.44444, $this->tolerance);

        $response_history = array('111', '111', '111', '110', '100', '100');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.55556, $this->tolerance);

        $response_history = array('110', '101', '101', '111', '110', '100');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.22222, $this->tolerance);

        $response_history = array('111', '110', '110', '111', '111', '100');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.22222, $this->tolerance);

        $response_history = array('011', '111', '110', '111', '111', '100');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.22222, $this->tolerance);

        $response_history = array('110', '111', '110', '111', '111', '100');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.22222, $this->tolerance);

        $response_history = array('111', '111', '111', '110', '110', '100');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.44444, $this->tolerance);

        $response_history = array('110', '111', '110', '111', '111', '100');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.22222, $this->tolerance);

        $response_history = array('011', '111', '110', '111', '111', '100');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.22222, $this->tolerance);

        $response_history = array('011', '111', '110', '110', '111', '001');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.33333, $this->tolerance);

        $response_history = array('11', '01', '01', '10', '10', '00');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 3), 0.77778, $this->tolerance);

        $penalty = 0.2;
        $answers = array($right, $right, $right, $right, $wrong, $wrong, $wrong, $wrong);
        $response_history = array('11111', '10111', '11100', '11011', '10011', '01010', '01000', '00100');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 5), 0.45, $this->tolerance);

        $penalty = 0.33334;
        $answers = array($right, $right, $wrong, $wrong, $wrong);
        $response_history = array('0', '0', '1', '1', '0');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 1), 0.0, $this->tolerance);

        $response_history = array('0', '1', '1', '0', '0');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 1), 0.5, $this->tolerance);

        $response_history = array('1', '1', '0', '0', '0');
        $this->assertWithinMargin($this->qtype->grade_computation(
                $response_history, $answers, $penalty, 1), 1.0, $this->tolerance);
    }

    public function test_xml_import() {
        $xml = '  <question type="oumultiresponse">
    <name>
      <text>OU multiple response question</text>
    </name>
    <questiontext format="html">
      <text>Which are the odd numbers?</text>
    </questiontext>
    <generalfeedback>
      <text>General feedback.</text>
    </generalfeedback>
    <defaultgrade>6</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <answernumbering>abc</answernumbering>
    <shuffleanswers>true</shuffleanswers>
    <correctfeedback>
      <text>Well done.</text>
    </correctfeedback>
    <partiallycorrectfeedback>
      <text>Not entirely.</text>
    </partiallycorrectfeedback>
    <incorrectfeedback>
      <text>Completely wrong!</text>
    </incorrectfeedback>
    <answer fraction="100">
      <text>One</text>
      <feedback>
        <text>Specific feedback to correct answer.</text>
      </feedback>
    </answer>
    <answer fraction="0">
      <text>Two</text>
      <feedback>
        <text>Specific feedback to wrong answer.</text>
      </feedback>
    </answer>
    <answer fraction="100">
      <text>Three</text>
      <feedback>
        <text>Specific feedback to correct answer.</text>
      </feedback>
    </answer>
    <answer fraction="0">
      <text>Four</text>
      <feedback>
        <text>Specific feedback to wrong answer.</text>
      </feedback>
    </answer>
    <hint>
      <text>Try again.</text>
      <shownumcorrect />
    </hint>
    <hint>
      <text>Hint 2.</text>
      <shownumcorrect />
      <clearwrong />
      <options>1</options>
    </hint>
  </question>';
        $xmldata = xmlize($xml);

        $importer = new qformat_xml();
        $q = $importer->try_importing_using_qtypes(
                $xmldata['question'], null, null, 'oumultiresponse');

        $expectedq = new stdClass;
        $expectedq->qtype = 'oumultiresponse';
        $expectedq->name = 'OU multiple response question';
        $expectedq->questiontext = 'Which are the odd numbers?';
        $expectedq->questiontextformat = FORMAT_HTML;
        $expectedq->generalfeedback = 'General feedback.';
        $expectedq->defaultgrade = 6;
        $expectedq->length = 1;
        $expectedq->penalty = 0.3333333;

        $expectedq->shuffleanswers = 1;
        $expectedq->correctfeedback = 'Well done.';
        $expectedq->partiallycorrectfeedback = 'Not entirely.';
        $expectedq->shownumcorrect = false;
        $expectedq->incorrectfeedback = 'Completely wrong!';

        $expectedq->answer = array('One', 'Two', 'Three', 'Four');
        $expectedq->correctanswer = array(1, 0, 1, 0);
        $expectedq->feedback = array(
            'Specific feedback to correct answer.',
            'Specific feedback to wrong answer.',
            'Specific feedback to correct answer.',
            'Specific feedback to wrong answer.',
        );

        $expectedq->hint = array('Try again.', 'Hint 2.');
        $expectedq->hintshownumcorrect = array(true, true);
        $expectedq->hintclearwrong = array(false, true);
        $expectedq->hintshowchoicefeedback = array(false, true);

        $this->assert(new CheckSpecifiedFieldsExpectation($expectedq), $q);
    }

    public function test_xml_import_legacy() {
        $xml = '  <question type="oumultiresponse">
    <name>
      <text>008 OUMR feedback test</text>
    </name>
    <questiontext format="html">
      <text>&lt;p&gt;OUMR question.&lt;/p&gt; &lt;p&gt;Right answers are eighta and eightb.&lt;/p&gt;</text>
    </questiontext>
    <image></image>
    <generalfeedback>
      <text>General feedback.</text>
    </generalfeedback>
    <defaultgrade>1</defaultgrade>
    <penalty>0.33</penalty>
    <hidden>0</hidden>
    <shuffleanswers>1</shuffleanswers>
    <answernumbering>abc</answernumbering>
    <shuffleanswers>true</shuffleanswers>
    <answer>
      <correctanswer>1</correctanswer>
      <text>eighta</text>
      <feedback>
        <text>&lt;p&gt;Specific feedback to correct answer.&lt;/p&gt;</text>
      </feedback>
    </answer>
    <answer>
      <correctanswer>1</correctanswer>
      <text>eightb</text>
      <feedback>
        <text>&lt;p&gt;Specific feedback to correct answer.&lt;/p&gt;</text>
      </feedback>
    </answer>
    <answer>
      <correctanswer>0</correctanswer>
      <text>one</text>
      <feedback>
        <text>&lt;p&gt;Specific feedback to wrong answer.&lt;/p&gt;</text>
      </feedback>
    </answer>
    <answer>
      <correctanswer>0</correctanswer>
      <text>two</text>
      <feedback>
        <text>&lt;p&gt;Specific feedback to wrong answer.&lt;/p&gt;</text>
      </feedback>
    </answer>
    <correctfeedback>
      <text>Correct overall feedback</text>
    </correctfeedback>
    <correctresponsesfeedback>0</correctresponsesfeedback>
    <partiallycorrectfeedback>
      <text>Partially correct overall feedback.</text>
    </partiallycorrectfeedback>
    <incorrectfeedback>
      <text>Incorrect overall feedback.</text>
    </incorrectfeedback>
    <unlimited>0</unlimited>
    <penalty>0.33</penalty>
    <hint>
      <statenumberofcorrectresponses>0</statenumberofcorrectresponses>
      <showfeedbacktoresponses>1</showfeedbacktoresponses>
      <clearincorrectresponses>0</clearincorrectresponses>
      <hintcontent>
        <text>Hint 1.</text>
      </hintcontent>
    </hint>
    <hint>
      <statenumberofcorrectresponses>0</statenumberofcorrectresponses>
      <showfeedbacktoresponses>1</showfeedbacktoresponses>
      <clearincorrectresponses>0</clearincorrectresponses>
      <hintcontent>
        <text>Hint 2.</text>
      </hintcontent>
    </hint>
  </question>';
        $xmldata = xmlize($xml);

        $importer = new qformat_xml();
        $q = $importer->try_importing_using_qtypes(
                $xmldata['question'], null, null, 'oumultiresponse');

        $expectedq = new stdClass;
        $expectedq->qtype = 'oumultiresponse';
        $expectedq->name = '008 OUMR feedback test';
        $expectedq->questiontext = '<p>OUMR question.</p><p>Right answers are eighta and eightb.</p>';
        $expectedq->questiontextformat = FORMAT_HTML;
        $expectedq->generalfeedback = 'General feedback.';
        $expectedq->defaultgrade = 1;
        $expectedq->length = 1;
        $expectedq->penalty = 0.3333333;

        $expectedq->shuffleanswers = 1;
        $expectedq->answernumbering = 'abc';
        $expectedq->correctfeedback = 'Correct overall feedback';
        $expectedq->partiallycorrectfeedback = 'Partially correct overall feedback.';
        $expectedq->shownumcorrect = false;
        $expectedq->incorrectfeedback = 'Incorrect overall feedback.';

        $expectedq->answer = array('eighta', 'eightb', 'one', 'two');
        $expectedq->correctanswer = array(1, 1, 0, 0);
        $expectedq->feedback = array(
            '<p>Specific feedback to correct answer.</p>',
            '<p>Specific feedback to correct answer.</p>',
            '<p>Specific feedback to wrong answer.</p>',
            '<p>Specific feedback to wrong answer.</p>',
        );

        $expectedq->hint = array('Hint 1.', 'Hint 2.');
        $expectedq->hintshownumcorrect = array(false, false);
        $expectedq->hintclearwrong = array(false, false);
        $expectedq->hintshowchoicefeedback = array(true, true);

        $this->assert(new CheckSpecifiedFieldsExpectation($expectedq), $q);
    }

    public function test_xml_export() {
        $qdata = new stdClass;
        $qdata->id = 123;
        $qdata->qtype = 'oumultiresponse';
        $qdata->name = 'OU multiple response question';
        $qdata->questiontext = 'Which are the odd numbers?';
        $qdata->questiontextformat = FORMAT_HTML;
        $qdata->generalfeedback = 'General feedback.';
        $qdata->defaultgrade = 6;
        $qdata->length = 1;
        $qdata->penalty = 0.3333333;
        $qdata->hidden = 0;

        $qdata->options->shuffleanswers = 1;
        $qdata->options->answernumbering = '123';
        $qdata->options->correctfeedback = 'Well done.';
        $qdata->options->partiallycorrectfeedback = 'Not entirely.';
        $qdata->options->shownumcorrect = false;
        $qdata->options->incorrectfeedback = 'Completely wrong!';

        $qdata->options->answers = array(
            new question_answer('One', 1, 'Specific feedback to correct answer.'),
            new question_answer('Two', 0, 'Specific feedback to wrong answer.'),
            new question_answer('Three', 1, 'Specific feedback to correct answer.'),
            new question_answer('Four', 0, 'Specific feedback to wrong answer.'),
        );

        $qdata->hints = array(
            new question_hint_with_parts('Try again.', true, false),
            new question_hint_with_parts('Hint 2.', true, true),
        );
        $qdata->hints[0]->options = 0;
        $qdata->hints[1]->options = 1;

        $exporter = new qformat_xml();
        $xml = $exporter->writequestion($qdata);

        $expectedxml = '<!-- question: 123  -->
  <question type="oumultiresponse">
    <name>
      <text>OU multiple response question</text>
    </name>
    <questiontext format="html">
      <text>Which are the odd numbers?</text>
    </questiontext>
    <generalfeedback>
      <text>General feedback.</text>
    </generalfeedback>
    <defaultgrade>6</defaultgrade>
    <penalty>0.3333333</penalty>
    <hidden>0</hidden>
    <shuffleanswers>true</shuffleanswers>
    <answernumbering>123</answernumbering>
    <correctfeedback>
      <text>Well done.</text>
    </correctfeedback>
    <partiallycorrectfeedback>
      <text>Not entirely.</text>
    </partiallycorrectfeedback>
    <incorrectfeedback>
      <text>Completely wrong!</text>
    </incorrectfeedback>
    <answer fraction="100">
      <text>One</text>
      <feedback>
        <text>Specific feedback to correct answer.</text>
      </feedback>
    </answer>
    <answer fraction="0">
      <text>Two</text>
      <feedback>
        <text>Specific feedback to wrong answer.</text>
      </feedback>
    </answer>
    <answer fraction="100">
      <text>Three</text>
      <feedback>
        <text>Specific feedback to correct answer.</text>
      </feedback>
    </answer>
    <answer fraction="0">
      <text>Four</text>
      <feedback>
        <text>Specific feedback to wrong answer.</text>
      </feedback>
    </answer>
    <hint>
      <text>Try again.</text>
      <shownumcorrect/>
    </hint>
    <hint>
      <text>Hint 2.</text>
      <shownumcorrect/>
      <clearwrong/>
      <options>1</options>
    </hint>
  </question>
';

        $this->assertEqual($expectedxml, $xml);
    }
}
