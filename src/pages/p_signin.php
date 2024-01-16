<?php
// pages/p_signin.php -- HotCRP password reset partials
// Copyright (c) 2006-2022 Eddie Kohler; see LICENSE.

class Signin_Page {
    /** @var ?string */
    public $_reset_tokstr;
    /** @var ?TokenInfo */
    public $_reset_token;
    /** @var ?Contact */
    public $_reset_user;
    /** @var ?MessageSet */
    private $_ms;

    static private function bad_post_error(Contact $user, Qrequest $qreq, $action) {
        $sid = $qreq->qsid() ?? "";
        $msg = "{$user->conf->dbname}: ignoring unvalidated {$action}"
            . ", sid=" . ($sid === "" ? ".empty" : $sid);
        if ($qreq->email) {
            $msg .= ", email=" . $qreq->email;
        }
        if ($qreq->password) {
            $msg .= ", password";
        }
        if ($qreq->post) {
            $msg .= ", post=" . $qreq->post;
        }
        if ($qreq->sessionreport) {
            $msg .= ", sessionreport=" . $qreq->sessionreport;
        }
        if ($sid !== "" || $action !== "signin") {
            error_log($msg);
        }
        $qreq->open_session();
        if ($qreq->post_retry) {
            $user->conf->error_msg($user->conf->_i("session_failed_error"));
        } else {
            $user->conf->warning_msg($user->conf->_i("badpost"));
        }
    }

    /** @return MessageSet */
    function ms() {
        $this->_ms = $this->_ms ?? new MessageSet;
        return $this->_ms;
    }
    /** @param string $field
     * @param string $rest
     * @return string */
    private function control_class($field, $rest = "") {
        return $this->_ms ? $this->_ms->control_class($field, $rest) : $rest;
    }
    /** @param string $field
     * @return string */
    private function feedback_html_at($field) {
        return $this->_ms ? $this->_ms->feedback_html_at($field) : "";
    }
    /** @param string $field
     * @return int */
    private function problem_status_at($field) {
        return $this->_ms ? $this->_ms->problem_status_at($field) : 0;
    }


    // Signin request
    /** @param ComponentSet $cs */
    function signin_request(Contact $user, Qrequest $qreq, $cs) {
        assert($qreq->method() === "POST");
        if ($qreq->cancel) {
            $info = ["ok" => false];
            foreach ($cs->members("signin/request") as $gj) {
                $info = call_user_func($gj->signin_function, $user, $qreq, $info, $gj);
            }
            $user->conf->redirect();
        } else if ($user->conf->opt("httpAuthLogin")) {
            LoginHelper::check_http_auth($user, $qreq);
        } else if ($qreq->valid_post()) {
            if (!$user->is_empty() && strcasecmp($qreq->email, $user->email) === 0) {
                $user->conf->redirect();
            } else if (!$qreq->start) {
                $info = ["ok" => true];
                foreach ($cs->members("signin/request") as $gj) {
                    $info = call_user_func($gj->signin_function, $user, $qreq, $info, $gj);
                }
                if ($info["ok"] || isset($info["redirect"])) {
                    $user->conf->redirect($info["redirect"] ?? "");
                } else if (($code = self::check_password_as_reset_code($user, $qreq))) {
                    $user->conf->redirect_hoturl("resetpassword", ["__PATH__" => $code]);
                } else {
                    LoginHelper::login_error($user->conf, $qreq->email, $info, $this->ms());
                }
            }
        } else {
            self::bad_post_error($user, $qreq, "signin");
        }
    }

    static function signin_request_basic(Contact $user, Qrequest $qreq, $info) {
        if (!$info["ok"]) {
            return $info;
        } else if ($user->conf->external_login()) {
            return LoginHelper::external_login_info($user->conf, $qreq);
        } else {
            return LoginHelper::login_info($user->conf, $qreq);
        }
    }

    static function signin_request_success(Contact $user, Qrequest $qreq, $info)  {
        if (!$info["ok"]) {
            return $info;
        } else {
            return LoginHelper::login_complete($info, $qreq);
        }
    }

