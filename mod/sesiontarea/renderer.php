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
 * This file contains a renderer for the sesiontareament class
 *
 * @package   mod_sesiontarea
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/sesiontarea/locallib.php');
require_once($CFG->libdir . '/itse/cerrar_actividad_form.php');

/**
 * A custom renderer class that extends the plugin_renderer_base and is used by the sesiontarea module.
 *
 * @package mod_sesiontarea
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_sesiontarea_renderer extends plugin_renderer_base {

    /**
     * Rendering sesiontareament files
     *
     * @param context $context
     * @param int $userid
     * @param string $filearea
     * @param string $component
     * @return string
     */
    public function sesiontarea_files(context $context, $userid, $filearea, $component) {
        return $this->render(new sesiontarea_files($context, $userid, $filearea, $component));
    }

    /**
     * Rendering sesiontareament files
     *
     * @param sesiontarea_files $tree
     * @return string
     */
    public function render_sesiontarea_files(sesiontarea_files $tree) {
        $this->htmlid = 'sesiontarea_files_tree_'.uniqid();
        $this->page->requires->js_init_call('M.mod_sesiontarea.init_tree', array(true, $this->htmlid));
        $html = '<div id="'.$this->htmlid.'">';
        $html .= $this->htmllize_tree($tree, $tree->dir);
        $html .= '</div>';

        if ($tree->portfolioform) {
            $html .= $tree->portfolioform;
        }
        return $html;
    }

    /**
     * Utility function to add a row of data to a table with 2 columns. Modified
     * the table param and does not return a value
     *
     * @param html_table $table The table to append the row of data to
     * @param string $first The first column text
     * @param string $second The second column text
     * @return void
     */
    private function add_table_row_tuple(html_table $table, $first, $second) {
        $row = new html_table_row();
        $cell1 = new html_table_cell($first);
        $cell2 = new html_table_cell($second);
        $row->cells = array($cell1, $cell2);
        $table->data[] = $row;
    }

    /**
     * Render a grading message notification
     * @param sesiontarea_gradingmessage $result The result to render
     * @return string
     */
    public function render_sesiontarea_gradingmessage(sesiontarea_gradingmessage $result) {
        $urlparams = array('id' => $result->coursemoduleid, 'action'=>'grading');
        $url = new moodle_url('/mod/sesiontarea/view.php', $urlparams);

        $o = '';
        $o .= $this->output->heading($result->heading, 4);
        $o .= $this->output->notification($result->message);
        $o .= $this->output->continue_button($url);
        return $o;
    }

    /**
     * Render the generic form
     * @param sesiontarea_form $form The form to render
     * @return string
     */
    public function render_sesiontarea_form(sesiontarea_form $form) {
        $o = '';
        if ($form->jsinitfunction) {
            $this->page->requires->js_init_call($form->jsinitfunction, array());
        }
        $o .= $this->output->box_start('boxaligncenter ' . $form->classname);
        $o .= $this->moodleform($form->form);
        $o .= $this->output->box_end();
        return $o;
    }

    /**
     * Render the user summary
     *
     * @param sesiontarea_user_summary $summary The user summary to render
     * @return string
     */
    public function render_sesiontarea_user_summary(sesiontarea_user_summary $summary) {
        $o = '';

        if (!$summary->user) {
            return;
        }
        $o .= $this->output->container_start('usersummary');
        $o .= $this->output->box_start('boxaligncenter usersummarysection');
        if ($summary->blindmarking) {
            $o .= get_string('hiddenuser', 'sesiontarea') . $summary->uniqueidforuser;
        } else {
            $o .= $this->output->user_picture($summary->user);
            $o .= $this->output->spacer(array('width'=>30));
            $urlparams = array('id' => $summary->user->id, 'course'=>$summary->courseid);
            $url = new moodle_url('/user/view.php', $urlparams);
            $fullname = fullname($summary->user, $summary->viewfullnames);
            $extrainfo = array();
            foreach ($summary->extrauserfields as $extrafield) {
                $extrainfo[] = $summary->user->$extrafield;
            }
            if (count($extrainfo)) {
                $fullname .= ' (' . implode(', ', $extrainfo) . ')';
            }
            $o .= $this->output->action_link($url, $fullname);
        }
        $o .= $this->output->box_end();
        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * Render the submit for grading page
     *
     * @param sesiontarea_submit_for_grading_page $page
     * @return string
     */
    public function render_sesiontarea_submit_for_grading_page($page) {
        $o = '';

        $o .= $this->output->container_start('submitforgrading');
        $o .= $this->output->heading(get_string('submitsesiontareament', 'sesiontarea'), 3);
        $o .= $this->output->spacer(array('height'=>30));

        $cancelurl = new moodle_url('/mod/sesiontarea/view.php', array('id' => $page->coursemoduleid));
        if (count($page->notifications)) {
            // At least one of the submission plugins is not ready for submission.

            $o .= $this->output->heading(get_string('submissionnotready', 'sesiontarea'), 4);

            foreach ($page->notifications as $notification) {
                $o .= $this->output->notification($notification);
            }

            $o .= $this->output->continue_button($cancelurl);
        } else {
            // All submission plugins ready - show the confirmation form.
            $o .= $this->output->box_start('generalbox submitconfirm');
            $o .= $this->moodleform($page->confirmform);
            $o .= $this->output->box_end();
        }
        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * Page is done - render the footer.
     *
     * @return void
     */
    public function render_footer() {
        return $this->output->footer();
    }

    /**
     * Render the header.
     *
     * @param sesiontarea_header $header
     * @return string
     */
    public function render_sesiontarea_header(sesiontarea_header $header) {
        $o = '';

        if ($header->subpage) {
            $this->page->navbar->add($header->subpage);
        }

        $this->page->set_title(get_string('pluginname', 'sesiontarea'));
        $this->page->set_heading($header->sesiontarea->name);

        $o .= $this->output->header();
        if ($header->preface) {
            $o .= $header->preface;
        }
        $heading = format_string($header->sesiontarea->name, false, array('context' => $header->context));
        $o .= $this->output->heading($heading);

        $sesion = new SesionClase($header->sesiontarea->id, 'sesiontarea', $header->coursemoduleid);
        if ($header->showintro) 
        {            
            $o .= $this->output->box_start('generalbox boxaligncenter', 'intro');
            $o .= $sesion->display_selector_grupos($header->context);
            $o .= $sesion->display($header->context);
            $o .= $sesion->display_cerrar_actividad($header->context);
            $o .= $sesion->display_asistencias($header->context);
            $o .= '<h3>' . get_string('rubrica', 'sesiontarea') . '</h3>';
            $o .= format_module_intro('sesiontarea', $header->sesiontarea, $header->coursemoduleid);
            $o .= $this->output->box_end();
        }

        return $o;
    }

    public function render_sesiontarea_sesionclase($header)
    {
        global $OUTPUT, $DB;
        $out = '';
        $sesionclase = $header->sesiontarea;
        $context = $header->context;
        
        $out .= $OUTPUT->notification(get_string($sesionclase->estado == ESTADO_ACTIVIDAD_CERRADA ? 'estado_cerrada': 'estado_abierta', 'sesionclase'));
        $out .= "<table style='border: 1px solid; width=100%'>";
        $out .= '<tr>';
        $out .= "<th style='border: 1px solid;'>" . get_string('aprendizaje', 'sesionclase') . "</th>";
        $out .= "<th style='border: 1px solid;'>" . get_string('ensenanza', 'sesionclase') . "</th>";
        $out .= "<th style='border: 1px solid;'>" . get_string('portafolio', 'sesionclase') . "</th>";
        $out .= '</tr>';
        $out .= '<tr>';
        $out .= "<td style='border: 1px solid;'>$sesionclase->aprendizaje</td>";
        $out .= "<td style='border: 1px solid;'>$sesionclase->ensenanza</td>";
        $out .= "<td style='border: 1px solid;'>$sesionclase->portafolio</td>";
        $out .= '</tr>';
        $out .= '</table>';

        $diaprogramada = date('d', $sesionclase->fprogramada);
        $mesprogramada = date('F', $sesionclase->fprogramada);
        $anoprogramada = date('Y', $sesionclase->fprogramada);
        $horaprograma = $sesionclase->hprogramada / (60 * 60);

        $out .= "<p>La fecha programada para esta actividad es <strong>$diaprogramada de $mesprogramada de $anoprogramada</strong>. ";
        $out .= "La cantidad de horas programadas son <strong>$horaprograma</strong>.</p>";
        if(!empty($sesionclase->observaciones))
            $out .= "<p>Observaciones: <strong>$sesionclase->observaciones</strong></p>";
        if ($sesionclase->hreal > 0 && $sesionclase->estado == ESTADO_ACTIVIDAD_CERRADA)
        {
            $diareal = date('d', $sesionclase->freal);
            $mesreal = date('F', $sesionclase->freal);
            $anoreal = date('Y', $sesionclase->freal);
            $horareal = $sesionclase->hreal / (60 * 60);

            $out .= "<br><p>La fecha real en la cual se desarrollo esta actividad es <strong>$diareal de $mesreal de $anoreal</strong>. ";
            $out .= "La cantidad de horas reales usadas en su desarrollo fueron <strong>$horareal</strong>.</p>";
        }

        $url = new moodle_url('/mod/sesiontarea/view.php', array('id' => $header->coursemoduleid));
        $forma_cerrar_actividad = new cerrar_actividad_form($url, array('context' => $context, 'sesion' => $sesionclase));
        $forma_cerrar_actividad->set_data($sesionclase);

        if (has_capability('mod/sesionclase:cerrar', $context) && $sesionclase->estado == ESTADO_ACTIVIDAD_ABIERTA)
        {
            $out .= $forma_cerrar_actividad->render();

            if($data = $forma_cerrar_actividad->get_data())
            {
                //print_r($data);
                $DB->update_record('sesiontarea', $data);
                redirect($url);
            }
        }

        if ((has_capability('mod/sesionclase:desbloquear', $context) && $sesionclase->estado == ESTADO_ACTIVIDAD_CERRADA))
        {    
            $out .= $forma_cerrar_actividad->render();

            if($data = $forma_cerrar_actividad->get_data())
            {
                //print_r($data);
                $DB->update_record('sesiontarea', $data);
                redirect($url);
            }
        }

        return $out;
        
    }

    /**
     * Render a table containing the current status of the grading process.
     *
     * @param sesiontarea_grading_summary $summary
     * @return string
     */
    public function render_sesiontarea_grading_summary(sesiontarea_grading_summary $summary) {
        // Create a table for the data.
        $o = '';
        $o .= $this->output->container_start('gradingsummary');
        $o .= $this->output->heading(get_string('gradingsummary', 'sesiontarea'), 3);
        $o .= $this->output->box_start('boxaligncenter gradingsummarytable');
        $t = new html_table();

        // Status.
        if ($summary->teamsubmission) {
            $this->add_table_row_tuple($t, get_string('numberofteams', 'sesiontarea'),
                                       $summary->participantcount);
        } else {
            $this->add_table_row_tuple($t, get_string('numberofparticipants', 'sesiontarea'),
                                       $summary->participantcount);
        }

        // Drafts.
        if ($summary->submissiondraftsenabled) {
            $this->add_table_row_tuple($t, get_string('numberofdraftsubmissions', 'sesiontarea'),
                                       $summary->submissiondraftscount);
        }

        // Submitted for grading.
        if ($summary->submissionsenabled) {
            $this->add_table_row_tuple($t, get_string('numberofsubmittedsesiontareaments', 'sesiontarea'),
                                       $summary->submissionssubmittedcount);
            if (!$summary->teamsubmission) {
                $this->add_table_row_tuple($t, get_string('numberofsubmissionsneedgrading', 'sesiontarea'),
                                           $summary->submissionsneedgradingcount);
            }
        }

        $time = time();
        if ($summary->duedate) {
            // Due date.
            $duedate = $summary->duedate;
            $this->add_table_row_tuple($t, get_string('duedate', 'sesiontarea'),
                                       userdate($duedate));

            // Time remaining.
            $due = '';
            if ($duedate - $time <= 0) {
                $due = get_string('sesiontareamentisdue', 'sesiontarea');
            } else {
                $due = format_time($duedate - $time);
            }
            $this->add_table_row_tuple($t, get_string('timeremaining', 'sesiontarea'), $due);

            if ($duedate < $time) {
                $cutoffdate = $summary->cutoffdate;
                if ($cutoffdate) {
                    if ($cutoffdate > $time) {
                        $late = get_string('latesubmissionsaccepted', 'sesiontarea');
                    } else {
                        $late = get_string('nomoresubmissionsaccepted', 'sesiontarea');
                    }
                    $this->add_table_row_tuple($t, get_string('latesubmissions', 'sesiontarea'), $late);
                }
            }

        }

        // All done - write the table.
        $o .= html_writer::table($t);
        $o .= $this->output->box_end();

        // Link to the grading page.
        $o .= $this->output->container_start('submissionlinks');
        $urlparams = array('id' => $summary->coursemoduleid, 'action'=>'grading');
        $url = new moodle_url('/mod/sesiontarea/view.php', $urlparams);
        $o .= $this->output->action_link($url, get_string('viewgrading', 'sesiontarea'));
        $o .= $this->output->container_end();

        // Close the container and insert a spacer.
        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * Render a table containing all the current grades and feedback.
     *
     * @param sesiontarea_feedback_status $status
     * @return string
     */
    public function render_sesiontarea_feedback_status(sesiontarea_feedback_status $status) {
        global $DB, $CFG;
        $o = '';

        $o .= $this->output->container_start('feedback');
        $o .= $this->output->heading(get_string('feedback', 'sesiontarea'), 3);
        $o .= $this->output->box_start('boxaligncenter feedbacktable');
        $t = new html_table();

        // Grade.
        if (isset($status->gradefordisplay)) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('grade'));
            $cell2 = new html_table_cell($status->gradefordisplay);
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;

            // Grade date.
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('gradedon', 'sesiontarea'));
            $cell2 = new html_table_cell(userdate($status->gradeddate));
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }

        if ($status->grader) {
            // Grader.
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('gradedby', 'sesiontarea'));
            $userdescription = $this->output->user_picture($status->grader) .
                               $this->output->spacer(array('width'=>30)) .
                               fullname($status->grader);
            $cell2 = new html_table_cell($userdescription);
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }

        foreach ($status->feedbackplugins as $plugin) {
            if ($plugin->is_enabled() &&
                    $plugin->is_visible() &&
                    $plugin->has_user_summary() &&
                    !empty($status->grade) &&
                    !$plugin->is_empty($status->grade)) {

                $row = new html_table_row();
                $cell1 = new html_table_cell($plugin->get_name());
                $displaymode = sesiontarea_feedback_plugin_feedback::SUMMARY;
                $pluginfeedback = new sesiontarea_feedback_plugin_feedback($plugin,
                                                                      $status->grade,
                                                                      $displaymode,
                                                                      $status->coursemoduleid,
                                                                      $status->returnaction,
                                                                      $status->returnparams);
                $cell2 = new html_table_cell($this->render($pluginfeedback));
                $row->cells = array($cell1, $cell2);
                $t->data[] = $row;
            }
        }

        $o .= html_writer::table($t);
        $o .= $this->output->box_end();

        $o .= $this->output->container_end();
        return $o;
    }

    /**
     * Render a table containing the current status of the submission.
     *
     * @param sesiontarea_submission_status $status
     * @return string
     */
    public function render_sesiontarea_submission_status(sesiontarea_submission_status $status) {
        $o = '';
        $o .= $this->output->container_start('submissionstatustable');
        $o .= $this->output->heading(get_string('submissionstatusheading', 'sesiontarea'), 3);
        $time = time();

        if ($status->allowsubmissionsfromdate &&
                $time <= $status->allowsubmissionsfromdate) {
            $o .= $this->output->box_start('generalbox boxaligncenter submissionsalloweddates');
            if ($status->alwaysshowdescription) {
                $date = userdate($status->allowsubmissionsfromdate);
                $o .= get_string('allowsubmissionsfromdatesummary', 'sesiontarea', $date);
            } else {
                $date = userdate($status->allowsubmissionsfromdate);
                $o .= get_string('allowsubmissionsanddescriptionfromdatesummary', 'sesiontarea', $date);
            }
            $o .= $this->output->box_end();
        }
        $o .= $this->output->box_start('boxaligncenter submissionsummarytable');

        $t = new html_table();

        if ($status->teamsubmissionenabled) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('submissionteam', 'sesiontarea'));
            $group = $status->submissiongroup;
            if ($group) {
                $cell2 = new html_table_cell(format_string($group->name, false, $status->context));
            } else {
                $cell2 = new html_table_cell(get_string('defaultteam', 'sesiontarea'));
            }
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }

        if ($status->attemptreopenmethod != SESIONTAREA_ATTEMPT_REOPEN_METHOD_NONE) {
            $currentattempt = 1;
            if (!$status->teamsubmissionenabled) {
                if ($status->submission) {
                    $currentattempt = $status->submission->attemptnumber + 1;
                }
            } else {
                if ($status->teamsubmission) {
                    $currentattempt = $status->teamsubmission->attemptnumber + 1;
                }
            }

            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('attemptnumber', 'sesiontarea'));
            $maxattempts = $status->maxattempts;
            if ($maxattempts == SESIONTAREA_UNLIMITED_ATTEMPTS) {
                $message = get_string('currentattempt', 'sesiontarea', $currentattempt);
            } else {
                $message = get_string('currentattemptof', 'sesiontarea', array('attemptnumber'=>$currentattempt,
                                                                          'maxattempts'=>$maxattempts));
            }
            $cell2 = new html_table_cell($message);
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('submissionstatus', 'sesiontarea'));
        if (!$status->teamsubmissionenabled) {
            if ($status->submission) {
                $statusstr = get_string('submissionstatus_' . $status->submission->status, 'sesiontarea');
                $cell2 = new html_table_cell($statusstr);
                $cell2->attributes = array('class'=>'submissionstatus' . $status->submission->status);
            } else {
                if (!$status->submissionsenabled) {
                    $cell2 = new html_table_cell(get_string('noonlinesubmissions', 'sesiontarea'));
                } else {
                    $cell2 = new html_table_cell(get_string('noattempt', 'sesiontarea'));
                }
            }
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        } else {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('submissionstatus', 'sesiontarea'));
            if ($status->teamsubmission) {
                $teamstatus = $status->teamsubmission->status;
                $submissionsummary = get_string('submissionstatus_' . $teamstatus, 'sesiontarea');
                $groupid = 0;
                if ($status->submissiongroup) {
                    $groupid = $status->submissiongroup->id;
                }

                $members = $status->submissiongroupmemberswhoneedtosubmit;
                $userslist = array();
                foreach ($members as $member) {
                    $urlparams = array('id' => $member->id, 'course'=>$status->courseid);
                    $url = new moodle_url('/user/view.php', $urlparams);
                    if ($status->view == sesiontarea_submission_status::GRADER_VIEW && $status->blindmarking) {
                        $userslist[] = $member->alias;
                    } else {
                        $fullname = fullname($member, $status->canviewfullnames);
                        $userslist[] = $this->output->action_link($url, $fullname);
                    }
                }
                if (count($userslist) > 0) {
                    $userstr = join(', ', $userslist);
                    $formatteduserstr = get_string('userswhoneedtosubmit', 'sesiontarea', $userstr);
                    $submissionsummary .= $this->output->container($formatteduserstr);
                }

                $cell2 = new html_table_cell($submissionsummary);
                $cell2->attributes = array('class'=>'submissionstatus' . $status->teamsubmission->status);
            } else {
                $cell2 = new html_table_cell(get_string('nosubmission', 'sesiontarea'));
                if (!$status->submissionsenabled) {
                    $cell2 = new html_table_cell(get_string('noonlinesubmissions', 'sesiontarea'));
                } else {
                    $cell2 = new html_table_cell(get_string('nosubmission', 'sesiontarea'));
                }
            }
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }

        // Is locked?
        if ($status->locked) {
            $row = new html_table_row();
            $cell1 = new html_table_cell();
            $cell2 = new html_table_cell(get_string('submissionslocked', 'sesiontarea'));
            $cell2->attributes = array('class'=>'submissionlocked');
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }

        // Grading status.
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('gradingstatus', 'sesiontarea'));

        if ($status->graded) {
            $cell2 = new html_table_cell(get_string('graded', 'sesiontarea'));
            $cell2->attributes = array('class'=>'submissiongraded');
        } else {
            $cell2 = new html_table_cell(get_string('notgraded', 'sesiontarea'));
            $cell2->attributes = array('class'=>'submissionnotgraded');
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;

        $duedate = $status->duedate;
        if ($duedate > 0) {
            // Due date.
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('duedate', 'sesiontarea'));
            $cell2 = new html_table_cell(userdate($duedate));
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;

            if ($status->view == sesiontarea_submission_status::GRADER_VIEW) {
                if ($status->cutoffdate) {
                    // Cut off date.
                    $row = new html_table_row();
                    $cell1 = new html_table_cell(get_string('cutoffdate', 'sesiontarea'));
                    $cell2 = new html_table_cell(userdate($status->cutoffdate));
                    $row->cells = array($cell1, $cell2);
                    $t->data[] = $row;
                }
            }

            if ($status->extensionduedate) {
                // Extension date.
                $row = new html_table_row();
                $cell1 = new html_table_cell(get_string('extensionduedate', 'sesiontarea'));
                $cell2 = new html_table_cell(userdate($status->extensionduedate));
                $row->cells = array($cell1, $cell2);
                $t->data[] = $row;
                $duedate = $status->extensionduedate;
            }

            // Time remaining.
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('timeremaining', 'sesiontarea'));
            if ($duedate - $time <= 0) {
                if (!$status->submission ||
                        $status->submission->status != SESIONTAREA_SUBMISSION_STATUS_SUBMITTED) {
                    if ($status->submissionsenabled) {
                        $overduestr = get_string('overdue', 'sesiontarea', format_time($time - $duedate));
                        $cell2 = new html_table_cell($overduestr);
                        $cell2->attributes = array('class'=>'overdue');
                    } else {
                        $cell2 = new html_table_cell(get_string('duedatereached', 'sesiontarea'));
                    }
                } else {
                    if ($status->submission->timemodified > $duedate) {
                        $latestr = get_string('submittedlate',
                                              'sesiontarea',
                                              format_time($status->submission->timemodified - $duedate));
                        $cell2 = new html_table_cell($latestr);
                        $cell2->attributes = array('class'=>'latesubmission');
                    } else {
                        $earlystr = get_string('submittedearly',
                                               'sesiontarea',
                                               format_time($status->submission->timemodified - $duedate));
                        $cell2 = new html_table_cell($earlystr);
                        $cell2->attributes = array('class'=>'earlysubmission');
                    }
                }
            } else {
                $cell2 = new html_table_cell(format_time($duedate - $time));
            }
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }

        // Show graders whether this submission is editable by students.
        if ($status->view == sesiontarea_submission_status::GRADER_VIEW) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('editingstatus', 'sesiontarea'));
            if ($status->canedit) {
                $cell2 = new html_table_cell(get_string('submissioneditable', 'sesiontarea'));
                $cell2->attributes = array('class'=>'submissioneditable');
            } else {
                $cell2 = new html_table_cell(get_string('submissionnoteditable', 'sesiontarea'));
                $cell2->attributes = array('class'=>'submissionnoteditable');
            }
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }

        // Grading criteria preview.
        if (!empty($status->gradingcontrollerpreview)) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('gradingmethodpreview', 'sesiontarea'));
            $cell2 = new html_table_cell($status->gradingcontrollerpreview);
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }

        // Last modified.
        $submission = $status->teamsubmission ? $status->teamsubmission : $status->submission;
        if ($submission) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('timemodified', 'sesiontarea'));
            $cell2 = new html_table_cell(userdate($submission->timemodified));
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;

            foreach ($status->submissionplugins as $plugin) {
                $pluginshowsummary = !$plugin->is_empty($submission) || !$plugin->allow_submissions();
                if ($plugin->is_enabled() &&
                    $plugin->is_visible() &&
                    $plugin->has_user_summary() &&
                    $pluginshowsummary) {

                    $row = new html_table_row();
                    $cell1 = new html_table_cell($plugin->get_name());
                    $displaymode = sesiontarea_submission_plugin_submission::SUMMARY;
                    $pluginsubmission = new sesiontarea_submission_plugin_submission($plugin,
                                                                                $submission,
                                                                                $displaymode,
                                                                                $status->coursemoduleid,
                                                                                $status->returnaction,
                                                                                $status->returnparams);
                    $cell2 = new html_table_cell($this->render($pluginsubmission));
                    $row->cells = array($cell1, $cell2);
                    $t->data[] = $row;
                }
            }
        }

        $o .= html_writer::table($t);
        $o .= $this->output->box_end();

        // Links.
        if ($status->view == sesiontarea_submission_status::STUDENT_VIEW) {
            if ($status->canedit) {
                if (!$submission) {
                    $o .= $this->output->box_start('generalbox submissionaction');
                    $urlparams = array('id' => $status->coursemoduleid, 'action' => 'editsubmission');
                    $o .= $this->output->single_button(new moodle_url('/mod/sesiontarea/view.php', $urlparams),
                                                       get_string('addsubmission', 'sesiontarea'), 'get');
                    $o .= $this->output->box_start('boxaligncenter submithelp');
                    $o .= get_string('editsubmission_help', 'sesiontarea');
                    $o .= $this->output->box_end();
                    $o .= $this->output->box_end();
                } else if ($submission->status == SESIONTAREA_SUBMISSION_STATUS_REOPENED) {
                    $o .= $this->output->box_start('generalbox submissionaction');
                    $urlparams = array('id' => $status->coursemoduleid, 'action' => 'editprevioussubmission');
                    $o .= $this->output->single_button(new moodle_url('/mod/sesiontarea/view.php', $urlparams),
                                                       get_string('addnewattemptfromprevious', 'sesiontarea'), 'get');
                    $o .= $this->output->box_start('boxaligncenter submithelp');
                    $o .= get_string('addnewattemptfromprevious_help', 'sesiontarea');
                    $o .= $this->output->box_end();
                    $o .= $this->output->box_end();
                    $o .= $this->output->box_start('generalbox submissionaction');
                    $urlparams = array('id' => $status->coursemoduleid, 'action' => 'editsubmission');
                    $o .= $this->output->single_button(new moodle_url('/mod/sesiontarea/view.php', $urlparams),
                                                       get_string('addnewattempt', 'sesiontarea'), 'get');
                    $o .= $this->output->box_start('boxaligncenter submithelp');
                    $o .= get_string('addnewattempt_help', 'sesiontarea');
                    $o .= $this->output->box_end();
                    $o .= $this->output->box_end();
                } else {
                    $o .= $this->output->box_start('generalbox submissionaction');
                    $urlparams = array('id' => $status->coursemoduleid, 'action' => 'editsubmission');
                    $o .= $this->output->single_button(new moodle_url('/mod/sesiontarea/view.php', $urlparams),
                                                       get_string('editsubmission', 'sesiontarea'), 'get');
                    $o .= $this->output->box_start('boxaligncenter submithelp');
                    $o .= get_string('editsubmission_help', 'sesiontarea');
                    $o .= $this->output->box_end();
                    $o .= $this->output->box_end();
                }
            }

            if ($status->cansubmit) {
                $urlparams = array('id' => $status->coursemoduleid, 'action'=>'submit');
                $o .= $this->output->box_start('generalbox submissionaction');
                $o .= $this->output->single_button(new moodle_url('/mod/sesiontarea/view.php', $urlparams),
                                                   get_string('submitsesiontareament', 'sesiontarea'), 'get');
                $o .= $this->output->box_start('boxaligncenter submithelp');
                $o .= get_string('submitsesiontareament_help', 'sesiontarea');
                $o .= $this->output->box_end();
                $o .= $this->output->box_end();
            }
        }

        $o .= $this->output->container_end();
        return $o;
    }

    /**
     * Output the attempt history for this sesiontareament
     *
     * @param sesiontarea_attempt_history $history
     * @return string
     */
    public function render_sesiontarea_attempt_history(sesiontarea_attempt_history $history) {
        $o = '';

        $submittedstr = get_string('submitted', 'sesiontarea');
        $gradestr = get_string('grade');
        $gradedonstr = get_string('gradedon', 'sesiontarea');
        $gradedbystr = get_string('gradedby', 'sesiontarea');

        // Don't show the last one because it is the current submission.
        array_pop($history->submissions);

        // Show newest to oldest.
        $history->submissions = array_reverse($history->submissions);

        if (empty($history->submissions)) {
            return '';
        }

        $containerid = 'attempthistory' . uniqid();
        $o .= $this->heading(get_string('attempthistory', 'sesiontarea'), 3);
        $o .= $this->box_start('attempthistory', $containerid);

        foreach ($history->submissions as $i => $submission) {
            $grade = null;
            foreach ($history->grades as $onegrade) {
                if ($onegrade->attemptnumber == $submission->attemptnumber) {
                    $grade = $onegrade;
                    break;
                }
            }

            $editbtn = '';

            if ($submission) {
                $submissionsummary = userdate($submission->timemodified);
            } else {
                $submissionsummary = get_string('nosubmission', 'sesiontarea');
            }

            $attemptsummaryparams = array('attemptnumber'=>$submission->attemptnumber+1,
                                          'submissionsummary'=>$submissionsummary);
            $o .= $this->heading(get_string('attemptheading', 'sesiontarea', $attemptsummaryparams), 4);

            $t = new html_table();

            if ($submission) {
                $cell1 = new html_table_cell(get_string('submissionstatus', 'sesiontarea'));
                $cell2 = new html_table_cell(get_string('submissionstatus_' . $submission->status, 'sesiontarea'));
                $t->data[] = new html_table_row(array($cell1, $cell2));

                foreach ($history->submissionplugins as $plugin) {
                    $pluginshowsummary = !$plugin->is_empty($submission) || !$plugin->allow_submissions();
                    if ($plugin->is_enabled() &&
                            $plugin->is_visible() &&
                            $plugin->has_user_summary() &&
                            $pluginshowsummary) {

                        $cell1 = new html_table_cell($plugin->get_name());
                        $pluginsubmission = new sesiontarea_submission_plugin_submission($plugin,
                                                                                    $submission,
                                                                                    sesiontarea_submission_plugin_submission::SUMMARY,
                                                                                    $history->coursemoduleid,
                                                                                    $history->returnaction,
                                                                                    $history->returnparams);
                        $cell2 = new html_table_cell($this->render($pluginsubmission));

                        $t->data[] = new html_table_row(array($cell1, $cell2));
                    }
                }
            }

            if ($grade) {
                // Heading 'feedback'.
                $title = get_string('feedback', 'sesiontarea', $i);
                $title .= $this->output->spacer(array('width'=>10));
                if ($history->cangrade) {
                    // Edit previous feedback.
                    $returnparams = http_build_query($history->returnparams);
                    $urlparams = array('id' => $history->coursemoduleid,
                                   'userid'=>$grade->userid,
                                   'attemptnumber'=>$grade->attemptnumber,
                                   'action'=>'grade',
                                   'rownum'=>0,
                                   'returnaction'=>$history->returnaction,
                                   'returnparams'=>$returnparams);
                    $url = new moodle_url('/mod/sesiontarea/view.php', $urlparams);
                    $icon = new pix_icon('gradefeedback',
                                            get_string('editattemptfeedback', 'sesiontarea', $grade->attemptnumber+1),
                                            'mod_sesiontarea');
                    $title .= $this->output->action_icon($url, $icon);
                }
                $cell = new html_table_cell($title);
                $cell->attributes['class'] = 'feedbacktitle';
                $cell->colspan = 2;
                $t->data[] = new html_table_row(array($cell));

                // Grade.
                $cell1 = new html_table_cell($gradestr);
                $cell2 = $grade->gradefordisplay;
                $t->data[] = new html_table_row(array($cell1, $cell2));

                // Graded on.
                $cell1 = new html_table_cell($gradedonstr);
                $cell2 = new html_table_cell(userdate($grade->timemodified));
                $t->data[] = new html_table_row(array($cell1, $cell2));

                // Graded by.
                $cell1 = new html_table_cell($gradedbystr);
                $cell2 = new html_table_cell($this->output->user_picture($grade->grader) .
                                             $this->output->spacer(array('width'=>30)) . fullname($grade->grader));
                $t->data[] = new html_table_row(array($cell1, $cell2));

                // Feedback from plugins.
                foreach ($history->feedbackplugins as $plugin) {
                    if ($plugin->is_enabled() &&
                        $plugin->is_visible() &&
                        $plugin->has_user_summary() &&
                        !$plugin->is_empty($grade)) {

                        $cell1 = new html_table_cell($plugin->get_name());
                        $pluginfeedback = new sesiontarea_feedback_plugin_feedback(
                            $plugin, $grade, sesiontarea_feedback_plugin_feedback::SUMMARY, $history->coursemoduleid,
                            $history->returnaction, $history->returnparams
                        );
                        $cell2 = new html_table_cell($this->render($pluginfeedback));
                        $t->data[] = new html_table_row(array($cell1, $cell2));
                    }

                }

            }

            $o .= html_writer::table($t);
        }
        $o .= $this->box_end();
        $jsparams = array($containerid);

        $this->page->requires->yui_module('moodle-mod_sesiontarea-history', 'Y.one("#' . $containerid . '").history');

        return $o;
    }

    /**
     * Render a submission plugin submission
     *
     * @param sesiontarea_submission_plugin_submission $submissionplugin
     * @return string
     */
    public function render_sesiontarea_submission_plugin_submission(sesiontarea_submission_plugin_submission $submissionplugin) {
        $o = '';

        if ($submissionplugin->view == sesiontarea_submission_plugin_submission::SUMMARY) {
            $showviewlink = false;
            $summary = $submissionplugin->plugin->view_summary($submissionplugin->submission,
                                                               $showviewlink);

            $classsuffix = $submissionplugin->plugin->get_subtype() .
                           '_' .
                           $submissionplugin->plugin->get_type() .
                           '_' .
                           $submissionplugin->submission->id;

            $o .= $this->output->box_start('boxaligncenter plugincontentsummary summary_' . $classsuffix);

            $link = '';
            if ($showviewlink) {
                $previewstr = get_string('viewsubmission', 'sesiontarea');
                $icon = $this->output->pix_icon('t/preview', $previewstr);

                $expandstr = get_string('viewfull', 'sesiontarea');
                $options = array('class'=>'expandsummaryicon expand_' . $classsuffix);
                $o .= $this->output->pix_icon('t/switch_plus', $expandstr, null, $options);

                $jsparams = array($submissionplugin->plugin->get_subtype(),
                                  $submissionplugin->plugin->get_type(),
                                  $submissionplugin->submission->id);

                $this->page->requires->js_init_call('M.mod_sesiontarea.init_plugin_summary', $jsparams);

                $action = 'viewplugin' . $submissionplugin->plugin->get_subtype();
                $returnparams = http_build_query($submissionplugin->returnparams);
                $link .= '<noscript>';
                $urlparams = array('id' => $submissionplugin->coursemoduleid,
                                   'sid'=>$submissionplugin->submission->id,
                                   'plugin'=>$submissionplugin->plugin->get_type(),
                                   'action'=>$action,
                                   'returnaction'=>$submissionplugin->returnaction,
                                   'returnparams'=>$returnparams);
                $url = new moodle_url('/mod/sesiontarea/view.php', $urlparams);
                $link .= $this->output->action_link($url, $icon);
                $link .= '</noscript>';

                $link .= $this->output->spacer(array('width'=>15));
            }

            $o .= $link . $summary;
            $o .= $this->output->box_end();
            if ($showviewlink) {
                $o .= $this->output->box_start('boxaligncenter hidefull full_' . $classsuffix);
                $classes = 'expandsummaryicon contract_' . $classsuffix;
                $o .= $this->output->pix_icon('t/switch_minus',
                                              get_string('viewsummary', 'sesiontarea'),
                                              null,
                                              array('class'=>$classes));
                $o .= $submissionplugin->plugin->view($submissionplugin->submission);
                $o .= $this->output->box_end();
            }
        } else if ($submissionplugin->view == sesiontarea_submission_plugin_submission::FULL) {
            $o .= $this->output->box_start('boxaligncenter submissionfull');
            $o .= $submissionplugin->plugin->view($submissionplugin->submission);
            $o .= $this->output->box_end();
        }

        return $o;
    }

    /**
     * Render the grading table.
     *
     * @param sesiontarea_grading_table $table
     * @return string
     */
    public function render_sesiontarea_grading_table(sesiontarea_grading_table $table) {
        $o = '';
        $o .= $this->output->box_start('boxaligncenter gradingtable');

        $this->page->requires->js_init_call('M.mod_sesiontarea.init_grading_table', array());
        $this->page->requires->string_for_js('nousersselected', 'sesiontarea');
        $this->page->requires->string_for_js('batchoperationconfirmgrantextension', 'sesiontarea');
        $this->page->requires->string_for_js('batchoperationconfirmlock', 'sesiontarea');
        $this->page->requires->string_for_js('batchoperationconfirmreverttodraft', 'sesiontarea');
        $this->page->requires->string_for_js('batchoperationconfirmunlock', 'sesiontarea');
        $this->page->requires->string_for_js('batchoperationconfirmaddattempt', 'sesiontarea');
        $this->page->requires->string_for_js('editaction', 'sesiontarea');
        foreach ($table->plugingradingbatchoperations as $plugin => $operations) {
            foreach ($operations as $operation => $description) {
                $this->page->requires->string_for_js('batchoperationconfirm' . $operation,
                                                     'sesiontareafeedback_' . $plugin);
            }
        }
        $o .= $this->flexible_table($table, $table->get_rows_per_page(), true);
        $o .= $this->output->box_end();

        return $o;
    }

    /**
     * Render a feedback plugin feedback
     *
     * @param sesiontarea_feedback_plugin_feedback $feedbackplugin
     * @return string
     */
    public function render_sesiontarea_feedback_plugin_feedback(sesiontarea_feedback_plugin_feedback $feedbackplugin) {
        $o = '';

        if ($feedbackplugin->view == sesiontarea_feedback_plugin_feedback::SUMMARY) {
            $showviewlink = false;
            $summary = $feedbackplugin->plugin->view_summary($feedbackplugin->grade, $showviewlink);

            $classsuffix = $feedbackplugin->plugin->get_subtype() .
                           '_' .
                           $feedbackplugin->plugin->get_type() .
                           '_' .
                           $feedbackplugin->grade->id;
            $o .= $this->output->box_start('boxaligncenter plugincontentsummary summary_' . $classsuffix);

            $link = '';
            if ($showviewlink) {
                $previewstr = get_string('viewfeedback', 'sesiontarea');
                $icon = $this->output->pix_icon('t/preview', $previewstr);

                $expandstr = get_string('viewfull', 'sesiontarea');
                $options = array('class'=>'expandsummaryicon expand_' . $classsuffix);
                $o .= $this->output->pix_icon('t/switch_plus', $expandstr, null, $options);

                $jsparams = array($feedbackplugin->plugin->get_subtype(),
                                  $feedbackplugin->plugin->get_type(),
                                  $feedbackplugin->grade->id);
                $this->page->requires->js_init_call('M.mod_sesiontarea.init_plugin_summary', $jsparams);

                $urlparams = array('id' => $feedbackplugin->coursemoduleid,
                                   'gid'=>$feedbackplugin->grade->id,
                                   'plugin'=>$feedbackplugin->plugin->get_type(),
                                   'action'=>'viewplugin' . $feedbackplugin->plugin->get_subtype(),
                                   'returnaction'=>$feedbackplugin->returnaction,
                                   'returnparams'=>http_build_query($feedbackplugin->returnparams));
                $url = new moodle_url('/mod/sesiontarea/view.php', $urlparams);
                $link .= '<noscript>';
                $link .= $this->output->action_link($url, $icon);
                $link .= '</noscript>';

                $link .= $this->output->spacer(array('width'=>15));
            }

            $o .= $link . $summary;
            $o .= $this->output->box_end();
            if ($showviewlink) {
                $o .= $this->output->box_start('boxaligncenter hidefull full_' . $classsuffix);
                $classes = 'expandsummaryicon contract_' . $classsuffix;
                $o .= $this->output->pix_icon('t/switch_minus',
                                              get_string('viewsummary', 'sesiontarea'),
                                              null,
                                              array('class'=>$classes));
                $o .= $feedbackplugin->plugin->view($feedbackplugin->grade);
                $o .= $this->output->box_end();
            }
        } else if ($feedbackplugin->view == sesiontarea_feedback_plugin_feedback::FULL) {
            $o .= $this->output->box_start('boxaligncenter feedbackfull');
            $o .= $feedbackplugin->plugin->view($feedbackplugin->grade);
            $o .= $this->output->box_end();
        }

        return $o;
    }

    /**
     * Render a course index summary
     *
     * @param sesiontarea_course_index_summary $indexsummary
     * @return string
     */
    public function render_sesiontarea_course_index_summary(sesiontarea_course_index_summary $indexsummary) {
        $o = '';

        $strplural = get_string('modulenameplural', 'sesiontarea');
        $strsectionname  = $indexsummary->courseformatname;
        $strduedate = get_string('duedate', 'sesiontarea');
        $strsubmission = get_string('submission', 'sesiontarea');
        $strgrade = get_string('grade');

        $table = new html_table();
        if ($indexsummary->usesections) {
            $table->head  = array ($strsectionname, $strplural, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right', 'right');
        } else {
            $table->head  = array ($strplural, $strduedate, $strsubmission, $strgrade);
            $table->align = array ('left', 'left', 'center', 'right');
        }
        $table->data = array();

        $currentsection = '';
        foreach ($indexsummary->sesiontareaments as $info) {
            $params = array('id' => $info['cmid']);
            $link = html_writer::link(new moodle_url('/mod/sesiontarea/view.php', $params),
                                      $info['cmname']);
            $due = $info['timedue'] ? userdate($info['timedue']) : '-';

            $printsection = '';
            if ($indexsummary->usesections) {
                if ($info['sectionname'] !== $currentsection) {
                    if ($info['sectionname']) {
                        $printsection = $info['sectionname'];
                    }
                    if ($currentsection !== '') {
                        $table->data[] = 'hr';
                    }
                    $currentsection = $info['sectionname'];
                }
            }

            if ($indexsummary->usesections) {
                $row = array($printsection, $link, $due, $info['submissioninfo'], $info['gradeinfo']);
            } else {
                $row = array($link, $due, $info['submissioninfo'], $info['gradeinfo']);
            }
            $table->data[] = $row;
        }

        $o .= html_writer::table($table);

        return $o;
    }



    /**
     * Internal function - creates htmls structure suitable for YUI tree.
     *
     * @param sesiontarea_files $tree
     * @param array $dir
     * @return string
     */
    protected function htmllize_tree(sesiontarea_files $tree, $dir) {
        global $CFG;
        $yuiconfig = array();
        $yuiconfig['type'] = 'html';

        if (empty($dir['subdirs']) and empty($dir['files'])) {
            return '';
        }

        $result = '<ul>';
        foreach ($dir['subdirs'] as $subdir) {
            $image = $this->output->pix_icon(file_folder_icon(),
                                             $subdir['dirname'],
                                             'moodle',
                                             array('class'=>'icon'));
            $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'>' .
                       '<div>' . $image . ' ' . s($subdir['dirname']) . '</div> ' .
                       $this->htmllize_tree($tree, $subdir) .
                       '</li>';
        }

        foreach ($dir['files'] as $file) {
            $filename = $file->get_filename();
            if ($CFG->enableplagiarism) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $plagiarismlinks = plagiarism_get_links(array('userid'=>$file->get_userid(),
                                                             'file'=>$file,
                                                             'cmid'=>$tree->cm->id,
                                                             'course'=>$tree->course));
            } else {
                $plagiarismlinks = '';
            }
            $image = $this->output->pix_icon(file_file_icon($file),
                                             $filename,
                                             'moodle',
                                             array('class'=>'icon'));
            $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'>' .
                       '<div>' . $image . ' ' .
                                 $file->fileurl . ' ' .
                                 $plagiarismlinks .
                                 $file->portfoliobutton . '</div>' .
                       '</li>';
        }

        $result .= '</ul>';

        return $result;
    }

    /**
     * Helper method dealing with the fact we can not just fetch the output of flexible_table
     *
     * @param flexible_table $table The table to render
     * @param int $rowsperpage How many sesiontareaments to render in a page
     * @param bool $displaylinks - Whether to render links in the table
     *                             (e.g. downloads would not enable this)
     * @return string HTML
     */
    protected function flexible_table(flexible_table $table, $rowsperpage, $displaylinks) {

        $o = '';
        ob_start();
        $table->out($rowsperpage, $displaylinks);
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }

    /**
     * Helper method dealing with the fact we can not just fetch the output of moodleforms
     *
     * @param moodleform $mform
     * @return string HTML
     */
    protected function moodleform(moodleform $mform) {

        $o = '';
        ob_start();
        $mform->display();
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }

}

