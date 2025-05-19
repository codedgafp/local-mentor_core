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
 * Class entity
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core;

use context_system;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/local/mentor_core/classes/model/model.php');
require_once($CFG->dirroot . '/course/format/edadmin/lib.php');
require_once($CFG->libdir . '/tablelib.php');

class entity extends model {

    /**
     * Name of the category containing the sub entities
     */
    public const SUB_ENTITY_CATEGORY = 'Espaces';

    /**
     * Edadmin courses not created for sub-entity
     */
    public const SUB_ENTITY_EDADMIN_EXCEPT
        = [
            'user',
            'trainings',
            'session',
        ];

    /**
     * List all required capabilities to be considered as a manager.
     */
    public const ENTITY_MANAGER_CAPABILITIES
        = [
            'local/entities:manageentity',
            'moodle/category:manage',
        ];

    /**
     * Edadmin courses not created for sub-entity but must be seen
     * It will be the courses of the main entity that will be seen
     */
    public const SUB_ENTITY_COURSES_EXCLUDED_BUT_MUST_SEEN
        = [
            'trainings',
            'session',
        ];

    /**
     * @var string
     */
    public $name;

    /**
     * @var array
     */
    private $courses;

    /**
     * @var array
     */
    protected $members;

    /**
     * @var \stdClass
     */
    private $cohort;

    /**
     * @var int
     */
    public $parentid;

    /**
     * @var string
     */
    public $shortname;

    /**
     * @var string
     */
    public $entitypath;

    public $subentities = null;

    public $lastedadmincourses = [];

    protected $context;

    /**
     * entity constructor.
     *
     * @param int|\stdClass $entityorid an entityid is a course category id
     * @throws \moodle_exception
     * @throws \Exception
     */
    public function __construct($entityorid) {
        parent::__construct();

        if (is_object($entityorid)) {
            $category = $entityorid;
        } else {
            $category = $this->dbinterface->get_course_category_by_id($entityorid);
        }

        // Check if the category is a main or a sub category of a main category.
        if (!$category || ((int) $category->parent !== 0 && $category->parentname != self::SUB_ENTITY_CATEGORY)) {
            throw new \moodle_exception('errorcategoryisnotentity', 'local_mentor_core', '', $category);
        }

        $this->id = $category->id;
        $this->name = $category->name;
        $this->parentid = $category->parentid;
        $this->shortname = $category->shortname;
        $this->entitypath = $this->get_entity_path();
        $this->members = [];
    }

    /**
     * Update entity
     *
     * @param \stdClass $data
     * @param \moodleform $mform
     * @return boolean
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function update($data, $mform = null) {
        global $CFG;
        require_once($CFG->dirroot . '/local/profile/lib.php');
        require_once($CFG->dirroot . '/local/mentor_core/lib.php');

        // Cast data into stdClass.
        if (is_array($data)) {
            $data = (object) $data;
        }

        $data->name = trim($data->name);

        // Check if the name is not empty.
        if (isset($data->name) && empty($data->name)) {
            throw new \Exception("Entity name is empty");
        }

        $namehaschanged = false;

        if ($this->name != $data->name) {
            $namehaschanged = true;
            $oldname = $this->name;
            $this->name = $data->name;
        }

        $this->shortname = isset($data->shortname) ? trim($data->shortname) : $this->shortname;

        $this->dbinterface->update_entity($this);

        // The entity name has changed.
        if ($namehaschanged) {

            // Update cohort name.
            $this->rename_cohort();

            // Update edadmin course names.
            $this->update_its_sub_entity_edadmin_courses_name();

            if ($this->is_main_entity()) {
                // Update the main entity of all users.
                $this->dbinterface->update_main_entities_name($oldname, $data->name);

                // Update the main secondary of all users.
                $this->dbinterface->update_secondary_entities_name($oldname, $data->name);

                // Update contact page name.
                $this->update_contact_page_name();

                // Update presentation page name.
                $this->update_presentation_page_name();

                // Update all the courses of its sub-entity.
                $this->update_its_sub_entity_edadmin_courses_name();
            }
        }

        // If the entity is updated by a moodle form.
        if (!empty($mform) && $this->is_main_entity()) {

            // Manage entity logo.
            $name = $mform->get_new_filename('logo');

            if (!empty($name)) {

                // Remove the old logo.
                if ($oldlogo = $this->get_logo()) {
                    $oldlogo->delete();
                }

                // Save the new logo.
                $file = $mform->save_stored_file('logo', $this->get_context()->id, 'local_entities', 'logo', 0);

                // Resize the file.
                local_mentor_core_resize_picture($file, 400);
            }
        }

        if ($this->is_main_entity()) {
            // Update list of available entities within the user profile.
            local_mentor_core_update_entities_list();

            // Create contact page if not exists.
            $pagescategoryid = $this->get_entity_pages_category();
            if (!$this->dbinterface->get_category_course_by_idnumber($pagescategoryid, 'contact_' . $this->id)) {
                $this->create_contact_page();
            }
        } else {
            // Update parent id.
            if (isset($data->parentid) && $this->parentid != $data->parentid) {
                $this->change_parent_entity($data->parentid);
            }
        }

        return true;
    }

    /**
     * Get entity logo
     *
     * @return bool|\stored_file
     * @throws \coding_exception
     */
    public function get_logo() {
        $fs = get_file_storage();

        $areafiles = $fs->get_area_files($this->get_context()->id, 'local_entities', 'logo', 0, "itemid, filepath, filename",
            false);

        if (count($areafiles) == 0) {
            return false;
        }

        return current($areafiles);
    }