    /** @param string $token
     * @return ?TokenInfo */
    static private function _find_reset_token(Conf $conf, $token) {
        if ($token) {
            $is_cdb = str_starts_with($token, "2") /* XXX */ || str_starts_with($token, "hcpw1");
            if (($tok = TokenInfo::find($token, $conf, $is_cdb))
                && $tok->is_active()
                && $tok->capabilityType === TokenInfo::RESETPASSWORD) {
                return $tok;
            }
        }
        return null;
    }

    /** @param Qrequest $qreq
     * @return string|false */
    static function check_password_as_reset_code(Contact $user, $qreq) {
        $pw = trim($qreq->password ?? "");
        if (($cap = self::_find_reset_token($user->conf, $pw))
            && ($capuser = $cap->user())
            && strcasecmp($capuser->email, trim($qreq->email)) === 0) {
            return $pw;
        } else {
            return false;
        }
    }

    /** @param ComponentSet $cs */
    static function print_signin_head(Contact $user, Qrequest $qreq, $cs) {
        $st = $user->conf->saved_messages_status();
        $qreq->print_header("Sign in", "home");
        $cs->push_print_cleanup("__footer");
    }

    static function print_form_start_for(Qrequest $qreq, $page, $extraclass = "") {
        echo '<div class="', Ht::add_tokens("homegrp", $extraclass),
            '" id="homeaccount">',
            Ht::form($qreq->conf()->hoturl($page), ["class" => "compact-form ui-submit js-signin"]),
            Ht::hidden("post", $qreq->post_value(true));
        if ($qreq->is_post() && !$qreq->valid_token()) {
            echo Ht::hidden("post_retry", "1");
        }
    }

    /** @param ComponentSet $cs */
    static function print_signin_form(Contact $user, Qrequest $qreq, $cs) {
        $conf = $user->conf;
        if (($password_reset = $qreq->csession("password_reset"))) {
            if ($password_reset->time < Conf::$now - 900) {
                $qreq->unset_csession("password_reset");
            } else if (!isset($qreq->email)) {
                $qreq->email = $password_reset->email;
            }
        }

        $unfolded = $cs->root === "signin" || $qreq->signin;
        self::print_form_start_for($qreq, "signin", $unfolded ? "foldo" : "foldc");
        if ($qreq->redirect) {
            echo Ht::hidden("redirect", $qreq->redirect);
        }
        if (!$unfolded) {
            echo Ht::unstash_script('hotcrp.fold("homeaccount",false)');
        }

        $cs->print_group("signin/form");

        if (!$unfolded) {
            echo '<div class="fn">',
                Ht::submit("start", "Sign in", ["class" => "btn-success", "tabindex" => 1, "value" => 1]),
                '</div>';
        }
        echo '</form></div>';
    }

    static function print_signin_form_description(Contact $user, Qrequest $qreq) {
        if (($su = Contact::session_users($qreq))) {
            $nav = $qreq->navigation();
            $links = [];
            foreach ($su as $i => $email) {
                $usuf = count($su) > 1 ? "u/{$i}/" : "";
                $links[] = '<a href="' . htmlspecialchars($nav->base_path_relative . $usuf) . '">' . htmlspecialchars($email) . '</a>';
            }
            echo '<p class="is-warning"><span class="warning-mark"></span> ', $user->conf->_("You are already signed in as %s. Use this form to add another account to this browser session.", commajoin($links)), '</p>';
        }
        if (($t = $user->conf->_("Sign in to submit or review applications.")) !== "") {
            echo '<p class="mb-5">', $t, '</p>';
        }
    }

    function print_signin_form_email(Contact $user, Qrequest $qreq) {
        $is_external_login = $user->conf->external_login();
        $email = $qreq->email ?? "";
        echo '<div class="', $this->control_class("email", "f-i fx"), '">',
            Ht::label($is_external_login ? "Username" : "Email", "signin_email"),
            $this->feedback_html_at("email"),
            Ht::entry("email", $email, [
                "size" => 36, "id" => "signin_email", "class" => "fullw",
                "autocomplete" => "username", "tabindex" => 1,
                "type" => !$is_external_login && !str_ends_with($email, "@_.com") ? "email" : "text",
                "autofocus" => $this->problem_status_at("email")
                    || $email === ""
                    || (!$this->problem_status_at("password") && !$qreq->csession("password_reset"))
            ]), '</div>';
    }

