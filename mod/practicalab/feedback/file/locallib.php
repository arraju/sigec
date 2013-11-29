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
 * This file contains the definition for the library class for file feedback plugin
 *
 *
 * @package   practicalabfeedback_file
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// File areas for file feedback practicalabment.
define('PRACTICALABFEEDBACK_FILE_FILEAREA', 'feedback_files');
define('PRACTICALABFEEDBACK_FILE_BATCH_FILEAREA', 'feedback_files_batch');
define('PRACTICALABFEEDBACK_FILE_IMPORT_FILEAREA', 'feedback_files_import');
define('PRACTICALABFEEDBACK_FILE_MAXSUMMARYFILES', 5);
define('PRACTICALABFEEDBACK_FILE_MAXSUMMARYUSERS', 5);
define('PRACTICALABFEEDBACK_FILE_MAXFILEUNZIPTIME', 120);

/**
 * Library class for file feedback plugin extending feedback plugin base class.
 *
 * @package   asignfeedback_file
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class practicalab_feedback_file extends practicalab_feedback_plugin {

    /**
     * Get the name of the file feedback plugin.
     *
     * @return string
     */
    public function get_name() {
        return get_string('file', 'practicalabfeedback_file');
    }

    /**
     * Get file feedback information from the database.
     *
     * @param int $gradeid
     * @return mixed
     */
    public function get_file_feedback($gradeid) {
        global $DB;
        return $DB->get_record('practicalabfeedback_file', array('grade'=>$gradeid));
    }

    /**
     * File format options.
     *
     * @return array
     */
    private function get_file_options() {
        global $COURSE;

        $fileoptions = array('subdirs'=>1,
                             'maxbytes'=>$COURSE->maxbytes,
                             'accepted_types'=>'*',
                             'return_types'=>FILE_INTERNAL);
        return $fileoptions;
    }

    /**
     * Copy all the files from one file area to another.
     *
     * @param file_storage $fs - The source context id
     * @param int $fromcontextid - The source context id
     * @param string $fromcomponent - The source component
     * @param string $fromfilearea - The source filearea
     * @param int $fromitemid - The source item id
     * @param int $tocontextid - The destination context id
     * @param string $tocomponent - The destination component
     * @param string $tofilearea - The destination filearea
     * @param int $toitemid - The destination item id
     * @return boolean
     */
    private function copy_area_files(file_storage $fs,
                                     $fromcontextid,
                                     $fromcomponent,
                                     $fromfilearea,
                                     $fromitemid,
                                     $tocontextid,
                                     $tocomponent,
                                     $tofilearea,
                                     $toitemid) {

        $newfilerecord = new stdClass();
        $newfilerecord->contextid = $tocontextid;
        $newfilerecord->component = $tocomponent;
        $newfilerecord->filearea = $tofilearea;
        $newfilerecord->itemid = $toitemid;

        if ($files = $fs->get_area_files($fromcontextid, $fromcomponent, $fromfilearea, $fromitemid)) {
            foreach ($files as $file) {
                if ($file->is_directory() and $file->get_filepath() === '/') {
                    // We need a way to mark the age of each draft area.
                    // By not copying the root dir we force it to be created
                    // automatically with current timestamp.
                    continue;
                }
                $newfile = $fs->create_file_from_storedfile($newfilerecord, $file);
            }
        }
        return true;
    }

    /**
     * Get form elements for grading form.
     *
     * @param stdClass $grade
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @param int $userid The userid we are currently grading
     * @return bool true if elements were added to the form
     */
    public function get_form_elements_for_user($grade, MoodleQuickForm $mform, stdClass $data, $userid) {

        $fileoptions = $this->get_file_options();
        $gradeid = $grade ? $grade->id : 0;
        $elementname = 'files_' . $userid;

        $data = file_prepare_standard_filemanager($data,
                                                  $elementname,
                                                  $fileoptions,
                                                  $this->practicalabment->get_context(),
                                                  'practicalabfeedback_file',
                                                  PRACTICALABFEEDBACK_FILE_FILEAREA,
                                                  $gradeid);
        $mform->addElement('filemanager', $elementname . '_filemanager', html_writer::tag('span', $this->get_name(),
            array('class' => 'accesshide')), null, $fileoptions);

        return true;
    }

    /**
     * Count the number of files.
     *
     * @param int $gradeid
     * @param string $area
     * @return int
     */
    private function count_files($gradeid, $area) {

        $fs = get_file_storage();
        $files = $fs->get_area_files($this->practicalabment->get_context()->id,
                                     'practicalabfeedback_file',
                                     $area,
                                     $gradeid,
                                     'id',
                                     false);

        return count($files);
    }

    /**
     * Update the number of files in the file area.
     *
     * @param stdClass $grade The grade record
     * @return bool - true if the value was saved
     */
    public function update_file_count($grade) {
        global $DB;

        $filefeedback = $this->get_file_feedback($grade->id);
        if ($filefeedback) {
            $filefeedback->numfiles = $this->count_files($grade->id, PRACTICALABFEEDBACK_FILE_FILEAREA);
            return $DB->update_record('practicalabfeedback_file', $filefeedback);
        } else {
            $filefeedback = new stdClass();
            $filefeedback->numfiles = $this->count_files($grade->id, PRACTICALABFEEDBACK_FILE_FILEAREA);
            $filefeedback->grade = $grade->id;
            $filefeedback->practicalabment = $this->practicalabment->get_instance()->id;
            return $DB->insert_record('practicalabfeedback_file', $filefeedback) > 0;
        }
    }

    /**
     * Save the feedback files.
     *
     * @param stdClass $grade
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $grade, stdClass $data) {
        $fileoptions = $this->get_file_options();

        // The element name may have been for a different user.
        foreach ($data as $key => $value) {
            if (strpos($key, 'files_') === 0 && strpos($key, '_filemanager')) {
                $elementname = substr($key, 0, strpos($key, '_filemanager'));
            }
        }

        $data = file_postupdate_standard_filemanager($data,
                                                     $elementname,
                                                     $fileoptions,
                                                     $this->practicalabment->get_context(),
                                                     'practicalabfeedback_file',
                                                     PRACTICALABFEEDBACK_FILE_FILEAREA,
                                                     $grade->id);

        return $this->update_file_count($grade);
    }

    /**
     * Display the list of files in the feedback status table.
     *
     * @param stdClass $grade
     * @param bool $showviewlink - Set to true to show a link to see the full list of files
     * @return string
     */
    public function view_summary(stdClass $grade, & $showviewlink) {

        $count = $this->count_files($grade->id, PRACTICALABFEEDBACK_FILE_FILEAREA);

        // Show a view all link if the number of files is over this limit.
        $showviewlink = $count > PRACTICALABFEEDBACK_FILE_MAXSUMMARYFILES;

        if ($count <= PRACTICALABFEEDBACK_FILE_MAXSUMMARYFILES) {
            return $this->practicalabment->render_area_files('practicalabfeedback_file',
                                                        PRACTICALABFEEDBACK_FILE_FILEAREA,
                                                        $grade->id);
        } else {
            return get_string('countfiles', 'practicalabfeedback_file', $count);
        }
    }

    /**
     * Display the list of files in the feedback status table.
     *
     * @param stdClass $grade
     * @return string
     */
    public function view(stdClass $grade) {
        return $this->practicalabment->render_area_files('practicalabfeedback_file',
                                                    PRACTICALABFEEDBACK_FILE_FILEAREA,
                                                    $grade->id);
    }

    /**
     * The practicalabment has been deleted - cleanup.
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        // Will throw exception on failure.
        $DB->delete_records('practicalabfeedback_file',
                            array('practicalabment'=>$this->practicalabment->get_instance()->id));

        return true;
    }

    /**
     * Return true if there are no feedback files.
     *
     * @param stdClass $grade
     */
    public function is_empty(stdClass $grade) {
        return $this->count_files($grade->id, PRACTICALABFEEDBACK_FILE_FILEAREA) == 0;
    }

    /**
     * Get file areas returns a list of areas this plugin stores files.
     *
     * @return array - An array of fileareas (keys) and descriptions (values)
     */
    public function get_file_areas() {
        return array(PRACTICALABFEEDBACK_FILE_FILEAREA=>$this->get_name());
    }

    /**
     * Return true if this plugin can upgrade an old Moodle 2.2 practicalabment of this type
     * and version.
     *
     * @param string $type old practicalabment subtype
     * @param int $version old practicalabment version
     * @return bool True if upgrade is possible
     */
    public function can_upgrade($type, $version) {
        if (($type == 'upload' || $type == 'uploadsingle') && $version >= 2011112900) {
            return true;
        }
        return false;
    }

    /**
     * Upgrade the settings from the old practicalabment to the new plugin based one.
     *
     * @param context $oldcontext - the context for the old practicalabment
     * @param stdClass $oldpracticalabment - the data for the old practicalabment
     * @param string $log - can be appended to by the upgrade
     * @return bool was it a success? (false will trigger a rollback)
     */
    public function upgrade_settings(context $oldcontext, stdClass $oldpracticalabment, & $log) {
        // First upgrade settings (nothing to do).
        return true;
    }

    /**
     * Upgrade the feedback from the old practicalabment to the new one.
     *
     * @param context $oldcontext - the database for the old practicalabment context
     * @param stdClass $oldpracticalabment The data record for the old practicalabment
     * @param stdClass $oldsubmission The data record for the old submission
     * @param stdClass $grade The data record for the new grade
     * @param string $log Record upgrade messages in the log
     * @return bool true or false - false will trigger a rollback
     */
    public function upgrade(context $oldcontext,
                            stdClass $oldpracticalabment,
                            stdClass $oldsubmission,
                            stdClass $grade,
                            & $log) {
        global $DB;

        // Now copy the area files.
        $this->practicalabment->copy_area_files_for_upgrade($oldcontext->id,
                                                        'mod_practicalabment',
                                                        'response',
                                                        $oldsubmission->id,
                                                        $this->practicalabment->get_context()->id,
                                                        'practicalabfeedback_file',
                                                        PRACTICALABFEEDBACK_FILE_FILEAREA,
                                                        $grade->id);

        // Now count them!
        $filefeedback = new stdClass();
        $filefeedback->numfiles = $this->count_files($grade->id, PRACTICALABFEEDBACK_FILE_FILEAREA);
        $filefeedback->grade = $grade->id;
        $filefeedback->practicalabment = $this->practicalabment->get_instance()->id;
        if (!$DB->insert_record('practicalabfeedback_file', $filefeedback) > 0) {
            $log .= get_string('couldnotconvertgrade', 'mod_practicalab', $grade->userid);
            return false;
        }
        return true;
    }

    /**
     * Return a list of the batch grading operations performed by this plugin.
     * This plugin supports batch upload files and upload zip.
     *
     * @return array The list of batch grading operations
     */
    public function get_grading_batch_operations() {
        return array('uploadfiles'=>get_string('uploadfiles', 'practicalabfeedback_file'));
    }

    /**
     * Upload files and send them to multiple users.
     *
     * @param array $users - An array of user ids
     * @return string - The response html
     */
    public function view_batch_upload_files($users) {
        global $CFG, $DB, $USER;

        require_capability('mod/practicalab:grade', $this->practicalabment->get_context());
        require_once($CFG->dirroot . '/mod/practicalab/feedback/file/batchuploadfilesform.php');
        require_once($CFG->dirroot . '/mod/practicalab/renderable.php');

        $formparams = array('cm'=>$this->practicalabment->get_course_module()->id,
                            'users'=>$users,
                            'context'=>$this->practicalabment->get_context());

        $usershtml = '';

        $usercount = 0;
        foreach ($users as $userid) {
            if ($usercount >= PRACTICALABFEEDBACK_FILE_MAXSUMMARYUSERS) {
                $moreuserscount = count($users) - PRACTICALABFEEDBACK_FILE_MAXSUMMARYUSERS;
                $usershtml .= get_string('moreusers', 'practicalabfeedback_file', $moreuserscount);
                break;
            }
            $user = $DB->get_record('user', array('id'=>$userid), '*', MUST_EXIST);

            $usersummary = new practicalab_user_summary($user,
                                                   $this->practicalabment->get_course()->id,
                                                   has_capability('moodle/site:viewfullnames',
                                                   $this->practicalabment->get_course_context()),
                                                   $this->practicalabment->is_blind_marking(),
                                                   $this->practicalabment->get_uniqueid_for_user($user->id),
                                                   get_extra_user_fields($this->practicalabment->get_context()));
            $usershtml .= $this->practicalabment->get_renderer()->render($usersummary);
            $usercount += 1;
        }

        $formparams['usershtml'] = $usershtml;

        $mform = new practicalabfeedback_file_batch_upload_files_form(null, $formparams);

        if ($mform->is_cancelled()) {
            redirect(new moodle_url('view.php',
                                    array('id'=>$this->practicalabment->get_course_module()->id,
                                          'action'=>'grading')));
            return;
        } else if ($data = $mform->get_data()) {
            // Copy the files from the draft area to a temporary import area.
            $data = file_postupdate_standard_filemanager($data,
                                                         'files',
                                                         $this->get_file_options(),
                                                         $this->practicalabment->get_context(),
                                                         'practicalabfeedback_file',
                                                         PRACTICALABFEEDBACK_FILE_BATCH_FILEAREA,
                                                         $USER->id);
            $fs = get_file_storage();

            // Now copy each of these files to the users feedback file area.
            foreach ($users as $userid) {
                $grade = $this->practicalabment->get_user_grade($userid, true);
                $this->practicalabment->notify_grade_modified($grade);

                $this->copy_area_files($fs,
                                       $this->practicalabment->get_context()->id,
                                       'practicalabfeedback_file',
                                       PRACTICALABFEEDBACK_FILE_BATCH_FILEAREA,
                                       $USER->id,
                                       $this->practicalabment->get_context()->id,
                                       'practicalabfeedback_file',
                                       PRACTICALABFEEDBACK_FILE_FILEAREA,
                                       $grade->id);

                $filefeedback = $this->get_file_feedback($grade->id);
                if ($filefeedback) {
                    $filefeedback->numfiles = $this->count_files($grade->id,
                                                                 PRACTICALABFEEDBACK_FILE_FILEAREA);
                    $DB->update_record('practicalabfeedback_file', $filefeedback);
                } else {
                    $filefeedback = new stdClass();
                    $filefeedback->numfiles = $this->count_files($grade->id,
                                                                 PRACTICALABFEEDBACK_FILE_FILEAREA);
                    $filefeedback->grade = $grade->id;
                    $filefeedback->practicalabment = $this->practicalabment->get_instance()->id;
                    $DB->insert_record('practicalabfeedback_file', $filefeedback);
                }
            }

            // Now delete the temporary import area.
            $fs->delete_area_files($this->practicalabment->get_context()->id,
                                   'practicalabfeedback_file',
                                   PRACTICALABFEEDBACK_FILE_BATCH_FILEAREA,
                                   $USER->id);

            redirect(new moodle_url('view.php',
                                    array('id'=>$this->practicalabment->get_course_module()->id,
                                          'action'=>'grading')));
            return;
        } else {

            $header = new practicalab_header($this->practicalabment->get_instance(),
                                        $this->practicalabment->get_context(),
                                        false,
                                        $this->practicalabment->get_course_module()->id,
                                        get_string('batchuploadfiles', 'practicalabfeedback_file'));
            $o = '';
            $o .= $this->practicalabment->get_renderer()->render($header);
            $o .= $this->practicalabment->get_renderer()->render(new practicalab_form('batchuploadfiles', $mform));
            $o .= $this->practicalabment->get_renderer()->render_footer();
        }

        return $o;
    }

    /**
     * User has chosen a custom grading batch operation and selected some users.
     *
     * @param string $action - The chosen action
     * @param array $users - An array of user ids
     * @return string - The response html
     */
    public function grading_batch_operation($action, $users) {

        if ($action == 'uploadfiles') {
            return $this->view_batch_upload_files($users);
        }
        return '';
    }

    /**
     * View the upload zip form.
     *
     * @return string - The html response
     */
    public function view_upload_zip() {
        global $CFG, $USER;

        require_capability('mod/practicalab:grade', $this->practicalabment->get_context());
        require_once($CFG->dirroot . '/mod/practicalab/feedback/file/uploadzipform.php');
        require_once($CFG->dirroot . '/mod/practicalab/feedback/file/importziplib.php');
        require_once($CFG->dirroot . '/mod/practicalab/feedback/file/importzipform.php');

        $formparams = array('context'=>$this->practicalabment->get_context(),
                            'cm'=>$this->practicalabment->get_course_module()->id);
        $mform = new practicalabfeedback_file_upload_zip_form(null, $formparams);

        $o = '';

        $confirm = optional_param('confirm', 0, PARAM_BOOL);
        $renderer = $this->practicalabment->get_renderer();

        // Delete any existing files.
        $importer = new practicalabfeedback_file_zip_importer();
        $contextid = $this->practicalabment->get_context()->id;

        if ($mform->is_cancelled()) {
            $importer->delete_import_files($contextid);
            $urlparams = array('id'=>$this->practicalabment->get_course_module()->id,
                               'action'=>'grading');
            $url = new moodle_url('view.php', $urlparams);
            redirect($url);
            return;
        } else if ($confirm) {
            $params = array('practicalabment'=>$this->practicalabment, 'importer'=>$importer);

            $mform = new practicalabfeedback_file_import_zip_form(null, $params);
            if ($mform->is_cancelled()) {
                $importer->delete_import_files($contextid);
                $urlparams = array('id'=>$this->practicalabment->get_course_module()->id,
                                   'action'=>'grading');
                $url = new moodle_url('view.php', $urlparams);
                redirect($url);
                return;
            }

            $o .= $importer->import_zip_files($this->practicalabment, $this);
            $importer->delete_import_files($contextid);
        } else if (($data = $mform->get_data()) &&
                   ($zipfile = $mform->save_stored_file('feedbackzip',
                                                        $contextid,
                                                        'practicalabfeedback_file',
                                                        PRACTICALABFEEDBACK_FILE_IMPORT_FILEAREA,
                                                        $USER->id,
                                                        '/',
                                                        'import.zip',
                                                        true))) {

            $importer->extract_files_from_zip($zipfile, $contextid);

            $params = array('practicalabment'=>$this->practicalabment, 'importer'=>$importer);

            $mform = new practicalabfeedback_file_import_zip_form(null, $params);

            $header = new practicalab_header($this->practicalabment->get_instance(),
                                        $this->practicalabment->get_context(),
                                        false,
                                        $this->practicalabment->get_course_module()->id,
                                        get_string('confirmuploadzip', 'practicalabfeedback_file'));
            $o .= $renderer->render($header);
            $o .= $renderer->render(new practicalab_form('confirmimportzip', $mform));
            $o .= $renderer->render_footer();

        } else {

            $header = new practicalab_header($this->practicalabment->get_instance(),
                                        $this->practicalabment->get_context(),
                                        false,
                                        $this->practicalabment->get_course_module()->id,
                                        get_string('uploadzip', 'practicalabfeedback_file'));
            $o .= $renderer->render($header);
            $o .= $renderer->render(new practicalab_form('uploadfeedbackzip', $mform));
            $o .= $renderer->render_footer();
        }

        return $o;
    }

    /**
     * Called by the practicalabment module when someone chooses something from the
     * grading navigation or batch operations list.
     *
     * @param string $action - The page to view
     * @return string - The html response
     */
    public function view_page($action) {
        if ($action == 'uploadfiles') {
            $users = required_param('selectedusers', PARAM_SEQUENCE);
            return $this->view_batch_upload_files(explode(',', $users));
        }
        if ($action == 'uploadzip') {
            return $this->view_upload_zip();
        }

        return '';
    }

    /**
     * Return a list of the grading actions performed by this plugin.
     * This plugin supports upload zip.
     *
     * @return array The list of grading actions
     */
    public function get_grading_actions() {
        return array('uploadzip'=>get_string('uploadzip', 'practicalabfeedback_file'));
    }
}