    /**
     * Rename entity cohort's name
     *
     * @param string $name
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function rename_cohort($name = null) {

        if (empty($name)) {
            $name = $this->name;
        }

        // Update cohort name.
        $cohort = $this->get_cohort();
        $cohort->name = $name;
        return $this->dbinterface->update_cohort($cohort);
    }

    /**
     * Create a contact page for the entity
     *
     * @return \stdClass
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function create_contact_page() {

        $course = new \stdClass();
        $course->fullname = $this->name . ' - Page de contact';
        $course->shortname = $this->get_entity_path() . ' - Page de contact';
        $course->category = $this->get_entity_pages_category();
        $course->format = 'singleactivity';
        $course->activitytype = 'page';
        $course->idnumber = 'contact_' . $this->id;

        // Create a single activity course of type Page.
        $course = create_course($course);

        // Allow a guest access to the course.
        $plugin = enrol_get_plugin('guest');

        $instance = (object) $plugin->get_instance_defaults();
        $instance->status = 0;
        $instance->id = '';
        $instance->courseid = $course->id;
        $instance->expirythreshold = 0;
        $instance->enrolstartdate = 0;
        $instance->enrolenddate = 0;
        $instance->timecreated = time();
        $instance->timemodified = time();

        $fields = (array) $instance;

        $plugin->add_instance($course, $fields);

        // Create cohort sync enrol to entity page contact.
        $this->create_cohort_enrol_page_contact();

        // Return the created course object.
        return $course;
    }

    /**
     * Create cohort sync enrol to entity page contact if not exist.
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function create_cohort_enrol_page_contact() {
        $course = $this->get_contact_page_course();

        if (!$course) {
            $this->create_contact_page();
            return;
        }

        $cohortid = $this->get_cohort()->id;

        // Check if enrol instance exist.
        $enrolmentinstances = enrol_get_instances($course->id, false);
        foreach ($enrolmentinstances as $enrolmentinstance) {
            if ($enrolmentinstance->enrol === 'cohort' && $enrolmentinstance->customint1 = $cohortid) {
                return;
            }
        }

        // Create cohort sync enrol to the course.
        $plugin = enrol_get_plugin('cohort');

        $instance = (object) $plugin->get_instance_defaults();
        $instance->status = 0;
        $instance->id = '';
        $instance->courseid = $course->id;
        $instance->expirythreshold = 0;
        $instance->enrolstartdate = 0;
        $instance->enrolenddate = 0;
        $instance->timecreated = time();
        $instance->timemodified = time();
        $instance->customint1 = $this->get_cohort()->id;
        $instance->customint2 = 0;

        $fields = (array) $instance;

        $plugin->add_instance($course, $fields);
    }

    /**
     * Create a presentation page for the entity
     *
     * @return \stdClass
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function create_presentation_page() {
        global $USER;

        // Check capabilities.
        if (!$this->is_manager($USER)) {
            return false;
        }

        // Course already exists.
        if ($this->get_presentation_page_course()) {
            return false;
        }

        $course = new \stdClass();
        $course->fullname = 'Présentation de l\'espace Mentor ' . $this->name;
        $course->shortname = 'Présentation de l\'espace Mentor ' . $this->name;
        $course->category = $this->get_entity_pages_category();
        $course->format = 'topics';
        $course->idnumber = 'presentation_' . $this->id;

        // Create a single activity course of type Page.
        $course = create_course($course);

        // Allow a guest access to the course.
        $plugin = enrol_get_plugin('guest');

        $instance = (object) $plugin->get_instance_defaults();
        $instance->status = 0;
        $instance->id = '';
        $instance->courseid = $course->id;
        $instance->expirythreshold = 0;
        $instance->enrolstartdate = 0;
        $instance->enrolenddate = 0;
        $instance->timecreated = time();
        $instance->timemodified = time();

        $fields = (array) $instance;

        $plugin->add_instance($course, $fields);

        // Return the created course object.
        return $course;
    }

    /**
     * Get entity contact page url
     *
     * @return \moodle_url
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_contact_page_url() {

        if (!$course = $this->get_contact_page_course()) {
            $course = $this->create_contact_page();
        }

        return new \moodle_url('/course/view.php', ['id' => $course->id]);
    }

    /**
     * Get entity presentation page url if exists
     *
     * @return bool|\moodle_url
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_presentation_page_url() {
        if (!$course = $this->get_presentation_page_course()) {
            return false;
        }

        $url = new \moodle_url('/course/view.php', ['id' => $course->id]);

        // The course hasn't a topics format.
        if ($course->format != 'topics') {
            return $url;
        }

        $coursedisplay = $this->dbinterface->get_course_format_option($course->id, 'coursedisplay');

        // The course is configured to display all sections in the same page.
        if ($coursedisplay != 1) {
            return $url;
        }

        $firstsection = 1;

        // Can we view the first section.
        if ($this->dbinterface->is_course_section_visible($course->id, $firstsection)) {
            $url->param('section', $firstsection);
        }

        return $url;
    }

    /**
     * Check if contact page is initialised
     *
     * @return boolean
     * @throws \dml_exception
     */
    public function contact_page_is_initialized() {
        // Check if the contact course has been created.
        if (!$course = $this->get_contact_page_course()) {
            return false;
        }

        // Check if the page module has been created.
        if (empty(get_course_mods($course->id))) {
            return false;
        }

        return true;
    }