    function print_signin_form_password(Contact $user, Qrequest $qreq) {
        $is_external_login = $user->conf->external_login();
        echo '<div class="', $this->control_class("password", "f-i fx"), '">';
        if (!$is_external_login) {
            echo '<div class="float-right"><a href="',
                $user->conf->hoturl("forgotpassword"),
                '" class="n ulh small uic js-href-add-email">Forgot your password?</a></div>';
        }
        $password_reset = $qreq->csession("password_reset");
        echo Ht::label("Password", "signin_password"),
            $this->feedback_html_at("password"),
            Ht::password("password",
                $this->problem_status_at("password") !== 1 ? "" : $qreq->password, [
                "size" => 36, "id" => "signin_password", "class" => "fullw",
                "autocomplete" => "current-password", "tabindex" => 1,
                "autofocus" => !$this->problem_status_at("email")
                    && $qreq->email
                    && ($this->problem_status_at("password") || $password_reset)
            ]), '</div>';
        if ($password_reset) {
            echo Ht::unstash_script("\$(function(){\$(\"#signin_password\").val(" . json_encode_browser($password_reset->password) . ")})");
        }
    }

    /** @param ComponentSet $cs */
    static function print_signin_form_actions(Contact $user, Qrequest $qreq, $cs) {
        echo '<div class="popup-actions fx">',
            Ht::submit("", "Sign in", ["id" => "signin_signin", "class" => "btn-success", "tabindex" => 1]);
        if ($cs->root !== "home") {
            echo Ht::submit("cancel", "Cancel", ["tabindex" => 1, "formnovalidate" => true, "class" => "uic js-no-signin"]);
        }
        echo '</div>';
    }

    static function print_signin_form_create(Contact $user) {
        if ($user->conf->allow_user_self_register()) {
            echo '<p class="mt-2 hint fx">New to the site? <a href="',
                $user->conf->hoturl("newaccount"),
                '" class="uic js-href-add-email">Create an account</a></p>';
        }
    }


    // signout
    static function signout_request(Contact $user, Qrequest $qreq) {
        assert($qreq->method() === "POST");
        if ($qreq->cancel) {
            $user->conf->redirect();
        } else if ($qreq->valid_post()) {
            LoginHelper::logout($user, $qreq, true);
            $user->conf->redirect_hoturl("index", "signedout=1");
        } else if ($user->is_empty()) {
            $user->conf->redirect_hoturl("index", "signedout=1");
        } else {
            self::bad_post_error($user, $qreq, "signout");
        }
    }

    /** @param ComponentSet $cs */
    static function print_signout_head(Contact $user, Qrequest $qreq, $cs) {
        $qreq->print_header("Sign out", "signout", ["action_bar" => ""]);
        $cs->push_print_cleanup("__footer");
    }
    /** @param ComponentSet $cs */
    static function print_signout_body(Contact $user, Qrequest $qreq, $cs) {
        self::print_form_start_for($qreq, "signout");
        if ($user->is_empty()) {
            echo '<div class="mb-5">',
                $user->conf->_("You are not signed in."),
                " ", Ht::link("Return home", $user->conf->hoturl("index")),
                '</div>';
        } else {
            echo '<div class="mb-5">',
                $user->conf->_("Use this page to sign out of the site."),
                '</div><div class="popup-actions">',
                Ht::submit("Sign out", ["class" => "btn-danger float-left", "value" => 1]),
                Ht::submit("cancel", "Cancel", ["class" => "float-left uic js-no-signin", "formnovalidate" => true]),
                '</div>';
        }
        echo '</form></div>';
    }


    // newaccount
    /** @param array $info
     * @return ?HotCRPMailPreparation */
    function mail_user(Conf $conf, $info) {
        $user = $info["user"];
        $prep = $user->send_mail($info["mailtemplate"], $info["mailrest"] ?? null);
        if (!$prep)  {
            if ($conf->opt("sendEmail")) {
                $conf->error_msg("<0>The email address you provided seems invalid. Please try again.");
                $this->ms()->error_at("email");
            } else {
                $conf->error_msg("<0>The system cannot send email at this time. You’ll need help from the site administrator to sign in.");
            }
        } else if (strpos($info["mailtemplate"], "@newaccount") !== false) {
            $conf->success_msg("<0>Sent mail to {$user->email}. When you receive that mail, follow the link to set a password and sign in to the site.");
        } else {
            $conf->success_msg("<0>Sent mail to {$user->email}. When you receive that mail, follow the link to reset your password.");
            if ($prep->reset_capability) {
                $conf->log_for($user, null, "Password link sent " . substr($prep->reset_capability, 0, 8) . "...");
            }
        }
        return $prep;
    }

