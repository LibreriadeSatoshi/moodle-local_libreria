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

namespace local_libreria\form;

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/form/text.php');

/**
 * A normal text input whose blank value receives a default only when exported.
 *
 * Keeping the rendered input blank prevents a failed signup from turning an
 * automatic default into an explicit value on the next submission.
 *
 * @package local_libreria
 * @copyright 2026 Libreria de Satoshi
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class signup_text extends \MoodleQuickForm_text {
    /**
     * Supply defaults from Moodle's cleaned submission for validation and saving.
     *
     * @param array $submitvalues Cleaned values supplied by QuickForm.
     * @param bool $assoc Whether to return an associative array.
     * @return mixed The explicit value or its default.
     */
    public function exportValue(&$submitvalues, $assoc = false) { // phpcs:ignore moodle.NamingConventions.ValidFunctionName
        $value = parent::exportValue($submitvalues, false);
        if ($value !== null && (!is_string($value) || trim($value) !== '')) {
            return $this->_prepareValue($value, $assoc);
        }

        $email = $submitvalues['email'] ?? '';
        if (!is_string($email)) {
            return $this->_prepareValue($value, $assoc);
        }
        $email = trim($email);
        $defaults = [
            'username' => \core_text::strtolower($email),
            'firstname' => clean_param($email, \core_user::get_property_type('firstname')),
            'lastname' => '.',
            'email2' => $email,
        ];
        return $this->_prepareValue($defaults[$this->getName()], $assoc);
    }
}