    /**
     * Return contact page course
     *
     * @return false|\stdClass
     * @throws \dml_exception
     */
    public function get_contact_page_course() {
        $pagescategoryid = $this->get_entity_pages_category();

        if (!$course = $this->dbinterface->get_category_course_by_idnumber($pagescategoryid, 'contact_' . $this->id)) {
            return false;
        }

        return $course;
    }

    /**
     * Get presentation course
     *
     * @return bool|\stdClass
     * @throws \dml_exception
     */
    public function get_presentation_page_course() {
        $pagescategoryid = $this->get_entity_pages_category();

        if (!$course = $this->dbinterface->get_category_course_by_idnumber($pagescategoryid, 'presentation_' . $this->id)) {
            return false;
        }

        return $course;
    }

    /**
     * Update cotact page information
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function update_contact_page_name() {
        $course = $this->get_contact_page_course();
        $course->fullname = $this->name . ' - Page de contact';
        $course->shortname = $this->get_entity_path(true) . ' - Page de contact';
        update_course($course);
    }

    /**
     * Update presentation page
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function update_presentation_page_name() {
        $course = $this->get_presentation_page_course();

        if ($course) {
            $course->fullname = 'Présentation de l\'espace Mentor ' . $this->name;
            $course->shortname = 'Présentation de l\'espace Mentor ' . $this->name;
            update_course($course);
        }
    }

    /**
     * Return edadmin course list by this entity
     *
     * @param string $type of a given course type
     * @return array
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_edadmin_courses($type = null) {

        if (!empty($type) && isset($this->lastedadmincourses[$type])) {
            return $this->lastedadmincourses[$type];
        }

        $db = database_interface::get_instance();

        // Get all edadmin course of the entity category.
        $edadmincourses = $db->get_edadmin_courses_by_category($this->id);

        $ismainentity = $this->is_main_entity();

        if (!$ismainentity) {
            $parententity = $this->get_main_entity();
            $parentedadmincourses = $parententity->get_edadmin_courses();

            // Add main entity course edadmin link.
            foreach (self::SUB_ENTITY_COURSES_EXCLUDED_BUT_MUST_SEEN as $edadmincoursename) {

                if (!empty($type) && isset($parententity->lastedadmincourses[$edadmincoursename])) {
                    return $parententity->lastedadmincourses[$edadmincoursename];
                }

                $this->lastedadmincourses[$edadmincoursename] = $parentedadmincourses[$edadmincoursename];

                // Add sub entity id information to url params.
                $this->lastedadmincourses[$edadmincoursename]['link'] .= '&subentityid=' . $this->id;
            }
        }

        $cohortid = $this->get_cohort()->id;
        $ismanager = $this->is_manager();

        foreach ($edadmincourses as $edadmincourse) {

            $formattype = $edadmincourse->formattype;

            // When the user only manages courses and sessions.
            if (!$ismanager && in_array($formattype, ['entities', 'user'])) {
                continue;
            }

            // If is not main entity, skip the Edadmin courses that are in exception.
            // If this course is to be viewed, it will have been added the course of the previous main entity.
            if (!$ismainentity && in_array($formattype, self::SUB_ENTITY_EDADMIN_EXCEPT)) {
                continue;
            }

            if (!isset($this->lastedadmincourses[$formattype])) {
                $this->lastedadmincourses[$formattype] = [];
            }

            // Set course data.
            $this->lastedadmincourses[$formattype]['cohortid'] = $cohortid;
            $this->lastedadmincourses[$formattype]['link'] = (new \moodle_url('/course/view.php',
                ['id' => $edadmincourse->id])
            )->out();
            $this->lastedadmincourses[$formattype]['name'] = $edadmincourse->fullname;
            $this->lastedadmincourses[$formattype]['shortname'] = $edadmincourse->shortname;
            $this->lastedadmincourses[$formattype]['id'] = $edadmincourse->id;

            if (!empty($type) && isset($this->lastedadmincourses[$type])) {
                return $this->lastedadmincourses[$type];
            }
        }

        // Return a specific course type.
        if (!empty($type)) {
            if (!isset($this->lastedadmincourses[$type])) {
                return $this->get_edadmin_courses($type);
            }

            return $this->lastedadmincourses[$type];
        }

        // Return all edadmin courses.
        return $this->lastedadmincourses;

    }

    /**
     * Return edadmin course url by type
     *
     * @param string $type of a given course type
     * @return string
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_edadmin_courses_url($type = 'trainings') {
        global $CFG;

        if (
            !empty($type) &&
            isset($this->lastedadmincourses[$type]) &&
            isset($this->lastedadmincourses[$type]['link'])
        ) {
            return $this->lastedadmincourses[$type]['link'];
        }

        $db = database_interface::get_instance();

        if (!$this->is_main_entity()) {
            $parententity = $this->get_main_entity();
            $parenturl = $parententity->get_edadmin_courses_url($type);
            return !empty($parenturl) ? $parenturl . '&subentityid=' . $this->id : '';
        }

        // Get all edadmin course of the entity category.
        $edadmincourses = $db->get_edadmin_courses_by_category($this->id);
        foreach ($edadmincourses as $edadmincourse) {
            if ($edadmincourse->formattype === $type) {
                return $CFG->wwwroot . '/course/view.php?id=' . $edadmincourse->id;
            }
        }

        return '';
    }

    /**
     * Get the context of the entity
     *
     * @return \context_coursecat
     */
    public function get_context() {
        if (empty($this->context)) {
            $this->context = \context_coursecat::instance($this->id);
        }

        return $this->context;
    }

