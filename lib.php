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
 * Lib.
 *
 * @package    mod_unitinfo
 * @copyright  2023 Edinburgh College
 * @author     Tristan daCosta <tristan.dacosta@edinburghcollege.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use core_course\external\course_summary_exporter;

/**
 * Whether the module supportes a certain feature.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return bool|null True if module supports feature, false if not, null if doesn't know.
 */
function mod_unitinfo_supports($feature) {
    switch($feature) {
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_COMPLETION_HAS_RULES:
            return false;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_MODEDIT_DEFAULT_COMPLETION:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_SHOW_DESCRIPTION:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return false;
        case FEATURE_IDNUMBER:
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_NO_VIEW_LINK:
            return true;

        case FEATURE_MOD_ARCHETYPE:
            return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_INTERFACE;
        default:
            return null;
    }
}

/**
 * Add instance.
 *
 * @param stdClass $instance The instance.
 * @param object $mform The form.
 * @return int
 */
function unitinfo_add_instance($instance, $mform) {
    global $DB, $COURSE;

    $cmid = $instance->coursemodule;
    $hb_draftitemid = $instance->schedule;
    unset($instance->schedule);
    // print_r($mform);
    $instance->name = 'Unit Info';
    if (strpos($COURSE->shortname, '/') !== false) {
        $instance->unit = $COURSE->shortname;
    }
    $id = $DB->insert_record('unitinfo', $instance);

    

    // Save the files.
    if (!empty($hb_draftitemid)) {
        $fs = get_file_storage();
        $context = context_module::instance($cmid);
        $options = mod_unitinfo_filemanager_options();
        
        file_save_draft_area_files($hb_draftitemid, $context->id, 'mod_unitinfo', 'schedule', 0, $options);
    }

    //Has user opted to rename the topic this module lives in?
    if ($instance->renametopic == 1) {
        renameTopic($instance);
    }
    // print_r($instance);
    return $id;
}

/**
 * Update instance.
 *
 * @param stdClass $instance The instance.
 * @param object $mform The form.
 * @return bool
 */
function unitinfo_update_instance($instance, $mform) {
    global $DB;
// print_r($instance);
    $id = $instance->instance;
    $cmid = $instance->coursemodule;
    $hb_draftitemid = $instance->schedule;

    unset($instance->schedule);

    $instance->id = $id;
    $instance->name = 'Unit Info';
    $success = $DB->update_record('unitinfo', $instance);
    if (!$success) {
        return false;
    }

    // Save the files.
    if ($success && !empty($hb_draftitemid)) {
        $fs = get_file_storage();
        $context = context_module::instance($cmid);
        $options = mod_unitinfo_filemanager_options();
        file_save_draft_area_files($hb_draftitemid, $context->id, 'mod_unitinfo', 'schedule', 0, $options);
    }

    //Has user opted to rename the topic this module lives in?
    if ($instance->renametopic == 1) {
        renameTopic($instance);
    }

    return $success;
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function unitinfo_reset_userdata($data) {

    // Any changes to the list of dates that needs to be rolled should be same during course restore and course reset.
    // See MDL-9367.

    return array();
}


/**
 * Delete instance.
 *
 * @param int $id The ID.
 * @return bool
 */
function unitinfo_delete_instance($id) {
    global $DB;

    // Note that all context files are deleted by core.
    $DB->delete_records('unitinfo', ['id' => $id]);

    return true;
}

/**
 * Cache course module info for course page display.
 *
 * @param stdClass $cm The CM record.
 * @return cached_cm_info Cached information.
 */
function unitinfo_get_coursemodule_info($cm) {
    global $DB;

    $params = ['id' => $cm->instance];
    if (!$record = $DB->get_record('unitinfo', $params, 'id, name, intro, introformat, unit')) {
        return false;
    }

    $context = context_module::instance($cm->id);

    $result = new cached_cm_info();
    $result->name = $record->name;
    $result->customdata = new stdClass();
    $result->customdata->intro = $record->intro;
    $result->customdata->introformat = $record->introformat;
    $result->customdata->unit = $record->unit;

    
    // Find the files, and store their details.
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_unitinfo', 'schedule', 0, '', false);
    $file = reset($files);
    if (!empty($file)) {
        $result->customdata->schedule = (object) [
            'filename' => $file->get_filename(),
            'timemodified' => $file->get_timemodified()
        ];
    }

    return $result;
}

/**
 * Sets the special flag to display on course page.
 *
 * @param cm_info $cm Course-module object
 */
function unitinfo_cm_info_view(cm_info $cm) {
    $cm->set_custom_cmlist_item(true);
}

/**
 * Content to display on the course page.
 *
 * @param cm_info $cm The CM info.
 */
function mod_unitinfo_cm_info_view(cm_info $cm) {
    global $PAGE;

    if (!$cm->uservisible) {
        return;
    }

    $renderer = $PAGE->get_renderer('mod_unitinfo');
    $cm->set_content($renderer->display_content($cm), true);
}

/**
 * File serving function.
 *
 * @param object $course The course.
 * @param object $cm The course module.
 * @param context $context The context.
 * @param string $filearea The file area.
 * @param array $args The arguments.
 * @param bool $forcedownload Whether to force the download.
 * @param array $options The options.
 * @return bool|void
 */
function mod_unitinfo_pluginfile($course, $cm, context $context, $filearea, array $args, $forcedownload, array $options = []) {
    global $CFG, $DB;
    require_once("$CFG->libdir/resourcelib.php");

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);
    if (!has_capability('mod/unitinfo:view', $context)) {
        return false;
    }

    if ($filearea !== 'schedule') {
        // intro is handled automatically in pluginfile.php
        return false;
    }

    array_shift($args); // ignore revision - designed to prevent caching problems only

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = rtrim("/$context->id/mod_unitinfo/$filearea/0/$relativepath", '/');
    do {
        if (!$file = $fs->get_file_by_hash(sha1($fullpath))) {
            if ($fs->get_file_by_hash(sha1("$fullpath/."))) {
                if ($file = $fs->get_file_by_hash(sha1("$fullpath/index.htm"))) {
                    break;
                }
                if ($file = $fs->get_file_by_hash(sha1("$fullpath/index.html"))) {
                    break;
                }
                if ($file = $fs->get_file_by_hash(sha1("$fullpath/Default.htm"))) {
                    break;
                }
            }
        }
    } while (false);

    

    // finally send the file
    send_stored_file($file, null, $filter, $forcedownload, $options);
}

