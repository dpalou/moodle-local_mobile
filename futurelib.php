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
 * Backported functions that in a future exists.
 *
 * @package    local_mobile
 * @copyright  2014 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->dirroot/user/lib.php");

if (!class_exists("core_user")) {

    /**
     * User class to access user details.
     *
     * @todo       move api's from user/lib.php and depreciate old ones.
     * @package    core
     * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since 2.6
     */
    class core_user {
        /**
         * No reply user id.
         */
        const NOREPLY_USER = -10;

        /**
         * Support user id.
         */
        const SUPPORT_USER = -20;

        /** @var stdClass keep record of noreply user */
        public static $noreplyuser = false;

        /** @var stdClass keep record of support user */
        public static $supportuser = false;

        /**
         * Return user object from db or create noreply or support user,
         * if userid matches corse_user::NOREPLY_USER or corse_user::SUPPORT_USER
         * respectively. If userid is not found, then return false.
         *
         * @param int $userid user id
         * @param string $fields A comma separated list of user fields to be returned, support and noreply user
         *                       will not be filtered by this.
         * @param int $strictness IGNORE_MISSING means compatible mode, false returned if user not found, debug message if more found;
         *                        IGNORE_MULTIPLE means return first user, ignore multiple user records found(not recommended);
         *                        MUST_EXIST means throw an exception if no user record or multiple records found.
         * @return stdClass|bool user record if found, else false.
         * @throws dml_exception if user record not found and respective $strictness is set.
         */
        public static function get_user($userid, $fields = '*', $strictness = IGNORE_MISSING) {
            global $DB;

            // If noreply user then create fake record and return.
            switch ($userid) {
                case self::NOREPLY_USER:
                    return self::get_noreply_user($strictness);
                    break;
                case self::SUPPORT_USER:
                    return self::get_support_user($strictness);
                    break;
                default:
                    return $DB->get_record('user', array('id' => $userid), $fields, $strictness);
            }
        }


        /**
         * Return user object from db based on their username.
         *
         * @param string $username The username of the user searched.
         * @param string $fields A comma separated list of user fields to be returned, support and noreply user.
         * @param int $mnethostid The id of the remote host.
         * @param int $strictness IGNORE_MISSING means compatible mode, false returned if user not found, debug message if more found;
         *                        IGNORE_MULTIPLE means return first user, ignore multiple user records found(not recommended);
         *                        MUST_EXIST means throw an exception if no user record or multiple records found.
         * @return stdClass|bool user record if found, else false.
         * @throws dml_exception if user record not found and respective $strictness is set.
         */
        public static function get_user_by_username($username, $fields = '*', $mnethostid = null, $strictness = IGNORE_MISSING) {
            global $DB, $CFG;

            // Because we use the username as the search criteria, we must also restrict our search based on mnet host.
            if (empty($mnethostid)) {
                // If empty, we restrict to local users.
                $mnethostid = $CFG->mnet_localhost_id;
            }

            return $DB->get_record('user', array('username' => $username, 'mnethostid' => $mnethostid), $fields, $strictness);
        }

        /**
         * Helper function to return dummy noreply user record.
         *
         * @return stdClass
         */
        protected static function get_dummy_user_record() {
            global $CFG;

            $dummyuser = new stdClass();
            $dummyuser->id = self::NOREPLY_USER;
            $dummyuser->email = $CFG->noreplyaddress;
            $dummyuser->firstname = get_string('noreplyname');
            $dummyuser->username = 'noreply';
            $dummyuser->lastname = '';
            $dummyuser->confirmed = 1;
            $dummyuser->suspended = 0;
            $dummyuser->deleted = 0;
            $dummyuser->picture = 0;
            $dummyuser->auth = 'manual';
            $dummyuser->firstnamephonetic = '';
            $dummyuser->lastnamephonetic = '';
            $dummyuser->middlename = '';
            $dummyuser->alternatename = '';
            $dummyuser->imagealt = '';
            return $dummyuser;
        }

        /**
         * Return noreply user record, this is currently used in messaging
         * system only for sending messages from noreply email.
         * It will return record of $CFG->noreplyuserid if set else return dummy
         * user object with hard-coded $user->emailstop = 1 so noreply can be sent to user.
         *
         * @return stdClass user record.
         */
        public static function get_noreply_user() {
            global $CFG;

            if (!empty(self::$noreplyuser)) {
                return self::$noreplyuser;
            }

            // If noreply user is set then use it, else create one.
            if (!empty($CFG->noreplyuserid)) {
                self::$noreplyuser = self::get_user($CFG->noreplyuserid);
            }

            if (empty(self::$noreplyuser)) {
                self::$noreplyuser = self::get_dummy_user_record();
                self::$noreplyuser->maildisplay = '1'; // Show to all.
            }
            self::$noreplyuser->emailstop = 1; // Force msg stop for this user.
            return self::$noreplyuser;
        }

        /**
         * Return support user record, this is currently used in messaging
         * system only for sending messages to support email.
         * $CFG->supportuserid is set then returns user record
         * $CFG->supportemail is set then return dummy record with $CFG->supportemail
         * else return admin user record with hard-coded $user->emailstop = 0, so user
         * gets support message.
         *
         * @return stdClass user record.
         */
        public static function get_support_user() {
            global $CFG;

            if (!empty(self::$supportuser)) {
                return self::$supportuser;
            }

            // If custom support user is set then use it, else if supportemail is set then use it, else use noreply.
            if (!empty($CFG->supportuserid)) {
                self::$supportuser = self::get_user($CFG->supportuserid, '*', MUST_EXIST);
            }

            // Try sending it to support email if support user is not set.
            if (empty(self::$supportuser) && !empty($CFG->supportemail)) {
                self::$supportuser = self::get_dummy_user_record();
                self::$supportuser->id = self::SUPPORT_USER;
                self::$supportuser->email = $CFG->supportemail;
                if ($CFG->supportname) {
                    self::$supportuser->firstname = $CFG->supportname;
                }
                self::$supportuser->username = 'support';
                self::$supportuser->maildisplay = '1'; // Show to all.
            }

            // Send support msg to admin user if nothing is set above.
            if (empty(self::$supportuser)) {
                self::$supportuser = get_admin();
            }

            // Unset emailstop to make sure support message is sent.
            self::$supportuser->emailstop = 0;
            return self::$supportuser;
        }

        /**
         * Reset self::$noreplyuser and self::$supportuser.
         * This is only used by phpunit, and there is no other use case for this function.
         * Please don't use it outside phpunit.
         */
        public static function reset_internal_users() {
            if (PHPUNIT_TEST) {
                self::$noreplyuser = false;
                self::$supportuser = false;
            } else {
                debugging('reset_internal_users() should not be used outside phpunit.', DEBUG_DEVELOPER);
            }
        }

        /**
         * Return true is user id is greater than self::NOREPLY_USER and
         * alternatively check db.
         *
         * @param int $userid user id.
         * @param bool $checkdb if true userid will be checked in db. By default it's false, and
         *                      userid is compared with NOREPLY_USER for performance.
         * @return bool true is real user else false.
         */
        public static function is_real_user($userid, $checkdb = false) {
            global $DB;

            if ($userid < 0) {
                return false;
            }
            if ($checkdb) {
                return $DB->record_exists('user', array('id' => $userid));
            } else {
                return true;
            }
        }
    }
}

if (!function_exists("get_all_user_name_fields")) {
    /**
     * A centralised location for the all name fields. Returns an array / sql string snippet.
     *
     * @param bool $returnsql True for an sql select field snippet.
     * @param string $tableprefix table query prefix to use in front of each field.
     * @param string $prefix prefix added to the name fields e.g. authorfirstname.
     * @param string $fieldprefix sql field prefix e.g. id AS userid.
     * @return array|string All name fields.
     */
    function get_all_user_name_fields($returnsql = false, $tableprefix = null, $prefix = null, $fieldprefix = null) {
        $alternatenames = array('firstname' => 'firstname',
                                'lastname' => 'lastname');

        // Let's add a prefix to the array of user name fields if provided.
        if ($prefix) {
            foreach ($alternatenames as $key => $altname) {
                $alternatenames[$key] = $prefix . $altname;
            }
        }

        // Create an sql field snippet if requested.
        if ($returnsql) {
            if ($tableprefix) {
                if ($fieldprefix) {
                    foreach ($alternatenames as $key => $altname) {
                        $alternatenames[$key] = $tableprefix . '.' . $altname . ' AS ' . $fieldprefix . $altname;
                    }
                } else {
                    foreach ($alternatenames as $key => $altname) {
                        $alternatenames[$key] = $tableprefix . '.' . $altname;
                    }
                }
            }
            $alternatenames = implode(',', $alternatenames);
        }
        return $alternatenames;
    }
}

if (!function_exists("username_load_fields_from_object")) {
    /**
     * Reduces lines of duplicated code for getting user name fields.
     *
     * See also {@link user_picture::unalias()}
     *
     * @param object $addtoobject Object to add user name fields to.
     * @param object $secondobject Object that contains user name field information.
     * @param string $prefix prefix to be added to all fields (including $additionalfields) e.g. authorfirstname.
     * @param array $additionalfields Additional fields to be matched with data in the second object.
     * The key can be set to the user table field name.
     * @return object User name fields.
     */
    function username_load_fields_from_object($addtoobject, $secondobject, $prefix = null, $additionalfields = null) {
        $fields = get_all_user_name_fields(false, null, $prefix);
        if ($additionalfields) {
            // Additional fields can specify their own 'alias' such as 'id' => 'userid'. This checks to see if
            // the key is a number and then sets the key to the array value.
            foreach ($additionalfields as $key => $value) {
                if (is_numeric($key)) {
                    $additionalfields[$value] = $prefix . $value;
                    unset($additionalfields[$key]);
                } else {
                    $additionalfields[$key] = $prefix . $value;
                }
            }
            $fields = array_merge($fields, $additionalfields);
        }
        foreach ($fields as $key => $field) {
            // Important that we have all of the user name fields present in the object that we are sending back.
            $addtoobject->$key = '';
            if (isset($secondobject->$field)) {
                $addtoobject->$key = $secondobject->$field;
            }
        }
        return $addtoobject;
    }
}

