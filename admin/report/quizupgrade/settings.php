<?php
$ADMIN->add('reports', new admin_externalpage('reportquizupgrade',
        get_string('quizupgrade', 'report_quizupgrade'),
        $CFG->wwwroot . '/' . $CFG->admin . '/report/quizupgrade/index.php',
        'moodle/site:config'));
