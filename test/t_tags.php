<?php
// t_tags.php -- HotCRP tests
// Copyright (c) 2006-2024 Eddie Kohler; see LICENSE.

class Tags_Tester {
    /** @var Conf
     * @readonly */
    public $conf;
    /** @var Contact
     * @readonly */
    public $u_chair;

    function __construct(Conf $conf) {
        $this->conf = $conf;
        $this->u_chair = $conf->checked_user_by_email("chair@_.com");
    }

    function test_mutual_automatic_search() {
        xassert_search_all($this->u_chair, "#up", "");
        xassert_search_all($this->u_chair, "#withdrawn", "");
        xassert_search_all($this->u_chair, "tcpanaly", "15");

        $sv = (new SettingValues($this->u_chair))->add_json_string('{
            "automatic_tag": [
                {"tag": "up", "search": "!#withdrawn AND tcpanaly"},
                {"tag": "withdrawn", "search": "status:withdrawn"}
            ]
        }');
        xassert($sv->execute());

        xassert_search_all($this->u_chair, "#up", "15");
        xassert_search_all($this->u_chair, "#withdrawn", "");

        xassert_assign($this->u_chair, "paper,action,notify\n15,withdraw,no\n");

        $p15 = $this->conf->checked_paper_by_id(15);
        xassert_gt($p15->timeWithdrawn, 0);

        xassert_search_all($this->u_chair, "#up", "");
        xassert_search_all($this->u_chair, "#withdrawn", "15");

        xassert_assign($this->u_chair, "paper,action,notify\n15,revive,no\n");

        xassert_search_all($this->u_chair, "#up", "15");
        xassert_search_all($this->u_chair, "#withdrawn", "");

        $sv = (new SettingValues($this->u_chair))->add_json_string('{
            "automatic_tag": [
                {"tag": "up", "delete": true},
                {"tag": "withdrawn", "delete": true}
            ]
        }');
        xassert($sv->execute());

        xassert_search_all($this->u_chair, "#up", "");
        xassert_search_all($this->u_chair, "#withdrawn", "");
    }

    function test_mutual_valued_automatic_search() {
        xassert_search_all($this->u_chair, "#nau", "");
        xassert_search_all($this->u_chair, "#lotsau", "");

        $sv = (new SettingValues($this->u_chair))->add_json_string('{
            "automatic_tag": [
                {"tag": "nau", "search": "all", "value": "au"},
                {"tag": "lotsau", "search": "#nau>3"}
            ]
        }');
        xassert($sv->execute());

        xassert_search_all($this->u_chair, "#nau 1-10 sort:id", "1 2 3 4 5 6 7 8 9 10");
        xassert_eqq($this->conf->checked_paper_by_id(1)->tag_value("nau"), 4.0);
        xassert_search_all($this->u_chair, "#lotsau 1-10 sort:id", "1 2 4 6 10");

        $sv = (new SettingValues($this->u_chair))->add_json_string('{
            "automatic_tag": [
                {"tag": "nau", "delete": true},
                {"tag": "lotsau", "delete": true}
            ]
        }');
        xassert($sv->execute());

        xassert_search_all($this->u_chair, "#nau", "");
        xassert_search_all($this->u_chair, "#lotsau", "");
    }

    function test_tag_patterns() {
        $sv = new SettingValues($this->u_chair);
        xassert_eqq($sv->oldv("tag_readonly"), "accept pcpaper reject");
        xassert_eqq($sv->oldv("tag_sitewide"), "");
        $sv->add_json_string('{
            "tag_readonly": "t*",
            "tag_hidden": "top",
            "tag_sitewide": "t*"
        }');
        xassert($sv->execute());

        $ti = $this->conf->tags()->find("tan");
        xassert($ti->is(TagInfo::TF_READONLY));
        xassert(!$ti->is(TagInfo::TF_HIDDEN));
        xassert($ti->is(TagInfo::TF_SITEWIDE));
        $ti = $this->conf->tags()->find("top");
        xassert($ti->is(TagInfo::TF_READONLY));
        xassert($ti->is(TagInfo::TF_HIDDEN));
        xassert($ti->is(TagInfo::TF_SITEWIDE));
        $ti = $this->conf->tags()->find("tan");
        xassert($ti->is(TagInfo::TF_READONLY));
        xassert(!$ti->is(TagInfo::TF_HIDDEN));
        xassert($ti->is(TagInfo::TF_SITEWIDE));

        $sv = (new SettingValues($this->u_chair))->add_json_string('{
            "tag_readonly": "accept pcpaper reject",
            "tag_sitewide": ""
        }');
        xassert($sv->execute());
    }

