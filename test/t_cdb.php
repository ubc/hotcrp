<?php
// t_cdb.php -- HotCRP tests
// Copyright (c) 2006-2023 Eddie Kohler; see LICENSE.

class Cdb_Tester {
    /** @var Conf
     * @readonly */
    public $conf;
    /** @var UserStatus
     * @readonly */
    public $us1;
    /** @var Contact
     * @readonly */
    public $user_chair;

    const MARINA = "marina@poema.ru";

    function __construct(Conf $conf) {
        $this->conf = $conf;
        $this->us1 = new UserStatus($conf->root_user());
        $this->user_chair = $conf->checked_user_by_email("chair@_.com");

        if (!$conf->contactdb()) {
            error_log("! Error: The test contactdb has not been initialized.");
            error_log("! You may need to run `lib/createdb.sh -c test/cdb-options.php --no-dbuser --batch`.");
            exit(1);
        }
    }

    function test_setup() {
        $removables = ["te@_.com", "te2@_.com", "akhmatova@poema.ru"];
        $this->conf->qe("delete from ContactInfo where email?a", $removables);
        Dbl::qe($this->conf->contactdb(), "delete from ContactInfo where email?a", $removables);
    }

    function test_passwords_1() {
        user(self::MARINA)->change_password("rosdevitch");
        xassert_eqq(password(self::MARINA), "");
        xassert_neqq(password(self::MARINA, true), "");
        xassert(user(self::MARINA)->check_password("rosdevitch"));
        $this->conf->qe("update ContactInfo set password=? where contactId=?", "rosdevitch", user(self::MARINA)->contactId);
        xassert_neqq(password(self::MARINA), "");
        xassert_neqq(password(self::MARINA, true), "");
        xassert(user(self::MARINA)->check_password("rosdevitch"));

        // different password in localdb => both passwords work
        save_password(self::MARINA, "crapdevitch", false);
        xassert(user(self::MARINA)->check_password("crapdevitch"));
        xassert(user(self::MARINA)->check_password("rosdevitch"));

        // change contactdb password => both passwords change
        user(self::MARINA)->change_password("dungdevitch");
        xassert(user(self::MARINA)->check_password("dungdevitch"));
        xassert(!user(self::MARINA)->check_password("assdevitch"));
        xassert(!user(self::MARINA)->check_password("rosdevitch"));
        xassert(!user(self::MARINA)->check_password("crapdevitch"));

        // update contactdb password => old local password useless
        save_password(self::MARINA, "isdevitch", true);
        xassert_eqq(password(self::MARINA), "");
        xassert(user(self::MARINA)->check_password("isdevitch"));
        xassert(!user(self::MARINA)->check_password("dungdevitch"));

        // update local password only
        save_password(self::MARINA, "ncurses", false);
        xassert_eqq(password(self::MARINA), "ncurses");
        xassert_neqq(password(self::MARINA, true), "ncurses");
        xassert(user(self::MARINA)->check_password("ncurses"));

        // logging in with global password makes local password obsolete
        Conf::advance_current_time(Conf::$now + 3);
        xassert(user(self::MARINA)->check_password("isdevitch"));
        Conf::advance_current_time(Conf::$now + 3);
        xassert(!user(self::MARINA)->check_password("ncurses"));

        // null contactdb password => password is unset
        save_password(self::MARINA, null, true);
        $info = user(self::MARINA)->check_password_info("ncurses");
        xassert(!$info["ok"]);
        xassert($info["unset"] ?? null);

        // restore to "this is a cdb password"
        user(self::MARINA)->change_password("isdevitch");
        xassert_eqq(password(self::MARINA), "");
        save_password(self::MARINA, "isdevitch", true);
        xassert_eqq(password(self::MARINA, true), "isdevitch");
        // current status: local password is empty, global password "isdevitch"
    }

    function test_password_encryption() {
        // checking an unencrypted password encrypts it
        $mu = user(self::MARINA);
        xassert($mu->check_password("isdevitch"));
        xassert_eqq(substr(password(self::MARINA, true), 0, 2), " \$");
        xassert_eqq(password(self::MARINA), "");

        // checking an encrypted password doesn't change it
        save_password(self::MARINA, ' $$2y$10$/URgqlFgQHpfE6mg4NzJhOZbg9Cc2cng58pA4cikzRD9F0qIuygnm', true);
        save_password(self::MARINA, '', false);
        xassert(user(self::MARINA)->check_password("isdevitch"));
        xassert_eqq(password(self::MARINA, true), ' $$2y$10$/URgqlFgQHpfE6mg4NzJhOZbg9Cc2cng58pA4cikzRD9F0qIuygnm');
    }

