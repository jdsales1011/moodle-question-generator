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
 * Internal API of local_questiongenerator.
 *
 * @package     local_questiongenerator
 * @copyright   2023 Jivielyn Sales <jivielyn.sales@gmail.com>
 * @copyright   based on work by 2017 Martin Gauk (@innoCampus, TU Berlin) and 2022 Kacper Rokicki <k.k.rokicki@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_questiongenerator;

use coding_exception;
use context;
use course_modinfo;
use dml_exception;
use lang_string;
use moodle_exception;
use zip_packer;

use moodle_url;
use stdClass;

use qformat_gift;
use question_bank;
use question_check_specified_fields_expectation;

use qbank_importquestions\form\question_import_form;

// Import the question_bank class
require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->dirroot . '/question/engine/lib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/type/questiontypebase.php');

// Include the required files from the Moodle installation directory
require_once($CFG->dirroot . '/question/format.php');
require_once($CFG->dirroot . '/question/format/gift/format.php');

/**
 * Class course_files
 * @package local_questiongenerator
 */
class course_files {
    /**
     * @var context
     */
    protected $context;

    /**
     * @var array
     */
    protected $components = null;

    /**
     * @var array
     */
    protected $filelist = null;

    /**
     * @var string
     */
    protected $filtercomponent;

    /**
     * @var string
     */
    protected $filterfiletype;

    /**
     * @var course_modinfo
     */
    protected $coursemodinfo;

    /**
     * @var int
     */
    protected $courseid;


    /**
     * course_files constructor.
     * @param int $courseid
     * @param context $context
     * @param string $component
     * @param string $filetype
     * @throws moodle_exception
     */
    public function __construct(int $courseid, context $context, string $component, string $filetype) {
        $this->courseid = $courseid;
        $this->context = $context;
        $this->filtercomponent = $component;
        $this->filterfiletype = $filetype;
        $this->coursemodinfo = get_fast_modinfo($courseid);
    }

    /**
     * Get course id.
     *
     * @return int
     */
    public function get_course_id() : int {
        return $this->courseid;
    }

    /**
     * Get filter component name.
     *
     * @return string
     */
    public function get_filter_component() : string {
        return $this->filtercomponent;
    }

    /**
     * Get filter file type name.
     *
     * @return string
     */
    public function get_filter_file_type() : string {
        return $this->filterfiletype;
    }

    /**
     * Retrieve the files within a course/context available to user.
     *
     * @param bool $ignorefilters Whether filters should be ignored and all available files should be returned.
     * @return array
     * @throws dml_exception|coding_exception|moodle_exception
     */
    public function get_file_list(bool $ignorefilters = false): ?array {
        global $DB;

        if ($this->filelist !== null) {
            return $this->filelist;
        }

        $sqlwhere = '';
        $sqlwherecomponent = '';

        if ($ignorefilters != true) {
            if ($this->filtercomponent == 'all') {
                $sqlwhere .= 'AND f.component NOT LIKE :component';
                $sqlwherecomponent = 'assign%';
            } else {
                $availcomponents = $this->get_components();
                if (isset($availcomponents[$this->filtercomponent])) {
                    $sqlwhere .= 'AND f.component LIKE :component';
                    $sqlwherecomponent = $this->filtercomponent;
                }
            }

            if ($this->filterfiletype === 'other') {
                $sqlwhere .= ' AND ' . $this->get_sql_mimetype(array_keys(mimetypes::get_mime_types()), false);
            } else if (isset(mimetypes::get_mime_types()[$this->filterfiletype])) {
                $sqlwhere .= ' AND ' . $this->get_sql_mimetype($this->filterfiletype, true);
            }
        }

        $sql = 'FROM {files} f
                LEFT JOIN {context} c ON (c.id = f.contextid)
                WHERE f.filename NOT LIKE \'.\'
                    AND (c.path LIKE :path OR c.id = :cid) ' . $sqlwhere;

        $sqlselectfiles = 'SELECT f.*, c.contextlevel, c.instanceid' .
        ' ' . $sql . ' ORDER BY f.component, f.filename';

        $params = array(
            'path' => $this->context->path . '/%',
            'cid' => $this->context->id,
            'component' => $sqlwherecomponent,
        );

        $records = $DB->get_records_sql($sqlselectfiles, $params);

        $records = array_filter($records, function($file) {
            $cm = $this->coursemodinfo->cms[$file->instanceid];
            return $cm->available && $cm->uservisible;
        });

        $files = array();
        foreach ($records as $rec) {
            $file = course_file::create($rec);
            if ($file->fileused) {
                $files[] = $file;
            }
        }

        if ($ignorefilters != true) {
            $this->filelist = $files;
        }
        return $files;
    }