/**
 * Get the file manager options.
 *
 * @return array
 */
function mod_unitinfo_filemanager_options() {
    return ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['application/pdf']];
}


function get_all_course_groups($selectedunit) {
    global $COURSE, $DB;
    $groupobjects = groups_get_all_groups($COURSE->id);
    $groups = array();
    foreach ($groupobjects as $groupobject) {
        if ($groupobject->name != $COURSE->fullname) {
            $groupobject->name_s = strtok($groupobject->name, '/');
            $groups[$groupobject->name] = $groupobject->name_s;
            if ($DB->record_exists('course', array('fullname'=>$groupobject->name))) {
                $groups[$groupobject->name] = $groupobject->name_s.' *';
            }
            if ($groupobject->name == $selectedunit) {
                continue;
            }
            if ($DB->record_exists('unitinfo', array('course'=>$COURSE->id, 'unit'=>$groupobject->name))) {
                unset($groups[$groupobject->name]);
            }
        }
    }
    // print_r($groupobject);
    return $groups;
}

function strip_unitcode($unit) {
    $last_word_start = strrpos($unit, ' ') + 1;
    $last_word = substr($unit, $last_word_start);
    $code = strtok($last_word, '/');
    return $code;
}


function renameTopic($instance) {
    global $DB;
    $topic = $DB->get_record('course_sections', array('course'=>$instance->course,'section'=>$instance->section));
    $topic->name = strtok($instance->unit, '/');
    $DB->update_record('course_sections', $topic);
}

function get_unit_teachers($unit) {
    global $DB;

    $teachers = array();
    $teachers_enrol = $DB->get_records('enrol_collegedb_teachunits', array('unitfullname'=>$unit));
    if ($teachers_enrol) {
        foreach ($teachers_enrol as $teacher) {
            $record = $DB->get_record('user', array('email'=>$teacher->userid));
            $o365record = $DB->get_record('local_o365_objects', array('moodleid'=>$teacher->userid,'type'=>'user'));
            $teachers[] = array('id'=>$record->id, 'teachername'=>$record->firstname.' '.$record->lastname, 'email'=>$record->email, 'o365id'=>$$o365record->objectid);
        }
        return $teachers;
    } else {
        return false;
    }
}

function find_unit($unit) {
    global $DB;
    $course = $DB->get_record('course', array('fullname'=>$unit));
    
    if ($course) {
        return $course->id;
    }
}