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
 * @package   mod_practicalab
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot . '/mod/practicalab/locallib.php');

/**
 * Assignment grading options form
 *
 * @package   mod_practicalab
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_practicalab_grading_batch_operations_form extends moodleform {
    /**
     * Define this form - called by the parent constructor.
     */
    public function definition() {
        $mform = $this->_form;
        $instance = $this->_customdata;

        // Visible elements.
        $options = array();
        $options['lock'] = get_string('locksubmissions', 'practicalab');
        $options['unlock'] = get_string('unlocksubmissions', 'practicalab');
        if ($instance['submissiondrafts']) {
            $options['reverttodraft'] = get_string('reverttodraft', 'practicalab');
        }
        if ($instance['duedate']) {
            $options['grantextension'] = get_string('grantextension', 'practicalab');
        }
        if ($instance['attemptreopenmethod'] == PRACTICALAB_ATTEMPT_REOPEN_METHOD_MANUAL) {
            $options['addattempt'] = get_string('addattempt', 'practicalab');
        }

        foreach ($instance['feedbackplugins'] as $plugin) {
            if ($plugin->is_visible() && $plugin->is_enabled()) {
                foreach ($plugin->get_grading_batch_operations() as $action => $description) {
                    $operationkey = 'plugingradingbatchoperation_' . $plugin->get_type() . '_' . $action;
                    $options[$operationkey] = $description;
                }
            }
        }

        $mform->addElement('hidden', 'action', 'gradingbatchoperation');
        $mform->setType('action', PARAM_ALPHA);
        $mform->addElement('hidden', 'id', $instance['cm']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'selectedusers', '', array('class'=>'selectedusers'));
        $mform->setType('selectedusers', PARAM_SEQUENCE);
        $mform->addElement('hidden', 'returnaction', 'grading');
        $mform->setType('returnaction', PARAM_ALPHA);

        $objs = array();
        $objs[] =& $mform->createElement('select', 'operation', get_string('chooseoperation', 'practicalab'), $options);
        $objs[] =& $mform->createElement('submit', 'submit', get_string('go'));
        $batchdescription = get_string('batchoperationsdescription', 'practicalab');
        $mform->addElement('group', 'actionsgrp', $batchdescription, $objs, ' ', false);
    }
}

