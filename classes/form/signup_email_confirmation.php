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
require_once($CFG->libdir . '/form/hidden.php');

/**
 * Supply core's repeated email value from the single cleaned email input.
 *
 * @package local_libreria
 * @copyright 2026 Libreria de Satoshi
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class signup_email_confirmation extends \MoodleQuickForm_hidden {
    /**
     * Ignore any stale or supplied email2 value, including after validation errors.
     *
     * @param array $submitvalues Cleaned values supplied by QuickForm.
     * @param bool $assoc Whether to return an associative array.
     * @return mixed The email address in the requested export format.
     */
    public function exportValue(&$submitvalues, $assoc = false) { // phpcs:ignore moodle.NamingConventions.ValidFunctionName
        $email = $submitvalues['email'] ?? '';
        return $this->_prepareValue(is_string($email) ? trim($email) : '', $assoc);
    }
}
