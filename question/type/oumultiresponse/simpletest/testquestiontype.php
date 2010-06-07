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
    private $qtype;

    public function setUp() {
        $this->qtype = new qtype_oumultiresponse();
    }

    public function tearDown() {
        $this->qtype = null;
    }

    public function test_name() {
        $this->assertEqual($this->qtype->name(), 'oumultiresponse');
    }

    public function test_get_random_guess_score() {
        $questiondata = new stdClass;
        $questiondata->options->answers = array(
            1 => new question_answer('A', 1, ''),
            2 => new question_answer('B', 0, ''),
            3 => new question_answer('C', 0, ''),
        );
        $this->assertWithinMargin(1/3, $this->qtype->get_random_guess_score($questiondata), 0.000001);

        $questiondata->options->answers[2]->fraction = 1;
        $this->assertWithinMargin(2/3, $this->qtype->get_random_guess_score($questiondata), 0.000001);

        $questiondata->options->answers[4] = new question_answer('D', 0, '');
        $this->assertWithinMargin(1/2, $this->qtype->get_random_guess_score($questiondata), 0.000001);
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
