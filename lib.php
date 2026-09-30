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
 * Site-wide tweaks for Librería de Satoshi.
 *
 * @package    local_libreria
 * @copyright  2026 local_libreria contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Retain the plugin's existing direct-access guard.
// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalNotNeeded
defined('MOODLE_INTERNAL') || die();

/**
 * Simplify email signup when enabled, otherwise retain the pseudonym note.
 *
 * The optional short form supplies blank-field defaults before core validation.
 * The standard form keeps its existing required fields and explanatory note.
 *
 * Only applies to email self-registration. Nostr logins (auth_nostr) never
 * render this form, and the callback is not executed for the mobile app or
 * the web services API.
 *
 * @param MoodleQuickForm $mform The signup form.
 */
function local_libreria_extend_signup_form($mform) {
    global $CFG;

    if ($CFG->registerauth === 'email' && get_config('local_libreria', 'simplifiedsignup')) {
        \local_libreria\signup::extend_form($mform);
        return;
    }

    $mform->addElement(
        'static',
        'local_libreria_pseudonote',
        '',
        get_string('pseudonote', 'local_libreria')
    );

    // The callback runs after the core elements are defined, which would leave
    // the note at the bottom of the form. Move it up to the name fields.
    if ($mform->elementExists('firstname')) {
        $mform->insertElementBefore(
            $mform->removeElement('local_libreria_pseudonote', false),
            'firstname'
        );
    }
}
