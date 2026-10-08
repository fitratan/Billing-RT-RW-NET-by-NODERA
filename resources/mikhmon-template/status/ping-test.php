<?php
/*
 *  Copyright (C) 2018 Laksamadi Guko.
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
session_start();
// hide all error
error_reporting(0);
if (!isset($_SESSION["mikhmon"])) {
    header("Location:../admin.php?id=login");
    exit;
} else {
    $session = $_GET['session'] ?? '';
    $ping = $_GET['ping'] ?? null;
    if (isset($_GET['ping']) && !empty($session)) {
        include_once('../include/config.php');
        $raw_config = $data[$session][1] ?? '';
        $iphost = '';
        if (strpos((string)$raw_config, '!') !== false) {
            $iphost = explode('!', (string)$raw_config)[1] ?? '';
        } else {
            $iphost = (string)$raw_config;
        }

        $parts = explode(":", (string)$iphost);
        $host = $parts[0] ?? '';
        $port = (isset($parts[1]) && is_numeric($parts[1]) && (int)$parts[1] > 0) ? (int)$parts[1] : 8728;

        if (!function_exists('ping')) {
            function ping($host, $port) {
                $errno = 0;
                $errstr = '';
                $fsock = @fsockopen((string)$host, (int)$port, $errno, $errstr, 5);
                if (!$fsock) {
                    return (
                        '<div id="pingX" class="col-12">
                        <div class="card">
                        <div class="card-header">
                        <h3 class="card-title">Ping Test [' . htmlspecialchars((string)$host) . ':' . (int)$port . '] </h3>
                        </div>
                        <div class="card-body">' .
                        "Host : " . htmlspecialchars((string)$host) . "&nbsp;Port : " . (int)$port . "<br>" .
                        "Error Code : " . htmlspecialchars((string)$errno) . "<br>" .
                        "Error Message : " . htmlspecialchars((string)$errstr) .
                        "<br><b class='text-warning'>Ping Timeout </b><br>" .
                        '<span class="pointer btn bg-grey" onclick="closeX()"><i class="fa fa-close text-red "></i> Close</span>' .
                        '</div>
                        </div>
                        </div>'
                    );
                } else {
                    fclose($fsock);
                    return (
                        '<div id="pingX" class="col-12">
                        <div class="card">
                        <div class="card-header">
                        <h3 class="card-title">Ping Test [' . htmlspecialchars((string)$host) . ':' . (int)$port . ']</h3>
                        </div>
                        <div class="card-body">' .
                        "Host : " . htmlspecialchars((string)$host) . "&nbsp;Port : " . (int)$port . "<br>" .
                        "<b class='text-green'>Ping OK</b><br>" .
                        '<span class="pointer btn bg-grey" onclick="closeX()"><i class="fa fa-close text-red "></i> Close</span>' .
                        '</div>
                        </div>
                        </div>'
                    );
                }
            }
        }

        $ping_test = ping($host, $port);
        echo $ping_test;
    }
}