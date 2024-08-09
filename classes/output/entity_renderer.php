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
 * admin renderer
 *
 * @package    local
 * @subpackage mentor_core
 * @copyright  2020 Edunao SAS (contact@edunao.com)
 * @author     adrien <adrien@edunao.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mentor_core\output;

use lang_string;
use moodle_page;

class entity_renderer extends \plugin_renderer_base {

    /**
     * Get the HTML of the new entity form
     *
     * @param string $extrahtml optional extra html for the template
     * @return string
     * @throws \moodle_exception
     */
    public function get_new_entity_form($extrahtml = '') {

        $options = (object) [
            'extrahtml' => $extrahtml,
        ];

        // Return the form HTML template.
        return $this->render_from_template('local_mentor_core/new_entity_form', $options);
    }
}
