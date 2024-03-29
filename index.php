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
 * List all files in a course.
 *
 * @package     local_questiongenerator
 * @copyright   2023 Jivielyn Sales <jivielyn.sales@gmail.com>
 * @copyright   based on work by 2017 Martin Gauk (@innoCampus, TU Berlin) and 2022 Kacper Rokicki <k.k.rokicki@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\notification;


require_once(dirname(__FILE__) . '/../../config.php');

// Import the question_bank class
require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/questiontypebase.php');

global $DB, $PAGE;

$courseid = required_param('courseid', PARAM_INT);
$component = optional_param('component', 'all', PARAM_ALPHANUMEXT);
$filetype = optional_param('filetype', 'all', PARAM_ALPHAEXT);
$action = optional_param('action', '', PARAM_ALPHAEXT);
$chosenfiles = optional_param_array('file', array(), PARAM_INT);
$num_questions = optional_param('num_ques', 1, PARAM_INT);
$type_questions = optional_param('type_question', 1, PARAM_INT);
$chosen_questions = optional_param_array('question', array(), PARAM_INT);
$file_name = optional_param('file_name', '', PARAM_ALPHANUMEXT);

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    throw new moodle_exception('invalidcourseid');
}

$context = context_course::instance($courseid);
$title = get_string('pluginname', 'local_questiongenerator');

$params = array('courseid' => $courseid);
$componentparams = array('courseid' => $courseid);
$filetypeparams = array('courseid' => $courseid);
if ($component != 'all') {
    $params['component'] = $component;
    $filetypeparams['component'] = $component;
}
if ($filetype != 'all') {
    $params['filetype'] = $filetype;
    $componentparams['filetype'] = $filetype;
}

$url = new moodle_url('/local/questiongenerator/index.php', $params);
$componenturl = new moodle_url('/local/questiongenerator/index.php', $componentparams);
$filetypeurl = new moodle_url('/local/questiongenerator/index.php', $filetypeparams);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

require_login($courseid);
require_capability('local/questiongenerator:view', $context);
$downloadallowed = has_capability('local/questiongenerator:download', $context);

$coursefiles = new local_questiongenerator\course_files($courseid, $context, $component, $filetype);

$renderer = $PAGE->get_renderer('local_questiongenerator');


if ($action === 'generate') {
    require_sesskey();
    try {
        $questions_dir = $coursefiles->generate_questions($chosenfiles, $num_questions, $type_questions);
        
        echo $OUTPUT->header();
        echo $renderer->question_selection_page($type_questions, $questions_dir);
        echo $OUTPUT->footer();
    } catch (moodle_exception $e) {
        notification::add($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    }
}
else if ($action === 'save'){
    echo $OUTPUT->header();
    $temp_dir = "temp/";
    $file_path = $temp_dir . $file_name . ".txt";
    $params = $coursefiles->save_questions($url, $course, $courseid, $context, $chosen_questions, $file_path, $type_questions);
    echo $OUTPUT->continue_button(new moodle_url('/question/edit.php', $params));
    echo $OUTPUT->footer();
    
}
else{
    echo $OUTPUT->header();
    echo $renderer->overview_page($url, $componenturl, $filetypeurl, $coursefiles, $downloadallowed);
    echo $OUTPUT->footer();
}