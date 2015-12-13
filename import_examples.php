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
 * @package    qtype_gapfill
 * @copyright  2013 Marcus Green
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/xmlize.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/question/format/xml/format.php');


admin_externalpage_setup('qtype_gapfill_import');

class gapfill_import_form extends moodleform {

    public $questioncategory;
    public $course;

    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'courseshortname', 'Course');
        $mform->setType('courseshortname', PARAM_RAW);
        $mform->addElement('submit', 'submitbutton', 'Import');
    }

    public function get_question_category($courseshortname) {
        global $DB;
        /* parent=0 means where you have multiple categories it is at the top */
        $sql = 'Select qcat.id id, c.id courseid,c.shortname,ctx.id contextid from {course} c
        join {context} ctx on ctx.instanceid=c.id
        join {question_categories} qcat on qcat.contextid=ctx.id
        and ctx.contextlevel=50 and qcat.parent=0 and c.shortname =?';
        $category = $DB->get_records_sql($sql, array($courseshortname));
        $category = array_shift($category);
        return $category;
    }

    public function validation($fromform, $data) {
        $errors = array();
        global $DB;
        $sql = 'select id from {course} where shortname =?';
        $this->course = $DB->get_records_sql($sql, array($fromform['courseshortname']));
        $this->course = array_shift($this->course);
        if ($this->course == null) {
            $errors['courseshortname'] = get_string('coursenotfound', 'qtype_gapfill');
        } else {
            $this->questioncategory = $this->get_question_category($fromform['courseshortname']);
            if (count($this->questioncategory) == 0) {
                $url = new moodle_url('/question/edit.php?courseid=' . array_shift($courseid)->id);
                $errors['courseshortname'] = get_string('questioncatnotfound', 'qtype_gapfill', $url->out());
            }
        }

        if ($errors) {
            return $errors;
        } else {
            return true;
        }
    }

}

$mform = new gapfill_import_form(new moodle_url('/question/type/gapfill/import_examples.php/'));
if ($fromform = $mform->get_data()) {
    $category = $mform->questioncategory;
    $categorycontext = context::instance_by_id($category->contextid);
    $category->context = $categorycontext;

    $qformat = new qformat_xml();
    $file = $CFG->dirroot . '/question/type/gapfill/sample_questions.xml';
    $qformat->setFilename($file);

    $qformat->setCategory($category);
    echo $OUTPUT->header();
    // Do anything before that we need to.
    if (!$qformat->importpreprocess()) {
        print_error('cannotimport', 'qtype_gapfill', $PAGE->out);
    }
    // Process the uploaded file.
    if (!$qformat->importprocess($category)) {
        print_error(get_string('cannotimport', ''), '', $PAGE->url);
    } else {
        /* after the import offer a link to go to the course and view the questions */
        $visitquestions = new moodle_url('/question/edit.php?courseid=' . $mform->course->id);
        echo $OUTPUT->notification(get_string('visitquestions', 'qtype_gapfill', $visitquestions->out()), 'notifysuccess');
        echo $OUTPUT->continue_button(new moodle_url('import_examples.php'));
        echo $OUTPUT->footer();
        return;
    }
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