    private function _print_email_entry($user, $qreq, $k) {
        echo '<div class="', $this->control_class($k, "f-i"), '">',
            '<label for="', $k, '">',
            ($k === "email" ? "Email" : "Email or password reset code"),
            '</label>',
            $this->feedback_html_at("resetcap"),
            $this->feedback_html_at("email"),
            Ht::entry($k, $qreq[$k], [
                "size" => 36, "id" => $k, "class" => "fullw",
                "autocomplete" => $k === "email" ? $k : null,
                "type" => $k === "email" ? $k : "text",
                "autofocus" => true
            ]), '</div>';
    }

    static private function _create_message(Conf $conf) {
        return $conf->_("Enter your email and we’ll create an account and send you instructions for signing in.");
    }
    function create_request(Contact $user, Qrequest $qreq) {
        assert($qreq->method() === "POST");
        $conf = $user->conf;
        if ($qreq->cancel) {
            $conf->redirect();
        } else if (!$user->conf->allow_user_self_register()) {
            // do nothing
        } else if ($qreq->valid_post()) {
            $info = LoginHelper::new_account_info($user->conf, $qreq);
            if ($info["ok"]) {
                $prep = $this->mail_user($user->conf, $info);
                if ($prep
                    && $prep->reset_capability
                    && isset($info["firstuser"])) {
                    $conf->success_msg("<0>As the first user, you have been assigned system administrator privilege. Use this screen to set a password. All later users will have to sign in normally.");
                    $conf->redirect_hoturl("resetpassword", ["__PATH__" => $prep->reset_capability]);
                } else if ($prep) {
                    $conf->redirect_hoturl("signin");
                }
            } else {
                LoginHelper::login_error($user->conf, $qreq->email, $info, $this->ms());
            }
        } else {
            self::bad_post_error($user, $qreq, "newaccount");
        }
    }
    /** @param ComponentSet $cs */
    static function print_newaccount_head(Contact $user, Qrequest $qreq, $cs) {
        $qreq->print_header("New account", "newaccount", ["action_bar" => ""]);
        $cs->push_print_cleanup("__footer");
        if (!$user->conf->allow_user_self_register()) {
            $user->conf->error_msg("<0>User self-registration is disabled on this site.");
            echo '<p class="mb-5">', Ht::link("Return home", $user->conf->hoturl("index")), '</p>';
            return false;
        }
    }
    /** @param ComponentSet $cs */
    static function print_newaccount_body(Contact $user, Qrequest $qreq, $cs) {
        self::print_form_start_for($qreq, "newaccount");
        $cs->print_group("newaccount/form");
        echo '</form></div>';
        Ht::stash_script("hotcrp.focus_within(\$(\"#homeaccount\"));window.scroll(0,0)");
    }
    static function print_newaccount_form_description(Contact $user) {
        $m = $user->conf->_("Enter your email and we’ll create an account and send you instructions for signing in.");
        if ($m) {
            echo '<p class="mb-5">', $m, '</p>';
        }
    }
    function print_newaccount_form_email(Contact $user, Qrequest $qreq) {
        $this->_print_email_entry($user, $qreq, "email");
    }
    static function print_newaccount_form_actions(Contact $user, Qrequest $qreq) {
        echo '<div class="popup-actions">',
            Ht::submit("Create account", ["class" => "btn-primary"]),
            Ht::submit("cancel", "Cancel", ["class" => "uic js-no-signin", "formnovalidate" => true]),
            '</div>';
    }


