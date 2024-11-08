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

namespace local_mentor_core;

global $CFG;
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/form/autocomplete.php');

/**
 * autocomplete type secondary entity form element
 *
 * Contains HTML class for a autocomplete secondary entity type element
 */
class MoodleQuickForm_autocomplete_secondaryentity extends \MoodleQuickForm_autocomplete  {

    /**
     * constructor
     *
     * @param string $elementName Select name attribute
     * @param mixed $elementLabel Label(s) for the select
     * @param mixed $options Data to be used to populate options
     * @param mixed $attributes Either a typical HTML attribute string or an associative array. Special options
     *                          "tags", "placeholder", "ajax", "multiple", "casesensitive" are supported.
     */
    public function __construct($elementName=null, $elementLabel=null, $options=null, $attributes=null) {
        parent::__construct($elementName, $elementLabel, $options, $attributes);
        $this->_type = 'autocomplete_secondaryentity';
    }

    /**
     * Called by HTML_QuickForm whenever form event is made on this element
     * Overridden because we want autocomplete values consider as tag (and no taglist)
     *
     * @param string $event Name of event
     * @param mixed $arg event arguments
     * @param object $caller calling object
     * @return bool
     */
    function onQuickFormEvent($event, $arg, &$caller) {
        switch ($event) {
            case 'createElement':
                $caller->setType($arg[0], PARAM_TAG);
                break;
        }
        return \MoodleQuickForm_select::onQuickFormEvent($event, $arg, $caller);
    }

    /**
    * Accepts a renderer
    * Overridden because we want the same rendering of a classical autocomplete 
    * (and not re-implement already existing nodes/css)
    *
    * @param object     An HTML_QuickForm_Renderer object
    * @param bool       Whether an element is required
    * @param string     An error message associated with an element
    * @access public
    * @return void 
    */
    function accept(&$renderer, $required=false, $error=null)
    {
        $this->_type = 'autocomplete';
        $renderer->renderElement($this, $required, $error);
        $this->_type = 'autocomplete_secondaryentity';
    } // end func accept
}
