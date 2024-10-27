<?php
// api_error.php -- HotCRP error reporting API calls
// Copyright (c) 2008-2022 Eddie Kohler; see LICENSE.

class Error_API {
    static function jserror(Contact $user, Qrequest $qreq) {
        $errormsg = trim((string) $qreq->error);
        if ($errormsg === ""
            || preg_match('/(?:moz|safari|chrome)-extension/', $errormsg . ($qreq->stack ?? ""))) {
            return new JsonResult(["ok" => true]);
        }
        $url = $qreq->url ?? "";
        if (preg_match(',[/=]((?:script|jquery)[^/&;]*[.]js),', $url, $m)) {
            $url = $m[1];
        }
        if (($n = $qreq->lineno)) {
            $url .= ":" . $n;
        }
        if (($n = $qreq->colno)) {
            $url .= ":" . $n;
        }
        if ($url !== "") {
            $url .= ": ";
        }
        $suffix = "";
        if ($user->email) {
            $suffix .= ", user " . $user->email;
        }
        if (isset($_SERVER["REMOTE_ADDR"])) {
            $suffix .= ", host " . $_SERVER["REMOTE_ADDR"];
        }
        error_log("JS error: {$url}{$errormsg}{$suffix}");
        if ($qreq->detail) {
            error_log("JS error: {$url}detail " . substr($qreq->detail, 0, 200));
        }
        if (($stacktext = $qreq->stack)) {
            $stack = [];
            foreach (explode("\n", $stacktext) as $line) {
                $line = trim($line);
                if ($line === "" || $line === $errormsg || "Uncaught $line" === $errormsg) {
                    continue;
                }
                if (preg_match('/\Aat (\S+) \((\S+)\)/', $line, $m)) {
                    $line = $m[1] . "@" . $m[2];
                } else if (substr($line, 0, 1) === "@") {
                    $line = substr($line, 1);
                } else if (substr($line, 0, 3) === "at ") {
                    $line = substr($line, 3);
                }
                $stack[] = $line;
            }
            error_log("JS error: {$url}via " . join(" ", $stack));
        }
        return new JsonResult(["ok" => true]);
    }
}