    // Forgot password request
    static function forgot_externallogin_message(Contact $user) {
        $user->conf->error_msg("<0>Password reset links aren’t used for this site. Contact your system administrator if you’ve forgotten your password.");
        echo '<p class="mb-5">', Ht::link("Return home", $user->conf->hoturl("index")), '</p>';
        return false;
    }
    function forgot_request(Contact $user, Qrequest $qreq) {
        assert($qreq->method() === "POST");
        if ($qreq->cancel) {
            $user->conf->redirect();
        } else if ($qreq->valid_post()) {
            $info = LoginHelper::forgot_password_info($user->conf, $qreq, false);
            if ($info["ok"]) {
                $this->mail_user($user->conf, $info);
                $user->conf->redirect($info["redirect"] ?? $qreq->annex("redirect"));
            } else {
                LoginHelper::login_error($user->conf, $qreq->email, $info, $this->ms());
            }
        } else if ($qreq->is_post()) {
            self::bad_post_error($user, $qreq, "forgotpassword");
        }
    }
    static function print_forgot_head(Contact $user, Qrequest $qreq, $cs) {
        $qreq->print_header("Forgot password", "resetpassword", ["action_bar" => ""]);
        $cs->push_print_cleanup("__footer");
        if ($user->conf->external_login()) {
            return $cs->print("forgotpassword/__externallogin");
        }
    }
    static function print_forgot_body(Contact $user, Qrequest $qreq, $cs) {
        self::print_form_start_for($qreq, "forgotpassword");
        $cs->print_group("forgotpassword/form");
        echo '</form></div>';
        Ht::stash_script("hotcrp.focus_within(\$(\"#homeaccount\"));window.scroll(0,0)");
    }
    static function print_forgot_form_description(Contact $user, Qrequest $qreq, $cs) {
        echo '<p class="mb-5">Enter your email and we’ll send you a link to reset your password.';
        if ($cs->root === "resetpassword") {
            echo ' Or enter a password reset code if you have one.';
        }
        echo '</p>';
    }
    function print_forgot_form_email(Contact $user, Qrequest $qreq, $cs) {
        $this->_print_email_entry($user, $qreq,
            $cs->root === "resetpassword" ? "resetcap" : "email");
    }
    function print_forgot_form_actions() {
        echo '<div class="popup-actions">',
            Ht::submit("Reset password", ["class" => $this->_reset_user ? "btn-success" : "btn-primary"]),
            Ht::submit("cancel", "Cancel", ["class" => "uic js-no-signin", "formnovalidate" => true]),
            '</div>';
    }


