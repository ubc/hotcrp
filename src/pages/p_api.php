<?php
// pages/p_api.php -- HotCRP JSON API access page
// Copyright (c) 2006-2024 Eddie Kohler; see LICENSE.

class API_Page {
    /** @return JsonResult */
    static function go(Contact $user, Qrequest $qreq) {
        // initialize user, paper request
        $conf = $user->conf;
        if ($qreq->base !== null) {
            $conf->set_site_path_relative($qreq->navigation(), $qreq->base);
        }
        if (!$user->has_account_here()
            && ($key = $user->capability("@kiosk"))) {
            $kiosks = $conf->setting_json("__tracker_kiosk") ? : (object) [];
            if (isset($kiosks->$key) && $kiosks->$key->update_at >= Conf::$now - 172800) {
                if ($kiosks->$key->update_at < Conf::$now - 3600) {
                    $kiosks->$key->update_at = Conf::$now;
                    $conf->save_setting("__tracker_kiosk", 1, $kiosks);
                }
                $user->tracker_kiosk_state = $kiosks->$key->show_papers ? 2 : 1;
            }
        }
        if ($qreq->p) {
            $conf->set_paper_request($qreq, $user);
        }

        // handle requests
        $fn = $qreq->fn;
        $jr = null;
        if ($fn !== "status") {
            if ($fn !== "track" || $user->is_disabled()) {
                $jr = self::normal_api($fn, $user, $qreq);
            } else {
                $jr = MeetingTracker::track_api($user, $qreq);
            }
        }
        $jr = $jr ?? self::status_api($fn, $user, $qreq);

        // maybe save messages in session under a token
        if ($qreq->smsg
            && !isset($jr->content["_smsg"])) {
            $conf->feedback_msg(self::export_messages($jr));
            $ml = $conf->take_saved_messages();
            if (empty($ml)) {
                $jr->content["_smsg"] = false;
            } else {
                $jr->content["_smsg"] = $smsg = base48_encode(random_bytes(6));
                $qreq->open_session();
                $smsgs = $qreq->gsession("smsg") ?? [];
                array_unshift($ml, $smsg, Conf::$now);
                $smsgs[] = $ml;
                $qreq->set_gsession("smsg", $smsgs);
            }
        }

        return $jr;
    }

    /** @param string $fn
     * @param Contact $user
     * @param Qrequest $qreq
     * @return JsonResult */
    static private function normal_api($fn, $user, $qreq) {
        JsonCompletion::$allow_short_circuit = true;
        $conf = $user->conf;
        $uf = $conf->api($fn, $user, $qreq->method());
        $jr = $conf->call_api_on($uf, $fn, $user, $qreq, $conf->paper);
        if ($uf
            && ($uf->redirect ?? false)
            && ($url = $conf->qreq_redirect_url($qreq))) {
            $conf->feedback_msg(self::export_messages($jr));
            $conf->redirect($url);
        }
        return $jr;
    }

    /** @param string $fn
     * @param Contact $user
     * @param Qrequest $qreq
     * @return JsonResult */
    static private function status_api($fn, $user, $qreq) {
        $prow = $user->conf->paper;
        $jr = new JsonResult($user->my_deadlines($prow ? [$prow] : []));
        $jr["ok"] = true;
        if ($fn === "track" && ($new_trackerid = $qreq->annex("new_trackerid"))) {
            $jr["new_trackerid"] = $new_trackerid;
        }
        if ($prow
            && $user->can_view_tags($prow)
            && !$user->is_disabled()) {
            $pj = new TagMessageReport;
            $pj->pid = $prow->paperId;
            $prow->add_tag_info_json($pj, $user);
            if (count((array) $pj) > 1) {
                $jr["p"] = [$prow->paperId => $pj];
            }
        }
        return $jr;
    }

    /** @return list<MessageItem> */
    static function export_messages(JsonResult $jr) {
        $ml = [];
        foreach ($jr->content["message_list"] ?? [] as $mi) {
            if ($mi instanceof MessageItem) {
                $ml[] = $mi;
            }
        }
        if (empty($ml) && isset($jr->content["error"])) {
            $ml[] = new MessageItem(null, "<0>" . $jr->content["error"], 2);
        }
        if (empty($ml) && !($jr->content["ok"] ?? ($jr->status <= 299))) {
            $ml[] = new MessageItem(null, "<0>Internal error", 2);
        }
        return $ml;
    }

    static function go_options(NavigationState $nav) {
        if ($nav->page === "u"
            && ($unum = $nav->path_component(0)) !== false
            && ctype_digit($unum)) {
            $nav->shift_path_components(2);
        }
        $ok = true;
        if (($m = $_SERVER["HTTP_ACCESS_CONTROL_REQUEST_METHOD"] ?? null)) {
            if ($nav->page === "api") {
                $origin = $_SERVER["HTTP_ORIGIN"] ?? "*";
                header("Access-Control-Allow-Origin: {$origin}");
                if (($hdrs = $_SERVER["HTTP_ACCESS_CONTROL_REQUEST_HEADERS"] ?? null)) {
                    header("Access-Control-Allow-Headers: {$hdrs}");
                }
                header("Access-Control-Allow-Credentials: true");
                header("Access-Control-Allow-Methods: OPTIONS, GET, HEAD, POST");
                header("Access-Control-Max-Age: 86400");
            } else if (in_array($nav->page, ["cacheable", "scorechart", "images", "scripts", "stylesheets"])) {
                header("Access-Control-Allow-Origin: *");
                header("Access-Control-Allow-Methods: OPTIONS, GET, HEAD");
                header("Access-Control-Max-Age: 86400");
            } else {
                $ok = false;
            }
        } else {
            header("Allow: OPTIONS, GET, HEAD, POST"); // XXX other methods?
        }
        http_response_code($ok ? 200 : 403);
        exit();
    }

    /** @param NavigationState $nav
     * @param Conf $conf */
    static function go_nav($nav, $conf) {
        // argument cleaning
        if (!isset($_GET["fn"])) {
            $fn = $nav->path_component(0, true);
            if ($fn && ctype_digit($fn)) {
                if (!isset($_GET["p"])) {
                    $_GET["p"] = $fn;
                }
                $fn = $nav->path_component(1, true);
            }
            if ($fn) {
                $_GET["fn"] = $fn;
            } else if (isset($_GET["track"])) {
                $_GET["fn"] = "track";
            } else {
                http_response_code(404);
                header("Content-Type: application/json; charset=utf-8");
                echo '{"ok": false, "error": "API function missing"}', "\n";
                exit();
            }
        }
        if ($_GET["fn"] === "deadlines") {
            $_GET["fn"] = "status";
        }
        if (!isset($_GET["p"])
            && ($p = $nav->path_component(1, true))
            && ctype_digit($p)) {
            $_GET["p"] = $p;
        }

        // trackerstatus is a special case: prevent session creation
        if ($_GET["fn"] === "trackerstatus") {
            initialize_request(["no_main_user" => true]);
            MeetingTracker::trackerstatus_api(Contact::make($conf));
        } else {
            list($user, $qreq) = initialize_request(["bearer" => true]);
            try {
                $jr = self::go($user, $qreq);
            } catch (JsonCompletion $jc) {
                $jr = $jc->result;
            }
            $jr->emit($qreq);
        }
    }
}