    /**
     * Creates an SQL snippet
     *
     * @param mixed $types
     * @param bool $in
     * @return string
     */
    protected function get_sql_mimetype($types, bool $in): string {
        if (is_array($types)) {
            $list = array();
            foreach ($types as $type) {
                $list = array_merge($list, mimetypes::get_mime_types()[$type]);
            }
        } else {
            $list = &mimetypes::get_mime_types()[$types];
        }

        if ($in) {
            $first = "(f.mimetype LIKE '";
            $glue = "' OR f.mimetype LIKE '";
        } else {
            $first = "(f.mimetype NOT LIKE '";
            $glue = "' AND f.mimetype NOT LIKE '";
        }

        return $first . implode($glue, $list) . "')";
    }

    /**
     * Get all available components with files.
     * @return array
     * @throws coding_exception|dml_exception|moodle_exception
     */
    public function get_components(): ?array {
        if ($this->components !== null) {
            return $this->components;
        }

        $filelist = $this->get_file_list(true);

        $components = array();
        foreach ($filelist as $file) {
            $components[$file->filecomponent] = self::get_component_translation($file->filecomponent);
        }

        asort($components, SORT_STRING | SORT_FLAG_CASE);
        $componentsall = array(
            'all' => get_string('allcomponents', 'local_questiongenerator')
        );

        $this->components = $componentsall + $components;
        return $this->components;
    }

    /**
     * Check given files whether they are available to the current user.
     *
     * @param array $files records from the files table left join files_reference table
     * @return array files that are available
     * @throws dml_exception|coding_exception|moodle_exception
     */
    protected function check_files(array $files): array {
        $availablefileids = array_map(function ($file) {
            return $file->get_file()->id;
        }, $this->get_file_list(true));
        $checkedfiles = array();
        foreach ($files as $file) {
            if (in_array($file->id, $availablefileids)) {
                $checkedfiles[] = $file;
            }
        }
        return $checkedfiles;
    }

    /**
     * Sends over the selected files to the AI web server for question generation.
     *
     * @param array $fileids file ids
     * @param int $number number of questions to be generated
     * @param int $qtype question type
     * 
     * @return string with the question-answer pairs in JSON format
     */