    /**
     * Get the cohort related to the entity
     *
     * @return bool|\stdClass cohort
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_cohort() {

        // Fetch the cohort for the first time.
        if (empty($this->cohort)) {
            $cohort = $this->dbinterface->get_cohort_by_context_id($this->get_context()->id);

            // Create a new cohort if missing.
            if (empty($cohort)) {
                return $this->create_cohort();
            }

            $this->cohort = $cohort;
        }

        return $this->cohort;
    }

    /**
     * Assign a new category manager
     *
     * @param int $userid
     * @return bool for success
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function assign_manager($userid) {
        global $CFG;
        require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');

        // Throw an exception if the user does not exist.
        if (!\core_user::get_user($userid)) {
            throw new \moodle_exception('errorunknownuser', 'local_mentor_core', '', $userid);
        }

        // Assign user manager to entity.
        $role = $this->get_manager_role();

        $contextid = $this->get_context()->id;
        $roleid = $role->id;

        role_assign($roleid, $userid, $contextid);

        // Add the manager as an entity member.
        $this->members[$userid] = profile_api::get_profile($userid);

        // When no exception occured.
        return true;
    }

    /**
     * Get manager role
     *
     * @return \stdClass
     * @throws \dml_exception
     */
    public function get_manager_role() {
        return $this->dbinterface->get_role_by_name('manager');
    }

    /**
     * Create a cohort related to the category
     *
     * @return \stdClass the entity cohort
     * @throws \coding_exception
     */
    private function create_cohort() {
        $cohort = new \stdClass();
        $cohort->name = $this->name;
        $cohort->contextid = $this->get_context()->id;
        $cohort->id = cohort_add_cohort($cohort);

        $this->cohort = $cohort;

        return $this->cohort;
    }

