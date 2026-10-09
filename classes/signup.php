<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_libreria;

/**
 * Optional details and server-side defaults for the existing email signup form.
 *
 * @package local_libreria
 * @copyright 2026 Libreria de Satoshi
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class signup {
    /**
     * Keep Moodle's signup controller and validation, changing only the form.
     *
     * @param \MoodleQuickForm $mform Existing signup form.
     */
    public static function extend_form(\MoodleQuickForm $mform): void {
        $mform->updateAttributes(['class' => $mform->getAttribute('class') . ' local-libreria-simplified-signup']);
        $optional = ['username', 'firstname', 'lastname', 'city'];
        $elements = [];
        foreach ($optional as $name) {
            $element = $mform->removeElement($name, false);
            if (in_array($name, ['username', 'firstname', 'lastname'], true)) {
                $replacement = new \local_libreria\form\signup_text($name, $element->getLabel(), $element->getAttributes());
                $replacement->set_force_ltr($element->get_force_ltr());
                $elements[] = $replacement;
            } else {
                $elements[] = $element;
            }
            // QuickForm has no removeRule API. Retain all rules except required.
            $mform->_rules[$name] = array_values(array_filter(
                $mform->_rules[$name] ?? [],
                static fn(array $rule): bool => $rule['type'] !== 'required'
            ));
        }
        // QuickForm tracks the required marker separately from validation rules.
        $mform->_required = array_values(array_diff($mform->_required, $optional));

        // Keep country visible and require it in both browser and server validation.
        $mform->addRule('country', get_string('required'), 'required', null, 'client');

        // Core signup validation expects email2 even though users only enter email once.
        $mform->removeElement('email2');
        $mform->addElement(new \local_libreria\form\signup_email_confirmation('email2', '', ['id' => 'id_email2']));
        $mform->setType('email2', \core_user::get_property_type('email'));

        // Preserve custom field types, defaults and validation when moving optional fields.
        $categories = [];
        foreach (profile_get_signup_fields() as $field) {
            $name = $field->object->inputname;
            $config = $field->object->get_field_config_for_external();
            $categories['category_' . $field->categoryid] = true;
            if (empty($config['required']) && $mform->elementExists($name)) {
                $elements[] = $mform->removeElement($name, false);
            }
        }
        // Remove only category headers whose remaining contents are empty.
        $emptyheaders = [];
        $header = null;
        $boundaries = $mform->defaultRenderer()->getStopFieldsetElements();
        foreach ($mform->_elements as $element) {
            if (in_array($element->getName(), $boundaries, true)) {
                $header = null;
            }
            if ($element->getType() === 'header') {
                $header = $element->getName();
                if (isset($categories[$header])) {
                    $emptyheaders[$header] = true;
                }
            } else if ($element->getType() !== 'hidden' && $header !== null) {
                unset($emptyheaders[$header]);
            }
        }
        foreach (array_keys($emptyheaders) as $name) {
            $mform->removeElement($name);
        }

        // Move email before password and its policy help text.
        $before = $mform->elementExists('passwordpolicyinfo') ? 'passwordpolicyinfo' : 'password';
        $emailelement = $mform->removeElement('email', false);
        $mform->insertElementBefore($emailelement, $before);

        // Native details remains keyboard accessible and usable without JavaScript.
        // Open it on a submitted form so optional-field errors are always visible.
        $submitted = data_submitted();
        $attributes = ['class' => 'mb-3 local-libreria-signup-details'];
        if ($submitted) {
            $attributes['open'] = 'open';
        }
        $details = [];
        $details[] = $mform->createElement('html', \html_writer::start_tag('details', $attributes)
            . \html_writer::tag('summary', get_string('additionaldetails', 'local_libreria'), ['class' => 'mb-3']));
        $details[] = $mform->createElement('static', 'local_libreria_signupdefaults', '',
            get_string('signupdefaults', 'local_libreria'));
        foreach ($elements as $element) {
            $details[] = $element;
        }
        $details[] = $mform->createElement('html', \html_writer::end_tag('details'));

        // Raw HTML does not close QuickForm fieldsets. Insert before the first header
        // so the optional section never becomes a child of a custom field category.
        $beforeheader = null;
        foreach ($mform->_elements as $element) {
            if ($element->getType() === 'header') {
                $beforeheader = $element->getName();
                break;
            }
        }
        // QuickForm retains references, so each insertion needs its own array slot.
        foreach (array_keys($details) as $index) {
            if ($beforeheader !== null) {
                $mform->insertElementBefore($details[$index], $beforeheader);
            } else {
                $mform->addElement($details[$index]);
            }
        }
    }
}
