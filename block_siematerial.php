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
 * Classes to implement the block.
 *
 * @package    SIE
 * @copyright  2015 Planificacion de Entornos Tecnologicos SL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/blocklib.php');
require_once($CFG->dirroot . '/blocks/siematerial/lib.php');

/**
 * Block siematerial class definition.
 *
 * This block can be added to a course page to display of
 * list of files for a course. This block allow to download
 * files and if you are a teacher, can delete files, or upload more.
 *
 * @package    SIE
 * @copyright  2015 Planificacion de Entornos Tecnologicos SL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_siematerial extends block_base {
    /**
     * Core function used to initialize the block.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('title', 'block_siematerial');
    }

    /**
     * This function will delete the file selected from directory and the 'deleted' field will set to 1,
     * for don't appear in the course files list
     *
     * @return void
     */
    public static function block_siematerial_delete_file() {
        global $DB, $CFG, $COURSE, $USER;
        $fileid = required_param('file_id', PARAM_INT);
        $file = $DB->get_record('block_siematerial_uploaded', array('id' => $fileid));
        $coursecontext = context_course::instance($COURSE->id);
        if (has_capability('block/siematerial:managefiles', $coursecontext, $USER, true)) {
            if ($file != '') {
                unlink($CFG->dirroot. '/blocks/siematerial/coursefiles/course'.$file->afg_id.'/'.$file->realfilename);
                // Logical delete.
                $file->deleted = 1;
                $DB->update_record('block_siematerial_uploaded', $file);
                header('Location: view.php?id='.$COURSE->id);
            }
        }
    }

    /**
     * Used to generate the content for the block.
     *
     * @return string
     */
    public function get_content() {
        global $COURSE, $DB, $CFG, $PAGE, $USER;

        $PAGE->requires->js_call_amd('block_siematerial/siematerial', 'init', array($USER->id));
        if (isset($this->content)) {
            if ($this->content !== null) {
                return $this->content;
            }
        } else {
            $this->content = new stdClass();
            $this->content->text = '';
        }

        // The user must be in a course context to be able to see the block content.
        if ($PAGE->pagelayout != 'course') {
            $courseid = optional_param('courseid', null, PARAM_INT);
            if ($courseid != null) {
                $this->content->text = html_writer::tag('a', get_string('gobacktocourse', 'block_siematerial'),
                        array(
                            'href' => $CFG->wwwroot."/course/view.php?id=$courseid",
                            'class' => 'btn btn-default block_siematerial_button'));
            } else {
                $this->content->text = get_string('accesstocoursemessage', 'block_siematerial');
            }
            return null;
        }

        $action = optional_param('action', null, PARAM_ALPHA);
        if (isset($action)) {
            if ($action == 'delete') {
                // Receiving delete action.
                self::block_siematerial_delete_file();
            }
        }

        $content = '';

        // The teachers of this course or admins, could see the upload files form.
        $coursecontext = context_course::instance($COURSE->id);
        if (has_capability('block/siematerial:managefiles', $coursecontext, $USER, true)) {
            $form = new block_siematerial_form();
            $afgid = optional_param('afg_id', null, PARAM_INT);
            $categorytype = optional_param('categorytype', null, PARAM_ALPHAEXT);
            $courseid = optional_param('courseid', null, PARAM_INT);
            $description = optional_param('descriptionfile', null, PARAM_TEXT);

            if (!isset($afgid)) {
                $afgid = '';
            }
            if (!isset($categorytype)) {
                $categorytype = '';
            }
            if (!isset($courseid)) {
                $courseid = '';
            }
            if (!isset($description)) {
                $description = '';
            }
            $name = $form->get_new_filename('userfile');
            $path = $CFG->dirroot. '/blocks/siematerial/coursefiles/course'.$afgid;

            if (!is_dir($path)) {
                mkdir($path);
            }
            if (is_dir($path)) {
                $uniquename = explode('.', $name);
                // For prevent files are overwritten.
                if (count($uniquename) > 1) {
                    $uniquename = $uniquename[0]. '.' .md5(uniqid(rand(), true)). '.' .$uniquename[1];
                } else {
                    $uniquename = $name. '.' .md5(uniqid(rand(), true));
                }
                $completedir = $path.'/'.$uniquename;
                if ($form->save_file('userfile', $completedir, true)) {
                    $record = new stdClass();
                    $record->description = $description;
                    $record->filename = $name;
                    $record->afg_id = $afgid;
                    $record->afg_type = $categorytype;
                    $record->deleted = 0;
                    $record->realfilename = $uniquename;
                    $record->userid = $USER->id;
                    $record->uploaded_date = date('Y-m-d H:i:s');
                    $DB->insert_record('block_siematerial_uploaded', $record, false);
                }
            } else {
                $content .= get_string('unabletomakedir', 'block_siematerial');
            }
        }

        $afgidlms = '';
        $categorytype = '';
        if ($COURSE->category == 1) {
            // Propia.
            $afgidlms = $COURSE->id;
            $categorytype = 'C';
        } else {
            // CNCP.
            $afgidlms = $COURSE->category;
            $categorytype = 'SC';
        }
        $sql = "SELECT *
                  FROM {block_siematerial_uploaded}
                 WHERE afg_id = :afgidvalue
                       AND afg_type = :categorytype
                       AND deleted = 0";
        $result = $DB->get_recordset_sql($sql, array('afgidvalue' => $afgidlms , 'categorytype' => $categorytype));
        $content .= html_writer::start_tag('table', array('class' => 'table', 'id' => 'coursematerialfilestable'));
        if (count($result) > 0) {
            $content .= html_writer::tag('th', get_string('files', 'block_siematerial').' ('.count($result).')',
                    array('colspan' => '100%'));
            foreach ($result as $file) {
                // Show the description field, but if it is empty, the file name.
                $filenameshown = $file->description != '' ? $file->description : $file->filename;
                $filenameshown = block_siematerial_shorten_text_with_ellipsis($filenameshown,
                        27);
                $content .= html_writer::start_tag('tr');
                $content .= html_writer::start_tag('td');
                $content .= block_siematerial_get_mime_type_image_tag($file->filename);
                $content .= html_writer::end_tag('td');
                $content .= html_writer::start_tag('td');
                $downloadfileurl = $CFG->wwwroot.'/blocks/siematerial/download.php?fileid='.
                        $file->id.'&afg_id='.$afgidlms.'&courseid='.$COURSE->id;
                $content .= html_writer::tag('a', $filenameshown, array(
                    'href' => $downloadfileurl,
                    'target' => '_blank',
                    'class' => 'block_siematerial_filelink'));
                $content .= html_writer::end_tag('td');
                // File managers can see the delete file button.
                if (has_capability('block/siematerial:managefiles', $coursecontext, $USER)) {
                    $content .= html_writer::start_tag('td');
                    $content .= html_writer::img('../blocks/siematerial/pix/close-icon.png', 'Delete',
                            array(
                                'onclick' => "(function() {
                                    require('block_siematerial/siematerial').confirm_delete(".$file->id.")
                                })();",
                                'style'   => 'cursor: pointer;')
                    );
                    $content .= html_writer::end_tag('td');
                }
            }
        } else {
            $content .= html_writer::tag('th', get_string('files', 'block_siematerial'));
            $content .= html_writer::start_tag('tr');
            $content .= html_writer::tag('td', get_string('nofiles', 'block_siematerial'),
                    array('class' => 'block_siematerial_nodatafound'));
            $content .= html_writer::end_tag('tr');
        }
        $content .= html_writer::end_tag('table');

        if (has_capability('block/siematerial:managefiles', $coursecontext, $USER)) {
            $content .= $form->display();
            $this->content->footer = html_writer::tag('a', get_string('downloadlist_button', 'block_siematerial'),
                    array(
                        'href'  => $CFG->wwwroot.'/blocks/siematerial/list.php?courseid='.$COURSE->id.'&action=download',
                        'class' => 'btn btn-default block_siematerial_descriptionfile_btn')
            );
            $this->content->footer .= html_writer::tag('a', get_string('uploadlist_button', 'block_siematerial'),
                    array(
                        'href'  => $CFG->wwwroot.'/blocks/siematerial/list.php?courseid='.$COURSE->id.'&action=upload',
                        'class' => 'btn btn-default block_siematerial_descriptionfile_btn')
            );
        }
        $this->content->text = $content;
        return $this->content;
    }

    /**
     * Core function, specifies where the block can be used.
     *
     * @return array
     */
    public function applicable_formats() {
        return array(
            'all'                => true,
            'site'               => true,
            'site-index'         => true,
            'course-view'        => true,
            'course-view-social' => false,
            'mod'                => true,
            'mod-quiz'           => false);
    }

    /**
     * Allows the block to be added multiple times to a single page
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * This line tells Moodle that the block has a settings.php file.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }
}

/**
 * This class is used to build the form for uploading files
 *
 * @package    SIE
 * @copyright  2015 Planificacion de Entornos Tecnologicos SL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_siematerial_form extends moodleform{
    /**
     * Method to initialize the form object.
     *
     * @return void
     */
    public function definition() {
        global $COURSE;
        $afgidlms = 0;
        $categorytype = '';
        if ($COURSE->category == 1) {
            // Propia.
            $afgidlms = $COURSE->id;
            $categorytype = 'C';
        } else {
            // CNCP.
            $afgidlms = $COURSE->category;
            $categorytype = 'SC';
        }

        $mform = $this->_form;
        $mform->setAttributes(array(
            'class'  => 'block_siematerial_filepickerbutton',
            'method' => 'POST',
            'id'     => 'coursefileform'));
        $mform->addElement('filepicker', 'userfile', '', null, array('maxbytes' => 0, 'accepted_types' => '*'));

        $mform->setType('descriptionfile', PARAM_TEXT);
        $attributes = array('size' => '20', 'maxlength' => '50', 'class' => 'block_siematerial_descriptionfile');
        $mform->addElement('text', 'descriptionfile', get_string('descriptionfile', 'block_siematerial'), $attributes);

        $mform->addElement('hidden', 'afg_id', $afgidlms);
        $mform->setType('afg_id', PARAM_INT);

        $mform->addElement('hidden', 'categorytype', $categorytype);
        $mform->setType('categorytype', PARAM_ALPHAEXT);

        $mform->addElement('hidden', 'action', '', array('id' => 'action'));
        $mform->setType('action', PARAM_ALPHAEXT);

        $mform->addElement('hidden', 'askquestion', get_string('askquestion', 'block_siematerial'),
                array('id' => 'askquestion'));
        $mform->setType('askquestion', PARAM_TEXT);

        $mform->addElement('hidden', 'file_id', '', array('id' => 'file_id'));
        $mform->setType('file_id', PARAM_INT);

        $mform->addElement('hidden', 'courseid', $COURSE->id);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('submit', 'metalink_submit', get_string('upload'));
    }

    /**
     * Generate the HTML for the form, capture it in an output buffer, then return it
     *
     * @return string
     */
    public function display() {
        // Finalize the form definition if not yet done.
        if (!$this->_definition_finalized) {
            $this->_definition_finalized = true;
            $this->definition_after_data();
        }
        ob_start();
        $this->_form->display();
        $form = ob_get_clean();
        return $form;
    }
}
