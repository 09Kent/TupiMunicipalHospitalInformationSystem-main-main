<?php

/**
 * Serverless Entry Point Wrapper for Vercel Deployment from Repository Root
 */
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require __DIR__ . '/tmhis/api/index.php';

