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
 * Activity event reminder handler.
 *
 * @package    local_reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/local/reminders/reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/activity_handlers.class.php');

/**
 * Class to specify the reminder message object for due events.
 *
 * @package    local_reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class due_reminder extends course_reminder {

    /**
     * @var object
     */
    private $coursemodule;
    /**
     * @var object
     */
    private $cm;
    /**
     * Activity reference.
     *
     * @var object
     */
    private $activityobj;
    /**
     * Activity name.
     * @var string
     */
    private $modname;

    /**
     * Creates new activity reminder instance.
     *
     * @param object $event calendar event.
     * @param object $course course instance.
     * @param object $cm coursemodulecontext instance.
     * @param object $coursemodule course module.
     * @param integer $aheaddays ahead days in number.
     */
    public function __construct($event, $course, $cm, $coursemodule, $aheaddays = 1) {
        parent::__construct($event, $course, $aheaddays);
        $this->cm = $cm;
        $this->coursemodule = $coursemodule;
    }

    /**
     * Set activity instance if there is any.
     *
     * @param string $modulename module name.
     * @param object $activity activity instance
     */
    public function set_activity($modulename, $activity) {
        $this->activityobj = $activity;
        $this->modname = $modulename;
    }

    /**
     * Cleanup this reminder instance.
     */
    public function cleanup() {
        parent::cleanup();

        if (isset($this->activityobj)) {
            unset($this->activityobj);
        }
    }

    /**
     * Filter out users who still does not have completed this activity.
     *
     * @param array $users user array to check.
     * @param string $type call type.
     * @return array array of filtered users.
     */
    public function filter_authorized_users($users, $type=null) {
        global $CFG;

        if (isset($CFG->local_reminders_noremindersforcompleted)
            && !$CFG->local_reminders_noremindersforcompleted) {
                return $users;
        }

        if (!empty($this->modname) && !empty($this->activityobj)) {
            $clsname = 'local_reminder_'.$this->modname.'_handler';
            if (class_exists($clsname)) {
                $handlercls = new $clsname;
                return $handlercls->filter_authorized_users($users, $type, $this->activityobj,
                    $this->course, $this->coursemodule, $this->cm);
            }
        }
        return $users;
    }

    /**
     * Generates a message content as a HTML for activities.
     *
     * @param object $user The user object
     * @param object $changetype change type (add/update/removed/overdue)
     * @return string Message content as HTML text.
     */
    public function get_message_html($user=null, $changetype=null) {
        $htmlmail = $this->get_html_header();
        $htmlmail .= html_writer::start_tag('body', array('id' => 'email'));
        $htmlmail .= html_writer::start_tag('div');
        $htmlmail .= html_writer::start_tag('table',
                array('cellspacing' => 0, 'cellpadding' => 8, 'style' => $this->tbodycssstyle));

        $contenttitle = '('.$this->course->fullname.') '.$this->event->name;
        if (!isemptystring($changetype)) {
            $contenttitle = "[$changetype]: $contenttitle";
        }
        $htmlmail .= html_writer::start_tag('tr');
        $htmlmail .= html_writer::start_tag('td', array('colspan' => 2));
        $htmlmail .= html_writer::link($this->generate_event_link(),
                html_writer::tag('h3', $contenttitle, array('style' => $this->titlestyle)),
                array('style' => 'text-decoration: none'));
        $htmlmail .= html_writer::end_tag('td').html_writer::end_tag('tr');

        if (!isemptystring($changetype) && $changetype == REMINDERS_CALL_TYPE_OVERDUE) {
            $htmlmail .= html_writer::start_tag('tr');
            $htmlmail .= html_writer::start_tag('td', array('colspan' => 2));
            $htmlmail .= html_writer::tag('h4', get_string('overduemessage', 'local_reminders'),
                array('style' => $this->overduestyle));
            $htmlmail .= html_writer::end_tag('td').html_writer::end_tag('tr');
        }

        $htmlmail .= $this->write_table_row(get_string('contentwhen', 'local_reminders'),
            format_event_time_duration($user, $this->event));
        $htmlmail .= $this->write_location_info($this->event);

        $htmlmail .= $this->write_table_row(get_string('contenttypecourse', 'local_reminders'), $this->course->fullname);

        $formattercls = null;
        if (!empty($this->modname) && !empty($this->activityobj)) {
            $clsname = 'local_reminder_'.$this->modname.'_handler';
            if (class_exists($clsname)) {
                $formattercls = new $clsname;
                $formattercls->append_info($htmlmail, $this->modname, $this->activityobj, $user, $this->event, $this);
            }
        }

        
        return $htmlmail.html_writer::end_tag('table').
            html_writer::end_tag('div').
            html_writer::end_tag('body').
            html_writer::end_tag('html');
    }

    /**
     * Generates a message content as a plain-text for activity.
     *
     * @param object $user The user object
     * @param object $changetype change type (add/update/removed)
     * @return string Message content as plain-text.
     */
    public function get_message_plaintext($user=null, $changetype=null) {
        $text  = $this->event->name.' '.get_string('contentwhen', 'local_reminders').': '.format_event_time_duration($user, $this->event);
		return $text;
    }

    /**
     * The name 'reminder_due'.
     *
     * @return string Message provider name
     */
    protected function get_message_provider() {
        return 'reminders_due';
    }

    /**
     * Generates a message title for the activity reminder.
     *
     * @param string $type type of message to be send (null=reminder cron)
     * @return string Message title as a plain-text.
     */
    public function get_message_title($type=null) {
        return '('.$this->course->fullname.') ';
    }

    /**
     * Adds activity id and name to header.
     *
     * @return array of new header.
     */
    public function get_custom_headers() {
        $headers = parent::get_custom_headers();

        $headers[] = 'X-Activity-Id: '.$this->cm->id;
        $headers[] = 'X-Activity-Name: '.$this->cm->get_context_name();

        return $headers;
    }

}