    function test_cdb_import_1() {
        $result = Dbl::qe($this->conf->contactdb(), "insert into ContactInfo set firstName='Te', lastName='Thamrongrattanarit', email='te@_.com', affiliation='Brandeis University', collaborators='Computational Linguistics Magazine', password=' $$2y$10$/URgqlFgQHpfE6mg4NzJhOZbg9Cc2cng58pA4cikzRD9F0qIuygnm'");
        assert(!Dbl::is_error($result));
        Dbl::free($result);
        xassert(!maybe_user("te@_.com"));

        $u = $this->conf->cdb_user_by_email("te@_.com");
        xassert(!!$u);
        xassert_eqq($u->firstName, "Te");
        xassert_eqq($u->disablement, 0);

        // inserting them should succeed and borrow their data
        $acct = $this->us1->save_user((object) ["email" => "te@_.com"]);
        xassert(!!$acct);

        $te = user("te@_.com");
        xassert(!!$te);
        xassert_eqq($te->firstName, "Te");
        xassert_eqq($te->lastName, "Thamrongrattanarit");
        xassert_eqq($te->affiliation, "Brandeis University");
        xassert($te->check_password("isdevitch"));
        xassert_eqq($te->collaborators(), "Computational Linguistics Magazine");
    }

    function test_change_email() {
        $result = Dbl::qe($this->conf->contactdb(), "insert into ContactInfo set firstName='', lastName='Thamrongrattanarit 2', email='te2@_.com', affiliation='Brandeis University or something', collaborators='Newsweek Magazine', password=' $$2y$10$/URgqlFgQHpfE6mg4NzJhOZbg9Cc2cng58pA4cikzRD9F0qIuygnm'");
        xassert(!Dbl::is_error($result));
        Dbl::free($result);

        $u = $this->conf->cdb_user_by_email("te@_.com");
        xassert(!!$u);
        xassert_eqq($u->firstName, "Te");
        xassert_eqq($u->disablement, 0);

        $u = $this->conf->cdb_user_by_email("te2@_.com");
        xassert(!!$u);
        xassert_eqq($u->firstName, "");
        xassert_eqq($u->disablement, 0);

        // changing email works locally
        user("te@_.com")->change_email("te2@_.com");
        $te = maybe_user("te@_.com");
        xassert(!$te);

        $te2 = user("te2@_.com");
        xassert(!!$te2);
        xassert_eqq($te2->firstName, "Te");
        xassert_eqq($te2->lastName, "Thamrongrattanarit");
        xassert_eqq($te2->affiliation, "Brandeis University");

        $te2_cdb = $this->conf->fresh_cdb_user_by_email("te2@_.com");
        xassert(!!$te2_cdb);
        xassert_eqq($te2_cdb->firstName, "");
        xassert_eqq($te2_cdb->lastName, "Thamrongrattanarit 2");
        xassert_eqq($te2_cdb->email, "te2@_.com");
        xassert_eqq($te2_cdb->affiliation, "Brandeis University or something");
        xassert_eqq($te2_cdb->disablement, 0);

        // changing local email does not change cdb
        $acct = $this->us1->save_user((object) ["email" => "te2@_.com", "lastName" => "Thamrongrattanarit 1", "firstName" => "Te 1"]);
        xassert(!!$acct);

        $te2 = user("te2@_.com");
        xassert_eqq($te2->lastName, "Thamrongrattanarit 1");
        xassert_eqq($te2->affiliation, "Brandeis University");

        $te2_cdb = $this->conf->fresh_cdb_user_by_email("te2@_.com");
        xassert(!!$te2_cdb);
        xassert_eqq($te2_cdb->firstName, "");
        xassert_eqq($te2_cdb->lastName, "Thamrongrattanarit 2");
        xassert_eqq($te2_cdb->email, "te2@_.com");
        xassert_eqq($te2_cdb->affiliation, "Brandeis University or something");
        xassert_eqq($te2_cdb->disablement, 0);
    }

    function test_simplify_whitespace_on_save() {
        $acct = $this->us1->save_user((object) ["email" => "te2@_.com", "lastName" => " Thamrongrattanarit  1  \t", "firstName" => "Te  1", "affiliation" => "  Brandeis   Friendiversity"]);
        xassert(!!$acct);
        $te2 = user("te2@_.com");
        xassert_eqq($te2->firstName, "Te 1");
        xassert_eqq($te2->lastName, "Thamrongrattanarit 1");
        xassert_eqq($te2->affiliation, "Brandeis Friendiversity");
    }

