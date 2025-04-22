<?php

// AFV (S8): loads the helpers of the package modules (databackd_module() etc.).
// This file sorts first among routes/databackd/*.php. The same helper file is also loaded from
// app/Helper/databackd.php, so it works with cached routes too (route files are not run then).

require_once app_path('Helper/databackd_modules.php');
