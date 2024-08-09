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
 * Base class with shared stuff between backup controller and restore
 * controller. (by Edunao)
 *
 * @package    core_backup
 * @copyright  2023 Edunao SAS (contact@edunao.com)
 * @author     remi <remi.colet@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

class backup_controller_edu extends backup_controller {

    /**
     * Liste of backup settings.
     *
     * @var array
     */
    private $settings;

    /**
     * Backup file name.
     *
     * @var string
     */
    private $filename;

    /**
     * Constructor for the backup controller class.
     *
     * @param int $type Type of the backup; One of backup::TYPE_1COURSE, TYPE_1SECTION, TYPE_1ACTIVITY
     * @param int $id The ID of the item to backup; e.g the course id
     * @param int $format The backup format to use; Most likely backup::FORMAT_MOODLE
     * @param bool $interactive Whether this backup will require user interaction; backup::INTERACTIVE_YES or INTERACTIVE_NO
     * @param int $mode One of backup::MODE_GENERAL, MODE_IMPORT, MODE_SAMESITE, MODE_HUB, MODE_AUTOMATED
     * @param int $userid The id of the user making the backup
     * @param array $options exemple :
     *                  $options = [
     *                       // Should release the session? backup::RELEASESESSION_YES or backup::RELEASESESSION_NO
     *                      'releasesession' => backup::RELEASESESSION_NO,
     *                      // List of backup settings
     *                      'settings =>
     *                           // Include activities to backup.
     *                           ['name' => 'activities', 'value' => true],
     *                           // Does not include blocks to backup.
     *                           ['name' => 'blocks', 'value' => false],
     *                          ...
     *                  ]
     */
    public function __construct($type, $id, $format, $interactive, $mode, $userid, $options = []) {
        global $CFG;

        // Default releasesession value is backup::RELEASESESSION_NO.
        $releasesession = backup::RELEASESESSION_NO;

        if (isset($options['releasesession'])) {
            // Set releasesession value.
            $releasesession = $options['releasesession'];
        }

        // Call default backup_controller construct.
        parent::__construct($type, $id, $format, $interactive, $mode, $userid, $releasesession);

        // Init settings attribute.
        $this->get_settings(true);

        // Checks on default settings have been defined.
        if (isset($CFG->backupdefault) && !empty($CFG->backupdefault) && is_array($CFG->backupdefault)) {
            $this->set_settings($CFG->backupdefault);
        }

        // Set new backup settings.
        if (isset($options['settings']) && !empty($options['settings'])) {
            $this->set_settings($options['settings']);
        }
    }

    /**
     * Return all backup settings.
     *
     * @param bool $refresh
     * @return array example : activities include and blocks not include.
     *          ['activities' => 1, 'blocks' => 0].
     */
    public function get_settings($refresh = false) {

        if (!isset($this->settings) || $refresh) {
            // Init new settings.
            $this->settings = [];
            // Browse all possible settings.
            foreach ($this->get_plan()->get_settings() as $name => $setting) {
                // File name in other attribute.
                if ($name === 'filename') {
                    $this->filename = $setting->get_value();
                }

                // Set settings by key.
                $this->settings[$name] = $setting->get_value();
            }
        }

        return $this->settings;
    }

    /**
     * Return backup file name.
     *
     * @return string
     */
    public function get_filename() {
        // Check if file name attributes is defined.
        if (!isset($this->filename)) {
            $this->get_settings(true);
        }

        return $this->filename;
    }

    /**
     * Set backup settings.
     *
     * @param array $options
     * @return void
     * @throws base_plan_exception
     * @throws base_setting_exception
     */
    public function set_settings($options = []) {
        // Empty data.
        if (!is_array($options) || empty($options)) {
            return;
        }

        // Browse all settings data.
        foreach ($options as $option) {
            // Check the structure of the data .
            if (!is_array($option) || !isset($option['name']) || !isset($option['value'])) {
                continue;
            }

            // Check if setting exist.
            if (!$this->setting_exists($option['name'])) {
                continue;
            }

            // Set value to backup setting.
            $this->set_setting($option['name'], $option['value']);
        }
    }

    /**
     * @param string $name
     * @param int $value
     * @return bool|void
     * @throws base_setting_exception
     * @throws base_plan_exception
     */
    public function set_setting($name, $value) {

        // Check if setting exist.
        if (!$this->setting_exists($name)) {
            return false;
        }

        // Change data to interger.
        $value = clean_param($value, PARAM_INT);

        // Check if data is interger.
        if ($value !== 0 && $value !== 1) {
            throw new moodle_exception('invalidextparam', 'webservice', '', $value);
        }

        // Set value to backup setting.
        $this->get_plan()->get_setting($name)->set_value($value);
        $this->settings[$name] = $value;
        return true;
    }

    /**
     *  Returns if the requested setting exists or no
     *
     * @param string $name
     * @return bool
     */
    public function setting_exists($name) {
        return $this->get_plan()->setting_exists($name);
    }

    /**
     * Disable user info to all specific activity.
     *
     * @param string $activityname
     * @return void
     * @throws base_plan_exception
     * @throws base_setting_exception
     */
    public function disable_userinfo_to_activity($activityname) {
        foreach ($this->settings as $setting => $value) {
            if (preg_match('/(^' . $activityname . '_)(\d*)(_userinfo$)/', $setting)) {
                $this->set_setting($setting, 0);
            }
        }
    }

    /**
     * Disable user info to all activities.
     *
     * @return void
     * @throws base_plan_exception
     * @throws base_setting_exception
     */
    public function disable_userinfo_to_all_activities() {
        foreach ($this->settings as $setting => $value) {
            if (preg_match('/(^.*_)(\d*)(_userinfo$)/', $setting)) {
                $this->set_setting($setting, 0);
            }
        }
    }
}
