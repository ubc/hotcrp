<?php
// test/options.php -- HotCRP conference options for test databases

global $Opt;
$Opt["dbName"] = "hotcrp_testdb";
$Opt["dbPassword"] = "m5LuaN23j26g";
$Opt["dbHost"] = "127.0.0.1";
$Opt["shortName"] = "Testconf I";
$Opt["longName"] = "Test Conference I";
$Opt["paperSite"] = "http://hotcrp.lcdf.org/test/";
$Opt["contactName"] = "Eddie Kohler";
$Opt["contactEmail"] = "ekohler@hotcrp.lcdf.org";
$Opt["sendEmail"] = false;
$Opt["debugShowSensitiveEmail"] = true;
$Opt["disablePrintEmail"] = true;
$Opt["emailFrom"] = "you@example.com";
$Opt["smartScoreCompare"] = true;
$Opt["timezone"] = "America/New_York";
$Opt["postfixEOL"] = "\n";
$Opt["contactdbDsn"] = "mysql://hotcrp_testdb:m5LuaN23j26g@127.0.0.1/hotcrp_testdb_cdb";
$Opt["obsoletePasswordInterval"] = 1;
$Opt["include"][] = "?test/localoptions.php";