    /**
     * Create edadmin courses related to the entity
     *
     * @return array
     * @throws \moodle_exception
     */
    public function create_edadmin_courses_if_missing() {
        global $CFG;
        require_once($CFG->dirroot . '/course/externallib.php');

        // Get the list of edadmin types.
        $typenamelist = \format_edadmin::get_all_type_name();

        // Get all existing edadmin courses.
        $existingcourses = $this->get_edadmin_courses();

        $courses = [];

        $cohortid = $this->get_cohort()->id;
        $ismanager = $this->is_manager();
        $ismainentity = $this->is_main_entity();
        $entitypath = $this->get_entity_path();

        foreach ($typenamelist as $typename) {

            $typename = substr($typename, strrpos($typename, '::'));

            // When the user only manages courses and sessions.
            if (!$ismanager && in_array($typename, ['entities', 'user'])) {
                continue;
            }

            // If is not main entity, skip the Edadmin courses that are in exception.
            if (!$ismainentity && in_array($typename, self::SUB_ENTITY_EDADMIN_EXCEPT)) {
                continue;
            }

            // If the edadmin course does not exist, then prepare all data to create it later.
            if (!isset($existingcourses[$typename])) {

                $langstringtypename = new \lang_string('edadmin' . $typename . 'coursetitle', 'local_' . $typename);

                // Minimum data to create a course.
                $newcourse = [
                    'fullname' => $this->name . ' - ' . $langstringtypename->out(),
                    'shortname' => $entitypath . ' - ' . $langstringtypename->out(),
                    'categoryid' => $this->id,
                    'format' => 'edadmin',
                    'courseformatoptions' =>
                        [
                            [
                                'name' => 'formattype',
                                'value' => $typename,
                            ],
                            [
                                'name' => 'categorylink',
                                'value' => $this->id,
                            ],
                             [
                                'name' => 'cohortlink',
                                'value' => $cohortid,
                            ],
                        ],
                ];

                // Information list for the creation of courses.
                $courses[] = $newcourse;
            }
        }

        // Create all missing edadmin courses.
        if (!empty($courses)) {
            $courses = \core_course_external::create_courses($courses);
        }

        // Set edadmin courses object.
        foreach ($typenamelist as $typename) {
            $this->{$typename} = $existingcourses[$typename] ?? '';
            $this->courses[$typename] = $this->{$typename};
        }

        // Return the created courses.
        return $courses;
    }

    /**
     * Update edadmin courses with the name of the entity
     *
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function update_edadmin_courses_name() {
        // Get all edadmin courses of the entity.
        $courses = $this->get_edadmin_courses();

        $ismainentity = $this->is_main_entity();
        $entitypath = $this->get_entity_path(true);

        foreach ($courses as $formattype => $course) {

            if (!$ismainentity && in_array($formattype, self::SUB_ENTITY_EDADMIN_EXCEPT)) {
                continue;
            }

            $langstringtypename = new \lang_string('edadmin' . $formattype . 'coursetitle', 'local_' . $formattype);
            $fullname = $this->name . ' - ' . $langstringtypename->out();
            $shortname = $entitypath . ' - ' . $langstringtypename->out();

            // Rename the edadmin course.
            $this->dbinterface->update_course_name($course['id'], $shortname, $fullname);
        }

        return true;
    }

    /**
     * Update its sub entity edadmin courses with the name of the entity
     *
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function update_its_sub_entity_edadmin_courses_name() {

        // Update juste its edamdin course if is not main entity.
        if (!$this->is_main_entity()) {
            return $this->update_edadmin_courses_name();
        }

        // Update its edadmin course.
        $this->update_edadmin_courses_name();

        // Update all edadmin courses of its sub-entity.
        $subentities = $this->get_sub_entities();

        foreach ($subentities as $subentity) {
            if (!$subentity->update_edadmin_courses_name()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a user can manage the entity
     *
     * @param \stdClass $user
     * @return bool
     * @throws \coding_exception
     */
    public function is_manager($user = null) {
        global $USER;

        if (is_null($user)) {
            $user = $USER;
        }

        $context = $this->get_context();

        foreach (self::ENTITY_MANAGER_CAPABILITIES as $capability) {

            // If a capability is missing then return false.
            if (!has_capability($capability, $context, $user)) {
                return false;
            }
        }

        return true;
    }

