<?php

define('MOD_CB_PATH', rtrim(__DIR__, DIRECTORY_SEPARATOR));
$folders = explode(DIRECTORY_SEPARATOR, MOD_CB_PATH);
define('MOD_CB_DIR', rtrim(array_pop($folders), DIRECTORY_SEPARATOR));
unset($folders);