    function test_assign_delete_create() {
        $p1 = $this->conf->checked_paper_by_id(1);
        xassert(!$p1->has_tag("testtag"));

        // set
        xassert_assign($this->u_chair, "action,paper,tag\ntag,1,testtag\n");
        $p1->load_tags();
        xassert($p1->has_tag("testtag"));
        xassert_eqq($p1->tag_value("testtag"), 0.0);

        // clear
        xassert_assign($this->u_chair, "action,paper,tag\ntag,1,testtag#clear\n");
        $p1->load_tags();
        xassert(!$p1->has_tag("testtag"));
        xassert_eqq($p1->tag_value("testtag"), null);

        // set with value
        xassert_assign($this->u_chair, "action,paper,tag\ntag,1,testtag#2\n");
        $p1->load_tags();
        xassert($p1->has_tag("testtag"));
        xassert_eqq($p1->tag_value("testtag"), 2.0);

        // set without value when value exists
        xassert_assign($this->u_chair, "action,paper,tag\ntag,1,testtag\n");
        $p1->load_tags();
        xassert($p1->has_tag("testtag"));
        xassert_eqq($p1->tag_value("testtag"), 2.0);

        // set -> clear -> set resets value
        xassert_assign($this->u_chair, "action,paper,tag\ntag,1,testtag\ntag,1,testtag#clear\ntag,1,testtag\n");
        $p1->load_tags();
        xassert($p1->has_tag("testtag"));
        xassert_eqq($p1->tag_value("testtag"), 0.0);

        // clear -> set
        xassert_assign($this->u_chair, "action,paper,tag\ntag,1,testtag#clear\ntag,1,testtag\n");
        $p1->load_tags();
        xassert($p1->has_tag("testtag"));
        xassert_eqq($p1->tag_value("testtag"), 0.0);

        // test #some value
        xassert_assign($this->u_chair, "action,paper,tag\ntag,1,testtag#2\n");
        $p1->load_tags();
        xassert($p1->has_tag("testtag"));
        xassert_eqq($p1->tag_value("testtag"), 2.0);

        xassert_assign($this->u_chair, "action,paper,tag\ntag,1,testtag#clear\ntag,1,testtag#some\n");
        $p1->load_tags();
        xassert($p1->has_tag("testtag"));
        xassert_eqq($p1->tag_value("testtag"), 2.0);

        xassert_assign($this->u_chair, "action,paper,tag\ntag,1,testtag#clear\n");
        xassert_assign($this->u_chair, "action,paper,tag\ntag,1,testtag#some\n");
        $p1->load_tags();
        xassert($p1->has_tag("testtag"));
        xassert_eqq($p1->tag_value("testtag"), 0.0);

        xassert_assign($this->u_chair, "action,paper,tag\ntag,1,testtag#3\ntag,1,testtag#some\n");
        $p1->load_tags();
        xassert($p1->has_tag("testtag"));
        xassert_eqq($p1->tag_value("testtag"), 3.0);

        xassert_assign($this->u_chair, "action,paper,tag\ntag,1,testtag#clear\n");
    }

    function test_assign_override_conflicts() {
        xassert_search($this->u_chair, "conf:me", "");

        $p1 = $this->conf->checked_paper_by_id(1);
        xassert_assign($this->u_chair, "action,paper,tag\ntag,1,testtag");
        $p1->load_tags();
        xassert($p1->has_tag("testtag"));
        xassert_eqq($p1->tag_value("testtag"), 0.0);

        $this->conf->qe("insert into PaperConflict set paperId=1, contactId=?, conflictType=?", $this->u_chair->contactId, Conflict::GENERAL);
        xassert_search($this->u_chair, "conf:me", "1");

        $aset = new AssignmentSet($this->u_chair);
        $aset->parse("action,paper,tag\ntag,1,testtag");
        xassert(!$aset->execute());
        xassert_str_contains($aset->full_feedback_text(), "You have a conflict");

        $p1->load_tags();
        xassert_eqq($p1->tag_value("testtag"), 0.0);

        $aset = new AssignmentSet($this->u_chair);
        $aset->set_override_conflicts(true);
        $aset->parse("action,paper,tag\ntag,1,testtag");
        xassert($aset->execute());
        xassert_eqq($aset->full_feedback_text(), "");

        $p1->load_tags();
        xassert_eqq($p1->tag_value("testtag"), 0.0);

        $this->conf->qe("delete from PaperConflict where paperId=1 and contactId=?", $this->u_chair->contactId);
        $this->conf->qe("delete from PaperTag where paperId=1 and tag='testtag'");
    }

    function test_tag_anno() {
        $v = [];
        foreach ([0, 10, 10, 10, 30, 31, 32, 50] as $i => $n) {
            $v[] = ["t", $i + 1, $n, "H" . ($i + 1)];
        }
        $this->conf->qe("insert into PaperTagAnno (tag,annoId,tagIndex,heading) values ?v", $v);

        $dt = $this->conf->tags()->ensure("t");
        $dt->invalidate_order_anno();
        xassert($dt->has_order_anno());

        $sv = [[-1, null], [0, 1], [1, 1], [10, 4], [10, 4], [12, 4], [30, 5], [31, 6], [32, 7], [33, 7], [49, 7], [50, 8], [60, 8]];
        foreach ($sv as $m) {
            xassert_eqq($dt->order_anno_search($m[0])->annoId ?? null, $m[1]);
        }
        $svmax = count($sv) - 1;
        for ($i = 0; $i !== 8000; ++$i) {
            $m = $sv[mt_rand(0, $svmax)];
            xassert_eqq($dt->order_anno_search($m[0])->annoId ?? null, $m[1]);
        }

        $this->conf->qe("delete from PaperTagAnno where tag='t'");
    }