require_once($CFG->dirroot . "/message/lib.php");

if (!function_exists("message_format_message_text")) {

    /**
     * Try to guess how to convert the message to html.
     *
     * @access private
     *
     * @param stdClass $message
     * @param bool $forcetexttohtml
     * @return string html fragment
     */
    function message_format_message_text($message, $forcetexttohtml = false) {
        // Note: this is a very nasty hack that tries to work around the weird messaging rules and design.

        $options = new stdClass();
        $options->para = false;

        $format = $message->fullmessageformat;

        if ($message->smallmessage !== '') {
            if ($message->notification == 1) {
                if ($message->fullmessagehtml !== '' or $message->fullmessage !== '') {
                    $format = FORMAT_PLAIN;
                }
            }
            $messagetext = $message->smallmessage;

        } else if ($message->fullmessageformat == FORMAT_HTML) {
            if ($message->fullmessagehtml !== '') {
                $messagetext = $message->fullmessagehtml;
            } else {
                $messagetext = $message->fullmessage;
                $format = FORMAT_MOODLE;
            }

        } else {
            if ($message->fullmessage !== '') {
                $messagetext = $message->fullmessage;
            } else {
                $messagetext = $message->fullmessagehtml;
                $format = FORMAT_HTML;
            }
        }

        if ($forcetexttohtml) {
            // This is a crazy hack, why not set proper format when creating the notifications?
            if ($format === FORMAT_PLAIN) {
                $format = FORMAT_MOODLE;
            }
        }
        return format_text($messagetext, $format, $options);
    }

}

require_once($CFG->dirroot . "/calendar/lib.php");

if (!function_exists("calendar_get_events_by_id")) {
    /** Get calendar events by id
     *
     * @since Moodle 2.5
     * @param array $eventids list of event ids
     * @return array Array of event entries, empty array if nothing found
     */

    function calendar_get_events_by_id($eventids) {
        global $DB;

        if (!is_array($eventids) || empty($eventids)) {
            return array();
        }
        list($wheresql, $params) = $DB->get_in_or_equal($eventids);
        $wheresql = "id $wheresql";

        return $DB->get_records_select('event', $wheresql, $params);
    }
}

require_once($CFG->libdir . "/grouplib.php");