    /**
     * CHeck if the user is a member of the entity cohort
     *
     * @param int $userid
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function is_member($userid) {

        if (!$this->is_main_entity()) {
            return [];
        }

        $cohort = $this->get_cohort();

        return $this->dbinterface->check_if_user_is_cohort_member($userid, $cohort->id);
    }

    /**
     * Add a user to the entity's cohort
     *
     * @param \stdCLass|profile $user
     * @return bool|int the id of the user
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function add_member($user) {

        // It is not allowed to add members to a sub-entity.
        if (!$this->is_main_entity()) {
            throw new \moodle_exception('subentityaddmember', 'local_mentor_core', '');
        }

        $cohort = $this->get_cohort();

        if (!$this->dbinterface->add_cohort_member($cohort->id, $user->id)) {
            return false;
        }

        return $user->id;
    }

    /**
     * Remove a user from the entity's cohort
     *
     * @param \stdClass $user
     * @return false|int
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function remove_member($user) {
        $cohort = $this->get_cohort();

        if (!$this->dbinterface->remove_cohort_member($cohort->id, $user->id)) {
            return false;
        }

        return $user->id;

    }

    /**
     * Get entity members
     *
     * @param $data object of paramets 
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_members($data = new \stdClass()) {
        global $CFG;
        $data->suspendedusers = isset($data->suspendedusers) ? $data->suspendedusers : 'all';
        $data->externalusers = isset($data->externalusers) ? $data->externalusers : 'all';
        require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');
        
        if (!$this->is_main_entity()) {
            return [];
        }

        $cohort = $this->get_cohort();

        $cohortmembers = $this->dbinterface->get_cohort_members_by_cohort_id($cohort->id, $data);

        $constextsystemid = context_system::instance()->id;
        
        // Cache for user roles.
        $user_roles_cache = [];

        // Filter cohort members based on external user role.
        $filteredmembers = array_filter($cohortmembers, function($member) use ($data, $constextsystemid, &$user_roles_cache) {
            if (!isset($user_roles_cache[$member->id])) {
                $user_roles_cache[$member->id] = $this->dbinterface->user_has_role_in_context($member->id, 'utilisateurexterne', $constextsystemid);
            }
            $is_external_user = $user_roles_cache[$member->id];
            return ($data->externalusers == 'true' && $is_external_user) ||
                ($data->externalusers == 'false' && !$is_external_user) ||
                ($data->externalusers == 'all');
        });

        // Populate members with filtered cohort members.
        foreach ($filteredmembers as $member) {
            $this->members[$member->id] = profile_api::get_profile($member);
        }
        return $this->members;

    }

    /**
     * Get members count 
     * @param object $data
     * @param bool $enablefilters
     * @return int
     */
    public function get_members_count($data, $enablefilters){
        $cohort = $this->get_cohort();
        return $this->dbinterface->get_cohort_members_count_by_cohort_id($cohort->id, $data, $enablefilters);
    }

    /**
     * Get all trainings of the entity.
     *
     * @return \stdClass[] - objects from training table
     * @throws \dml_exception
     */
    public function get_trainings() {
        return $this->dbinterface->get_trainings_by_entity_id($this->id);
    }

    /**
     * Get category if exist or create it named "Formations" and child of the entity.
     *
     * @param string $categoryname
     * @return int id of the category
     * @throws \dml_exception
     */
    public function get_entity_formation_category($categoryname = 'Formations') {
        return $this->get_or_create_subcategory($categoryname);
    }

    /**
     * Get category if exist or create it named "Formation" and child of the entity.
     *
     * @param string $categoryname
     * @return int id of the category
     * @throws \dml_exception
     */
    public function get_entity_session_category($categoryname = 'Sessions') {
        return $this->get_or_create_subcategory($categoryname);
    }

    /**
     * Get category if exist or create it named "Espaces" and child of the entity.
     *
     * @param string $categoryname
     * @return int id of the category
     * @throws \dml_exception
     */
    public function get_entity_space_category($categoryname = self::SUB_ENTITY_CATEGORY) {
        return $this->get_or_create_subcategory($categoryname);
    }

    /**
     * Create a subcategory of the entity
     *
     * @param string $categoryname
     * @return int
     * @throws \dml_exception
     */
    private function get_or_create_subcategory($categoryname) {
        $entitychild = $this->dbinterface->get_entity_category_by_name($this->id, $categoryname);

        // If not exist, create the category.
        if (!$entitychild) {
            try {
                // Init object for children category.
                $trainingchild = new \stdClass();
                $trainingchild->name = $categoryname;
                $trainingchild->depth = 2;
                $trainingchild->parent = $this->id;
                $trainingchild->sortorder = MAX_COURSES_IN_CATEGORY;
                $trainingchild->timemodified = time();

                // Create children category.
                $category = \core_course_category::create($trainingchild);
            } catch (\Exception $e) {
                throw new Exception("Unable to create an entity training child category, please try again.");
            }

            return $category->id;
        }

        return $entitychild->id;
    }

    /**
     * Get category if exist or create it named "Pages" and child of the entity.
     *
     * @param string $categoryname
     * @return int id of the category
     * @throws \dml_exception
     */
    public function get_entity_pages_category($categoryname = 'Pages') {
        return $this->get_or_create_subcategory($categoryname);
    }

    /**
     * Get data for the entity form
     *
     * @return \stdClass
     * @throws \coding_exception
     */
    public function get_form_data() {
        global $CFG;

        // Get entity by id.
        $entityobj = new \stdClass();
        $entityobj->namecategory = $this->name;
        $entityobj->idcategory = $this->id;
        $entityobj->shortname = $this->shortname;

        // If is not main entity.
        if (!$this->is_main_entity()) {
            $entityobj->parentid = $this->parentid;
            return $entityobj;
        }

        // Prefill the logo file picker.
        if ($logo = $this->get_logo()) {
            $draftitemid = file_get_submitted_draft_itemid('logo');

            $acceptedtypes = preg_split('/\s*,\s*/', trim($CFG->courseoverviewfilesext), -1, PREG_SPLIT_NO_EMPTY);

            file_prepare_draft_area($draftitemid, $this->get_context()->id, 'local_entities', 'logo', 0,
                ['accepted_types' => $acceptedtypes, 'subdirs' => 0, 'maxfiles' => 1, 'maxbytes' => 1024000]);

            $entityobj->logo = $draftitemid;
        }

        return $entityobj;
    }