    function test_copy_tag_anno() {
        $v = [];
        foreach ([0, 10, 10, 10, 30, 31, 32, 50] as $i => $n) {
            $v[] = ["t", $i + 1, $n, "H" . ($i + 1)];
        }
        $this->conf->qe("insert into PaperTagAnno (tag,annoId,tagIndex,heading) values ?v", $v);

        $dt = $this->conf->tags()->ensure("t");
        $dtt = $this->conf->tags()->ensure("tt");

        $dt->invalidate_order_anno();
        xassert($dt->has_order_anno());
        $dtt->invalidate_order_anno();
        xassert(!$dtt->has_order_anno());

        xassert_assign($this->u_chair, "action,paper,tag,new_tag\ncopytag,all,t,tt\n");

        $dt->invalidate_order_anno();
        xassert($dt->has_order_anno());
        $dtt->invalidate_order_anno();
        xassert($dtt->has_order_anno());

        $sv = [[-1, null], [0, 1], [1, 1], [10, 4], [10, 4], [12, 4], [30, 5], [31, 6], [32, 7], [33, 7], [49, 7], [50, 8], [60, 8]];
        foreach ($sv as $m) {
            xassert_eqq($dt->order_anno_search($m[0])->annoId ?? null, $m[1]);
            xassert_eqq($dtt->order_anno_search($m[0])->annoId ?? null, $m[1]);
        }

        xassert_assign($this->u_chair, "action,paper,tag,new_tag\nmovetag,all,tt,tu\n");

        $dtu = $this->conf->tags()->ensure("tu");
        $dt->invalidate_order_anno();
        xassert($dt->has_order_anno());
        $dtt->invalidate_order_anno();
        xassert(!$dtt->has_order_anno());
        $dtu->invalidate_order_anno();
        xassert($dtu->has_order_anno());

        $sv = [[-1, null], [0, 1], [1, 1], [10, 4], [10, 4], [12, 4], [30, 5], [31, 6], [32, 7], [33, 7], [49, 7], [50, 8], [60, 8]];
        foreach ($sv as $m) {
            xassert_eqq($dt->order_anno_search($m[0])->annoId ?? null, $m[1]);
            xassert_eqq($dtu->order_anno_search($m[0])->annoId ?? null, $m[1]);
        }

        $this->conf->qe("delete from PaperTagAnno where tag in ('t','tt','tu')");
    }

    function test_next() {
        $root = $this->conf->root_user();
        $u_varghese = $this->conf->checked_user_by_email("varghese@ccrc.wustl.edu");
        $p4 = $this->conf->checked_paper_by_id(4);
        xassert(!$u_varghese->can_view_tags($p4));
        xassert_search_all($root, "#order", "");

        // create order
        xassert_assign($root, "action,paper,tag\nseqnexttag,1,order\nseqnexttag,2,order\nseqnexttag,3,order\nseqnexttag,4,order\n");
        $p4->load_tags();
        xassert_eqq($p4->tag_value("order"), 4.0);

        // adding to order extends order
        xassert_assign($root, "action,paper,tag\nseqnexttag,5,order\n");
        $p5 = $this->conf->checked_paper_by_id(5);
        xassert_eqq($p5->tag_value("order"), 5.0);

        // `enable_papers` does not limit scope of order search
        $aset = (new AssignmentSet($root))->enable_papers(6);
        xassert_assign($root, "action,paper,tag\nseqnexttag,6,order\n");
        $p6 = $this->conf->checked_paper_by_id(6);
        xassert_eqq($p6->tag_value("order"), 6.0);

        // adding to order with `#seqnext` extends order
        xassert_assign($root, "action,paper,tag\ntag,7,order#seqnext\n");
        $p7 = $this->conf->checked_paper_by_id(7);
        xassert_eqq($p7->tag_value("order"), 7.0);

        // `enable_papers` does not limit scope of order search
        $aset = (new AssignmentSet($root))->enable_papers(6);
        xassert_assign($root, "action,paper,tag\ntag,8,order#seqnext\n");
        $p8 = $this->conf->checked_paper_by_id(8);
        xassert_eqq($p8->tag_value("order"), 8.0);

        // can’t use `nexttag` to peek at your own tag
        xassert_assign($root, "action,paper,tag\ntag,5-,order#clear\n");
        $p8->load_tags();
        xassert_eqq($p8->tag_value("order"), null);
        xassert_assign($u_varghese, "action,paper,tag\nseqnexttag,5,order\n");
        $p5->load_tags();
        xassert_eqq($p5->tag_value("order"), 4.0);

        // clearing order resets order
        xassert_assign($root, "action,paper,tag\ntag,7,order#seqnext\n");
        $p7 = $this->conf->checked_paper_by_id(7);
        xassert_eqq($p7->tag_value("order"), 5.0);
    }
}