if (!function_exists("groups_get_my_groups")) {
    /**
     * Gets array of all groups in current user.
     *
     * @since Moodle 2.5
     * @category group
     * @return array Returns an array of the group objects.
     */
    function groups_get_my_groups() {
        global $DB, $USER;
        return $DB->get_records_sql("SELECT *
                                       FROM {groups_members} gm
                                       JOIN {groups} g
                                        ON g.id = gm.groupid
                                      WHERE gm.userid = ?
                                       ORDER BY name ASC", array($USER->id));
    }
}

if (!function_exists("get_course")) {
    /**
     * Gets a course object from database. If the course id corresponds to an
     * already-loaded $COURSE or $SITE object, then the loaded object will be used,
     * saving a database query.
     *
     * If it reuses an existing object, by default the object will be cloned. This
     * means you can modify the object safely without affecting other code.
     *
     * @param int $courseid Course id
     * @param bool $clone If true (default), makes a clone of the record
     * @return stdClass A course object
     * @throws dml_exception If not found in database
     */
    function get_course($courseid, $clone = true) {
        global $DB, $COURSE, $SITE;
        if (!empty($COURSE->id) && $COURSE->id == $courseid) {
            return $clone ? clone($COURSE) : $COURSE;
        } else if (!empty($SITE->id) && $SITE->id == $courseid) {
            return $clone ? clone($SITE) : $SITE;
        } else {
            return $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
        }
    }
}

if (!function_exists('user_remove_user_device')) {
    /**
     * Remove a user device from the Moodle database (for PUSH notifications usually).
     *
     * @param string $uuid The device UUID.
     * @param string $appid The app id. If empty all the devices matching the UUID for the user will be removed.
     * @return bool true if removed, false if the device didn't exists in the database
     * @since Moodle 2.9
     */
    function user_remove_user_device($uuid, $appid = "") {
        global $DB, $USER;

        $conditions = array('uuid' => $uuid, 'userid' => $USER->id);
        if (!empty($appid)) {
            $conditions['appid'] = $appid;
        }

        if (!$DB->count_records('local_mobile_user_devices', $conditions)) {
            return false;
        }

        $DB->delete_records('local_mobile_user_devices', $conditions);

        return true;
    }
}

if (!function_exists('get_course_and_cm_from_instance')) {

    /**
     * @since Moodle 2.8
     */
    function get_course_and_cm_from_instance($instanceorid, $modulename, $courseorid = 0, $userid = 0) {
        global $DB;

        // Get data from parameter.
        if (is_object($instanceorid)) {
            $instanceid = $instanceorid->id;
            if (isset($instanceorid->course)) {
                $courseid = (int)$instanceorid->course;
            } else {
                $courseid = 0;
            }
        } else {
            $instanceid = (int)$instanceorid;
            $courseid = 0;
        }

        // Get course from last parameter if supplied.
        $course = null;
        if (is_object($courseorid)) {
            $course = $courseorid;
        } else if ($courseorid) {
            $courseid = (int)$courseorid;
        }

        if (!$course) {
            if ($courseid) {
                // If course ID is known, get it using normal function.
                $course = get_course($courseid);
            } else {
                // Get course record in a single query based on instance id.
                $pagetable = '{' . $modulename . '}';
                $course = $DB->get_record_sql("
                        SELECT c.*
                          FROM $pagetable instance
                          JOIN {course} c ON c.id = instance.course
                         WHERE instance.id = ?", array($instanceid), MUST_EXIST);
            }
        }

        // Get cm from get_fast_modinfo.
        $modinfo = get_fast_modinfo($course, $userid);
        $instances = $modinfo->get_instances_of($modulename);
        if (!array_key_exists($instanceid, $instances)) {
            throw new moodle_exception('invalidmoduleid', 'error', $instanceid);
        }
        return array($course, $instances[$instanceid]);
    }
}

require_once($CFG->dirroot . '/mod/chat/lib.php');
require_once($CFG->dirroot . '/mod/choice/lib.php');


if (!function_exists('chat_get_latest_messages')) {

    /**
     * Return a list of the latest messages in the given chat session.
     *
     * @param  stdClass $chatuser     chat user session data
     * @param  int      $chatlasttime last time messages were retrieved
     * @return array    list of messages
     * @since  Moodle 3.0
     */
    function chat_get_latest_messages($chatuser, $chatlasttime) {
        global $DB;

        $params = array('groupid' => $chatuser->groupid, 'chatid' => $chatuser->chatid, 'lasttime' => $chatlasttime);

        $groupselect = $chatuser->groupid ? " AND (groupid=" . $chatuser->groupid . " OR groupid=0) " : "";

        return $DB->get_records_select('chat_messages_current', 'chatid = :chatid AND timestamp > :lasttime ' . $groupselect,
                                        $params, 'timestamp ASC');
    }
}

if (!function_exists('chat_send_chatmessage')) {

    /**
     * Send a message on the chat.
     *
     * @param object $chatuser The chat user record.
     * @param string $messagetext The message to be sent.
     * @param bool $system False for non-system messages, true for system messages.
     * @param object $cm The course module object, pass it to save a database query when we trigger the event.
     * @return int The message ID.
     * @since Moodle 2.6
     */
    function chat_send_chatmessage($chatuser, $messagetext, $system = false, $cm = null) {
        global $DB;

        $message = new stdClass();
        $message->chatid    = $chatuser->chatid;
        $message->userid    = $chatuser->userid;
        $message->groupid   = $chatuser->groupid;
        $message->message   = $messagetext;
        $message->system    = $system ? 1 : 0;
        $message->timestamp = time();

        $messageid = $DB->insert_record('chat_messages', $message);
        $DB->insert_record('chat_messages_current', $message);
        $message->id = $messageid;

        return $message->id;
    }
}


if (!function_exists('choice_get_my_response')) {
    /**
     * Return my responses on a specific choice.
     * @param object $choice
     * @return array
     */
    function choice_get_my_response($choice) {
        global $DB, $USER;
        return $DB->get_records('choice_answers', array('choiceid' => $choice->id, 'userid' => $USER->id));
    }
}

if (!function_exists('choice_get_all_responses')) {
    /**
     * Get all the responses on a given choice.
     *
     * @param stdClass $choice Choice record
     * @return array of choice answers records
     * @since  Moodle 3.0
     */
    function choice_get_all_responses($choice) {
        global $DB;
        return $DB->get_records('choice_answers', array('choiceid' => $choice->id));
    }
}

if (!function_exists('choice_can_view_results')) {
    /**
     * Return true if we are allowd to see choice results as student
     * @param object $choice Choice
     * @param rows|null $current my choice responses
     * @param bool|null $choiceopen choice open
     * @return bool True if we can see results, false if not.
     */
    function choice_can_view_results($choice, $current = null, $choiceopen = null) {

        if (is_null($choiceopen)) {
            $timenow = time();
            if ($choice->timeclose != 0 && $timenow > $choice->timeclose) {
                $choiceopen = false;
            } else {
                $choiceopen = true;
            }
        }
        if (is_null($current)) {
            $current = choice_get_my_response($choice);
        }

        if ($choice->showresults == CHOICE_SHOWRESULTS_ALWAYS or
           ($choice->showresults == CHOICE_SHOWRESULTS_AFTER_ANSWER and !empty($current)) or
           ($choice->showresults == CHOICE_SHOWRESULTS_AFTER_CLOSE and !$choiceopen)) {
            return true;
        }
        return false;
    }
}

require_once($CFG->libdir . "/modinfolib.php");

if (!function_exists('get_course_and_cm_from_cmid')) {
    /**
     * Efficiently retrieves the $course (stdclass) and $cm (cm_info) objects, given
     * a cmid. If module name is also provided, it will ensure the cm is of that type.
     *
     * Usage:
     * list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'forum');
     *
     * Using this method has a performance advantage because it works by loading
     * modinfo for the course - which will then be cached and it is needed later
     * in most requests. It also guarantees that the $cm object is a cm_info and
     * not a stdclass.
     *
     * The $course object can be supplied if already known and will speed
     * up this function - although it is more efficient to use this function to
     * get the course if you are starting from a cmid.
     *
     * To avoid security problems and obscure bugs, you should always specify
     * $modulename if the cmid value came from user input.
     *
     * By default this obtains information (for example, whether user can access
     * the activity) for current user, but you can specify a userid if required.
     *
     * @param stdClass|int $cmorid Id of course-module, or database object
     * @param string $modulename Optional modulename (improves security)
     * @param stdClass|int $courseorid Optional course object if already loaded
     * @param int $userid Optional userid (default = current)
     * @return array Array with 2 elements $course and $cm
     * @throws moodle_exception If the item doesn't exist or is of wrong module name
     */
    function get_course_and_cm_from_cmid($cmorid, $modulename = '', $courseorid = 0, $userid = 0) {
        global $DB;
        if (is_object($cmorid)) {
            $cmid = $cmorid->id;
            if (isset($cmorid->course)) {
                $courseid = (int)$cmorid->course;
            } else {
                $courseid = 0;
            }
        } else {
            $cmid = (int)$cmorid;
            $courseid = 0;
        }

        // Validate module name if supplied.
        if ($modulename && !core_component::is_valid_plugin_name('mod', $modulename)) {
            throw new coding_exception('Invalid modulename parameter');
        }

        // Get course from last parameter if supplied.
        $course = null;
        if (is_object($courseorid)) {
            $course = $courseorid;
        } else if ($courseorid) {
            $courseid = (int)$courseorid;
        }

        if (!$course) {
            if ($courseid) {
                // If course ID is known, get it using normal function.
                $course = get_course($courseid);
            } else {
                // Get course record in a single query based on cmid.
                $course = $DB->get_record_sql("
                        SELECT c.*
                          FROM {course_modules} cm
                          JOIN {course} c ON c.id = cm.course
                         WHERE cm.id = ?", array($cmid), MUST_EXIST);
            }
        }

        // Get cm from get_fast_modinfo.
        $modinfo = get_fast_modinfo($course, $userid);
        $cm = $modinfo->get_cm($cmid);
        if ($modulename && $cm->modname !== $modulename) {
            throw new moodle_exception('invalidcoursemodule', 'error');
        }
        return array($course, $cm);
    }
}

require_once($CFG->libdir . "/completionlib.php");

if (!function_exists('completion_can_view_data')) {
    /**
     * Utility function for checking if the logged in user can view
     * another's completion data for a particular course
     *
     * @access  public
     * @param   int         $userid     Completion data's owner
     * @param   mixed       $course     Course object or Course ID (optional)
     * @return  boolean
     */
    function completion_can_view_data($userid, $course = null) {
        global $USER;

        if (!isloggedin()) {
            return false;
        }

        if (!is_object($course)) {
            $cid = $course;
            $course = new object();
            $course->id = $cid;
        }

        // Check if this is the site course
        if ($course->id == SITEID) {
            $course = null;
        }

        // Check if completion is enabled
        if ($course) {
            $cinfo = new completion_info($course);
            if (!$cinfo->is_enabled()) {
                return false;
            }
        } else {
            if (!completion_info::is_enabled_for_site()) {
                return false;
            }
        }

        // Is own user's data?
        if ($USER->id == $userid) {
            return true;
        }

        // Check capabilities
        $personalcontext = context_user::instance($userid);

        if (has_capability('moodle/user:viewuseractivitiesreport', $personalcontext)) {
            return true;
        } elseif (has_capability('report/completion:view', $personalcontext)) {
            return true;
        }

        if ($course->id) {
            $coursecontext = context_course::instance($course->id);
        } else {
            $coursecontext = context_system::instance();
        }

        if (has_capability('report/completion:view', $coursecontext)) {
            return true;
        }

        return false;
    }
}

require_once($CFG->libdir . '/externallib.php');
if (!class_exists("external_util")) {

    /**
     * Utility functions for the external API.
     *
     * @package    core_webservice
     * @copyright  2015 Juan Leyva
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since Moodle 3.0
     */
    class external_util extends external_api{

        /**
         * Validate a list of courses, returning the complete course objects for valid courses.
         *
         * @param  array $courseids A list of course ids
         * @return array            An array of courses and the validation warnings
         */
        public static function validate_courses($courseids) {
            // Delete duplicates.
            $courseids = array_unique($courseids);
            $courses = array();
            $warnings = array();

            foreach ($courseids as $cid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($cid);
                    self::validate_context($context);
                    $courses[$cid] = get_course($cid);
                } catch (Exception $e) {
                    $warnings[] = array(
                        'item' => 'course',
                        'itemid' => $cid,
                        'warningcode' => '1',
                        'message' => 'No access rights in course context'
                    );
                }
            }

            return array($courses, $warnings);
        }

    }
}

if (!function_exists('external_format_string')) {
    /**
     * Format the string to be returned properly as requested by the either the web service server,
     * either by an internally call.
     * The caller can change the format (raw) with the external_settings singleton
     * All web service servers must set this singleton when parsing the $_GET and $_POST.
     *
     * @param string $str The string to be filtered. Should be plain text, expect
     * possibly for multilang tags.
     * @param boolean $striplinks To strip any link in the result text. Moodle 1.8 default changed from false to true! MDL-8713
     * @param int $contextid The id of the context for the string (affects filters).
     * @param array $options options array/object or courseid
     * @return string text
     * @since Moodle 3.0
     */
    function external_format_string($str, $contextid, $striplinks = true, $options = array()) {

        // Get settings (singleton).
        $settings = external_settings::get_instance();
        if (empty($contextid)) {
            throw new coding_exception('contextid is required');
        }

        if (!$settings->get_raw()) {
            $context = context::instance_by_id($contextid);
            $options['context'] = $context;
            $options['filter'] = $settings->get_filter();
            $str = format_string($str, $striplinks, $options);
        }

        return $str;
    }
}

if (file_exists($CFG->libdir . 'classes/component.php')) {
    require_once($CFG->libdir . 'classes/component.php');
}

if (!class_exists("core_component")) {
    /**
     * Collection of components related methods.
     */
    class core_component {
        /** @var array list of ignored directories - watch out for auth/db exception */
        protected static $ignoreddirs = array('CVS'=>true, '_vti_cnf'=>true, 'simpletest'=>true, 'db'=>true, 'yui'=>true, 'tests'=>true, 'classes'=>true, 'fonts'=>true);
        /** @var array list plugin types that support subplugins, do not add more here unless absolutely necessary */
        protected static $supportsubplugins = array('mod', 'editor', 'tool', 'local');

        /** @var array cache of plugin types */
        protected static $plugintypes = null;
        /** @var array cache of plugin locations */
        protected static $plugins = null;
        /** @var array cache of core subsystems */
        protected static $subsystems = null;
        /** @var array subplugin type parents */
        protected static $parents = null;
        /** @var array subplugins */
        protected static $subplugins = null;
        /** @var array list of all known classes that can be autoloaded */
        protected static $classmap = null;
        /** @var array list of all classes that have been renamed to be autoloaded */
        protected static $classmaprenames = null;
        /** @var array list of some known files that can be included. */
        protected static $filemap = null;
        /** @var int|float core version. */
        protected static $version = null;
        /** @var array list of the files to map. */
        protected static $filestomap = array('lib.php', 'settings.php');
        /** @var array cache of PSR loadable systems */
        protected static $psrclassmap = null;

        /**
         * Class loader for Frankenstyle named classes in standard locations.
         * Frankenstyle namespaces are supported.
         *
         * The expected location for core classes is:
         *    1/ core_xx_yy_zz ---> lib/classes/xx_yy_zz.php
         *    2/ \core\xx_yy_zz ---> lib/classes/xx_yy_zz.php
         *    3/ \core\xx\yy_zz ---> lib/classes/xx/yy_zz.php
         *
         * The expected location for plugin classes is:
         *    1/ mod_name_xx_yy_zz ---> mod/name/classes/xx_yy_zz.php
         *    2/ \mod_name\xx_yy_zz ---> mod/name/classes/xx_yy_zz.php
         *    3/ \mod_name\xx\yy_zz ---> mod/name/classes/xx/yy_zz.php
         *
         * @param string $classname
         */
        public static function classloader($classname) {
            self::init();

            if (isset(self::$classmap[$classname])) {
                // Global $CFG is expected in included scripts.
                global $CFG;
                // Function include would be faster, but for BC it is better to include only once.
                include_once(self::$classmap[$classname]);
                return;
            }
            if (isset(self::$classmaprenames[$classname]) && isset(self::$classmap[self::$classmaprenames[$classname]])) {
                $newclassname = self::$classmaprenames[$classname];
                $debugging = "Class '%s' has been renamed for the autoloader and is now deprecated. Please use '%s' instead.";
                debugging(sprintf($debugging, $classname, $newclassname), DEBUG_DEVELOPER);
                if (PHP_VERSION_ID >= 70000 && preg_match('#\\\null(\\\|$)#', $classname)) {
                    throw new \coding_exception("Cannot alias $classname to $newclassname");
                }
                class_alias($newclassname, $classname);
                return;
            }

            // Attempt to normalize the classname.
            $normalizedclassname = str_replace(array('/', '\\'), '_', $classname);
            if (isset(self::$psrclassmap[$normalizedclassname])) {
                // Function include would be faster, but for BC it is better to include only once.
                include_once(self::$psrclassmap[$normalizedclassname]);
                return;
            }
        }

        /**
         * Initialise caches, always call before accessing self:: caches.
         */
        protected static function init() {
            global $CFG;

            // Init only once per request/CLI execution, we ignore changes done afterwards.
            if (isset(self::$plugintypes)) {
                return;
            }

            if (defined('IGNORE_COMPONENT_CACHE') and IGNORE_COMPONENT_CACHE) {
                self::fill_all_caches();
                return;
            }

            if (!empty($CFG->alternative_component_cache)) {
                // Hack for heavily clustered sites that want to manage component cache invalidation manually.
                $cachefile = $CFG->alternative_component_cache;

                if (file_exists($cachefile)) {
                    if (CACHE_DISABLE_ALL) {
                        // Verify the cache state only on upgrade pages.
                        $content = self::get_cache_content();
                        if (sha1_file($cachefile) !== sha1($content)) {
                            die('Outdated component cache file defined in $CFG->alternative_component_cache, can not continue');
                        }
                        return;
                    }
                    $cache = array();
                    include($cachefile);
                    self::$plugintypes      = $cache['plugintypes'];
                    self::$plugins          = $cache['plugins'];
                    self::$subsystems       = $cache['subsystems'];
                    self::$parents          = $cache['parents'];
                    self::$subplugins       = $cache['subplugins'];
                    self::$classmap         = $cache['classmap'];
                    self::$classmaprenames  = $cache['classmaprenames'];
                    self::$filemap          = $cache['filemap'];
                    self::$psrclassmap      = $cache['psrclassmap'];
                    return;
                }

                if (!is_writable(dirname($cachefile))) {
                    die('Can not create alternative component cache file defined in $CFG->alternative_component_cache, can not continue');
                }

                // Lets try to create the file, it might be in some writable directory or a local cache dir.

            } else {
                // Note: $CFG->cachedir MUST be shared by all servers in a cluster,
                //       use $CFG->alternative_component_cache if you do not like it.
                $cachefile = "$CFG->cachedir/core_component.php";
            }

            if (!CACHE_DISABLE_ALL and !self::is_developer()) {
                // 1/ Use the cache only outside of install and upgrade.
                // 2/ Let developers add/remove classes in developer mode.
                if (is_readable($cachefile)) {
                    $cache = false;
                    include($cachefile);
                    if (!is_array($cache)) {
                        // Something is very wrong.
                    } else if (!isset($cache['version'])) {
                        // Something is very wrong.
                    } else if ((float) $cache['version'] !== (float) self::fetch_core_version()) {
                        // Outdated cache. We trigger an error log to track an eventual repetitive failure of float comparison.
                        error_log('Resetting core_component cache after core upgrade to version ' . self::fetch_core_version());
                    } else if ($cache['plugintypes']['mod'] !== "$CFG->dirroot/mod") {
                        // $CFG->dirroot was changed.
                    } else {
                        // The cache looks ok, let's use it.
                        self::$plugintypes      = $cache['plugintypes'];
                        self::$plugins          = $cache['plugins'];
                        self::$subsystems       = $cache['subsystems'];
                        self::$parents          = $cache['parents'];
                        self::$subplugins       = $cache['subplugins'];
                        self::$classmap         = $cache['classmap'];
                        self::$classmaprenames  = $cache['classmaprenames'];
                        self::$filemap          = $cache['filemap'];
                        self::$psrclassmap      = $cache['psrclassmap'];
                        return;
                    }
                    // Note: we do not verify $CFG->admin here intentionally,
                    //       they must visit admin/index.php after any change.
                }
            }

            if (!isset(self::$plugintypes)) {
                // This needs to be atomic and self-fixing as much as possible.

                $content = self::get_cache_content();
                if (file_exists($cachefile)) {
                    if (sha1_file($cachefile) === sha1($content)) {
                        return;
                    }
                    // Stale cache detected!
                    unlink($cachefile);
                }

                // Permissions might not be setup properly in installers.
                $dirpermissions = !isset($CFG->directorypermissions) ? 02777 : $CFG->directorypermissions;
                $filepermissions = !isset($CFG->filepermissions) ? ($dirpermissions & 0666) : $CFG->filepermissions;

                clearstatcache();
                $cachedir = dirname($cachefile);
                if (!is_dir($cachedir)) {
                    mkdir($cachedir, $dirpermissions, true);
                }

                if ($fp = @fopen($cachefile.'.tmp', 'xb')) {
                    fwrite($fp, $content);
                    fclose($fp);
                    @rename($cachefile.'.tmp', $cachefile);
                    @chmod($cachefile, $filepermissions);
                }
                @unlink($cachefile.'.tmp'); // Just in case anything fails (race condition).
                self::invalidate_opcode_php_cache($cachefile);
            }
        }

        /**
         * Are we in developer debug mode?
         *
         * Note: You need to set "$CFG->debug = (E_ALL | E_STRICT);" in config.php,
         *       the reason is we need to use this before we setup DB connection or caches for CFG.
         *
         * @return bool
         */
        protected static function is_developer() {
            global $CFG;

            // Note we can not rely on $CFG->debug here because DB is not initialised yet.
            if (isset($CFG->config_php_settings['debug'])) {
                $debug = (int)$CFG->config_php_settings['debug'];
            } else {
                return false;
            }

            if ($debug & E_ALL and $debug & E_STRICT) {
                return true;
            }

            return false;
        }

        /**
         * Create cache file content.
         *
         * @private this is intended for $CFG->alternative_component_cache only.
         *
         * @return string
         */
        public static function get_cache_content() {
            if (!isset(self::$plugintypes)) {
                self::fill_all_caches();
            }

            $cache = array(
                'subsystems'        => self::$subsystems,
                'plugintypes'       => self::$plugintypes,
                'plugins'           => self::$plugins,
                'parents'           => self::$parents,
                'subplugins'        => self::$subplugins,
                'classmap'          => self::$classmap,
                'classmaprenames'   => self::$classmaprenames,
                'filemap'           => self::$filemap,
                'version'           => self::$version,
                'psrclassmap'       => self::$psrclassmap,
            );

            return '<?php
    $cache = '.var_export($cache, true).';
    ';
        }

        /**
         * Fill all caches.
         */
        protected static function fill_all_caches() {
            self::$subsystems = self::fetch_subsystems();

            list(self::$plugintypes, self::$parents, self::$subplugins) = self::fetch_plugintypes();

            self::$plugins = array();
            foreach (self::$plugintypes as $type => $fulldir) {
                self::$plugins[$type] = self::fetch_plugins($type, $fulldir);
            }

            self::fill_classmap_cache();
            self::fill_classmap_renames_cache();
            self::fill_filemap_cache();
            self::fill_psr_cache();
            self::fetch_core_version();
        }

        /**
         * Get the core version.
         *
         * In order for this to work properly, opcache should be reset beforehand.
         *
         * @return float core version.
         */
        protected static function fetch_core_version() {
            global $CFG;
            if (self::$version === null) {
                $version = null; // Prevent IDE complaints.
                require($CFG->dirroot . '/version.php');
                self::$version = $version;
            }
            return self::$version;
        }

        /**
         * Returns list of core subsystems.
         * @return array
         */
        protected static function fetch_subsystems() {
            global $CFG;

            // NOTE: Any additions here must be verified to not collide with existing add-on modules and subplugins!!!

            $info = array(
                'access'      => null,
                'admin'       => $CFG->dirroot.'/'.$CFG->admin,
                'auth'        => $CFG->dirroot.'/auth',
                'availability' => $CFG->dirroot . '/availability',
                'backup'      => $CFG->dirroot.'/backup/util/ui',
                'badges'      => $CFG->dirroot.'/badges',
                'block'       => $CFG->dirroot.'/blocks',
                'blog'        => $CFG->dirroot.'/blog',
                'bulkusers'   => null,
                'cache'       => $CFG->dirroot.'/cache',
                'calendar'    => $CFG->dirroot.'/calendar',
                'cohort'      => $CFG->dirroot.'/cohort',
                'comment'     => $CFG->dirroot.'/comment',
                'completion'  => $CFG->dirroot.'/completion',
                'countries'   => null,
                'course'      => $CFG->dirroot.'/course',
                'currencies'  => null,
                'dbtransfer'  => null,
                'debug'       => null,
                'editor'      => $CFG->dirroot.'/lib/editor',
                'edufields'   => null,
                'enrol'       => $CFG->dirroot.'/enrol',
                'error'       => null,
                'filepicker'  => null,
                'files'       => $CFG->dirroot.'/files',
                'filters'     => null,
                //'fonts'       => null, // Bogus.
                'form'        => $CFG->dirroot.'/lib/form',
                'grades'      => $CFG->dirroot.'/grade',
                'grading'     => $CFG->dirroot.'/grade/grading',
                'group'       => $CFG->dirroot.'/group',
                'help'        => null,
                'hub'         => null,
                'imscc'       => null,
                'install'     => null,
                'iso6392'     => null,
                'langconfig'  => null,
                'license'     => null,
                'mathslib'    => null,
                'media'       => null,
                'message'     => $CFG->dirroot.'/message',
                'mimetypes'   => null,
                'mnet'        => $CFG->dirroot.'/mnet',
                //'moodle.org'  => null, // Not used any more.
                'my'          => $CFG->dirroot.'/my',
                'notes'       => $CFG->dirroot.'/notes',
                'pagetype'    => null,
                'pix'         => null,
                'plagiarism'  => $CFG->dirroot.'/plagiarism',
                'plugin'      => null,
                'portfolio'   => $CFG->dirroot.'/portfolio',
                'publish'     => $CFG->dirroot.'/course/publish',
                'question'    => $CFG->dirroot.'/question',
                'rating'      => $CFG->dirroot.'/rating',
                'register'    => $CFG->dirroot.'/'.$CFG->admin.'/registration', // Broken badly if $CFG->admin changed.
                'repository'  => $CFG->dirroot.'/repository',
                'rss'         => $CFG->dirroot.'/rss',
                'role'        => $CFG->dirroot.'/'.$CFG->admin.'/roles',
                'search'      => null,
                'table'       => null,
                'tag'         => $CFG->dirroot.'/tag',
                'timezones'   => null,
                'user'        => $CFG->dirroot.'/user',
                'userkey'     => null,
                'webservice'  => $CFG->dirroot.'/webservice',
            );

            return $info;
        }

        /**
         * Returns list of known plugin types.
         * @return array
         */
        protected static function fetch_plugintypes() {
            global $CFG;

            $types = array(
                'availability'  => $CFG->dirroot . '/availability/condition',
                'qtype'         => $CFG->dirroot.'/question/type',
                'mod'           => $CFG->dirroot.'/mod',
                'auth'          => $CFG->dirroot.'/auth',
                'calendartype'  => $CFG->dirroot.'/calendar/type',
                'enrol'         => $CFG->dirroot.'/enrol',
                'message'       => $CFG->dirroot.'/message/output',
                'block'         => $CFG->dirroot.'/blocks',
                'filter'        => $CFG->dirroot.'/filter',
                'editor'        => $CFG->dirroot.'/lib/editor',
                'format'        => $CFG->dirroot.'/course/format',
                'profilefield'  => $CFG->dirroot.'/user/profile/field',
                'report'        => $CFG->dirroot.'/report',
                'coursereport'  => $CFG->dirroot.'/course/report', // Must be after system reports.
                'gradeexport'   => $CFG->dirroot.'/grade/export',
                'gradeimport'   => $CFG->dirroot.'/grade/import',
                'gradereport'   => $CFG->dirroot.'/grade/report',
                'gradingform'   => $CFG->dirroot.'/grade/grading/form',
                'mnetservice'   => $CFG->dirroot.'/mnet/service',
                'webservice'    => $CFG->dirroot.'/webservice',
                'repository'    => $CFG->dirroot.'/repository',
                'portfolio'     => $CFG->dirroot.'/portfolio',
                'qbehaviour'    => $CFG->dirroot.'/question/behaviour',
                'qformat'       => $CFG->dirroot.'/question/format',
                'plagiarism'    => $CFG->dirroot.'/plagiarism',
                'tool'          => $CFG->dirroot.'/'.$CFG->admin.'/tool',
                'cachestore'    => $CFG->dirroot.'/cache/stores',
                'cachelock'     => $CFG->dirroot.'/cache/locks',
            );
            $parents = array();
            $subplugins = array();

            if (!empty($CFG->themedir) and is_dir($CFG->themedir) ) {
                $types['theme'] = $CFG->themedir;
            } else {
                $types['theme'] = $CFG->dirroot.'/theme';
            }

            foreach (self::$supportsubplugins as $type) {
                if ($type === 'local') {
                    // Local subplugins must be after local plugins.
                    continue;
                }
                $plugins = self::fetch_plugins($type, $types[$type]);
                foreach ($plugins as $plugin => $fulldir) {
                    $subtypes = self::fetch_subtypes($fulldir);
                    if (!$subtypes) {
                        continue;
                    }
                    $subplugins[$type.'_'.$plugin] = array();
                    foreach($subtypes as $subtype => $subdir) {
                        if (isset($types[$subtype])) {
                            error_log("Invalid subtype '$subtype', duplicate detected.");
                            continue;
                        }
                        $types[$subtype] = $subdir;
                        $parents[$subtype] = $type.'_'.$plugin;
                        $subplugins[$type.'_'.$plugin][$subtype] = array_keys(self::fetch_plugins($subtype, $subdir));
                    }
                }
            }
            // Local is always last!
            $types['local'] = $CFG->dirroot.'/local';

            if (in_array('local', self::$supportsubplugins)) {
                $type = 'local';
                $plugins = self::fetch_plugins($type, $types[$type]);
                foreach ($plugins as $plugin => $fulldir) {
                    $subtypes = self::fetch_subtypes($fulldir);
                    if (!$subtypes) {
                        continue;
                    }
                    $subplugins[$type.'_'.$plugin] = array();
                    foreach($subtypes as $subtype => $subdir) {
                        if (isset($types[$subtype])) {
                            error_log("Invalid subtype '$subtype', duplicate detected.");
                            continue;
                        }
                        $types[$subtype] = $subdir;
                        $parents[$subtype] = $type.'_'.$plugin;
                        $subplugins[$type.'_'.$plugin][$subtype] = array_keys(self::fetch_plugins($subtype, $subdir));
                    }
                }
            }

            return array($types, $parents, $subplugins);
        }

        /**
         * Returns list of subtypes.
         * @param string $ownerdir
         * @return array
         */
        protected static function fetch_subtypes($ownerdir) {
            global $CFG;

            $types = array();
            if (file_exists("$ownerdir/db/subplugins.php")) {
                $subplugins = array();
                include("$ownerdir/db/subplugins.php");
                foreach ($subplugins as $subtype => $dir) {
                    if (!preg_match('/^[a-z][a-z0-9]*$/', $subtype)) {
                        error_log("Invalid subtype '$subtype'' detected in '$ownerdir', invalid characters present.");
                        continue;
                    }
                    if (isset(self::$subsystems[$subtype])) {
                        error_log("Invalid subtype '$subtype'' detected in '$ownerdir', duplicates core subsystem.");
                        continue;
                    }
                    if ($CFG->admin !== 'admin' and strpos($dir, 'admin/') === 0) {
                        $dir = preg_replace('|^admin/|', "$CFG->admin/", $dir);
                    }
                    if (!is_dir("$CFG->dirroot/$dir")) {
                        error_log("Invalid subtype directory '$dir' detected in '$ownerdir'.");
                        continue;
                    }
                    $types[$subtype] = "$CFG->dirroot/$dir";
                }
            }
            return $types;
        }

        /**
         * Returns list of plugins of given type in given directory.
         * @param string $plugintype
         * @param string $fulldir
         * @return array
         */
        protected static function fetch_plugins($plugintype, $fulldir) {
            global $CFG;

            $fulldirs = (array)$fulldir;
            if ($plugintype === 'theme') {
                if (realpath($fulldir) !== realpath($CFG->dirroot.'/theme')) {
                    // Include themes in standard location too.
                    array_unshift($fulldirs, $CFG->dirroot.'/theme');
                }
            }

            $result = array();

            foreach ($fulldirs as $fulldir) {
                if (!is_dir($fulldir)) {
                    continue;
                }
                $items = new \DirectoryIterator($fulldir);
                foreach ($items as $item) {
                    if ($item->isDot() or !$item->isDir()) {
                        continue;
                    }
                    $pluginname = $item->getFilename();
                    if ($plugintype === 'auth' and $pluginname === 'db') {
                        // Special exception for this wrong plugin name.
                    } else if (isset(self::$ignoreddirs[$pluginname])) {
                        continue;
                    }
                    if (!self::is_valid_plugin_name($plugintype, $pluginname)) {
                        // Always ignore plugins with problematic names here.
                        continue;
                    }
                    $result[$pluginname] = $fulldir.'/'.$pluginname;
                    unset($item);
                }
                unset($items);
            }

            ksort($result);
            return $result;
        }

        /**
         * Find all classes that can be autoloaded including frankenstyle namespaces.
         */
        protected static function fill_classmap_cache() {
            global $CFG;

            self::$classmap = array();

            self::load_classes('core', "$CFG->dirroot/lib/classes");

            foreach (self::$subsystems as $subsystem => $fulldir) {
                if (!$fulldir) {
                    continue;
                }
                self::load_classes('core_'.$subsystem, "$fulldir/classes");
            }

            foreach (self::$plugins as $plugintype => $plugins) {
                foreach ($plugins as $pluginname => $fulldir) {
                    self::load_classes($plugintype.'_'.$pluginname, "$fulldir/classes");
                }
            }
            ksort(self::$classmap);
        }

        /**
         * Fills up the cache defining what plugins have certain files.
         *
         * @see self::get_plugin_list_with_file
         * @return void
         */
        protected static function fill_filemap_cache() {
            global $CFG;

            self::$filemap = array();

            foreach (self::$filestomap as $file) {
                if (!isset(self::$filemap[$file])) {
                    self::$filemap[$file] = array();
                }
                foreach (self::$plugins as $plugintype => $plugins) {
                    if (!isset(self::$filemap[$file][$plugintype])) {
                        self::$filemap[$file][$plugintype] = array();
                    }
                    foreach ($plugins as $pluginname => $fulldir) {
                        if (file_exists("$fulldir/$file")) {
                            self::$filemap[$file][$plugintype][$pluginname] = "$fulldir/$file";
                        }
                    }
                }
            }
        }

        /**
         * Find classes in directory and recurse to subdirs.
         * @param string $component
         * @param string $fulldir
         * @param string $namespace
         */
        protected static function load_classes($component, $fulldir, $namespace = '') {
            if (!is_dir($fulldir)) {
                return;
            }

            $items = new \DirectoryIterator($fulldir);
            foreach ($items as $item) {
                if ($item->isDot()) {
                    continue;
                }
                if ($item->isDir()) {
                    $dirname = $item->getFilename();
                    self::load_classes($component, "$fulldir/$dirname", $namespace.'\\'.$dirname);
                    continue;
                }

                $filename = $item->getFilename();
                $classname = preg_replace('/\.php$/', '', $filename);

                if ($filename === $classname) {
                    // Not a php file.
                    continue;
                }
                if ($namespace === '') {
                    // Legacy long frankenstyle class name.
                    self::$classmap[$component.'_'.$classname] = "$fulldir/$filename";
                }
                // New namespaced classes.
                self::$classmap[$component.$namespace.'\\'.$classname] = "$fulldir/$filename";
            }
            unset($item);
            unset($items);
        }

        /**
         * Fill caches for classes following the PSR-0 standard for the
         * specified Vendors.
         *
         * PSR Autoloading is detailed at http://www.php-fig.org/psr/psr-0/.
         */
        protected static function fill_psr_cache() {
            global $CFG;

            $psrsystems = array(
                'Horde' => 'horde/framework',
            );
            self::$psrclassmap = array();

            foreach ($psrsystems as $system => $fulldir) {
                if (!$fulldir) {
                    continue;
                }
                self::load_psr_classes($CFG->libdir . DIRECTORY_SEPARATOR . $fulldir);
            }
        }

        /**
         * Find all PSR-0 style classes in within the base directory.
         *
         * @param string $basedir The base directory that the PSR-type library can be found in.
         * @param string $subdir The directory within the basedir to search for classes within.
         */
        protected static function load_psr_classes($basedir, $subdir = null) {
            if ($subdir) {
                $fulldir = realpath($basedir . DIRECTORY_SEPARATOR . $subdir);
                $classnameprefix = preg_replace('#' . preg_quote(DIRECTORY_SEPARATOR) . '#', '_', $subdir);
            } else {
                $fulldir = $basedir;
            }
            if (!$fulldir || !is_dir($fulldir)) {
                return;
            }

            $items = new \DirectoryIterator($fulldir);
            foreach ($items as $item) {
                if ($item->isDot()) {
                    continue;
                }
                if ($item->isDir()) {
                    $dirname = $item->getFilename();
                    $newsubdir = $dirname;
                    if ($subdir) {
                        $newsubdir = implode(DIRECTORY_SEPARATOR, array($subdir, $dirname));
                    }
                    self::load_psr_classes($basedir, $newsubdir);
                    continue;
                }

                $filename = $item->getFilename();
                $classname = preg_replace('/\.php$/', '', $filename);

                if ($filename === $classname) {
                    // Not a php file.
                    continue;
                }

                if ($classnameprefix) {
                    $classname = $classnameprefix . '_' . $classname;
                }

                self::$psrclassmap[$classname] = $fulldir . DIRECTORY_SEPARATOR . $filename;
            }
            unset($item);
            unset($items);
        }

        /**
         * List all core subsystems and their location
         *
         * This is a whitelist of components that are part of the core and their
         * language strings are defined in /lang/en/<<subsystem>>.php. If a given
         * plugin is not listed here and it does not have proper plugintype prefix,
         * then it is considered as course activity module.
         *
         * The location is absolute file path to dir. NULL means there is no special
         * directory for this subsystem. If the location is set, the subsystem's
         * renderer.php is expected to be there.
         *
         * @return array of (string)name => (string|null)full dir location
         */
        public static function get_core_subsystems() {
            self::init();
            return self::$subsystems;
        }

        /**
         * Get list of available plugin types together with their location.
         *
         * @return array as (string)plugintype => (string)fulldir
         */
        public static function get_plugin_types() {
            self::init();
            return self::$plugintypes;
        }

        /**
         * Get list of plugins of given type.
         *
         * @param string $plugintype
         * @return array as (string)pluginname => (string)fulldir
         */
        public static function get_plugin_list($plugintype) {
            self::init();

            if (!isset(self::$plugins[$plugintype])) {
                return array();
            }
            return self::$plugins[$plugintype];
        }

        /**
         * Get a list of all the plugins of a given type that define a certain class
         * in a certain file. The plugin component names and class names are returned.
         *
         * @param string $plugintype the type of plugin, e.g. 'mod' or 'report'.
         * @param string $class the part of the name of the class after the
         *      frankenstyle prefix. e.g 'thing' if you are looking for classes with
         *      names like report_courselist_thing. If you are looking for classes with
         *      the same name as the plugin name (e.g. qtype_multichoice) then pass ''.
         *      Frankenstyle namespaces are also supported.
         * @param string $file the name of file within the plugin that defines the class.
         * @return array with frankenstyle plugin names as keys (e.g. 'report_courselist', 'mod_forum')
         *      and the class names as values (e.g. 'report_courselist_thing', 'qtype_multichoice').
         */
        public static function get_plugin_list_with_class($plugintype, $class, $file = null) {
            global $CFG; // Necessary in case it is referenced by included PHP scripts.

            if ($class) {
                $suffix = '_' . $class;
            } else {
                $suffix = '';
            }

            $pluginclasses = array();
            $plugins = self::get_plugin_list($plugintype);
            foreach ($plugins as $plugin => $fulldir) {
                // Try class in frankenstyle namespace.
                if ($class) {
                    $classname = '\\' . $plugintype . '_' . $plugin . '\\' . $class;
                    if (class_exists($classname, true)) {
                        $pluginclasses[$plugintype . '_' . $plugin] = $classname;
                        continue;
                    }
                }

                // Try autoloading of class with frankenstyle prefix.
                $classname = $plugintype . '_' . $plugin . $suffix;
                if (class_exists($classname, true)) {
                    $pluginclasses[$plugintype . '_' . $plugin] = $classname;
                    continue;
                }

                // Fall back to old file location and class name.
                if ($file and file_exists("$fulldir/$file")) {
                    include_once("$fulldir/$file");
                    if (class_exists($classname, false)) {
                        $pluginclasses[$plugintype . '_' . $plugin] = $classname;
                        continue;
                    }
                }
            }

            return $pluginclasses;
        }

        /**
         * Get a list of all the plugins of a given type that contain a particular file.
         *
         * @param string $plugintype the type of plugin, e.g. 'mod' or 'report'.
         * @param string $file the name of file that must be present in the plugin.
         *                     (e.g. 'view.php', 'db/install.xml').
         * @param bool $include if true (default false), the file will be include_once-ed if found.
         * @return array with plugin name as keys (e.g. 'forum', 'courselist') and the path
         *               to the file relative to dirroot as value (e.g. "$CFG->dirroot/mod/forum/view.php").
         */
        public static function get_plugin_list_with_file($plugintype, $file, $include = false) {
            global $CFG; // Necessary in case it is referenced by included PHP scripts.
            $pluginfiles = array();

            if (isset(self::$filemap[$file])) {
                // If the file was supposed to be mapped, then it should have been set in the array.
                if (isset(self::$filemap[$file][$plugintype])) {
                    $pluginfiles = self::$filemap[$file][$plugintype];
                }
            } else {
                // Old-style search for non-cached files.
                $plugins = self::get_plugin_list($plugintype);
                foreach ($plugins as $plugin => $fulldir) {
                    $path = $fulldir . '/' . $file;
                    if (file_exists($path)) {
                        $pluginfiles[$plugin] = $path;
                    }
                }
            }

            if ($include) {
                foreach ($pluginfiles as $path) {
                    include_once($path);
                }
            }

            return $pluginfiles;
        }

        /**
         * Returns the exact absolute path to plugin directory.
         *
         * @param string $plugintype type of plugin
         * @param string $pluginname name of the plugin
         * @return string full path to plugin directory; null if not found
         */
        public static function get_plugin_directory($plugintype, $pluginname) {
            if (empty($pluginname)) {
                // Invalid plugin name, sorry.
                return null;
            }

            self::init();

            if (!isset(self::$plugins[$plugintype][$pluginname])) {
                return null;
            }
            return self::$plugins[$plugintype][$pluginname];
        }

        /**
         * Returns the exact absolute path to plugin directory.
         *
         * @param string $subsystem type of core subsystem
         * @return string full path to subsystem directory; null if not found
         */
        public static function get_subsystem_directory($subsystem) {
            self::init();

            if (!isset(self::$subsystems[$subsystem])) {
                return null;
            }
            return self::$subsystems[$subsystem];
        }

        /**
         * This method validates a plug name. It is much faster than calling clean_param.
         *
         * @param string $plugintype type of plugin
         * @param string $pluginname a string that might be a plugin name.
         * @return bool if this string is a valid plugin name.
         */
        public static function is_valid_plugin_name($plugintype, $pluginname) {
            if ($plugintype === 'mod') {
                // Modules must not have the same name as core subsystems.
                if (!isset(self::$subsystems)) {
                    // Watch out, this is called from init!
                    self::init();
                }
                if (isset(self::$subsystems[$pluginname])) {
                    return false;
                }
                // Modules MUST NOT have any underscores,
                // component normalisation would break very badly otherwise!
                return (bool)preg_match('/^[a-z][a-z0-9]*$/', $pluginname);

            } else {
                return (bool)preg_match('/^[a-z](?:[a-z0-9_](?!__))*[a-z0-9]+$/', $pluginname);
            }
        }

        /**
         * Normalize the component name.
         *
         * Note: this does not verify the validity of the plugin or component.
         *
         * @param string $component
         * @return string
         */
        public static function normalize_componentname($componentname) {
            list($plugintype, $pluginname) = self::normalize_component($componentname);
            if ($plugintype === 'core' && is_null($pluginname)) {
                return $plugintype;
            }
            return $plugintype . '_' . $pluginname;
        }

        /**
         * Normalize the component name using the "frankenstyle" rules.
         *
         * Note: this does not verify the validity of plugin or type names.
         *
         * @param string $component
         * @return array as (string)$type => (string)$plugin
         */
        public static function normalize_component($component) {
            if ($component === 'moodle' or $component === 'core' or $component === '') {
                return array('core', null);
            }

            if (strpos($component, '_') === false) {
                self::init();
                if (array_key_exists($component, self::$subsystems)) {
                    $type   = 'core';
                    $plugin = $component;
                } else {
                    // Everything else without underscore is a module.
                    $type   = 'mod';
                    $plugin = $component;
                }

            } else {
                list($type, $plugin) = explode('_', $component, 2);
                if ($type === 'moodle') {
                    $type = 'core';
                }
                // Any unknown type must be a subplugin.
            }

            return array($type, $plugin);
        }

        /**
         * Return exact absolute path to a plugin directory.
         *
         * @param string $component name such as 'moodle', 'mod_forum'
         * @return string full path to component directory; NULL if not found
         */
        public static function get_component_directory($component) {
            global $CFG;

            list($type, $plugin) = self::normalize_component($component);

            if ($type === 'core') {
                if ($plugin === null) {
                    return $path = $CFG->libdir;
                }
                return self::get_subsystem_directory($plugin);
            }

            return self::get_plugin_directory($type, $plugin);
        }

        /**
         * Returns list of plugin types that allow subplugins.
         * @return array as (string)plugintype => (string)fulldir
         */
        public static function get_plugin_types_with_subplugins() {
            self::init();

            $return = array();
            foreach (self::$supportsubplugins as $type) {
                $return[$type] = self::$plugintypes[$type];
            }
            return $return;
        }

        /**
         * Returns parent of this subplugin type.
         *
         * @param string $type
         * @return string parent component or null
         */
        public static function get_subtype_parent($type) {
            self::init();

            if (isset(self::$parents[$type])) {
                return self::$parents[$type];
            }

            return null;
        }

        /**
         * Return all subplugins of this component.
         * @param string $component.
         * @return array $subtype=>array($component, ..), null if no subtypes defined
         */
        public static function get_subplugins($component) {
            self::init();

            if (isset(self::$subplugins[$component])) {
                return self::$subplugins[$component];
            }

            return null;
        }

        /**
         * Returns hash of all versions including core and all plugins.
         *
         * This is relatively slow and not fully cached, use with care!
         *
         * @return string sha1 hash
         */
        public static function get_all_versions_hash() {
            global $CFG;

            self::init();

            $versions = array();

            // Main version first.
            $versions['core'] = self::fetch_core_version();

            // The problem here is tha the component cache might be stable,
            // we want this to work also on frontpage without resetting the component cache.
            $usecache = false;
            if (CACHE_DISABLE_ALL or (defined('IGNORE_COMPONENT_CACHE') and IGNORE_COMPONENT_CACHE)) {
                $usecache = true;
            }

            // Now all plugins.
            $plugintypes = core_component::get_plugin_types();
            foreach ($plugintypes as $type => $typedir) {
                if ($usecache) {
                    $plugs = core_component::get_plugin_list($type);
                } else {
                    $plugs = self::fetch_plugins($type, $typedir);
                }
                foreach ($plugs as $plug => $fullplug) {
                    $plugin = new stdClass();
                    $plugin->version = null;
                    $module = $plugin;
                    include($fullplug.'/version.php');
                    $versions[$type.'_'.$plug] = $plugin->version;
                }
            }

            return sha1(serialize($versions));
        }

        /**
         * Invalidate opcode cache for given file, this is intended for
         * php files that are stored in dataroot.
         *
         * Note: we need it here because this class must be self-contained.
         *
         * @param string $file
         */
        public static function invalidate_opcode_php_cache($file) {
            if (function_exists('opcache_invalidate')) {
                if (!file_exists($file)) {
                    return;
                }
                opcache_invalidate($file, true);
            }
        }

        /**
         * Return true if subsystemname is core subsystem.
         *
         * @param string $subsystemname name of the subsystem.
         * @return bool true if core subsystem.
         */
        public static function is_core_subsystem($subsystemname) {
            return isset(self::$subsystems[$subsystemname]);
        }

        /**
         * Records all class renames that have been made to facilitate autoloading.
         */
        protected static function fill_classmap_renames_cache() {
            global $CFG;

            self::$classmaprenames = array();

            self::load_renamed_classes("$CFG->dirroot/lib/");

            foreach (self::$subsystems as $subsystem => $fulldir) {
                self::load_renamed_classes($fulldir);
            }

            foreach (self::$plugins as $plugintype => $plugins) {
                foreach ($plugins as $pluginname => $fulldir) {
                    self::load_renamed_classes($fulldir);
                }
            }
        }

        /**
         * Loads the db/renamedclasses.php file from the given directory.
         *
         * The renamedclasses.php should contain a key => value array ($renamedclasses) where the key is old class name,
         * and the value is the new class name.
         * It is only included when we are populating the component cache. After that is not needed.
         *
         * @param string $fulldir
         */
        protected static function load_renamed_classes($fulldir) {
            $file = $fulldir . '/db/renamedclasses.php';
            if (is_readable($file)) {
                $renamedclasses = null;
                require($file);
                if (is_array($renamedclasses)) {
                    foreach ($renamedclasses as $oldclass => $newclass) {
                        self::$classmaprenames[(string)$oldclass] = (string)$newclass;
                    }
                }
            }
        }
    }
}

require_once($CFG->dirroot . '/mod/lti/locallib.php');

if (!function_exists('lti_get_launch_data')) {
    /**
     * Return the launch data required for opening the external tool.
     *
     * @param  stdClass $instance the external tool activity settings
     * @return array the endpoint URL and parameters (including the signature)
     * @since  Moodle 3.0
     */
    function lti_get_launch_data($instance) {
        global $PAGE, $CFG;

        if (empty($instance->typeid)) {
            $tool = lti_get_tool_by_url_match($instance->toolurl, $instance->course);
            if ($tool) {
                $typeid = $tool->id;
            } else {
                $typeid = null;
            }
        } else {
            $typeid = $instance->typeid;
            $tool = lti_get_type($typeid);
        }

        if ($typeid) {
            $typeconfig = lti_get_type_config($typeid);
        } else {
            // There is no admin configuration for this tool. Use configuration in the lti instance record plus some defaults.
            $typeconfig = (array)$instance;

            $typeconfig['sendname'] = $instance->instructorchoicesendname;
            $typeconfig['sendemailaddr'] = $instance->instructorchoicesendemailaddr;
            $typeconfig['customparameters'] = $instance->instructorcustomparameters;
            $typeconfig['acceptgrades'] = $instance->instructorchoiceacceptgrades;
            $typeconfig['allowroster'] = $instance->instructorchoiceallowroster;
            $typeconfig['forcessl'] = '0';
        }

        // Default the organizationid if not specified.
        if (empty($typeconfig['organizationid'])) {
            $urlparts = parse_url($CFG->wwwroot);

            $typeconfig['organizationid'] = $urlparts['host'];
        }

        if (isset($tool->toolproxyid)) {
            $toolproxy = lti_get_tool_proxy($tool->toolproxyid);
            $key = $toolproxy->guid;
            $secret = $toolproxy->secret;
        } else {
            $toolproxy = null;
            if (!empty($instance->resourcekey)) {
                $key = $instance->resourcekey;
            } else if (!empty($typeconfig['resourcekey'])) {
                $key = $typeconfig['resourcekey'];
            } else {
                $key = '';
            }
            if (!empty($instance->password)) {
                $secret = $instance->password;
            } else if (!empty($typeconfig['password'])) {
                $secret = $typeconfig['password'];
            } else {
                $secret = '';
            }
        }

        $endpoint = !empty($instance->toolurl) ? $instance->toolurl : $typeconfig['toolurl'];
        $endpoint = trim($endpoint);

        // If the current request is using SSL and a secure tool URL is specified, use it.
        if (lti_request_is_using_ssl() && !empty($instance->securetoolurl)) {
            $endpoint = trim($instance->securetoolurl);
        }

        // If SSL is forced, use the secure tool url if specified. Otherwise, make sure https is on the normal launch URL.
        if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
            if (!empty($instance->securetoolurl)) {
                $endpoint = trim($instance->securetoolurl);
            }

            $endpoint = lti_ensure_url_is_https($endpoint);
        } else {
            if (!strstr($endpoint, '://')) {
                $endpoint = 'http://' . $endpoint;
            }
        }

        $orgid = $typeconfig['organizationid'];

        $course = $PAGE->course;
        $islti2 = isset($tool->toolproxyid);
        $allparams = lti_build_request($instance, $typeconfig, $course, $typeid, $islti2);
        if ($islti2) {
            $requestparams = lti_build_request_lti2($tool, $allparams);
        } else {
            $requestparams = $allparams;
        }
        $requestparams = array_merge($requestparams, lti_build_standard_request($instance, $orgid, $islti2));
        $customstr = '';
        if (isset($typeconfig['customparameters'])) {
            $customstr = $typeconfig['customparameters'];
        }
        $requestparams = array_merge($requestparams, lti_build_custom_parameters($toolproxy, $tool, $instance, $allparams, $customstr,
            $instance->instructorcustomparameters, $islti2));

        $launchcontainer = lti_get_launch_container($instance, $typeconfig);
        $returnurlparams = array('course' => $course->id,
                                 'launch_container' => $launchcontainer,
                                 'instanceid' => $instance->id,
                                 'sesskey' => sesskey());

        // Add the return URL. We send the launch container along to help us avoid frames-within-frames when the user returns.
        $url = new \moodle_url('/mod/lti/return.php', $returnurlparams);
        $returnurl = $url->out(false);

        if (isset($typeconfig['forcessl']) && ($typeconfig['forcessl'] == '1')) {
            $returnurl = lti_ensure_url_is_https($returnurl);
        }

        $target = '';
        switch($launchcontainer) {
            case LTI_LAUNCH_CONTAINER_EMBED:
            case LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS:
                $target = 'iframe';
                break;
            case LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW:
                $target = 'frame';
                break;
            case LTI_LAUNCH_CONTAINER_WINDOW:
                $target = 'window';
                break;
        }
        if (!empty($target)) {
            $requestparams['launch_presentation_document_target'] = $target;
        }

        $requestparams['launch_presentation_return_url'] = $returnurl;

        // Allow request params to be updated by sub-plugins.
        $plugins = core_component::get_plugin_list('ltisource');
        foreach (array_keys($plugins) as $plugin) {
            $pluginparams = component_callback('ltisource_'.$plugin, 'before_launch',
                array($instance, $endpoint, $requestparams), array());

            if (!empty($pluginparams) && is_array($pluginparams)) {
                $requestparams = array_merge($requestparams, $pluginparams);
            }
        }

        if (!empty($key) && !empty($secret)) {
            $parms = lti_sign_parameters($requestparams, $endpoint, "POST", $key, $secret);

            $endpointurl = new \moodle_url($endpoint);
            $endpointparams = $endpointurl->params();

            // Strip querystring params in endpoint url from $parms to avoid duplication.
            if (!empty($endpointparams) && !empty($parms)) {
                foreach (array_keys($endpointparams) as $paramname) {
                    if (isset($parms[$paramname])) {
                        unset($parms[$paramname]);
                    }
                }
            }

        } else {
            // If no key and secret, do the launch unsigned.
            $returnurlparams['unsigned'] = '1';
            $parms = $requestparams;
        }

        return array($endpoint, $parms);
    }
}

if (!function_exists('lti_build_standard_request')) {
    /**
     * This function builds the standard parameters for an LTI 1 or 2 request that must be sent to the tool producer
     *
     * @param object    $instance       Basic LTI instance object
     * @param string    $orgid          Organisation ID
     * @param boolean   $islti2         True if an LTI 2 tool is being launched
     *
     * @return array                    Request details
     */
    function lti_build_standard_request($instance, $orgid, $islti2) {
        global $CFG;

        $requestparams = array();

        $requestparams['resource_link_id'] = $instance->id;
        if (property_exists($instance, 'resource_link_id') and !empty($instance->resource_link_id)) {
            $requestparams['resource_link_id'] = $instance->resource_link_id;
        }

        $requestparams['launch_presentation_locale'] = current_language();

        // Make sure we let the tool know what LMS they are being called from.
        $requestparams['ext_lms'] = 'moodle-2';
        $requestparams['tool_consumer_info_product_family_code'] = 'moodle';
        $requestparams['tool_consumer_info_version'] = strval($CFG->version);

        // Add oauth_callback to be compliant with the 1.0A spec.
        $requestparams['oauth_callback'] = 'about:blank';

        if (!$islti2) {
            $requestparams['lti_version'] = 'LTI-1p0';
        } else {
            $requestparams['lti_version'] = 'LTI-2p0';
        }
        $requestparams['lti_message_type'] = 'basic-lti-launch-request';

        if ($orgid) {
            $requestparams["tool_consumer_instance_guid"] = $orgid;
        }
        if (!empty($CFG->mod_lti_institution_name)) {
            $requestparams['tool_consumer_instance_name'] = $CFG->mod_lti_institution_name;
        } else {
            $requestparams['tool_consumer_instance_name'] = get_site()->fullname;
        }

        return $requestparams;
    }
}

if (!function_exists('lti_build_custom_parameters')) {
    /**
     * This function builds the custom parameters
     *
     * @param object    $toolproxy      Tool proxy instance object
     * @param object    $tool           Tool instance object
     * @param object    $instance       Tool placement instance object
     * @param array     $params         LTI launch parameters
     * @param string    $customstr      Custom parameters defined for tool
     * @param string    $instructorcustomstr      Custom parameters defined for this placement
     * @param boolean   $islti2         True if an LTI 2 tool is being launched
     *
     * @return array                    Custom parameters
     */
    function lti_build_custom_parameters($toolproxy, $tool, $instance, $params, $customstr, $instructorcustomstr, $islti2) {

        // Concatenate the custom parameters from the administrator and the instructor
        // Instructor parameters are only taken into consideration if the administrator
        // has given permission.
        $custom = array();
        if ($customstr) {
            $custom = lti_split_custom_parameters($toolproxy, $tool, $params, $customstr, $islti2);
        }
        if (!isset($typeconfig['allowinstructorcustom']) || $typeconfig['allowinstructorcustom'] != LTI_SETTING_NEVER) {
            if ($instructorcustomstr) {
                $custom = array_merge(lti_split_custom_parameters($toolproxy, $tool, $params,
                    $instructorcustomstr, $islti2), $custom);
            }
        }
        if ($islti2) {
            $custom = array_merge(lti_split_custom_parameters($toolproxy, $tool, $params,
                $tool->parameter, true), $custom);
            $settings = lti_get_tool_settings($tool->toolproxyid);
            $custom = array_merge($custom, lti_get_custom_parameters($toolproxy, $tool, $params, $settings));
            $settings = lti_get_tool_settings($tool->toolproxyid, $instance->course);
            $custom = array_merge($custom, lti_get_custom_parameters($toolproxy, $tool, $params, $settings));
            $settings = lti_get_tool_settings($tool->toolproxyid, $instance->course, $instance->id);
            $custom = array_merge($custom, lti_get_custom_parameters($toolproxy, $tool, $params, $settings));
        }

        return $custom;
    }
}

require_once($CFG->dirroot . '/mod/survey/lib.php');

if (!function_exists('survey_order_questions')) {

    /**
     * Helper function for ordering a set of questions by the given ids.
     *
     * @param  array $questions     array of questions objects
     * @param  array $questionorder array of questions ids indicating the correct order
     * @return array                list of questions ordered
     * @since Moodle 3.0
     */
    function survey_order_questions($questions, $questionorder) {

        $finalquestions = array();
        foreach ($questionorder as $qid) {
            $finalquestions[] = $questions[$qid];
        }
        return $finalquestions;
    }
}


if (!function_exists('survey_translate_question')) {

    /**
     * Translate the question texts and options.
     *
     * @param  stdClass $question question object
     * @return stdClass question object with all the text fields translated
     * @since Moodle 3.0
     */
    function survey_translate_question($question) {

        if ($question->text) {
            $question->text = get_string($question->text, "survey");
        }

        if ($question->shorttext) {
            $question->shorttext = get_string($question->shorttext, "survey");
        }

        if ($question->intro) {
            $question->intro = get_string($question->intro, "survey");
        }

        if ($question->options) {
            $question->options = get_string($question->options, "survey");
        }
        return $question;
    }
}

if (!function_exists('survey_get_questions')) {

    /**
     * Returns the questions for a survey (ordered).
     *
     * @param  stdClass $survey survey object
     * @return array list of questions ordered
     * @since Moodle 3.0
     * @throws  moodle_exception
     */
    function survey_get_questions($survey) {
        global $DB;

        $questionids = explode(',', $survey->questions);
        if (! $questions = $DB->get_records_list("survey_questions", "id", $questionids)) {
            throw new moodle_exception('cannotfindquestion', 'survey');
        }

        return survey_order_questions($questions, $questionids);
    }
}

if (!function_exists('survey_get_subquestions')) {

    /**
     * Returns subquestions for a given question (ordered).
     *
     * @param  stdClass $question questin object
     * @return array list of subquestions ordered
     * @since Moodle 3.0
     */
    function survey_get_subquestions($question) {
        global $DB;

        $questionids = explode(',', $question->multi);
        $questions = $DB->get_records_list("survey_questions", "id", $questionids);

        return survey_order_questions($questions, $questionids);
    }
}

if (!function_exists('survey_save_answers')) {

    /**
     * Save the answer for the given survey
     *
     * @param  stdClass $survey   a survey object
     * @param  array $answersrawdata the answers to be saved
     * @param  stdClass $course   a course object (required for trigger the submitted event)
     * @param  stdClass $context  a context object (required for trigger the submitted event)
     * @since Moodle 3.0
     */
    function survey_save_answers($survey, $answersrawdata, $course, $context) {
        global $DB, $USER;

        $answers = array();

        // Sort through the data and arrange it.
        // This is necessary because some of the questions may have two answers, eg Question 1 -> 1 and P1.
        foreach ($answersrawdata as $key => $val) {
            if ($key <> "userid" && $key <> "id") {
                if (substr($key, 0, 1) == "q") {
                    $key = clean_param(substr($key, 1), PARAM_ALPHANUM);   // Keep everything but the 'q', number or P number.
                }
                if (substr($key, 0, 1) == "P") {
                    $realkey = (int) substr($key, 1);
                    $answers[$realkey][1] = $val;
                } else {
                    $answers[$key][0] = $val;
                }
            }
        }

        // Now store the data.
        $timenow = time();
        $answerstoinsert = array();
        foreach ($answers as $key => $val) {
            if ($key != 'sesskey') {
                $newdata = new stdClass();
                $newdata->time = $timenow;
                $newdata->userid = $USER->id;
                $newdata->survey = $survey->id;
                $newdata->question = $key;
                if (!empty($val[0])) {
                    $newdata->answer1 = $val[0];
                } else {
                    $newdata->answer1 = "";
                }
                if (!empty($val[1])) {
                    $newdata->answer2 = $val[1];
                } else {
                    $newdata->answer2 = "";
                }

                $answerstoinsert[] = $newdata;
            }
        }

        if (!empty($answerstoinsert)) {
            $DB->insert_records("survey_answers", $answerstoinsert);
        }

        $params = array(
            'context' => $context,
            'courseid' => $course->id,
            'other' => array('surveyid' => $survey->id)
        );
        $event = \mod_survey\event\response_submitted::create($params);
        $event->trigger();
    }
}
