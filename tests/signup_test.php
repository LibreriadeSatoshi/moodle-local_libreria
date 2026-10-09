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
            'country' => 'CL',
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
        $this->assertNotNull($user, 'Email, password and country must pass the real signup validation.');
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
        $this->assertSame('CL', $saved->country);
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
     * The removed repeated-email input must not affect signup, even if a stale client posts it.
     */
    public function test_email_confirmation_is_derived_from_email(): void {
        $user = $this->submit(['email2' => 'different@example.com'])->get_data();
        $this->assertNotNull($user);
        $this->assertSame($user->email, $user->email2);
    }

    /**
     * Leaving the country placeholder selected must prevent registration.
     */
    public function test_blank_country_is_rejected(): void {
        $this->assert_field_error($this->submit(['country' => '']), 'country');
    }

    /**
     * Omitting country from a request must not bypass server-side validation.
     */
    public function test_missing_country_is_rejected(): void {
        \login_signup_form::mock_submit([
            'email' => 'student@example.com', 'password' => 'Learning!123',
        ]);
        $this->assert_field_error(new \login_signup_form(), 'country');
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
            'email' => 'student@example.com', 'password' => 'Learning!123', 'firstname' => 'Ada', 'country' => 'CL',
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
        foreach (['username', 'firstname', 'lastname', 'city'] as $name) {
            $this->assertEquals(1, $xpath->query('//details//*[@name="' . $name . '"]')->length, $name);
        }
        $this->assertEquals(0, $xpath->query('//input[@name="email2" and not(@type="hidden")]')->length);
        foreach (['email', 'password', 'country'] as $name) {
            $this->assertEquals(0, $xpath->query('//details//*[@name="' . $name . '"]')->length, $name);
        }
        $this->assertNotNull($document->getElementById('id_country'));
    }

    /**
     * Validation errors in optional fields are not hidden inside a closed section.
     */
    public function test_failed_submission_expands_details(): void {
        $this->getDataGenerator()->create_user(['username' => 'existingstudent']);
        $form = $this->submit(['username' => 'existingstudent']);
        $this->assertNull($form->get_data());
        $document = new \DOMDocument();
        @$document->loadHTML($form->render());
        $xpath = new \DOMXPath($document);
        $this->assertEquals(1, $xpath->query('//details[@open]')->length);
    }

    /**
     * Optional custom fields share one native details section with standard fields.
     */
    public function test_optional_custom_fields_share_one_section(): void {
        $gender = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'menu', 'shortname' => 'gender', 'name' => 'Gender',
            'signup' => 1, 'required' => 0, 'param1' => "Prefer not to answer\nAnother answer",
            'defaultdata' => 'Prefer not to answer',
        ]);
        $form = new \login_signup_form();
        $document = new \DOMDocument();
        @$document->loadHTML($form->render());
        $xpath = new \DOMXPath($document);
        $this->assertEquals(1, $xpath->query('//details[not(@open)]')->length);
        $this->assertEquals(1, $xpath->query('//details//select[@name="profile_field_gender"]')->length);
        $this->assertEquals(0, $xpath->query('//fieldset//details')->length);
        $this->assertEquals(0, $xpath->query('//fieldset[@id="id_category_' . $gender->categoryid . '"]')->length);
        $this->assertEquals(0, $xpath->query('//details//button[@type="submit"]')->length);
        $this->assertEquals(0, $xpath->query('//details//input[@name="email2"]')->length);

        $user = $this->submit(['profile_field_gender' => 'Another answer'])->get_data();
        $this->assertNotNull($user);
        $this->assertSame('Another answer', $user->profile_field_gender);
    }

    /**
     * CAPTCHA is outside the custom category and must not retain an empty header.
     */
    public function test_optional_category_removed_before_captcha(): void {
        set_config('recaptchapublickey', 'test-public-key');
        set_config('recaptchaprivatekey', 'test-private-key');
        set_config('recaptcha', 1, 'auth_email');
        $field = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'interests', 'name' => 'Interests',
            'signup' => 1, 'required' => 0,
        ]);
        $document = new \DOMDocument();
        @$document->loadHTML((new \login_signup_form())->render());
        $xpath = new \DOMXPath($document);
        $this->assertEquals(0, $xpath->query('//fieldset[@id="id_category_' . $field->categoryid . '"]')->length);
        $this->assertEquals(1, $xpath->query('//details//*[@name="profile_field_interests"]')->length);
        $this->assertEquals(0, $xpath->query('//details//*[@id="fitem_id_recaptcha_element"]')->length);
        $this->assertEquals(1, $xpath->query('//*[@id="fitem_id_recaptcha_element"]')->length);
    }

    /**
     * Required custom fields keep their category and validation outside optional details.
     */
    public function test_required_custom_fields_remain_outside_details(): void {
        $required = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'organisation', 'name' => 'Organisation',
            'signup' => 1, 'required' => 1,
        ]);
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'interests', 'name' => 'Interests',
            'signup' => 1, 'required' => 0, 'categoryid' => $required->categoryid,
        ]);
        $form = new \login_signup_form();
        $document = new \DOMDocument();
        @$document->loadHTML($form->render());
        $xpath = new \DOMXPath($document);
        $this->assertEquals(0, $xpath->query('//fieldset//details')->length);
        $this->assertEquals(0, $xpath->query('//details//*[@name="profile_field_organisation"]')->length);
        $this->assertEquals(1, $xpath->query('//details//*[@name="profile_field_interests"]')->length);
        $this->assertEquals(1, $xpath->query('//fieldset[@id="id_category_' . $required->categoryid . '"]')->length);
        $this->assert_field_error($this->submit(), 'profile_field_organisation');
        $this->assertNotNull($this->submit(['profile_field_organisation' => 'School'])->get_data());
    }

    /**
     * Switching the feature off keeps the repeated email field and custom categories.
     */
    public function test_disabled_feature_keeps_custom_fields_layout(): void {
        set_config('simplifiedsignup', 0, 'local_libreria');
        $field = $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'interests', 'name' => 'Interests', 'signup' => 1,
        ]);
        $form = new \login_signup_form();
        $document = new \DOMDocument();
        @$document->loadHTML($form->render());
        $xpath = new \DOMXPath($document);
        $this->assertEquals(0, $xpath->query('//details')->length);
        $this->assertEquals(1, $xpath->query('//input[@name="email2" and @type="text"]')->length);
        $this->assertEquals(1, $xpath->query('//fieldset[@id="id_category_' . $field->categoryid . '"]')->length);
    }

}