    function test_chair_update_no_cdb() {
        Contact::set_main_user(user("marina@poema.ru"));
        $te2 = user("te2@_.com");
        $te2->cdb_user();
        Dbl::qe($this->conf->contactdb(), "update ContactInfo set affiliation='' where email='te2@_.com'");
        $acct = $this->us1->save_user((object) ["firstName" => "Wacky", "affiliation" => "String", "email" => "te2@_.com"]);
        xassert(!!$acct);
        $te2 = user("te2@_.com");
        xassert(!!$te2);
        xassert_eqq($te2->firstName, "Wacky");
        xassert_eqq($te2->lastName, "Thamrongrattanarit 1");
        xassert_eqq($te2->affiliation, "String");
        $te2_cdb = $this->conf->fresh_cdb_user_by_email("te2@_.com");
        xassert(!!$te2_cdb);
        xassert_eqq($te2_cdb->firstName, "");
        xassert_eqq($te2_cdb->lastName, "Thamrongrattanarit 2");
        xassert_eqq($te2_cdb->affiliation, "String");
    }

    function test_cdb_import_2() {
        $acct = $this->us1->save_user((object) ["email" => "te@_.com"]);
        xassert(!!$acct);
        $te = user("te@_.com");
        xassert_eqq($te->email, "te@_.com");
        xassert_eqq($te->firstName, "Te");
        xassert_eqq($te->lastName, "Thamrongrattanarit");
        xassert_eqq($te->affiliation, "Brandeis University");
        xassert_eqq($te->collaborators(), "Computational Linguistics Magazine");
    }

    function test_create_no_password_mail() {
        MailChecker::clear();
        $anna = "akhmatova@poema.ru";
        xassert(!maybe_user($anna));
        $acct = $this->us1->save_user((object) ["email" => $anna, "first" => "Anna", "last" => "Akhmatova"]);
        xassert(!!$acct);
        Dbl::qe("delete from ContactInfo where email=?", $anna);
        Dbl::qe($this->conf->contactdb(), "update ContactInfo set passwordUseTime=1 where email=?", $anna);
        save_password($anna, "aquablouse", true);
        MailChecker::check0();
    }

    function test_author_becomes_contact() {
        $user_estrin = user("estrin@usc.edu");
        $user_floyd = user("floyd@EE.lbl.gov");
        $user_van = user("van@ee.lbl.gov");
        xassert(!maybe_user("akhmatova@poema.ru")); // but she is in cdb

        $ps = new PaperStatus($this->conf->root_user());
        $ps->save_paper_json((object) [
            "id" => 1,
            "authors" => ["puneet@catarina.usc.edu", $user_estrin->email,
                          $user_floyd->email, $user_van->email, "akhmatova@poema.ru"]
        ]);

        $paper1 = $this->user_chair->checked_paper_by_id(1);
        $user_anna = user("akhmatova@poema.ru");
        xassert(!!$user_anna);
        xassert($user_anna->act_author_view($paper1));
        xassert($user_estrin->act_author_view($paper1));
        xassert($user_floyd->act_author_view($paper1));
        xassert($user_van->act_author_view($paper1));
    }