    /**
     * Return entity name
     *
     * @return string
     */
    public function get_name(): string {
        return $this->name;
    }

    /**
     * Check if entity is main entity
     *
     * @return bool
     */
    public function is_main_entity() {
        return $this->parentid === null;
    }

    /**
     * Return course category of entity
     *
     * @return \core_course_category|false|null
     * @throws \moodle_exception
     */
    public function get_course_category() {
        return \core_course_category::get($this->id);
    }

    /**
     * Change parent entity
     *
     * @param int $newparententityid
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function change_parent_entity($newparententityid) {
        $coursecat = $this->get_course_category();
        $newparententity = new entity($newparententityid);
        $coursecat->change_parent($newparententity->get_entity_space_category());
        $this->parentid = $newparententityid;
    }

    /**
     * Return entity path (Different from the category path)
     *
     * @param bool $refresh
     * @return mixed|string
     * @throws \moodle_exception
     */
    public function get_entity_path($refresh = false) {
        // If is already initialized.
        if (isset($this->entitypath) && !$refresh) {
            return $this->entitypath;
        }

        // Just return name if is main entity.
        if ($this->is_main_entity()) {
            return $this->shortname;
        }

        // Generate entity path for sub entity.
        $parententity = new entity($this->parentid);
        $this->entitypath = $parententity->get_entity_path($refresh) . ' / ' . $this->name;

        return $this->entitypath;
    }

    /**
     * Get the main entity of this entity
     *
     * @return $this|entity
     * @throws \moodle_exception
     */
    public function get_main_entity() {
        // Return the entity with it has no parent.
        if ($this->is_main_entity()) {
            return $this;
        }

        return \local_mentor_core\entity_api::get_entity($this->parentid, false);
    }

    /**
     * Get subentities
     *
     * @return entity[]
     * @throws \moodle_exception
     */
    public function get_sub_entities() {

        if (is_null($this->subentities)) {
            $this->subentities = [];

            $subentitiesdata = $this->dbinterface->get_sub_entities($this->id);

            foreach ($subentitiesdata as $subentitydata) {
                $this->subentities[$subentitydata->id] = \local_mentor_core\entity_api::get_entity($subentitydata->id, false);
            }
        }

        return $this->subentities;
    }

    /**
     * Has subentities
     *
     * @return bool
     * @throws \dml_exception
     */
    public function has_sub_entities() {

        // Not has subneity if is not main entity.
        if (!$this->is_main_entity()) {
            return false;
        }

        // Count subentity if attribute if is init.
        if (!is_null($this->subentities)) {
            return count($this->subentities) > 0;
        }

        // Count subentity with request.
        return count($this->dbinterface->get_sub_entities($this->id)) > 0;
    }

    /**
     * Check if the user can manage entity of subentity trainings
     *
     * @param null $user
     * @param bool $checksubentities
     * @return bool
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function is_trainings_manager($user = null, $checksubentities = true) {

        // Use the current user of the $user parameter is empty.
        if (empty($user)) {
            global $USER;
            $user = $USER;
        }

        // Check if the user can manage the trainings of the entity.
        if (has_capability('local/trainings:manage', $this->get_context(), $user)) {
            return true;
        }

        if ($checksubentities) {
            // Check for subentities.
            $subentities = $this->get_sub_entities();

            foreach ($subentities as $subentity) {
                // Check if the user can manage the trainings of the subentity.
                if ($subentity->is_trainings_manager($user)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if the user can manage entity of subentity sessions
     *
     * @param null $user
     * @param bool $checksubentities
     * @return bool
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function is_sessions_manager($user = null, $checksubentities = true) {

        // Use the current user of the $user parameter is empty.
        if (empty($user)) {
            global $USER;
            $user = $USER;
        }

        // Check if the user can manage the trainings of the entity.
        if (has_capability('local/session:manage', $this->get_context(), $user)) {
            return true;
        }

        // Check if the user is manager of a sub entity.
        if ($checksubentities) {
            $subentities = $this->get_sub_entities();

            foreach ($subentities as $subentity) {
                // Check if the user can manage the sessions of any subentity.
                if ($subentity->is_sessions_manager($user)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get sessions recycle bin page url
     *
     * @return \moodle_url
     * @throws \moodle_exception
     */
    public function get_sessions_recyclebin_page_url() {
        return new \moodle_url('/local/session/pages/recyclebin_sessions.php', ['entityid' => $this->id]);
    }

