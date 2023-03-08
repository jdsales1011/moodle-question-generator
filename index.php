<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * @package     local_questiongenerator
 * @copyright   2023 Jivielyn Sales <jivielyn.sales@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


 // INITIAL CODE.
require_once('../../config.php');
require_once($CFG->dirroot. '/local/questiongenerator/lib.php');
require_once($CFG->dirroot. '/local/questiongenerator/generation_form.php');

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/questiongenerator/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title($SITE->fullname);
$PAGE->set_heading(get_string('pluginname', 'local_questiongenerator'));

// INITIALIZE FORM.
$generatorform = new local_questiongenerator_generation_form();

// DISPLAYING OF PAGE.
echo $OUTPUT->header();

echo '<h4> Generate questions based on the inputed article/resource </h4>';

$generatorform->display();

// If form is submitted, retrieve data from form.
if ($data = $generatorform->get_data()) {
    // Save text to context variable.
    $context = required_param('message', PARAM_TEXT);
    $number = required_param('num_ques', PARAM_INT);
    $qtype = required_param('type_question', PARAM_INT);

    $output = shell_exec('python3 python_logic.py "' . escapeshellarg($context) . '" ' . escapeshellarg($number) . ' ' . escapeshellarg($qtype));
    echo $output;
}

echo $OUTPUT->footer();
