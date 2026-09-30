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

/**
 * Settings for Libreria site customisations.
 *
 * @package local_libreria
 * @copyright 2026 Libreria de Satoshi
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_libreria', get_string('pluginname', 'local_libreria'));
    $settings->add(new admin_setting_configcheckbox(
        'local_libreria/simplifiedsignup',
        get_string('simplifiedsignup', 'local_libreria'),
        get_string('simplifiedsignup_desc', 'local_libreria'),
        0
    ));
    $ADMIN->add('localplugins', $settings);
}
