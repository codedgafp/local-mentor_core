<?php

namespace local_mentor_core;

global $CFG;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/form/select.php');

class MoodleQuickForm_menu_main_entity extends \MoodleQuickForm_select  {
    public function __construct($elementName=null, $elementLabel=null, $options=null, $attributes=null) {
        parent::__construct($elementName, $elementLabel, $options, $attributes);
        $this->_type = 'menu_main_entity';
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
        $this->_type = 'select';
        $renderer->renderElement($this, $required, $error);
        $this->_type = 'menu_main_entity';
    } // end func accept
}
