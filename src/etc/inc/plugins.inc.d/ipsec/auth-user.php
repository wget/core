#!/usr/local/bin/php
<?php

/*
    Copyright (C) 2008 Shrew Soft Inc
    Copyright (C) 2010 Ermal Luçi
    All rights reserved.

    Redistribution and use in source and binary forms, with or without
    modification, are permitted provided that the following conditions are met:

    1. Redistributions of source code must retain the above copyright notice,
       this list of conditions and the following disclaimer.

    2. Redistributions in binary form must reproduce the above copyright
       notice, this list of conditions and the following disclaimer in the
       documentation and/or other materials provided with the distribution.

    THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
    INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
    AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
*/

/*
 * ipsec calls this script to authenticate a user
 * based on a username and password. We lookup these
 * in our config.xml file and check the credentials.
 */

require_once("config.inc");
require_once("auth.inc");
require_once("interfaces.inc");
require_once("util.inc");

/* setup syslog logging */
openlog("charon", LOG_ODELAY, LOG_AUTH);

/* read data from environment */
$username = getenv("username");
$password = getenv("password");
$authmodes = explode(",", getenv("authcfg"));

if (!$username || !$password) {
    syslog(LOG_ERR, "invalid user authentication environment");
    closelog();
    exit(-1);
}

$authenticated = false;
foreach ($authmodes as $authmode) {
    $authcfg = auth_get_authserver($authmode);
    if (!$authcfg && $authmode != "local") {
        continue;
    }

    $authenticated = authenticate_user($username, $password, $authcfg);
    if ($authenticated == true) {
        if (stristr($authmode, "local")) {
            $user = getUserEntry($username);
            if (!is_array($user) || !userHasPrivilege($user, "user-ipsec-xauth-dialin")) {
                $authenticated = false;
                syslog(LOG_WARNING, "user '{$username}' cannot authenticate through IPsec since the required privileges are missing.\n");
                continue;
            }
        }
        break;
    }
}

if ($authenticated == false) {
    syslog(LOG_WARNING, "user '{$username}' could not authenticate.\n");
    exit(-1);
} else {
    syslog(LOG_NOTICE, "user '{$username}' authenticated\n");
    closelog();
    exit(0);
}