    // Password reset
    function reset_request(Contact $user, Qrequest $qreq) {
        $conf = $user->conf;
        if ($qreq->cancel) {
            $conf->redirect();
        } else if ($conf->external_login()) {
            return;
        }

        if ($qreq->resetcap === null /* [12] == XXX */
            && preg_match('/\A\/(hcpw[01][a-zA-Z]+|[12][-\w]+)(?:\/|\z)/', $qreq->path(), $m)) {
            $qreq->resetcap = $m[1];
        }

        // set $this->_reset_tokstr
        $resetcap = trim((string) $qreq->resetcap); /* [12] == XXX */
        if (preg_match('/\A\/?(hcpw[01][a-zA-Z]+|[12][-\w]+)\/?\z/', $resetcap, $m)) {
            $this->_reset_tokstr = $m[1];
        } else if (strpos($resetcap, "@") !== false) {
            if ($qreq->valid_post()) {
                $nqreq = new Qrequest("POST", ["email" => $resetcap]);
                $nqreq->approve_token();
                $nqreq->set_annex("redirect", $user->conf->hoturl_raw("resetpassword", null, Conf::HOTURL_SERVERREL));
                $this->forgot_request($user, $nqreq); // may redirect
                if ($this->problem_status_at("email")) {
                    $this->ms()->error_at("resetcap");
                }
            }
        }

        // set $this->_reset_token and $this->_reset_user
        if ($this->_reset_tokstr) {
            if (($tok = self::_find_reset_token($conf, $this->_reset_tokstr))) {
                $this->_reset_token = $tok;
                $this->_reset_user = $tok->user();
            } else {
                $this->ms()->error_at("resetcap", "Unknown or expired password reset code. Please check that you entered the code correctly.");
            }
        }

        // check passwords
        if ($this->_reset_user) {
            if ($qreq->valid_post()) {
                $this->reset_valid_post_request($user, $qreq);
            } else if ($qreq->is_post()) {
                self::bad_post_error($user, $qreq, "resetpassword");
            }
        } else if ($this->_reset_token) {
            $this->ms()->error_at("resetcap", "This password reset code refers to a user who no longer exists. Either create a new account or contact the conference administrator.");
        }
    }
    private function reset_valid_post_request(Contact $user, Qrequest $qreq) {
        $p1 = (string) $qreq->password;
        $p2 = (string) $qreq->password2;
        if ($p1 === "") {
            if ($p2 !== "" || $qreq->autopassword) {
                $this->ms()->error_at("password", "Password required.");
            }
        } else if (trim($p1) !== $p1) {
            $this->ms()->error_at("password", "Passwords cannot begin or end with spaces.");
            $this->ms()->error_at("password2");
        } else if (strlen($p1) <= 5) {
            $this->ms()->error_at("password", "Passwords must be at least six characters long.");
            $this->ms()->error_at("password2");
        } else if (!Contact::valid_password($p1)) {
            $this->ms()->error_at("password", "Invalid password.");
            $this->ms()->error_at("password2");
        } else if ($p1 !== $p2) {
            $this->ms()->error_at("password", "The passwords you entered did not match.");
            $this->ms()->error_at("password2");
        } else {
            $accthere = $this->_reset_user->ensure_account_here();
            $accthere->change_password($p1);
            $accthere->log_activity("Password reset via " . substr($this->_reset_tokstr, 0, 8) . "...");
            $user->conf->success_msg("<0>Password changed. Use the new password to sign in below.");
            $this->_reset_token->delete();
            $qreq->set_csession("password_reset", (object) [
                "time" => Conf::$now,
                "email" => $this->_reset_user->email,
                "password" => $p1
            ]);
            $user->conf->redirect_hoturl("signin");
        }
    }
    static function print_reset_head(Contact $user, Qrequest $qreq, $cs) {
        $qreq->print_header("Reset password", "resetpassword", ["action_bar" => ""]);
        $cs->push_print_cleanup("__footer");
        if ($user->conf->external_login()) {
            return $cs->print("forgotpassword/__externallogin");
        }
    }
    function print_reset_body(Contact $user, Qrequest $qreq, $cs) {
        self::print_form_start_for($qreq, "resetpassword");
        if ($this->_reset_user) {
            echo Ht::hidden("resetcap", $this->_reset_tokstr);
            $cs->print_group("resetpassword/form");
        } else {
            $cs->print_group("forgotpassword/form");
        }
        echo '</form></div>';
        Ht::stash_script("hotcrp.focus_within(\$(\"#homeaccount\"));window.scroll(0,0)");
    }
    static function print_reset_form_description() {
        echo '<p class="mb-5">Use this form to set a new password. You may want to use the random password we’ve chosen.</p>';
    }
    function print_reset_form_email() {
        echo '<div class="f-i"><label>Email</label>', htmlspecialchars($this->_reset_user->email), '</div>',
            Ht::entry("email", $this->_reset_user->email, ["class" => "hidden", "autocomplete" => "username"]);
    }
    static function print_reset_form_autopassword(Contact $user, Qrequest $qreq) {
        if (!isset($qreq->autopassword)
            || trim($qreq->autopassword) !== $qreq->autopassword
            || strlen($qreq->autopassword) < 16
            || !preg_match('/\A[-0-9A-Za-z@_+=]*\z/', $qreq->autopassword)) {
            $qreq->autopassword = hotcrp_random_password();
        }
        echo '<div class="f-i"><label for="autopassword">Suggested strong password</label>',
            Ht::entry("autopassword", $qreq->autopassword, ["class" => "fullw", "size" => 36, "id" => "autopassword", "readonly" => true]),
            '</div>';
    }
    function print_reset_form_password() {
        echo '<div class="', $this->control_class("password", "f-i"), '">',
            '<label for="password">New password</label>',
            $this->feedback_html_at("password"),
            Ht::password("password", "", ["class" => "fullw", "size" => 36, "id" => "password", "autocomplete" => "new-password", "autofocus" => true]),
            '</div>',

            '<div class="', $this->control_class("password2", "f-i"), '">',
            '<label for="password2">Repeat new password</label>',
            $this->feedback_html_at("password2"),
            Ht::password("password2", "", ["class" => "fullw", "size" => 36, "id" => "password2", "autocomplete" => "new-password"]),
            '</div>';
    }
}
