<?php

// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * XML-RPC web service entry point. The authentication is done via tokens.
 *
 * @package   webservice
 * @copyright 2009 Moodle Pty Ltd (http://moodle.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);

require('../../config.php');
require_once("$CFG->dirroot/webservice/xmlrpc/locallib.php");

//ob_start();

//TODO: for now disable all mess in xml
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$CFG->debug = 0;
$CFG->debugdisplay = false;

//error_log('yy');
//error_log(var_export($_SERVER, true));

if (!webservice_protocol_is_enabled('xmlrpc')) {
    die;
}

$server = new webservice_xmlrpc_server();
$server->run(true);
die;

