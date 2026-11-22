<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_ROOT', APP_ROOT . '/uploads');
const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;
const DAYON_MUNICIPALITY = 'San Jose de Buenavista';
const DAYON_PROVINCE = 'Antique';
const DAYON_LOCATION = DAYON_MUNICIPALITY . ', ' . DAYON_PROVINCE;
const DAYON_CAMPUS = "St. Anthony's College";

date_default_timezone_set('Asia/Manila');