    public function generate_questions(array $fileids, int $number, int $qtype) {
        // API.
        $ch = curl_init();          // Initialize a new cURL session.
        // $api_url = "http://127.0.0.1:5000/qgplugin/api/";
        $api_url = "https://moodle-qgplugin-api-production.up.railway.app/qgplugin/api/";
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            // 'Content-Type: multipart/form-data'
            'Content-Type: application/json'
        ));

        global $DB, $CFG, $USER;
        
        if (count($fileids) == 0) {
            throw new moodle_exception('nofileselected', 'local_questiongenerator');
        }

        list($sqlin, $paramfids) = $DB->get_in_or_equal(array_keys($fileids), SQL_PARAMS_QM);
        $sql = 'SELECT f.*, r.repositoryid, r.reference, r.lastsync AS referencelastsync
                FROM {files} f
                LEFT JOIN {files_reference} r ON (f.referencefileid = r.id)
                WHERE f.id ' . $sqlin;
        $res = $DB->get_records_sql($sql, $paramfids);

        $checkedfiles = $this->check_files($res);
        $fs = get_file_storage();
        $file_objs = array();
        foreach ($checkedfiles as $file) {
            // Get the file instance of selected files.
            $file_objs[] = $fs->get_file_instance($file);
        }

        // Encode the files as base64 strings
        $files_data = array();
        foreach ($file_objs as $file_obj) {
            $filename = $file_obj->get_filename();
            $file_encoded = base64_encode($file_obj->get_content());

            $files_data[] = array(
                "file_name" => $filename,
                "file_encoded" => $file_encoded
            );
        }
        
        // POST REQUEST.
        $api_params = array(
            'number' => $number,
            'type' => $qtype,
            'files' => $files_data
        );

        $api_params_json = json_encode($api_params);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $api_params_json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);


        $api_response = curl_exec($ch);

        $temp_dir = "temp/";
        $file_name = "giftq_" . $USER->id . "_" . uniqid();
        $file_path = $temp_dir . $file_name . ".txt";
        
        $data = $api_response;

        // Write the api_respose to the file
        file_put_contents($file_path, $data);

        // if($err = curl_error($ch)) {
        //     echo $err;
        // }
        // else {
        //     $decoded = json_decode($response, true);
        //     print_r($decoded);
        // }

        curl_close($ch);

        return $file_name;
    }


    /**
     * Saves the chosen questions to Moodle's Question Bank
     *
     * @param array $fileids file ids
     * @param int $number number of questions to be generated
     * @param int $qtype question type
     * @return string with the question-answer pairs in JSON format
     */

    public function save_questions(
        moodle_url $url,
        stdClass $COURSE,
        int $courseid, 
        context $context, 
        array $selected_ques,
        string $file_path, 
        int $qtype) {

        global $CFG;

        // Extract the contents inside the text file -- all generated questions.
        $gen_questions = file_get_contents($file_path);
        $gen_questions = json_decode($gen_questions, true)['questions'];

        // Array of selected question ids.
        $selected_ids = array_keys($selected_ques);

        // Filter the gen_questions associative array according to the selected_ids
        $selected_questions = array_filter($gen_questions, function($question) use ($selected_ids) {
            return in_array($question['id'], $selected_ids);
        });

        $format_str = "";

        switch ($qtype) {
            case 1:
              $format_str = $this->format_identification_gift($selected_questions);
              break;
            case 2:
                $format_str = $this->format_trueorfalse_gift($selected_questions);
              break;
            case 3:
                $format_str = $this->format_multichoice_gift($selected_questions);
              break;
        }

        // Save the GIFT-formatted question string to the text file.
        file_put_contents($file_path, $format_str);
        
        $format = "gift";
        $matchgrades = "error";
        $catfromfile = true;
        $contextfromfile = true;
        $stoponerror = true;

        $formatfile = $CFG->dirroot . '/question/format/' . $format . '/format.php';
        $category = question_get_default_category($context->id);
        $categoryid = $category->id;

        require_once($formatfile);

        $classname = 'qformat_' . $format;
        $qformat = new $classname();

        // Load data into class.
        $qformat->setCategory($category);
        // $qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
        $qformat->setCourse($COURSE);
        $qformat->setFilename($file_path);
        // $qformat->setRealfilename($realfilename);
        $qformat->setMatchgrades($matchgrades);
        $qformat->setCatfromfile($catfromfile);
        $qformat->setContextfromfile($contextfromfile);
        $qformat->setStoponerror($stoponerror);

        // Do anything before that we need to.
        if (!$qformat->importpreprocess()) {
            throw new moodle_exception('cannotimport', '', $thispageurl->out());
        }

        // Process the uploaded file.
        if (!$qformat->importprocess()) {
            throw new moodle_exception('cannotimport', '', $thispageurl->out());
        }

        // In case anything needs to be done after.
        if (!$qformat->importpostprocess()) {
            throw new moodle_exception('cannotimport', '', $thispageurl->out());
        }

        // Log the import into this category.
        $eventparams = [
                'contextid' => $qformat->category->contextid,
                'other' => ['format' => $format, 'categoryid' => $qformat->category->id],
        ];
        $event = \core\event\questions_imported::create($eventparams);
        $event->trigger();

        $params = $url->params() + ['category' => $qformat->category->id . ',' . $qformat->category->contextid];

        // Delete the temporary file.
        if (file_exists($file_path)) {
            unlink($file_path);
        }

        return $params;
    }

    /**
     * Format IDENTIFICATION questions according to GIFT format.
     *
     * @param array $questions
     */
    protected function format_identification_gift(array $questions): string {
        $format_str = '';

        foreach ($questions as $question) {
            $format_str .= ($question['question'] . " {" . $question['answer'] . "}\n\n");
        }

        return $format_str;
    }

    /**
     * Format TRUE OR FALSE questions according to GIFT format.
     *
     * @param array $questions
     */
    protected function format_trueorfalse_gift(array $questions): string {
        $format_str = '';

        foreach ($questions as $question) {
            $format_str .= ($question['question'] . " {" . strtoupper($question['answer']) . "}\n\n");
        }

        return $format_str;
    }

    /**
     * Format MULTIPLE CHOICE questions according to GIFT format.
     *
     * @param array $questions
     */
    protected function format_multichoice_gift(array $questions): string {
        $format_str = '';

        foreach ($questions as $question) {
            $format_str .= ($question['question'] . " {\n");
            
            // Convert the string choices to an array.
            $choices = explode(',', trim($question['choices'], "[]"));
            
            // Get the letter of the correct answer.
            $answer_parts = explode('.', trim($question['answer']), 2);
            $answer_ltr = $answer_parts[0];

            // Process each choice
            foreach ($choices as $item) {
                $trimmed = trim($item);
                $choice_parts = explode('.', $trimmed, 2);
                
                if(strcasecmp($choice_parts[0], $answer_ltr) == 0 ){
                    // Correct answer.
                    $format_str .= "=";
                }
                else{
                    // Incorrect answer.
                    $format_str .= "~";
                }

                $format_str .= (trim($choice_parts[1]) . "\n");
            }

            $format_str .= "}\n\n";
        }

        return $format_str;
    }

    /**
     * Generate a unique file name for storage.
     *
     * If a file does already exist with $filename in $existingfiles as key,
     * a number in parentheses is appended to the file name.
     *
     * @param string $filename
     * @param array $existingfiles
     * @return string unique file name
     */
    protected function get_unique_file_name(string $filename, array $existingfiles): string {
        $name = clean_filename($filename);

        $lastdot = strrpos($name, '.');
        if ($lastdot === false) {
            $filename = $name;
            $extension = '';
        } else {
            $filename = substr($name, 0, $lastdot);
            $extension = substr($name, $lastdot);
        }

        $i = 1;
        while (isset($existingfiles[$name])) {
            $name = $filename . '(' . $i++ . ')' . $extension;
        }

        return $name;
    }

    /**
     * Collate an array of available file types
     *
     * @return array
     * @throws coding_exception
     */
    public static function get_file_types(): array {
        $types = array('all' => get_string('filetype:all', 'local_questiongenerator'));
        foreach (array_keys(mimetypes::get_mime_types()) as $type) {
            $types[$type] = get_string('filetype:' . $type, 'local_questiongenerator');
        }
        $types['other'] = get_string('filetype:other', 'local_questiongenerator');
        return $types;
    }

    /**
     * Try to get the name of the file component in the user's lang.
     *
     * @param string $name
     * @return lang_string|string
     * @throws coding_exception
     */
    public static function get_component_translation(string $name) {
        if (get_string_manager()->string_exists('pluginname', $name)) {
            return get_string('pluginname', $name);
        } else if (get_string_manager()->string_exists($name, '')) {
            return get_string($name);
        }
        return $name;
    }
}
