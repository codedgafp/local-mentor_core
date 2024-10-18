<?php
namespace local_mentor_core\output;

class delete_subentity_renderer extends \plugin_renderer_base {

    /**
     * Get the HTML of the delete subentity form
     *
     * @param string $extrahtml optional extra html for the template
     * @return string
     * @throws \moodle_exception
     */
    public function get_delete_subentity_form($extrahtml = '') {
        $options = (object) [
            'extrahtml' => $extrahtml,
        ];
        // Return the form HTML template.
        return $this->render_from_template('local_mentor_core/delete_subentity_form', $options);
    }
}