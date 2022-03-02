<?php

/**
 * @author James Standbridge <james.standbridge.git@gmail.com>
 */

require 'vendor/autoload.php';

use James\SimpleSFTP\sftp\SimpleSFTP;

$client = new SimpleSFTP("185.72.89.110", "sftpBoeki", "**********");
$client->cd("/PUT/prep_CLICK_COLLECT");
$res = $client->rm("test", true);
$res = $client->get("artstock_fly_20220228_065501.xml", "test.xml");
$res = $client->get_dir("prep_CLICK_COLLECT","data");
$res = $client->get_last_file(null, "articles_JARCNT", STR_START_WITH);
$res = $client->handle_archive("archive", null, 3);
$client->rename("archive/articles_JARCNT.xml","articles_JARCNT.xml");
