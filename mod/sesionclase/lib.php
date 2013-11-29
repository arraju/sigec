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
 * Library of interface functions and constants for module sesionclase
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the sesionclase specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod
 * @subpackage sesionclase
 * @copyright  2011 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/itse/lib.php');

/** example constant */
//define('NEWMODULE_ULTIMATE_ANSWER', 42);

////////////////////////////////////////////////////////////////////////////////
// Moodle core API                                                            //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function sesionclase_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:                        
            return null;
    }
}

/**
 * Saves a new instance of the sesionclase into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $sesionclase An object from the form in mod_form.php
 * @param mod_sesionclase_mod_form $mform
 * @return int The id of the newly inserted sesionclase record
 */
function sesionclase_add_instance(stdClass $sesionclase, mod_sesionclase_mod_form $mform = null) {
    global $DB;

    $sesionclase->observacionesformat = FORMAT_HTML;
    $sesionclase->timecreated = time();
    $id = $DB->insert_record('sesionclase', $sesionclase);

    $grupos = groups_get_all_groups($sesionclase->course);
    foreach ($grupos as $grupo)
    {
        $programacion = new stdClass();
        $programacion->course = $sesionclase->course;
        $programacion->modulename = 'sesionclase';
        $programacion->sesionclaseid = $id;
        $programacion->groupid = $grupo->id;
        $fprogramada = "fprogramada$grupo->id";
        $programacion->fprogramada = $sesionclase->$fprogramada;
        $hprogramada = "hprogramada$grupo->id";
        $programacion->hprogramada = $sesionclase->$hprogramada;
        $DB->insert_record('sesionclase_programacion', $programacion);
    }

    # You may have to add extra stuff in here #
    return $id;
}

/**
 * Updates an instance of the sesionclase in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $sesionclase An object from the form in mod_form.php
 * @param mod_sesionclase_mod_form $mform
 * @return boolean Success/Fail
 */
function sesionclase_update_instance(stdClass $sesionclase, mod_sesionclase_mod_form $mform = null) {
    global $DB;
    
    $sesion = new SesionClase($sesionclase->instance, $sesionclase->modulename, $sesionclase->coursemodule);

    $sesionclase->observacionesformat = FORMAT_HTML;
    $sesionclase->id = $sesionclase->instance;
    $sesionclase->timemodified = time();

    $id = $DB->update_record('sesionclase', $sesionclase);        

    $grupos = groups_get_all_groups($sesionclase->course);
    foreach ($grupos as $grupo)
    {
        $programacion = $sesion->programacion($grupo->id);
        if($programacion != null)
        {
            $fprogramada = "fprogramada$grupo->id";
            $programacion->fprogramada = $sesionclase->$fprogramada;
            $hprogramada = "hprogramada$grupo->id";
            $programacion->hprogramada = $sesionclase->$hprogramada;
            $DB->update_record('sesionclase_programacion', $programacion);
        }
    }
    
    # You may have to add extra stuff in here #
    return $id;
}

/**
 * Removes an instance of the sesionclase from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function sesionclase_delete_instance($id) {
    global $DB;

    if (! $sesionclase = $DB->get_record('sesionclase', array('id' => $id))) 
    {
        return false;
    }

    # Delete any dependent records here #

    $DB->delete_records('sesionclase', array('id' => $sesionclase->id));

    $grupos = groups_get_all_groups($sesionclase->course);
    foreach ($grupos as $grupo)
    {
        $condion = array('course' => $sesionclase->course, 
            'modulename' => 'sesionclase',
            'sesionclaseid' => $sesionclase->id,
            'groupid' => $grupo->id);
        if ($DB->record_exists('sesionclase_programacion', $condion))
        {
            $registro = $DB->get_record('sesionclase_programacion', $condion);
            $DB->delete_records('sesionclase_programacion', array('id' => $registro->id));
        }
    }
    
    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return stdClass|null
 */
function sesionclase_user_outline($course, $user, $mod, $sesionclase) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $sesionclase the module instance record
 * @return void, is supposed to echp directly
 */
function sesionclase_user_complete($course, $user, $mod, $sesionclase) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in sesionclase activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 */
function sesionclase_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link sesionclase_print_recent_mod_activity()}.
 *
 * @param array $activities sequentially indexed array of objects with the 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 * @return void adds items into $activities and increases $index
 */
function sesionclase_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@see sesionclase_get_recent_mod_activity()}

 * @return void
 */
function sesionclase_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function sesionclase_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * @example return array('moodle/site:accessallgroups');
 * @return array
 */
function sesionclase_get_extra_capabilities() {
    return array();
}

////////////////////////////////////////////////////////////////////////////////
// Gradebook API                                                              //
////////////////////////////////////////////////////////////////////////////////

/**
 * Is a given scale used by the instance of sesionclase?
 *
 * This function returns if a scale is being used by one sesionclase
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $sesionclaseid ID of an instance of this module
 * @return bool true if the scale is used by the given sesionclase instance
 */
function sesionclase_scale_used($sesionclaseid, $scaleid) {
    global $DB;

    /** @example */
    if ($scaleid and $DB->record_exists('sesionclase', array('id' => $sesionclaseid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of sesionclase.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param $scaleid int
 * @return boolean true if the scale is used by any sesionclase instance
 */
function sesionclase_scale_used_anywhere($scaleid) {
    global $DB;

    /** @example */
    if ($scaleid and $DB->record_exists('sesionclase', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the give sesionclase instance
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 *
 * @param stdClass $sesionclase instance object with extra cmidnumber and modname property
 * @return void
 */
function sesionclase_grade_item_update(stdClass $sesionclase) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    /** @example */
    $item = array();
    $item['itemname'] = clean_param($sesionclase->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;
    $item['grademax']  = $sesionclase->grade;
    $item['grademin']  = 0;

    grade_update('mod/sesionclase', $sesionclase->course, 'mod', 'sesionclase', $sesionclase->id, 0, null, $item);
}

/**
 * Update sesionclase grades in the gradebook
 *
 * Needed by grade_update_mod_grades() in lib/gradelib.php
 *
 * @param stdClass $sesionclase instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 * @return void
 */
function sesionclase_update_grades(stdClass $sesionclase, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    /** @example */
    $grades = array(); // populate array of grade objects indexed by userid

    grade_update('mod/sesionclase', $sesionclase->course, 'mod', 'sesionclase', $sesionclase->id, 0, $grades);
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function sesionclase_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for sesionclase file areas
 *
 * @package mod_sesionclase
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function sesionclase_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the sesionclase file areas
 *
 * @package mod_sesionclase
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the sesionclase's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function sesionclase_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

////////////////////////////////////////////////////////////////////////////////
// Navigation API                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding sesionclase nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the sesionclase module instance
 * @param stdClass $course
 * @param stdClass $module
 * @param cm_info $cm
 */
function sesionclase_extend_navigation(navigation_node $navref, stdclass $course, stdclass $module, cm_info $cm) {
}

/**
 * Extends the settings navigation with the sesionclase settings
 *
 * This function is called when the context for the page is a sesionclase module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $sesionclasenode {@link navigation_node}
 */
function sesionclase_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $navref=null) 
{
    $nodo = $navref->get('modedit');
    if($nodo != null)
        $nodo->remove();    
}