    function test_merge_accounts() {
        // user merging
        $this->us1->save_user((object) ["email" => "anne1@_.com", "tags" => ["a#1"], "roles" => (object) ["pc" => true]]);
        $this->us1->save_user((object) ["email" => "anne2@_.com", "first" => "Anne", "last" => "Dudfield", "data" => (object) ["data_test" => 139], "tags" => ["a#2", "b#3"], "roles" => (object) ["sysadmin" => true], "collaborators" => "derpo\n"]);
        $user_anne1 = user("anne1@_.com");
        $a1id = $user_anne1->contactId;
        xassert_eqq($user_anne1->firstName, "");
        xassert_eqq($user_anne1->lastName, "");
        xassert_eqq($user_anne1->collaborators(), "");
        xassert_eqq($user_anne1->tag_value("a"), 1.0);
        xassert_eqq($user_anne1->tag_value("b"), null);
        xassert_eqq($user_anne1->roles, Contact::ROLE_PC);
        xassert_eqq($user_anne1->data("data_test"), null);
        xassert_eqq($user_anne1->email, "anne1@_.com");
        xassert_assign($user_anne1, "paper,tag\n1,~butt#1\n2,~butt#2");

        $user_anne2 = user("anne2@_.com");
        $a2id = $user_anne2->contactId;
        xassert_eqq($user_anne2->firstName, "Anne");
        xassert_eqq($user_anne2->lastName, "Dudfield");
        xassert_eqq($user_anne2->collaborators(), "All (derpo)");
        xassert_eqq($user_anne2->tag_value("a"), 2.0);
        xassert_eqq($user_anne2->tag_value("b"), 3.0);
        xassert_eqq($user_anne2->roles, Contact::ROLE_ADMIN);
        xassert_eqq($user_anne2->data("data_test"), 139);
        xassert_eqq($user_anne2->email, "anne2@_.com");
        xassert_assign($user_anne2, "paper,tag\n2,~butt#3\n3,~butt#4");
        xassert_assign($this->user_chair, "paper,action,user\n1,conflict,anne2@_.com");
        xassert($user_anne1 && $user_anne2);

        $paper1 = $this->conf->checked_paper_by_id(1);
        xassert_eqq($paper1->tag_value("{$a1id}~butt"), 1.0);
        xassert_eqq($paper1->tag_value("{$a2id}~butt"), null);
        $paper2 = $this->conf->checked_paper_by_id(2);
        xassert_eqq($paper2->tag_value("{$a1id}~butt"), 2.0);
        xassert_eqq($paper2->tag_value("{$a2id}~butt"), 3.0);
        $paper3 = $this->conf->checked_paper_by_id(3);
        xassert_eqq($paper3->tag_value("{$a1id}~butt"), null);
        xassert_eqq($paper3->tag_value("{$a2id}~butt"), 4.0);

        $merger = new MergeContacts($user_anne2, $user_anne1);
        xassert($merger->run());
        $user_anne1 = user("anne1@_.com");
        $user_anne2 = maybe_user("anne2@_.com");
        xassert($user_anne1 && !$user_anne2);
        xassert_eqq($user_anne1->firstName, "Anne");
        xassert_eqq($user_anne1->lastName, "Dudfield");
        xassert_eqq($user_anne1->collaborators(), "All (derpo)");
        xassert_eqq($user_anne1->tag_value("a"), 1.0);
        xassert_eqq($user_anne1->tag_value("b"), 3.0);
        xassert_eqq($user_anne1->roles, Contact::ROLE_PC | Contact::ROLE_ADMIN);
        xassert_eqq($user_anne1->data("data_test"), 139);
        xassert_eqq($user_anne1->email, "anne1@_.com");
        $paper1 = $this->conf->checked_paper_by_id(1);
        xassert($paper1->has_conflict($user_anne1));
        xassert_eqq($paper1->tag_value("{$a2id}~butt"), null);
        xassert_eqq($paper1->tag_value("{$a1id}~butt"), 1.0);
        $paper2 = $this->conf->checked_paper_by_id(2);
        xassert_eqq($paper2->tag_value("{$a2id}~butt"), null);
        xassert_eqq($paper2->tag_value("{$a1id}~butt"), 2.0);
        $paper3 = $this->conf->checked_paper_by_id(3);
        xassert_eqq($paper3->tag_value("{$a2id}~butt"), null);
        xassert_eqq($paper3->tag_value("{$a1id}~butt"), 4.0);
    }

    function test_role_save_formats() {
        // different forms of profile saving
        $this->us1->save_user((object) ["email" => "anne1@_.com", "roles" => "pc"]);
        $user_anne1 = user("anne1@_.com");
        xassert_eqq($user_anne1->roles, Contact::ROLE_PC);

        $this->us1->save_user((object) ["email" => "anne1@_.com", "roles" => ["pc", "sysadmin"]]);
        $user_anne1 = user("anne1@_.com");
        xassert_eqq($user_anne1->roles, Contact::ROLE_PC | Contact::ROLE_ADMIN);

        $this->us1->save_user((object) ["email" => "anne1@_.com", "roles" => "chair, sysadmin"]);
        $user_anne1 = user("anne1@_.com");
        xassert_eqq($user_anne1->roles, Contact::ROLE_PC | Contact::ROLE_CHAIR | Contact::ROLE_ADMIN);

        $this->us1->save_user((object) ["email" => "anne1@_.com", "roles" => "-chair"]);
        $user_anne1 = user("anne1@_.com");
        xassert_eqq($user_anne1->roles, Contact::ROLE_PC | Contact::ROLE_ADMIN);

        $this->us1->save_user((object) ["email" => "anne1@_.com", "roles" => "-sysadmin"]);
        $user_anne1 = user("anne1@_.com");
        xassert_eqq($user_anne1->roles, Contact::ROLE_PC);

        $this->us1->save_user((object) ["email" => "anne1@_.com", "roles" => "+chair"]);
        $user_anne1 = user("anne1@_.com");
        xassert_eqq($user_anne1->roles, Contact::ROLE_PC | Contact::ROLE_CHAIR);
    }