    /**
     * Get recyclebin items from a subcategory of the entity
     *
     * @param int $categoryid
     * @return array
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function get_recyclebin_items($categoryid, &$items) {
        $category = \context_coursecat::instance($categoryid);
        $recyclebin = new \tool_recyclebin\category_bin($category->instanceid);

        // Get all recycle bin items.
        $recyclebinitems = $recyclebin->get_items();

        // Check of recycle bin is enabled.
        if (!$recyclebin::is_enabled()) {
            throw new \moodle_exception('notenabled', 'tool_recyclebin');
        }

        $issubentity = $this->is_main_entity();
        $hassubentity = $this->has_sub_entities();
        $name = $this->get_name();

        // Set entity's trainings recycle bin table.
        foreach ($recyclebinitems as $recyclebinitem) {

            $item = new \stdClass();
            $item->id = $recyclebinitem->id;
            $item->contextid = $category->id;
            $item->instanceid = $category->instanceid;
            $item->entityid = $this->id;
            $item->name = $recyclebinitem->name;
            $item->timecreated = userdate($recyclebinitem->timecreated);

            // Sub-entity name row.
            if ($issubentity) {
                if ($hassubentity) {
                    $item->entity = "";
                }
            } else {
                $item->entity = $name;
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Get trainings recyclebin items
     *
     * @return array|void
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_training_recyclebin_items($includesubentities = true, &$items = []) {
        global $USER;

        // Get the entity's trainings recycle bin.
        $trainingcategoryid = $this->get_entity_formation_category();

        if (has_capability('local/trainings:delete', $this->get_context(), $USER)) {
            $this->get_recyclebin_items($trainingcategoryid, $items);
        }

        // Get subentities items.
        if ($includesubentities && $this->has_sub_entities()) {

            // Add all entity's sub-entities items to table.
            $subentities = $this->get_sub_entities();

            foreach ($subentities as $subentity) {
                if (has_capability('local/trainings:delete', $subentity->get_context(), $USER)) {
                    // Get subentity items.
                    $subentity->get_training_recyclebin_items($includesubentities, $items);
                }
            }
        }

        return $items;
    }

    /**
     * Get sessions recyclebin items
     *
     * @param bool $includesubentities - default true
     * @param array $items
     * @return array
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function get_sessions_recyclebin_items($includesubentities = true, &$items = []) {
        global $USER;

        // Get the entity's sessions recycle bin.
        $sessioncategoryid = $this->get_entity_session_category();

        if (has_capability('local/session:delete', $this->get_context(), $USER)) {
            $this->get_recyclebin_items($sessioncategoryid, $items);
        }

        // Get subentities items.
        if ($includesubentities && $this->has_sub_entities()) {

            // Add all entity's sub-entities items to table.
            $subentities = $this->get_sub_entities();

            foreach ($subentities as $subentity) {
                if (has_capability('local/session:delete', $subentity->get_context(), $USER)) {
                    $subentity->get_sessions_recyclebin_items($includesubentities, $items);
                }
            }
        }

        return $items;
    }

    /**
     * Get available sessions to catalog
     *
     * @return session[]
     * @throws \dml_exception
     */
    public function get_available_sessions_to_catalog() {
        $availablesessions = $this->dbinterface->get_available_sessions_to_catalog_by_entity($this->id);

        $sessions = [];

        // Convert stdClass into sessions.
        foreach ($availablesessions as $session) {
            $session = \local_mentor_core\session_api::get_session($session);

            // Skip hidden entities.
            if ($session->get_entity()->get_main_entity()->is_hidden()) {
                continue;
            }
            $sessions[$session->id] = $session;
        }

        // Sort by session id DESC.
        krsort($sessions);

        // Sort sessions.
        uasort($sessions, "local_mentor_core_uasort_session_to_catalog");

        return $sessions;
    }

    /**
     * Count available sessions to catalog
     *
     * @return int
     * @throws \dml_exception
     */
    public function count_available_sessions_to_catalog() {
        return count($this->dbinterface->get_available_sessions_to_catalog_by_entity($this->id));
    }

    /**
     * By default entities are not hidden
     *
     * @return int
     */
    public function is_hidden() {
        return 0;
    }

    /**
     * Can be a main entity
     *
     * @return bool
     * @throws \dml_exception
     */
    public function can_be_main_entity($refresh = false) {
        return true;
    }


    /**
     * Get the value of a category option
     *
     * @param int $entityid
     * @param string $optionname
     * @return string|false
     * @throws \moodle_exception
     */
    public function get_category_option_value($categoryid, $optionname) {
        global $DB;
        $option = $DB->get_record_sql("SELECT * FROM {category_options} WHERE name = ? AND categoryid = ?", [$optionname, $categoryid]);
        if ($option) {
            return $option->value == '1';
        }
        return false;
    }
}
