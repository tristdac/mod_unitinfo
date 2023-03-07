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
 * View page.
 *
 * @package    mod_unitinfo
 * @copyright  2023 Edinburgh College
 * @author     Tristan daCosta <tristan.dacosta@edinburghcollege.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$id = optional_param('id',0,PARAM_INT);    // Course Module ID, or
$l = optional_param('l',0,PARAM_INT);     // unitinfo ID

if ($id) {
    $PAGE->set_url('/mod/unitinfo/view.php', array('id' => $id));
    if (! $cm = get_coursemodule_from_id('unitinfo', $id, 0, true)) {
        throw new \moodle_exception('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        throw new \moodle_exception('coursemisconf');
    }

    if (! $unitinfo = $DB->get_record("unitinfo", array("id"=>$cm->instance))) {
        throw new \moodle_exception('invalidcoursemodule');
    }

} else {
    $PAGE->set_url('/mod/unitinfo/view.php', array('l' => $l));
    if (! $unitinfo = $DB->get_record("unitinfo", array("id"=>$l))) {
        throw new \moodle_exception('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id"=>$unitinfo->course)) ){
        throw new \moodle_exception('coursemisconf');
    }
    if (! $cm = get_coursemodule_from_instance("unitinfo", $unitinfo->id, $course->id, true)) {
        throw new \moodle_exception('invalidcoursemodule');
    }
}

require_login($course, true, $cm);

$url = course_get_url($course, $cm->sectionnum, []);
$url->set_anchor('module-' . $id);
redirect($url);
