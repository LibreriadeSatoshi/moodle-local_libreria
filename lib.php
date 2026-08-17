<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Site-wide tweaks for Librería de Satoshi.
 *
 * @package    local_libreria
 * @copyright  2026 local_libreria contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tell people on the signup form that a pseudonym is acceptable.
 *
 * Moodle hardcodes firstname and lastname as required on the signup form and
 * they cannot be removed through settings, so rather than relabel the core
 * strings site-wide we add one explanatory line above the name fields.
 *
 * Only applies to email self-registration. Nostr logins (auth_nostr) never
 * render this form, and the callback is not executed for the mobile app or
 * the web services API.
 *
 * @param MoodleQuickForm $mform The signup form.
 */
function local_libreria_extend_signup_form($mform) {
    $mform->addElement('static', 'local_libreria_pseudonote', '',
        get_string('pseudonote', 'local_libreria'));

    // The callback runs after the core elements are defined, which would leave
    // the note at the bottom of the form. Move it up to the name fields.
    if ($mform->elementExists('firstname')) {
        $mform->insertElementBefore(
            $mform->removeElement('local_libreria_pseudonote', false),
            'firstname'
        );
    }
}
