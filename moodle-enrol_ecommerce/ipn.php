<?php
//  This file is part of Moodle - http:// moodle.org/
//
//  Moodle is free software: you can redistribute it and/or modify
//  it under the terms of the GNU General Public License as published by
//  the Free Software Foundation, either version 3 of the License, or
//  (at your option) any later version.
//
//  Moodle is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU General Public License for more details.
//
//  You should have received a copy of the GNU General Public License
//  along with Moodle.  If not, see <http:// www.gnu.org/licenses/>.

/**
 * Elightenment ecommerce enrolment plugin.
 *
 * This plugin allows you to set up a course shop and shopping cart
 *
 * @package    enrol_ecommerce
 * @copyright  2015 Gary McKnight
 * @license    http:// www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);

require("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/filelib.php');

set_exception_handler('enrol_ecommerce_ipn_exception_handler');

$cdata = get_courses();

// To get the variable info back from paypal, you need to send it and get it back as 'custom.'
// This has been set up so that the last variable in the array is the user ID and the rest are the course IDs.
// I relaize that this is stupid, I am sorry.
$custom = json_decode(base64_decode($_POST['custom']));
$courses = array();
for ($x = 0; $x < (sizeof($custom) - 1); $x++){
    array_push($courses, $custom[$x]);
}
$uid = $custom[count($custom) - 1];
$creg = array();
$x = 0;

foreach ($cdata as $c){
    if (in_array($c->id, $courses)){
        $creg[$x] = $c;
        $x++;
    }
}

if (! $userfile = $DB->get_record('user', array('id' => $uid))) {
    exit("User $uid doesn't exist");
}

// Get the enrolid and the student's role ID from the database.
// If there are multiple matching entries somehow, the latest will be selected.
$sqlget = $DB->get_record_sql('SELECT id FROM {enrol} WHERE cost > 0', array(1));
foreach ($sqlget as $val){
    $enrolid = $val;
}
$sql = 'SELECT roleid FROM {enrol} WHERE id = '.$enrolid;
$sqlget = $DB->get_record_sql($sql, array(1));
foreach ($sqlget as $val){
    $roleid = $val;
}

// check all the courses to make sure they exist
foreach ($courses as $courseID){
    if (!$DB->get_record("course", array("id"=>$courseID))) {
        exit("Not a valid course id");
    }
}

// loop through every course and register the user
$enrolname = 'ecommerce';
$enrol = enrol_get_plugin($enrolname);

foreach ($creg as $course){
    $enrolinstances = enrol_get_instances($course->id, true);
    foreach ($enrolinstances as $courseenrolinstance) {
        if ($courseenrolinstance->enrol == $enrolname) {
            $instance = $courseenrolinstance;
            break;
        }
    }
    if (empty($instance)) {
        $enrolid = $enrol->add_default_instance($course);
        $instance = $DB->get_record('enrol', array('id' => $enrolid), '*', MUST_EXIST);
    }
    $enrol->enrol_user($instance, $userfile->id, $roleid);
}
