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
 * Renderer.
 *
 * @package    mod_unitinfo
 * @copyright  2023 Edinburgh College
 * @author     Tristan daCosta <tristan.dacosta@edinburghcollege.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_unitinfo\output;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/unitinfo/lib.php');
require_once($CFG->dirroot . '/mod/courseinfo/lib.php');

use moodle_url;
use mod_unitinfo\admin_setting_resourcesstyles;

/**
 * Renderer.
 *
 * @package    mod_unitinfo
 * @copyright  2023 Edinburgh College
 * @author     Tristan daCosta <tristan.dacosta@edinburghcollege.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {

    /**
     * Render the content.
     *
     * @param stdClass $data The data to use when rendering.
     * @return string
     */
    public function display_content(\cm_info $cm) {
        global $COURSE, $CFG, $DB;
        
        $config = get_config('mod_unitinfo');

        $title = $cm->name;

        $url_timetable = get_config('mod_courseinfo', 'timetableurl');

        $url_library = get_config('mod_courseinfo', 'libraryurl');

        $filetypes = array('schedule');
        $files = array();
        // print_r($cm->customdata);
        foreach($filetypes as $filetype) {
            ${"url_$filetype"} = null;
        
            if (!empty($cm->customdata->$filetype)) {
                ${"timemodified_$filetype"} = (int) $cm->customdata->$filetype->timemodified;
                ${"url_$filetype"} = moodle_url::make_pluginfile_url(
                    $cm->context->id,
                    'mod_unitinfo',
                    $filetype,
                    0,
                    "/",
                    $cm->customdata->$filetype->filename);              
            } else {
                ${"url_$filetype"} = get_config('mod_unitinfo', 'scheduleurl');
            }
        }

        $context = \context_course::instance($COURSE->id);
        if (has_capability('mod/unitinfo:addinstance', $context)) {
            $canedit = true;
        } else {
            $canedit = false;
        }

        // if ($cm->customdata->unit) {
        //     $sgselected = true;
        //     $url_sg = $cm->customdata->unit;
        // } else {
        //     $sgselected = false;
        //     $url_sg = null;
        // }
        $framework = new \stdClass();
        $framework->fullname = false;
        $framework_record = new \stdClass();
        $framework_record->id = false;
        $framework_teach = false;
        $multipleframeworks = false;

        if (strpos($COURSE->shortname, '/') !== false) {
            $isunit = true;
            
            $unitcode = strip_unitcode($COURSE->fullname);
            
            if ($canedit == true) {
                $child_units = $DB->get_records_sql('SELECT customint1 FROM {enrol} WHERE courseid = ? AND enrol = "meta"', array($COURSE->id));
                if ($child_units) {
                    $framework_teach = get_all_frameworks_of_metalinked_units_when_parent($child_units);
                    $multipleframeworks = true;
                } else {
                    $multipleframeworks = false;
                    $framework = get_natural_framework($COURSE->shortname);
                    $framework_record = $DB->get_record('course',  array('id'=>$framework->id));
                    $framework_teach = false;
                }
            } else {
                $framework = get_natural_framework($COURSE->shortname);
                $framework_record = $DB->get_record('course',  array('id'=>$framework->id));
            }
            $unitlink = false;
        } else {
            $unitcode = strip_unitcode($cm->customdata->unit);            
            $isunit = false;
            $unitid = find_unit($cm->customdata->unit);
        }

        if (preg_match('/^[A-Za-z][A-Za-z\d][A-Za-z\d][A-Za-z\d]\d{2}$/', $unitcode)) {
            $issqaunit = true;
        } else {
            $issqaunit = false;
        }

        $section = $cm->customdata->section;
        $teachers = get_unit_teachers($cm->customdata->unit);
        // print_r($teachers);
        $data = [
            'title' => $title,
            'content' => format_module_intro('unitinfo', $cm->customdata, $cm->id),
            'course' => strtoupper($COURSE->fullname),
            'cmid'    => $cm->id,
            'canedit' => $canedit,
            'isunit' => $isunit,
            'wwwroot' => $CFG->wwwroot,
            'unitcode' => $unitcode,
            'sqadescurl' => $config->sqadescurl,
            'issqaunit' => $issqaunit,
            'framework' => $framework->fullname,
            'framework_cid' => $framework_record->id,
            'framework_teach' => $framework_teach,
            'multipleframeworks'=>$multipleframeworks,
            'scheduleurl' => $url_schedule,
            'unitid' => $unitid,
            'teachers' => $teachers,
            'unitname' => strtok($cm->customdata->unit, '/'),
            'section' => $section
        ];
// print_r($data);
        return $this->render_from_template('mod_unitinfo/content', $data);
    }

}
