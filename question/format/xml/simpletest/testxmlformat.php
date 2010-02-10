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
 * Unit tests for the Moodle XML format.
 *
 * @package qformat_xml
 * @copyright 2010 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/question/format/xml/format.php');


/**
 * Unit tests for the matching question definition class.
 *
 * @copyright 2009 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qformat_xml_test extends UnitTestCase {
    public function make_test_question() {
        global $USER;
        $q = new stdClass;
        $q->id = 0;
        $q->category = 0;
        $q->parent = 0;
        $q->questiontextformat = FORMAT_HTML;
        $q->defaultgrade = 1;
        $q->penalty = 0.1;
        $q->length = 1;
        $q->stamp = make_unique_id_code();
        $q->version = make_unique_id_code();
        $q->hidden = 0;
        $q->timecreated = time();
        $q->timemodified = time();
        $q->createdby = $USER->id;
        $q->modifiedby = $USER->id;
        return $q;
    }

    public function test_write_hint_basic() {
        $q = $this->make_test_question();
        $q->name = 'Short answer question';
        $q->questiontext = 'Name an amphibian: __________';
        $q->generalfeedback = 'Generalfeedback: frog or toad would have been OK.';
        $q->options->usecase = false;
        $q->options->answers = array(
            new question_answer('frog', 1.0, 'Frog is a very good answer.'),
            new question_answer('toad', 0.8, 'Toad is an OK good answer.'),
            new question_answer('*', 0.0, 'That is a bad answer.'),
        );
        $q->qtype = 'shortanswer';
        $q->hints = array(
            new question_hint('This is the first hint.')
        );

        $exporter = new qformat_xml();
        $xml = $exporter->writequestion($q);

        $this->assertPattern('|<hints>\s*<hint>\s*<text>\s*This is the first hint\.\s*</text>\s*</hint>\s*</hints>|', $xml);
        $this->assertNoPattern('|<shownumcorrect/>|', $xml);
        $this->assertNoPattern('|<clearwrong/>|', $xml);
        $this->assertNoPattern('|<options>|', $xml);
    }


    public function test_write_hint_with_parts() {
        $q = $this->make_test_question();
        $q->name = 'Matching question';
        $q->questiontext = 'Classify the animals.';
        $q->generalfeedback = 'Frogs and toads are amphibians, the others are mammals.';
        $q->qtype = 'match';

        $q->options->shuffleanswers = 1;

        $q->options->subquestions = array();
        $q->hints = array(
            new question_hint_with_parts('This is the first hint.', false, true),
            new question_hint_with_parts('This is the second hint.', true, false),
        );

        $exporter = new qformat_xml();
        $xml = $exporter->writequestion($q);

        $this->assertPattern('|<hints>\s*<hint>\s*<text>\s*This is the first hint\.\s*</text>|', $xml);
        $this->assertPattern('|<hint>\s*<text>\s*This is the second hint\.\s*</text>|', $xml);
        list($ignored, $hint1, $hint2) = explode('<hint>', $xml);
        $this->assertNoPattern('|<shownumcorrect/>|', $hint1);
        $this->assertPattern('|<clearwrong/>|', $hint1);
        $this->assertPattern('|<shownumcorrect/>|', $hint2);
        $this->assertNoPattern('|<clearwrong/>|', $hint2);
        $this->assertNoPattern('|<options>|', $xml);
    }

    public function test_import_hints_no_parts() {
        $xml = <<<END
<question>
    <hints>
        <hint>
            <text>This is the first hint</text>
            <clearwrong/>
        </hint>
        <hint>
            <text>This is the second hint</text>
            <shownumcorrect/>
        </hint>
    </hints>
</question>
END;

        $questionxml = xmlize($xml);
        $qo = new stdClass;

        $importer = new qformat_xml();
        $importer->import_hints($qo, $questionxml['question']);

        $this->assertEqual(array('This is the first hint', 'This is the second hint'),
                $qo->hint);
        $this->assertFalse(isset($qo->hintclearwrong));
        $this->assertFalse(isset($qo->hintshownumcorrect));
    }

    public function test_import_hints_with_parts() {
        $xml = <<<END
<question>
    <hints>
        <hint>
            <text>This is the first hint</text>
            <clearwrong/>
        </hint>
        <hint>
            <text>This is the second hint</text>
            <shownumcorrect/>
        </hint>
    </hints>
</question>
END;

        $questionxml = xmlize($xml);
        $qo = new stdClass;

        $importer = new qformat_xml();
        $importer->import_hints($qo, $questionxml['question'], true, true);

        $this->assertEqual(array('This is the first hint', 'This is the second hint'),
                $qo->hint);
        $this->assertEqual(array(1, 0), $qo->hintclearwrong);
        $this->assertEqual(array(0, 1), $qo->hintshownumcorrect);
    }

    public function test_import_no_hints_no_error() {
        $xml = <<<END
<question>
</question>
END;

        $questionxml = xmlize($xml);
        $qo = new stdClass;

        $importer = new qformat_xml();
        $importer->import_hints($qo, $questionxml['question']);

        $this->assertFalse(isset($qo->hint));
    }
}
