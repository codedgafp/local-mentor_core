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
 *  Entity class
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     rcolet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core;

use core\event\course_category_deleted;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/local/mentor_core/classes/database_interface.php');
require_once($CFG->dirroot . '/local/mentor_core/classes/model/entity.php');
require_once($CFG->dirroot . '/local/mentor_core/api/profile.php');

/**
 * Entity class to access user details.
 *
 * @package    local_mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     rcolet <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entity_api {

    private static $entities = [];

    /**
     * Get an entity by id
     *
     * @param int $entityid
     * @param bool $refresh true to retrieve the entity from database
     * @return entity
     * @throws \moodle_exception
     *
     * TODO : set refresh to false and update unit tests
     */
    public static function get_entity($entityid, $refresh = true) {

        if ($refresh || empty(self::$entities[$entityid])) {
            // An entity can be extended by a specialization plugin.
            $specialization = specialization::get_instance();
            $entity = $specialization->get_specialization('get_entity', $entityid);

            // If the entity has no specialization, then initialise the default entity class.
            if (!is_object($entity)) {
                $entity = new entity($entityid);
            }

            self::$entities[$entity->id] = $entity;
        }

        return self::$entities[$entityid];
    }

    /**
     * Get an entity by name
     *
     * @param string $entityname
     * @param bool $mainonly
     * @param bool $refresh
     * @return entity|bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_entity_by_name($entityname, $mainonly = false, $refresh = true) {
        $db = database_interface::get_instance();

        // The category must exists.
        if ($mainonly) {
            // Check main entity.
            $coursecat = $db->get_main_entity_by_name($entityname, $refresh);
        } else {
            // Check all entity.
            $coursecat = $db->get_course_category_by_name($entityname, $refresh);
        }

        // No coursecat found.
        if (!$coursecat) {
            return false;
        }

        return self::get_entity($coursecat->id, $refresh);
    }

    /**
     * Get all entities
     * if $mainonly is true, juste return list of main entity
     *
     * @param bool $mainonly
     * @param array $exclude ids of categories to exclude
     * @param bool $refresh refresh entities list from database default false
     * @param null $filter
     * @param bool $includehidden - include or not the hidden entities.
     * @param bool $includecannotbemainentity - optional default true
     * @return entity[]
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_all_entities($mainonly = true, $exclude = [], $refresh = false, $filter = null, $includehidden
    = true, $includecannotbemainentity = true, $searchtext = null) {
        $db = database_interface::get_instance();

        // Entities are main course categories.
        $entitylist = $mainonly ? $db->get_all_main_categories($refresh, $includehidden, $filter) : $db->get_all_entities($refresh,
            $filter, $includehidden);

        $entities = [];

        foreach ($entitylist as $entity) {

            // Check if the entity id must be ignored.
            if (in_array($entity->id, $exclude)) {
                continue;
            }

            $objentity = self::get_entity($entity->id, $refresh);

            if (!$includecannotbemainentity && !$objentity->can_be_main_entity(true)) {
                continue;
            }

            if (!$includehidden && $objentity->get_category_option_value($entity->id, 'hidden')){
                continue;
            }
            
            // Check if the entity matches the search text
            if ($searchtext && $objentity->shortname && stripos($objentity->shortname, $searchtext) === false) {
                continue;
            }
            
            $entities[] = $objentity;
        }
        
        return $entities;
    }

    /**
     * Check if an entity exists
     *
     * @param string $entityname
     * @param bool $refresh to refresh the entities list before the check
     * @return bool
     * @throws \dml_exception
     */
    public static function entity_exists($entityname, $refresh = false) {
        $db = database_interface::get_instance();
        $category = $db->get_course_category_by_name($entityname, $refresh);
        return !empty($category);
    }

    /**
     * Check if a main entity exists
     *
     * @param string $entityname
     * @param bool $refresh to refresh the entities list before the check
     * @return bool
     * @throws \dml_exception
     */
    public static function main_entity_exists($entityname, $refresh = false) {
        $db = database_interface::get_instance();
        $category = $db->get_main_entity_by_name($entityname, $refresh);
        return !empty($category);
    }

    /**
     * Check if an entity shortname exists
     *
     * @param string $shortname
     * @param int $ignorecategoryid default 0
     * @return bool
     * @throws \dml_exception
     */
    public static function shortname_exists($shortname, $ignorecategoryid = 0) {
        $db = database_interface::get_instance();
        return $db->entity_shortname_exists($shortname, $ignorecategoryid);
    }

    /**
     * Check if a sub entity exists
     *
     * @param string $entityname
     * @param int $parentid
     * @param bool $refresh to refresh the entities list before the check
     * @return bool
     * @throws \dml_exception
     */
    public static function sub_entity_exists($entityname, $parentid, $refresh = false) {
        $db = database_interface::get_instance();
        $category = $db->get_sub_entity_by_name($entityname, $parentid, $refresh);
        return !empty($category);
    }

    /**
     * Create an entity
     *
     * @param array $formdata - array containing entityname (userid) (...)
     * @return int new entity id
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function create_entity($formdata) {
        global $CFG;
        require_once($CFG->dirroot . '/local/profile/lib.php');

        // Key name is required in formdata.
        if (!isset($formdata['name'])) {
            throw new \moodle_exception('missingentityname', 'local_mentor_core', '');
        }

        // Convert quotes in name.
        $formdata['name'] = str_replace('&#39;', "'", $formdata['name']);

        // Trim entity name.
        $formdata['name'] = trim($formdata['name']);

        // Check if the name is not empty.
        if (empty($formdata['name'])) {
            throw new \moodle_exception('entitynameisempty', 'local_mentor_core', '');
        }

        // Clear the entity name.
        $formdata['name'] = str_replace(['<', '>'], '', $formdata['name']);

        // Clear the entity name.
        $formdata['name'] = str_replace(['<', '>'], '', $formdata['name']);

        // Create a sub entity if a parentid is defined.
        if (isset($formdata['parentid'])) {
            return self::create_sub_entity($formdata);
        }

        // Key shortname is required in formdata.
        if (!isset($formdata['shortname'])) {
            throw new \moodle_exception('missingentityshortname', 'local_mentor_core', '');
        }

        // Trim entity shortname.
        $formdata['shortname'] = trim($formdata['shortname']);

        // Convert quotes in shortname.
        $formdata['shortname'] = str_replace('&#39;', "'", $formdata['shortname']);

        // Clear the entity shortname.
        $formdata['shortname'] = str_replace(['<', '>'], '', $formdata['shortname']);

        // Check if the shortname is not empty.
        if (empty($formdata['shortname'])) {
            throw new \moodle_exception('entityshortnameisempty', 'local_mentor_core', '');
        }

        $entityname = $formdata['name'];
        $userid = isset($formdata['userid']) ? $formdata['userid'] : 0;
        $idnumber = $formdata['shortname'];

        // Check if main entity name is not used.
        if (self::main_entity_exists($entityname, true)) {
            throw new \moodle_exception('errorentitynameexist', 'local_mentor_core', '');
        }

        // Check shortname size.
        if (!empty($idnumber) && mb_strlen($idnumber) > 18) {
            throw new \moodle_exception('entityshortnamelimit', 'local_mentor_core', '', 18);
        }

        // Check if main entity name is not used.
        if (!empty($idnumber) && self::shortname_exists($idnumber)) {
            throw new \moodle_exception('errorentityshortnameexistshort', 'local_mentor_core', '');
        }

        $nouser = false;
        if (!$userid) {
            // No user assigned.
            $nouser = true;
        }

        // Create a new parent course category.
        $db = database_interface::get_instance();
        $category = $db->create_course_category($entityname, 0, $idnumber);

        // Get the new category id.
        $newentityid = (int) $category->id;

        $newentity = self::get_entity($newentityid);

        // Assign a manager.
        if (!$nouser) {
            $newentity->assign_manager($userid);
        }

        // Update entity data with form values.
        $newentity->update((object) $formdata);

        // Create all edadmin courses.
        $newentity->create_edadmin_courses_if_missing();

        // Trigger an entity created event.
        $event = \local_mentor_core\event\entity_create::create([
            'objectid' => $newentityid,
            'context' => $newentity->get_context(),
            'other' => [
                'name' => $newentity->get_name(),
                'managementcourseid' => $newentity->get_edadmin_courses('entities')['id'],
            ],
        ]);
        $event->trigger();

        // Return the created entity id.
        return $newentityid;
    }

    /**
     * Create a sub entity
     *
     * @param array $formdata - array containing entityname (userid) (...)
     * @return int new entity id
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function create_sub_entity($formdata) {

        // Key parentid is required in formdata.
        if (!isset($formdata['parentid'])) {
            throw new \moodle_exception('missingparentid', 'local_mentor_core', '');
        }

        // Check if the parentid is not empty.
        if (empty($formdata['parentid'])) {
            throw new \moodle_exception('parentidisempty', 'local_mentor_core', '');
        }

        $entityname = trim($formdata['name']);

        // Convert quotes in name.
        $entityname = str_replace('&#39;', "'", $entityname);

        $parentid = $formdata['parentid'];

        // Check if sub entity name is not used.
        if (self::sub_entity_exists($entityname, $parentid, true)) {
            throw new \moodle_exception('errorentitynameexist', 'local_mentor_core', '');
        }

        // Create "Espaces" sub categories to parent if not exist.
        $parententity = self::get_entity($parentid);
        $parent = $parententity->get_entity_space_category();

        // Create a new parent course category.
        $db = database_interface::get_instance();
        $category = $db->create_course_category($entityname, $parent);

        // Get the new category id.
        $newentityid = (int) $category->id;

        $newentity = self::get_entity($newentityid);

        // Update entity data with form values.
        $newentity->update((object) $formdata);

        // Create all edadmin courses.
        $newentity->create_edadmin_courses_if_missing();

        // Trigger an entity created event.
        $event = \local_mentor_core\event\entity_create::create([
            'objectid' => $newentityid,
            'context' => $newentity->get_context(),
            'other' => [
                'name' => $newentity->get_name(),
                'managementcourseid' => $newentity->get_edadmin_courses('entities')['id'],
            ],
        ]);
        $event->trigger();

        self::$entities[$newentityid] = $newentity;

        // An entity can be extended by a specialization plugin.
        $specialization = specialization::get_instance();
        $specialization->get_specialization('create_sub_entity', $newentity, $formdata);

        return $newentityid;
    }

    /**
     * Update an entity
     *
     * @param int $entityid
     * @param \stdClass $data
     * @param null|entity_form $mform
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function update_entity($entityid, $data, $mform = null) {
        $entity = self::get_entity($entityid);
        $contactpage = $entity->get_contact_page_course();
        
        if(isset($data->canbemainentity) && isset($contactpage)){
            $contactpage->visibleold = $contactpage->visible;
            $contactpage->visible = $data->canbemainentity == '0' ? '0' : '1';
            $db = database_interface::get_instance();
            $db->update_record('course', $contactpage);
        }
        
        // Capture the updated fields for the log data.
        $updatedfields = [];
        foreach (get_object_vars($entity) as $field => $value) {
            if (isset($data->$field) && $data->$field != $value) {
                $updatedfields[$field] = $data->$field;
            }
        }

        $entity->update($data, $mform);

        // Trigger an entity updated event.
        $event = \local_mentor_core\event\entity_update::create([
            'objectid' => $entityid,
            'context' => $entity->get_context(),
            'other' => [
                'name' => $entity->get_name(),
                'managementcourseid' => $entity->get_edadmin_courses('entities')['id'],
                'updatedfields' => $updatedfields,
            ],
        ]);
        $event->set_legacy_logdata([
            $entity->id, 'entity', 'update', 'course/view.php?id=' . $entity->get_edadmin_courses('entities')['id'], $entity->id,
        ]);
        $event->trigger();

        self::$entities[$entityid] = $entity;

        return true;
    }

    /**
     * Get entities managed by a user to object.
     *
     * @param null $user - default current user
     * @param bool $mainonly
     * @param null|\stdClass $filter
     * @param bool $refresh
     * @param bool $includehidden
     * @param bool $includeothermanage Include other entities where the user only manages the trainings and sessions
     * @return \stdClass[]
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_managed_entities_object($user = null, $mainonly = true, $filter = null, $refresh = false,
        $includehidden = true, $includeothermanage = false) {

        $db = \local_mentor_core\database_interface::get_instance();

        // Use the current user if $user is null.
        if (empty($user)) {
            global $USER;
            $user = $USER;
        }

        $entitylist = $mainonly ? $db->get_all_main_categories($refresh, $includehidden) : $db->get_all_entities($refresh,
            $filter, $includehidden);

        // An admin can manage all the entities.
        if (is_siteadmin($user)) {
            if (!is_null($filter) && $filter->length != 0) {
                $entitylist = array_slice($entitylist, $filter->start, $filter->length);
            }
            return $entitylist;
        }

        $managedentities = [];

        $capabilities = \local_mentor_core\entity::ENTITY_MANAGER_CAPABILITIES;

        // Include other entities where the user only manages the trainings and sessions.
        if ($includeothermanage) {
            // Entity minimum capabilities when user manage trainings and session.
            $capabilities = [
                'local/mentor_core:movesessions',
                'local/mentor_core:movetrainings',
            ];
        }

        foreach ($entitylist as $entity) {
            $context = \context_coursecat::instance($entity->id);

            if (local_mentor_core_has_capabilities(
                $capabilities,
                $context,
                $user
            )) {
                $managedentities[$entity->id] = $entity;
            }
        }

        if (!is_null($filter) && $filter->length != 0) {
            $managedentities = array_slice($managedentities, $filter->start, $filter->length);
        }

        return $managedentities;
    }

    /**
     * Get entities managed by a user
     *
     * @param null $user - default current user
     * @param bool $mainonly
     * @param null|\stdClass $filter
     * @param bool $refresh
     * @param bool $includehidden
     * @param bool $includeothermanage Include other entities where the user only manages the trainings and sessions
     * @return entity[]
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_managed_entities($user = null, $mainonly = true, $filter = null, $refresh = false, $includehidden
    = true, $includeothermanage = false) {

        $entities = self::get_managed_entities_object($user, $mainonly, $filter, $refresh, $includehidden, $includeothermanage);

        $managedentities = [];

        foreach ($entities as $entity) {
            $managedentities[$entity->id] = self::get_entity($entity->id);
        }

        return $managedentities;
    }

    /**
     * Count entities managed by a user
     *
     * @param null $user - default current user
     * @param bool $mainonly
     * @param null|\stdClass $filter
     * @param bool $refresh
     * @param bool $includehidden
     * @param bool $includeothermanage Include other entities where the user only manages the trainings and sessions
     * @return int
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function count_managed_entities($user = null, $mainonly = true, $filter = null, $refresh = false, $includehidden
    = true, $includeothermanage = false) {
        return count(self::get_managed_entities_object($user, $mainonly, $filter, $refresh, $includehidden, $includeothermanage));
    }

    /**
     * Get entities where the user manages trainings
     *
     * @param null $user - default current user
     * @param bool $mainonly
     * @return entity[]
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_entities_where_trainings_managed($user = null, $mainonly = true) {

        // Use the current user if $user is null.
        if (empty($user)) {
            global $USER;
            $user = $USER;
        }

        // Main admin can see hidden entities.
        $includehidden = is_siteadmin();
        $entities = self::get_all_entities($mainonly, [], false, null, $includehidden);

        // An admin can manage all trainings in this entity.
        $isadmin = is_siteadmin($user);

        $managedentities = [];

        foreach ($entities as $entity) {
            // Check if the user can manage trainings in this entity.
            if ($isadmin || $entity->is_trainings_manager($user)) {
                $managedentities[$entity->id] = $entity;
            }
        }

        return $managedentities;
    }

    /**
     * Get entities where the user manages sessions
     *
     * @param null $user - default current user
     * @param bool $mainonly
     * @return entity[]
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_entities_where_sessions_managed($user = null, $mainonly = true) {

        // Use the current user if $user is null.
        if (empty($user)) {
            global $USER;
            $user = $USER;
        }

        $entities = self::get_all_entities($mainonly);

        // An admin can manage all sessions in this entity.
        $isadmin = is_siteadmin($user);

        $managedentities = [];

        foreach ($entities as $entity) {
            // Check if the user can manage the sessions in this entity.
            if ($isadmin || $entity->is_sessions_manager($user)) {
                $managedentities[$entity->id] = $entity;
            }
        }

        return $managedentities;
    }

    /**
     * Get entities as a list separated by \n
     *
     * @param bool $mainonly just main entity
     * @param bool $refresh true to refresh entities from database
     * @param bool $includehidden - optional default true
     * @param bool $includecannotbemainentity - optional default true
     * @return string
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_entities_list($mainonly = true, $refresh = false, $includehidden = true,
        $includecannotbemainentity = true) {

        // Order by entity name.
        $filter = new \stdClass();
        $filter->order = ['column' => 'name', 'dir' => 'asc'];

        // Get entities.
        $entitieslist = self::get_all_entities($mainonly, [], $refresh, $filter, $includehidden, $includecannotbemainentity);

        // Get all name of entities.
        $entitiesnames = array_map(function($entity) {
            return $entity->name;
        }, $entitieslist);

        usort($entitiesnames, function($a, $b) {
            return strcmp(local_mentor_core_sanitize_string($a), local_mentor_core_sanitize_string($b));
        });

        return implode("\n", $entitiesnames);
    }

    /**
     * Get entity form
     *
     * @param string $url - redirection url
     * @param int $entityid
     * @return \moodleform
     * @throws \moodle_exception
     */
    public static function get_entity_form($url, $entityid) {
        global $CFG;
        require_once($CFG->dirroot . '/local/mentor_core/forms/entity_form.php');

        $entity = self::get_entity($entityid);

        // If the entity is a sub entity, then return the subentity form.
        if (!$entity->is_main_entity()) {
            return self::get_sub_entity_form($url, $entityid);
        }

        $form = new entity_form($url);
        $specialization = specialization::get_instance();

        // The entity form can be overrided by a specialization plugin.
        return $specialization->get_specialization('get_entity_form', $form, $url);
    }



    /**
     * Get sub entity form
     *
     * @param string $url
     * @param int $entityid
     * @return \moodleform
     * @throws \moodle_exception
     */
    public static function get_sub_entity_form($url, $entityid) {
        global $CFG;
        require_once($CFG->dirroot . '/local/mentor_core/forms/sub_entity_form.php');

        $params = new \stdClass();
        $params->entity = self::get_entity($entityid);

        $form = new sub_entity_form($url, $params);
        $specialization = specialization::get_instance();

        // The entity form can be overriden by a specialization plugin.
        return $specialization->get_specialization('get_sub_entity_form', $form, $url, $params);
    }

    /**
     * Get new entity form
     *
     * @return string html
     */
    public static function get_new_entity_form() {
        global $PAGE;
        $specialization = specialization::get_instance();

        // Get extras form fields added by specialization plugins.
        $extrahtml = '';
        $extrahtml = $specialization->get_specialization('get_entity_form_fields', $extrahtml);

        // Call the entity renderer.
        $renderer = $PAGE->get_renderer('local_mentor_core', 'entity');

        return $renderer->get_new_entity_form($extrahtml);
    }

    /**
     * Get new sub entity form
     *
     * @return string html
     */
    public static function get_new_sub_entity_form() {
        global $PAGE;
        $specialization = specialization::get_instance();

        // Get extras form fields added by specialization plugins.
        $extrahtml = '';
        $extrahtml = $specialization->get_specialization('get_sub_entity_form_fields', $extrahtml);

        // Call the entity renderer.
        $renderer = $PAGE->get_renderer('local_mentor_core', 'sub_entity');

        return $renderer->get_new_sub_entity_form($extrahtml);
    }


    /**
     * Get main entity form
     *
     * @return string html
     */
    public static function get_default_entity_form() {
        global $PAGE;
        $specialization = specialization::get_instance();

        // Get extras form fields added by specialization plugins.
        $extrahtml = '';
        $extrahtml = $specialization->get_specialization('get_default_entity_form_fields', $extrahtml);

        // Call the entity renderer.
        $renderer = $PAGE->get_renderer('local_mentor_core', 'default_entity');

        return $renderer->get_default_entity_form($extrahtml);
    }

    /**
     * Get all user entities
     *
     * @param int $userid
     * @return entity[]
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_user_entities($userid) {
        $db = database_interface::get_instance();

        $dbentities = $db->get_user_entities($userid);

        $entities = [];

        // Convert stdclass into entities.
        foreach ($dbentities as $entity) {
            $entities[$entity->id] = self::get_entity($entity->id);
        }

        return $entities;
    }

    /**
     * Search among main entities
     *
     * @param string $searchtext
     * @param bool $includehidden
     * @return array
     * @throws \dml_exception
     */
    public static function search_main_entities($searchtext, $includehidden = true) {
        global $USER;

        $db = database_interface::get_instance();

        // Search in all entities.
        if (is_siteadmin()) {
            return array_values($db->search_main_entities($searchtext, $includehidden));
        }

        // Just search in entities user managed.
        return array_values($db->search_main_entities_user_managed(
            $searchtext,
            $USER->id,
            profile_api::get_user_manager_role_name(),
            $includehidden
        ));
    }

    
    /**
     * Get the specilization of the entity javascript
     *
     * @param string $defaultjs
     * @return mixed
     */
    public static function get_entity_javascript($defaultjs) {
        $specialization = specialization::get_instance();
        return $specialization->get_specialization('get_entity_javascript', $defaultjs);
    }

    /**
     * Check if an entity has sub entities
     *
     * @param int $entityid
     * @return bool
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function has_sub_entities($entityid) {
        $entity = self::get_entity($entityid);
        return $entity->has_sub_entities();
    }

    /**
     * Cleanup training recyclebin
     *
     * @param int $entityid
     * @param string|null $urlredirect
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function cleanup_training_recyblebin($entityid, $urlredirect = null) {
        // Get entity.
        $entity = self::get_entity($entityid);

        // Get all training items.
        $items = $entity->get_training_recyclebin_items();

        $dbinterface = database_interface::get_instance();

        foreach ($items as $item) {

            $recyclebin = new \tool_recyclebin\category_bin($item->instanceid);

            // Check if the user can delete the item.
            if ($recyclebin->can_delete()) {
                $recyclebinitem = $recyclebin->get_item($item->id);

                // Delete the item.
                $recyclebin->delete_item($recyclebinitem);

                $dbinterface->delete_training_sheet($recyclebinitem->shortname);
            }
        }

        // Redirect.
        if (!is_null($urlredirect)) {
            redirect($urlredirect, get_string('alertemptied', 'tool_recyclebin'), 2);
        }
    }

    /**
     * Cleanup session recyclebin
     *
     * @param int $entityid
     * @param string|null $urlredirect
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function cleanup_session_recyblebin($entityid, $urlredirect = null) {
        // Get entity.
        $entity = self::get_entity($entityid);

        // Get entity items.
        $items = $entity->get_sessions_recyclebin_items();

        $dbinterface = database_interface::get_instance();

        foreach ($items as $item) {

            $recyclebin = new \tool_recyclebin\category_bin($item->instanceid);

            // Check if the user can delete the item.
            if ($recyclebin->can_delete()) {
                $recyclebinitem = $recyclebin->get_item($item->id);

                // Delete the item.
                $recyclebin->delete_item($recyclebinitem);

                $dbinterface->delete_session_sheet($recyclebinitem->shortname);
            }
        }

        // Redirect to a specific URL after treatment.
        if (!is_null($urlredirect)) {
            redirect($urlredirect, get_string('alertemptied', 'tool_recyclebin'), 2);
        }
    }

    /**
     * Get entities where you can import the training library.
     *
     * @param null $user - default current user
     * @param bool $refresh
     * @param bool $includehidden
     * @return \stdClass[]
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function get_entities_can_import_training_library_object($user = null, $refresh = false, $includehidden = true) {

        $db = \local_mentor_core\database_interface::get_instance();

        // Use the current user if $user is null.
        if (empty($user)) {
            global $USER;
            $user = $USER;
        }

        $entitylist = $db->get_all_main_categories($refresh, $includehidden);

        // An admin can manage all the entities.
        if (is_siteadmin($user)) {
            return $entitylist;
        }

        $managedentities = [];

        foreach ($entitylist as $entity) {
            $context = \context_coursecat::instance($entity->id);

            if (
                local_mentor_core_has_capabilities( // Entity manager.
                    \local_mentor_core\entity::ENTITY_MANAGER_CAPABILITIES,
                    $context,
                    $user
                ) || has_capability('local/mentor_core:sharetrainings', $context) // RFC.
            ) {
                $managedentities[$entity->id] = $entity;
            }
        }

        return $managedentities;
    }

    /**
     * Gives the name of the capability that allows access to the edadmin course of entities format.
     *
     * @return string
     */
    public static function get_edadmin_course_view_capability() {
        return 'local/entities:manageentity';
    }

    /**
     * Get all categories where the user has a specific capability
     *
     * @param int $userid
     * @param string $capability
     * @return array
     */
    public static function get_categories_with_capability($userid, $capability) {
        $db = \local_mentor_core\database_interface::get_instance();
        return $db->get_categories_with_capability($userid, $capability);
    }


    /**
     * Hide space presentation page if space admin choose it 
     * @param int $entityd
     * @return void
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function hide_presentation($params) {
        global $DB;
        
        $isVisible = (int)$params['isVisible'];
        $courseId = $params['courseId'];
        
        $oldVisible = $DB->get_field('course', 'visible', ['id' => $courseId]);
        $success = $DB->set_field('course', 'visibleold', $oldVisible, ['id' => $courseId]);
        $success &= $DB->set_field('course', 'visible', $isVisible, ['id' => $courseId]);
        if ($success) {
            return $isVisible;
        } else {
            throw new \moodle_exception('entitypresentationparamerror', 'local_mentor_core', '');
        }
    }

}
