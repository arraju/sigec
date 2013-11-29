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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package   mod_asistencias
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');


require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot . '/mod/asistencias/locallib.php');

/**
 * Assignment grading options form
 *
 * @package   mod_asistencias
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_asistencias_grading_options_form extends moodleform {
    /**
     * Define this form - called from the parent constructor.
     */
    public function definition() {
        $mform = $this->_form;
        $instance = $this->_customdata;
        $dirtyclass = array('class'=>'ignoredirty');

        $mform->addElement('header', 'general', get_string('gradingoptions', 'asistencias'));
        // Visible elements.
        $options = array(-1=>get_string('all'), 10=>'10', 20=>'20', 50=>'50', 100=>'100');
        $mform->addElement('select', 'perpage', get_string('asistenciasmentsperpage', 'asistencias'), $options, $dirtyclass);
        $options = array('' => get_string('filternone', 'asistencias'),
                         ASISTENCIAS_FILTER_SUBMITTED => get_string('filtersubmitted', 'asistencias'),
                         ASISTENCIAS_FILTER_REQUIRE_GRADING => get_string('filterrequiregrading', 'asistencias'));
        if ($instance['submissionsenabled']) {
            $mform->addElement('select', 'filter', get_string('filter', 'asistencias'), $options, $dirtyclass);
        }

        // Quickgrading.
        if ($instance['showquickgrading']) {
            $mform->addElement('checkbox', 'quickgrading', get_string('quickgrading', 'asistencias'), '', $dirtyclass);
            $mform->addHelpButton('quickgrading', 'quickgrading', 'asistencias');
            $mform->setDefault('quickgrading', $instance['quickgrading']);
        }

        // Hidden params.
        $mform->addElement('hidden', 'contextid', $instance['contextid']);
        $mform->setType('contextid', PARAM_INT);
        $mform->addElement('hidden', 'id', $instance['cm']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'userid', $instance['userid']);
        $mform->setType('userid', PARAM_INT);
        $mform->addElement('hidden', 'action', 'saveoptions');
        $mform->setType('action', PARAM_ALPHA);

        // Buttons.
        $this->add_action_buttons(false, get_string('updatetable', 'asistencias'));
    }
}
