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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class local_questiongenerator_generation_form extends moodleform {
    /**
     * Define the form.
     */
    public function definition() {
        $mform = $this->_form;

        // Add textarea.
        $mform->addElement('textarea', 'content', get_string('entercontext', 'local_questiongenerator', 'wrap="virtual" rows="20" cols="50"'));
        $mform->setType('content', PARAM_TEXT);

        // Add num element.
        $mform->addElement('float', 'num_ques', get_string('enter_num_questions', 'local_questiongenerator'));

        // Add type of question radio element.
        $radioarray = array();
        $radioarray[] = $mform->createElement('radio', 'type_question', '', get_string('identification', 'local_questiongenerator'), 1);
        $radioarray[] = $mform->createElement('radio', 'type_question', '', get_string('trueorfalse', 'local_questiongenerator'), 2);
        $radioarray[] = $mform->createElement('radio', 'type_question', '', get_string('multichoice', 'local_questiongenerator'), 3);
        $radioarray[] = $mform->createElement('radio', 'type_question', '', get_string('essayques', 'local_questiongenerator'), 4);
        $mform->setDefault('type_question', 1);
        $mform->addGroup($radioarray, 'radioar', get_string('type_of_ques', 'local_questiongenerator'), array(' '), false);

        // File upload.
        $mform->addElement(
            'filepicker',
            'uploaded_file',
            get_string('file'),
            null,
            [
                'maxbytes' => $maxbytes,
                'accepted_types' => '*',
            ]
        );

        // Add submit button.
        $submitlabel = get_string('generate', 'local_questiongenerator');
        $mform->addElement('submit', 'submitmessage', $submitlabel);
    }
}