    function test_import_props() {
        // betty1 is in neither db;
        // betty2 is in local db with no password or name;
        // betty3-5 are in cdb with name but no password;
        Dbl::qe($this->conf->dblink, "insert into ContactInfo (email, password) values ('betty2@_.com','')");
        Dbl::qe($this->conf->contactdb(), "insert into ContactInfo (email, password, firstName, lastName) values
            ('betty3@_.com','','Betty','Shabazz'),
            ('betty4@_.com','','Betty','Kelly'),
            ('betty5@_.com','','Betty','Davis')");
        foreach (["betty3@_.com", "betty4@_.com", "betty5@_.com"] as $email) {
            $this->conf->invalidate_user(Contact::make_cdb_email($this->conf, $email));
        }

        // registration name populates new records
        $u = Contact::make_keyed($this->conf, [
            "email" => "betty1@_.com",
            "name" => "Betty Grable"
        ])->store();
        xassert_eqq($u->firstName, "Betty");
        xassert_eqq($u->lastName, "Grable");
        xassert(!$u->is_disabled());
        $u = $this->conf->fresh_cdb_user_by_email("betty1@_.com");
        xassert_eqq($u->firstName, "Betty");
        xassert_eqq($u->lastName, "Grable");
        xassert(!$u->is_disabled());

        // registration name replaces empty local name, populates new cdb record
        $u = Contact::make_keyed($this->conf, [
            "email" => "betty2@_.com",
            "name" => "Betty Apiafi"
        ])->store();
        xassert_eqq($u->firstName, "Betty");
        xassert_eqq($u->lastName, "Apiafi");
        xassert(!$u->is_disabled());
        $u = $this->conf->fresh_cdb_user_by_email("betty2@_.com");
        xassert_eqq($u->firstName, "Betty");
        xassert_eqq($u->lastName, "Apiafi");
        xassert(!$u->is_disabled());

        // cdb name overrides registration name
        $u = Contact::make_keyed($this->conf, [
            "email" => "betty3@_.com",
            "name" => "Betty Crocker"
        ])->store();
        xassert_eqq($u->firstName, "Betty");
        xassert_eqq($u->lastName, "Shabazz");
        xassert(!$u->is_disabled());
        $u = $this->conf->fresh_cdb_user_by_email("betty3@_.com");
        xassert_eqq($u->firstName, "Betty");
        xassert_eqq($u->lastName, "Shabazz");
        xassert(!$u->is_disabled());

        // registration affiliation replaces empty affiliations
        $u = Contact::make_keyed($this->conf, [
            "email" => "betty4@_.com",
            "name" => "Betty Crocker",
            "affiliation" => "France"
        ])->store();
        xassert_eqq($u->firstName, "Betty");
        xassert_eqq($u->lastName, "Kelly");
        xassert_eqq($u->affiliation, "France");
        xassert(!$u->is_disabled());
        $u = $this->conf->fresh_cdb_user_by_email("betty4@_.com");
        xassert_eqq($u->firstName, "Betty");
        xassert_eqq($u->lastName, "Kelly");
        xassert_eqq($u->affiliation, "France");
        xassert(!$u->is_disabled());

        // ensure_account_here
        $u = $this->conf->fresh_user_by_email("betty5@_.com");
        xassert(!$u);
        $u = $this->conf->cdb_user_by_email("betty5@_.com");
        xassert(!$u->is_disabled());
        $u->ensure_account_here();
        $u = $this->conf->checked_user_by_email("betty5@_.com");
        xassert($u->has_account_here());
        xassert_eqq($u->firstName, "Betty");
        xassert_eqq($u->lastName, "Davis");
        xassert(!$u->is_disabled());
    }

    function test_pc_json() {
        $pc_json = $this->conf->hotcrp_pc_json($this->user_chair);
        xassert_eqq($pc_json[$pc_json["__order__"][0]]->email, "anne1@_.com");
        $this->conf->sort_by_last = true;
        $this->conf->invalidate_caches(["pc" => true]);
        $pc_json = $this->conf->hotcrp_pc_json($this->user_chair);
        xassert_eqq($pc_json[$pc_json["__order__"][0]]->email, "mgbaker@cs.stanford.edu");
        xassert_eqq($pc_json["12"]->email, "mgbaker@cs.stanford.edu");
        xassert_eqq($pc_json["12"]->lastpos, 5);
        xassert_eqq($pc_json["21"]->email, "vera@bombay.com");
        xassert_eqq($pc_json["21"]->lastpos, 5);
    }

    function test_cdb_update() {
        // Betty is in the local db, but not yet the contact db;
        // cdb_update should put her in the cdb
        Dbl::qe($this->conf->dblink, "insert into ContactInfo set email='betty6@_.com', password='Fart', firstName='Betty', lastName='Knowles'");
        $u = $this->conf->checked_user_by_email("betty6@_.com");
        xassert(!!$u);
        xassert(!$u->cdb_user());
        $u->update_cdb();
        $v = $u->cdb_user();
        xassert(!!$v);
        xassert_eqq($v->firstName, "Betty");
        xassert_eqq($v->lastName, "Knowles");
    }

    function test_email_authored_papers() {
        // Cengiz is in localdb and cdb as placeholder
        $u = $this->conf->fresh_user_by_email("cengiz@isi.edu");
        xassert(!!$u);
        xassert_eqq($u->firstName, "Cengiz");
        xassert_eqq($u->lastName, "Alaettinoğlu");
        xassert_eqq($u->disablement, Contact::DISABLEMENT_PLACEHOLDER);
        $ldb_cid = $u->contactId;

        $u = $this->conf->cdb_user_by_email("cengiz@isi.edu");
        xassert(!!$u);
        xassert_eqq($u->firstName, "Cengiz");
        xassert_eqq($u->lastName, "Alaettinoğlu");
        xassert_eqq($u->disablement, Contact::DISABLEMENT_PLACEHOLDER);
        $cdb_cid = $u->contactId;

        // remove localdb user and cdb user's roles
        Dbl::qe($this->conf->dblink, "delete from ContactInfo where contactId=?", $ldb_cid);
        Dbl::qe($this->conf->dblink, "delete from PaperConflict where contactId=?", $ldb_cid);
        Dbl::qe($this->conf->contactdb(), "delete from Roles where contactDbId=?", $cdb_cid);

        // make cdb user non-disabled, but empty name
        Dbl::qe($this->conf->contactdb(), "update ContactInfo set email=?, password=?, firstName=?, lastName=?, disabled=? where email=?",
            'cenGiz@isi.edu', 'TEST PASSWORD', '', '', 0,
            'cengiz@isi.edu');
        $this->conf->invalidate_user(Contact::make_cdb_email($this->conf, "cengiz@isi.edu"));

        // creating a local user adopts name from authorship record
        $u = Contact::make_email($this->conf, "Cengiz@isi.edu")->store();
        xassert($u->contactId > 0);
        xassert_eqq($u->firstName, "Cengiz");
        xassert_eqq($u->lastName, "Alaettinoğlu");

        // creating a local user updates empty name from contactdb
        $cdbu = $this->conf->fresh_cdb_user_by_email("CENGiz@ISI.edu");
        xassert_eqq($cdbu->firstName, "Cengiz");
        xassert_eqq($cdbu->lastName, "Alaettinoğlu");

        // both accounts have correct roles
        $prow = $this->conf->checked_paper_by_id(27);
        xassert($prow->has_author($u));
        xassert($u->is_author());
        xassert_eqq($u->cdb_roles(), Contact::ROLE_AUTHOR);
        xassert_eqq($cdbu->roles, Contact::ROLE_AUTHOR);
    }

    function test_claim_review() {
        // Sophia is in cdb, not local db
        Dbl::qe($this->conf->contactdb(), "insert into ContactInfo set email='sophia@dros.nl', password='', firstName='Sophia', lastName='Dros'");
        $user_sophia = $this->conf->fresh_user_by_email("sophia@dros.nl");
        xassert(!$user_sophia);
        $user_sophia = $this->conf->cdb_user_by_email("sophia@dros.nl");
        xassert(!!$user_sophia);

        // Cengiz gets a review
        $user_cengiz = $this->conf->checked_user_by_email("cengiz@isi.edu");
        $rrid = $this->user_chair->assign_review(3, $user_cengiz->contactId, REVIEW_EXTERNAL);
        xassert($rrid > 0);
        $paper3 = $this->conf->checked_paper_by_id(3);
        $rrow = $paper3->fresh_review_by_id($rrid);
        xassert(!!$rrow);
        xassert_eqq($rrow->contactId, $user_cengiz->contactId);

        // current user is logged in as both Cengiz and Sophia
        Contact::$session_users = ["cengiz@isi.edu", "sophia@dros.nl"];

        // current user cannot edit Cengiz's review for some random user
        $result = RequestReview_API::claimreview($user_cengiz, new Qrequest("POST", ["p" => "3", "r" => "$rrid", "email" => "betty6@_.com"]), $paper3);
        xassert_eqq($result->content["ok"], false);
        $rrow = $paper3->fresh_review_by_id($rrid);
        xassert(!!$rrow);
        xassert_eqq($rrow->contactId, $user_cengiz->contactId);

        // current user can claim Sophia's review, even as Cengiz
        $result = RequestReview_API::claimreview($user_cengiz, new Qrequest("POST", ["p" => "3", "r" => "$rrid", "email" => "sophia@dros.nl"]), $paper3);
        xassert_eqq($result->content["ok"], true);
        $user_sophia = $this->conf->checked_user_by_email("sophia@dros.nl");
        xassert(!!$user_sophia);
        $rrow = $paper3->fresh_review_by_id($rrid);
        xassert(!!$rrow);
        xassert_neqq($rrow->contactId, $user_cengiz->contactId);
        xassert_eqq($rrow->contactId, $user_sophia->contactId);

        Contact::$session_users = null;
    }

    function test_cdb_roles_1() {
        // saving PC role works
        $acct = $this->conf->fresh_user_by_email("jmrv@startup.com");
        xassert(!$acct);
        $acct = $this->us1->save_user((object) ["email" => "jmrv@startup.com", "lastName" => "Rutherford", "firstName" => "John", "roles" => "pc"]);
        xassert(!!$acct);
        $acct = $this->conf->fresh_user_by_email("jmrv@startup.com");
        xassert(($acct->roles & Contact::ROLE_PCLIKE) === Contact::ROLE_PC);
        $acct = $this->conf->fresh_cdb_user_by_email("jmrv@startup.com");
        xassert_eqq($acct->roles, Contact::ROLE_PC);
    }

    function test_cdb_roles_2() {
        // authorship is encoded in placeholder
        $acct = $this->conf->fresh_user_by_email("pavlin@isi.edu");
        xassert_eqq($acct->disablement, Contact::DISABLEMENT_PLACEHOLDER);
        xassert($acct->is_author());
        xassert_eqq($acct->cdb_roles(), Contact::ROLE_AUTHOR);
        $acct = $this->conf->fresh_cdb_user_by_email("pavlin@isi.edu");
        xassert_eqq($acct->disablement, Contact::DISABLEMENT_PLACEHOLDER);
        xassert_eqq($acct->roles, Contact::ROLE_AUTHOR);

        // saving without disablement wakes up cdb
        $acct = $this->us1->save_user((object) ["email" => "pavlin@isi.edu"]);
        xassert_eqq($acct->disablement, 0);
        $acct = $this->conf->fresh_cdb_user_by_email("pavlin@isi.edu");
        xassert_eqq($acct->disablement, 0);
    }

    function test_cdb_roles_3() {
        // saving a user with a role does both role and authorship
        $email = "lam@cs.utexas.edu";
        $acct = $this->conf->fresh_user_by_email($email);
        xassert_eqq($acct->disablement, Contact::DISABLEMENT_PLACEHOLDER);

        $acct = $this->us1->save_user((object) ["email" => $email, "roles" => "sysadmin"]);
        xassert(!!$acct);
        xassert_eqq($acct->disablement, 0);
        xassert($acct->is_author());
        xassert($acct->isPC);
        xassert($acct->privChair);
        xassert_eqq($acct->cdb_roles(), Contact::ROLE_AUTHOR | Contact::ROLE_ADMIN);
        $acct = $this->conf->fresh_cdb_user_by_email($email);
        xassert_eqq($acct->roles, Contact::ROLE_AUTHOR | Contact::ROLE_ADMIN);
    }

    function test_placeholder() {
        // create a placeholder user
        Contact::make_keyed($this->conf, [
            "email" => "scapegoat@harvard.edu",
            "firstName" => "Shane",
            "disablement" => Contact::DISABLEMENT_PLACEHOLDER
        ])->store();

        $u = $this->conf->checked_user_by_email("scapegoat@harvard.edu");
        xassert_eqq($u->firstName, "Shane");
        xassert_eqq($u->lastName, "");
        xassert_eqq($u->disablement, Contact::DISABLEMENT_PLACEHOLDER);
        $cdb_u = $u->cdb_user();
        xassert_eqq($cdb_u->firstName, "Shane");
        xassert_eqq($cdb_u->lastName, "");
        xassert_eqq($cdb_u->disablement, Contact::DISABLEMENT_PLACEHOLDER);

        // creating another placeholder will override properties
        Contact::make_keyed($this->conf, [
            "email" => "scapegoat@harvard.edu",
            "firstName" => "Shapely",
            "lastName" => "Montréal",
            "disablement" => Contact::DISABLEMENT_PLACEHOLDER
        ])->store();

        $u = $this->conf->checked_user_by_email("scapegoat@harvard.edu");
        xassert_eqq($u->firstName, "Shapely");
        xassert_eqq($u->lastName, "Montréal");
        xassert_eqq($u->disablement, Contact::DISABLEMENT_PLACEHOLDER);
        $cdb_u = $u->cdb_user();
        xassert_eqq($cdb_u->firstName, "Shapely");
        xassert_eqq($cdb_u->lastName, "Montréal");
        xassert_eqq($cdb_u->disablement, Contact::DISABLEMENT_PLACEHOLDER);
        xassert_eqq($cdb_u->prop("password"), " unset");

        // enable user
        Contact::make_keyed($this->conf, [
            "email" => "scapegoat@harvard.edu",
            "disablement" => 0
        ])->store();

        $u = $this->conf->checked_user_by_email("scapegoat@harvard.edu");
        xassert_eqq($u->firstName, "Shapely");
        xassert_eqq($u->lastName, "Montréal");
        xassert_eqq($u->disablement, 0);
        $cdb_u = $u->cdb_user();
        xassert_eqq($cdb_u->firstName, "Shapely");
        xassert_eqq($cdb_u->lastName, "Montréal");
        xassert_eqq($cdb_u->disablement, 0);

        // saving another placeholder will not override properties
        // or disable the current user
        Contact::make_keyed($this->conf, [
            "email" => "scapegoat@harvard.edu",
            "firstName" => "Stickly",
            "lastName" => "Milquetoast",
            "disablement" => Contact::DISABLEMENT_PLACEHOLDER
        ])->store();

        $u = $this->conf->checked_user_by_email("scapegoat@harvard.edu");
        xassert_eqq($u->firstName, "Shapely");
        xassert_eqq($u->lastName, "Montréal");
        xassert_eqq($u->disablement, 0);
        $cdb_u = $u->cdb_user();
        xassert_eqq($cdb_u->firstName, "Shapely");
        xassert_eqq($cdb_u->lastName, "Montréal");
        xassert_eqq($cdb_u->disablement, 0);
    }

    function test_updatecontactdb_authors() {
        $paper9 = $this->conf->checked_paper_by_id(9);
        $aulist = $paper9->author_list();
        $aulist[] = Author::make_keyed([
            "name" => "Nonsense Person",
            "email" => "NONSENSE@_.com"
        ]);
        $austr = join("\n", array_map(function ($au) { return $au->unparse_tabbed(); }, $aulist));
        $this->conf->qe("update Paper set authorInformation=? where paperId=9", $austr);

        $paper10 = $this->conf->checked_paper_by_id(10);
        $aulist = $paper9->author_list();
        $aulist[] = Author::make_keyed([
            "email" => "nonsense@_.com",
            "affiliation" => "Nonsense University"
        ]);
        $austr = join("\n", array_map(function ($au) { return $au->unparse_tabbed(); }, $aulist));
        $this->conf->qe("update Paper set authorInformation=? where paperId=10", $austr);

        $u = $this->conf->fresh_user_by_email("nonsense@_.com");
        xassert(!$u);
        $u = $this->conf->fresh_cdb_user_by_email("nonsense@_.com");
        xassert(!$u);

        $ucdb = new UpdateContactdb_Batch($this->conf, ["authors" => false]);
        $ucdb->run_authors();

        $u = $this->conf->fresh_user_by_email("nonsense@_.com");
        xassert(!!$u);
        xassert_eqq($u->disablement, Contact::DISABLEMENT_PLACEHOLDER);
        xassert_eqq($u->email, "NONSENSE@_.com");
        xassert_eqq($u->firstName, "Nonsense");
        xassert_eqq($u->lastName, "Person");
        xassert_eqq($u->affiliation, "Nonsense University");
        $paper9 = $this->conf->checked_paper_by_id(9);
        xassert($paper9->has_author($u));
        $paper10 = $this->conf->checked_paper_by_id(10);
        xassert($paper10->has_author($u));

        $u = $this->conf->fresh_cdb_user_by_email("nonsense@_.com");
        xassert(!!$u);
        xassert_eqq($u->disablement, Contact::DISABLEMENT_PLACEHOLDER);
        xassert_eqq($u->email, "NONSENSE@_.com");
        xassert_eqq($u->firstName, "Nonsense");
        xassert_eqq($u->lastName, "Person");
        xassert_eqq($u->affiliation, "Nonsense University");
        xassert_eqq($u->disablement, Contact::DISABLEMENT_PLACEHOLDER);
    }
}
