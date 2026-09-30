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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;

/**
 * Tests the simplified form through Moodle's real signup and authentication flow.
 *
 * @package local_libreria
 * @copyright 2026 Libreria de Satoshi
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(signup::class)]
#[CoversFunction('local_libreria_extend_signup_form')]
final class signup_test extends \advanced_testcase {
    /**
     * Prepare email registration without changing any existing authentication code.
     */
    protected function setUp(): void {
        global $CFG, $PAGE;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/login/signup_form.php');
        set_config('registerauth', 'email');
        set_config('auth', 'email');
        set_config('passwordpolicy', 0);
        set_config('extendedusernamechars', 1);
        set_config('simplifiedsignup', 1, 'local_libreria');
        $PAGE->set_url('/login/signup.php');
    }

    /**
     * Submit exactly what a browser would send to the existing signup form.
     *
     * @param array $overrides Submitted field overrides.
     * @return \login_signup_form
     */
    private function submit(array $overrides = []): \login_signup_form {
        \login_signup_form::mock_submit(array_replace([
            'email' => 'Student+course@example.com',
            'password' => 'Learning!123',
            'username' => '',
            'firstname' => '',
            'lastname' => '',
            'email2' => '',
            'city' => '',
            'country' => '',
        ], $overrides));
        return new \login_signup_form();
    }

    /**
     * Check rejection at the intended field, rather than an unrelated required field.
     *
     * @param \login_signup_form $form Submitted form.
     * @param string $field Field expected to have the validation error.
     */
    private function assert_field_error(\login_signup_form $form, string $field): void {
        $this->assertNull($form->get_data());
        $document = new \DOMDocument();
        @$document->loadHTML($form->render());
        $error = $document->getElementById('id_error_' . $field);
        $this->assertNotNull($error);
        $this->assertNotSame('', trim($error->textContent), 'Expected a visible error for ' . $field);
    }

    /**
     * An email-only identity must survive registration, confirmation and login.
     */
    public function test_minimal_signup_confirmation_and_login(): void {
        global $DB, $SESSION;
        $form = $this->submit();
        $user = $form->get_data();
        $this->assertNotNull($user, 'Email and password alone must pass the real signup validation.');
        $this->assertSame('student+course@example.com', $user->username);
        $this->assertSame('Student+course@example.com', $user->firstname);
        $this->assertSame('.', $user->lastname);
        $this->assertSame($user->email, $user->email2);

        $sink = $this->redirectEmails();
        $SESSION->wantsurl = 'https://www.example.com/course/view.php?id=2';
        $user = signup_setup_new_user($user);
        core_login_post_signup_requests($user);
        $auth = get_auth_plugin('email');
        $auth->user_signup($user, false);
        $saved = $DB->get_record('user', ['username' => 'student+course@example.com'], '*', MUST_EXIST);
        $this->assertEquals(0, $saved->confirmed);
        $this->assertCount(1, $sink->get_messages());
        $this->assertSame('Student+course@example.com', $sink->get_messages()[0]->to);
        $this->assertSame($SESSION->wantsurl, get_user_preferences('auth_email_wantsurl', null, $saved));
        $this->assertEquals(AUTH_CONFIRM_OK, $auth->user_confirm($saved->username, $saved->secret));
        $loggedin = authenticate_user_login('student+course@example.com', 'Learning!123');
        $this->assertNotFalse($loggedin);
        $this->assertEquals($saved->id, $loggedin->id);
        $this->assertFalse(user_not_fully_set_up($loggedin));
    }

    /**
     * Optional values entered by the learner must not be overwritten.
     */
    public function test_explicit_details_are_preserved(): void {
        $user = $this->submit([
            'username' => 'student', 'firstname' => 'Ada', 'lastname' => 'Lovelace',
            'email2' => 'Student+course@example.com', 'city' => 'Santiago', 'country' => 'CL',
        ])->get_data();
        $this->assertNotNull($user);
        $this->assertSame('student', $user->username);
        $this->assertSame('Ada', $user->firstname);
        $this->assertSame('Lovelace', $user->lastname);
        $this->assertSame('Santiago', $user->city);
        $this->assertSame('CL', $user->country);
    }

    /**
     * Whitespace-only optional values behave like omitted fields.
     */
    public function test_blank_details_use_defaults(): void {
        $user = $this->submit(['username' => '  ', 'firstname' => '  ', 'lastname' => '  ', 'email2' => '  '])->get_data();
        $this->assertNotNull($user);
        $this->assertSame('student+course@example.com', $user->username);
        $this->assertSame('Student+course@example.com', $user->firstname);
        $this->assertSame('.', $user->lastname);
    }

    /**
     * A mistyped optional confirmation must still be rejected by Moodle.
     */
    public function test_email_confirmation_mismatch_is_rejected(): void {
        $this->assert_field_error($this->submit(['email2' => 'different@example.com']), 'email2');
    }

    /**
     * Invalid email input must not become a persisted identity.
     */
    public function test_invalid_email_is_rejected(): void {
        $this->assert_field_error($this->submit(['email' => 'not-an-email']), 'email');
    }

    /**
     * Existing password policy still applies to the short form.
     */
    public function test_password_policy_is_preserved(): void {
        set_config('passwordpolicy', 1);
        $this->assert_field_error($this->submit(['password' => 'x']), 'password');
    }

    /**
     * Existing account emails must not silently register a second account.
     */
    public function test_duplicate_email_is_rejected(): void {
        $this->getDataGenerator()->create_user(['username' => 'original', 'email' => 'Student+course@example.com']);
        $this->assert_field_error($this->submit(), 'email');
    }

    /**
     * A default username collision must surface as a correctable validation error.
     */
    public function test_duplicate_username_is_rejected(): void {
        $this->getDataGenerator()->create_user([
            'username' => 'student+course@example.com', 'email' => 'another@example.com',
        ]);
        $this->assert_field_error($this->submit(), 'username');
    }

    /**
     * Site-policy consent cannot be bypassed by leaving additional details blank.
     */
    public function test_consent_is_still_required(): void {
        set_config('sitepolicy', 'https://example.com/policy');
        $this->assert_field_error($this->submit(), 'policyagreed');
    }

    /**
     * Turning the feature off restores the original form contract.
     */
    public function test_disabled_feature_keeps_standard_requirements(): void {
        set_config('simplifiedsignup', 0, 'local_libreria');
        $this->assertNull($this->submit()->get_data());
    }

    /**
     * Preserve explicitly supplied names while defaulting only missing values.
     */
    public function test_partial_details_and_missing_inputs(): void {
        \login_signup_form::mock_submit([
            'email' => 'student@example.com', 'password' => 'Learning!123', 'firstname' => 'Ada',
        ]);
        $user = (new \login_signup_form())->get_data();
        $this->assertNotNull($user);
        $this->assertSame('student@example.com', $user->username);
        $this->assertSame('Ada', $user->firstname);
        $this->assertSame('.', $user->lastname);
    }

    /**
     * Correcting email on retry must recompute defaults from the corrected address.
     */
    public function test_defaults_follow_corrected_email_after_validation_failure(): void {
        set_config('passwordpolicy', 1);
        $form = $this->submit(['email' => 'typo@example.com', 'password' => 'x']);
        $this->assertNull($form->get_data());
        $document = new \DOMDocument();
        @$document->loadHTML($form->render());
        $submitted = ['email' => 'correct@example.com', 'password' => 'Learning!123'];
        foreach (['username', 'firstname', 'lastname', 'email2'] as $name) {
            $submitted[$name] = $document->getElementById('id_' . $name)->getAttribute('value');
        }
        $user = $this->submit($submitted)->get_data();
        $this->assertNotNull($user);
        $this->assertSame('correct@example.com', $user->username);
        $this->assertSame('correct@example.com', $user->firstname);
        $this->assertSame('correct@example.com', $user->email2);
    }

    /**
     * Values which become blank after Moodle strips markup must receive defaults.
     */
    public function test_sanitized_blank_names_receive_defaults(): void {
        $user = $this->submit(['firstname' => '<b></b>', 'lastname' => '<i></i>'])->get_data();
        $this->assertNotNull($user);
        $this->assertSame('Student+course@example.com', $user->firstname);
        $this->assertSame('.', $user->lastname);
    }

    /**
     * Custom authentication providers must keep their existing form behavior.
     */
    public function test_non_email_registration_is_unchanged(): void {
        set_config('registerauth', 'manual');
        $form = new \login_signup_form();
        $document = new \DOMDocument();
        @$document->loadHTML($form->render());
        $this->assertEquals(0, $document->getElementsByTagName('details')->length);
    }

    /**
     * A failed form cannot disable Moodle's CSRF protection.
     */
    public function test_invalid_session_key_is_rejected(): void {
        \login_signup_form::mock_submit(['email' => 'student@example.com', 'password' => 'Learning!123']);
        $_POST['sesskey'] = 'invalid-session-key';
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('invalidsesskey', 'error'));
        new \login_signup_form();
    }

    /**
     * The extra inputs remain usable, but are initially collapsed.
     */
    public function test_initial_form_layout(): void {
        $form = new \login_signup_form();
        $document = new \DOMDocument();
        @$document->loadHTML($form->render());
        $xpath = new \DOMXPath($document);
        $this->assertEquals(1, $xpath->query('//details[not(@open)]')->length);
        foreach (['username', 'firstname', 'lastname', 'email2', 'city', 'country'] as $name) {
            $this->assertEquals(1, $xpath->query('//details//*[@name="' . $name . '"]')->length, $name);
        }
        foreach (['email', 'password'] as $name) {
            $this->assertEquals(0, $xpath->query('//details//*[@name="' . $name . '"]')->length, $name);
        }
    }

    /**
     * Validation errors in optional fields are not hidden inside a closed section.
     */
    public function test_failed_submission_expands_details(): void {
        $form = $this->submit(['email2' => 'different@example.com']);
        $this->assertNull($form->get_data());
        $document = new \DOMDocument();
        @$document->loadHTML($form->render());
        $xpath = new \DOMXPath($document);
        $this->assertEquals(1, $xpath->query('//details[@open]')->length);
    }
}
