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

/**
 * library class for importing feedback files from a zip
 *
 * @package   practicalabfeedback_file
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class practicalabfeedback_file_zip_importer {

    /**
     * Is this filename valid (contains a unique participant ID) for import?
     *
     * @param practicalab $practicalabment - The practicalabment instance
     * @param stored_file $fileinfo - The fileinfo
     * @param array $participants - A list of valid participants for this module indexed by unique_id
     * @param stdClass $user - Set to the user that matches by participant id
     * @param practicalab_plugin $plugin - Set to the plugin that exported the file
     * @param string $filename - Set to truncated filename (prefix stripped)
     * @return true If the participant Id can be extracted and this is a valid user
     */
    public function is_valid_filename_for_import($practicalabment, $fileinfo, $participants, & $user, & $plugin, & $filename) {
        if ($fileinfo->is_directory()) {
            return false;
        }

        // Ignore hidden files.
        if (strpos($fileinfo->get_filename(), '.') === 0) {
            return false;
        }
        // Ignore hidden files.
        if (strpos($fileinfo->get_filename(), '~') === 0) {
            return false;
        }

        $info = explode('_', $fileinfo->get_filename(), 5);

        if (count($info) < 5) {
            return false;
        }

        $participantid = $info[1];
        $filename = $info[4];
        $plugin = $practicalabment->get_plugin_by_type($info[2], $info[3]);

        if (!is_numeric($participantid)) {
            return false;
        }

        if (!$plugin) {
            return false;
        }

        // Convert to int.
        $participantid += 0;

        if (empty($participants[$participantid])) {
            return false;
        }

        $user = $participants[$participantid];
        return true;
    }

    /**
     * Does this file exist in any of the current files supported by this plugin for this user?
     *
     * @param practicalab $practicalabment - The practicalabment instance
     * @param stdClass $user The user matching this uploaded file
     * @param practicalab_plugin $plugin The matching plugin from the filename
     * @param string $filename The parsed filename from the zip
     * @param stored_file $fileinfo The info about the extracted file from the zip
     * @return bool - True if the file has been modified or is new
     */
    public function is_file_modified($practicalabment, $user, $plugin, $filename, $fileinfo) {
        $sg = null;

        if ($plugin->get_subtype() == 'practicalabsubmission') {
            $sg = $practicalabment->get_user_submission($user->id, false);
        } else if ($plugin->get_subtype() == 'practicalabfeedback') {
            $sg = $practicalabment->get_user_grade($user->id, false);
        } else {
            return false;
        }

        if (!$sg) {
            return true;
        }
        foreach ($plugin->get_files($sg, $user) as $pluginfilename => $file) {
            if ($pluginfilename == $filename) {
                // Extract the file and compare hashes.
                $contenthash = '';
                if (is_array($file)) {
                    $content = reset($file);
                    $contenthash = sha1($content);
                } else {
                    $contenthash = $file->get_contenthash();
                }
                if ($contenthash != $fileinfo->get_contenthash()) {
                    return true;
                } else {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Delete all temp files used when importing a zip
     *
     * @param int $contextid - The context id of this practicalabment instance
     * @return bool true if all files were deleted
     */
    public function delete_import_files($contextid) {
        global $USER;

        $fs = get_file_storage();

        return $fs->delete_area_files($contextid,
                                      'practicalabfeedback_file',
                                      PRACTICALABFEEDBACK_FILE_IMPORT_FILEAREA,
                                      $USER->id);
    }

    /**
     * Extract the uploaded zip to a temporary import area for this user
     *
     * @param stored_file $zipfile The uploaded file
     * @param int $contextid The context for this practicalabment
     * @return bool - True if the files were unpacked
     */
    public function extract_files_from_zip($zipfile, $contextid) {
        global $USER;

        $feedbackfilesupdated = 0;
        $feedbackfilesadded = 0;
        $userswithnewfeedback = array();

        // Unzipping a large zip file is memory intensive.
        raise_memory_limit(MEMORY_EXTRA);

        $packer = get_file_packer('application/zip');
        @set_time_limit(PRACTICALABFEEDBACK_FILE_MAXFILEUNZIPTIME);

        return $packer->extract_to_storage($zipfile,
                                    $contextid,
                                    'practicalabfeedback_file',
                                    PRACTICALABFEEDBACK_FILE_IMPORT_FILEAREA,
                                    $USER->id,
                                    'import');

    }

    /**
     * Get the list of files extracted from the uploaded zip
     *
     * @param int $contextid
     * @return array of stored_files
     */
    public function get_import_files($contextid) {
        global $USER;

        $fs = get_file_storage();
        $files = $fs->get_directory_files($contextid,
                                          'practicalabfeedback_file',
                                          PRACTICALABFEEDBACK_FILE_IMPORT_FILEAREA,
                                          $USER->id,
                                          '/import/');

        return $files;
    }

    /**
     * Process an uploaded zip file
     *
     * @param practicalab $practicalabment - The practicalabment instance
     * @param practicalab_feedback_file $fileplugin - The file feedback plugin
     * @return string - The html response
     */
    public function import_zip_files($practicalabment, $fileplugin) {
        global $USER, $CFG, $PAGE, $DB;

        @set_time_limit(PRACTICALABFEEDBACK_FILE_MAXFILEUNZIPTIME);
        $packer = get_file_packer('application/zip');

        $feedbackfilesupdated = 0;
        $feedbackfilesadded = 0;
        $userswithnewfeedback = array();
        $contextid = $practicalabment->get_context()->id;

        $fs = get_file_storage();
        $files = $fs->get_directory_files($contextid,
                                          'practicalabfeedback_file',
                                          PRACTICALABFEEDBACK_FILE_IMPORT_FILEAREA,
                                          $USER->id,
                                          '/import/');

        $currentgroup = groups_get_activity_group($practicalabment->get_course_module(), true);
        $allusers = $practicalabment->list_participants($currentgroup, false);
        $participants = array();
        foreach ($allusers as $user) {
            $participants[$practicalabment->get_uniqueid_for_user($user->id)] = $user;
        }

        foreach ($files as $unzippedfile) {
            // Set the timeout for unzipping each file.
            $user = null;
            $plugin = null;
            $filename = '';

            if ($this->is_valid_filename_for_import($practicalabment, $unzippedfile, $participants, $user, $plugin, $filename)) {
                if ($this->is_file_modified($practicalabment, $user, $plugin, $filename, $unzippedfile)) {
                    $grade = $practicalabment->get_user_grade($user->id, true);

                    if ($oldfile = $fs->get_file($contextid,
                                                 'practicalabfeedback_file',
                                                 PRACTICALABFEEDBACK_FILE_FILEAREA,
                                                 $grade->id,
                                                 '/',
                                                 $filename)) {
                        // Update existing feedback file.
                        $oldfile->replace_content_with($unzippedfile);
                        $feedbackfilesupdated++;
                    } else {
                        // Create a new feedback file.
                        $newfilerecord = new stdClass();
                        $newfilerecord->contextid = $contextid;
                        $newfilerecord->component = 'practicalabfeedback_file';
                        $newfilerecord->filearea = PRACTICALABFEEDBACK_FILE_FILEAREA;
                        $newfilerecord->filename = $filename;
                        $newfilerecord->filepath = '/';
                        $newfilerecord->itemid = $grade->id;
                        $fs->create_file_from_storedfile($newfilerecord, $unzippedfile);
                        $feedbackfilesadded++;
                    }
                    $userswithnewfeedback[$user->id] = 1;

                    // Update the number of feedback files for this user.
                    $fileplugin->update_file_count($grade);

                    // Update the last modified time on the grade which will trigger student notifications.
                    $practicalabment->notify_grade_modified($grade);
                }
            }
        }

        require_once($CFG->dirroot . '/mod/practicalab/feedback/file/renderable.php');
        $importsummary = new practicalabfeedback_file_import_summary($practicalabment->get_course_module()->id,
                                                            count($userswithnewfeedback),
                                                            $feedbackfilesadded,
                                                            $feedbackfilesupdated);

        $practicalabrenderer = $practicalabment->get_renderer();
        $renderer = $PAGE->get_renderer('practicalabfeedback_file');

        $o = '';

        $o .= $practicalabrenderer->render(new practicalab_header($practicalabment->get_instance(),
                                                        $practicalabment->get_context(),
                                                        false,
                                                        $practicalabment->get_course_module()->id,
                                                        get_string('uploadzipsummary', 'practicalabfeedback_file')));

        $o .= $renderer->render($importsummary);

        $o .= $practicalabrenderer->render_footer();
        return $o;
    }

}