<?php
declare(strict_types=1);
// Stable entry point for page bootstrap. All paths are anchored to this file.
require_once __DIR__ . '/session.run.php';
require_once __DIR__ . '/conn.dbh.php';
require_once __DIR__ . '/global.dbh.php';
require_once __DIR__ . '/product_hierarchy.php';
require_once __DIR__ . '/show/global.shw.php';
require_once __DIR__ . '/show/global_forms.shw.php';
require_once __DIR__ . '/form_menu.shw.php';
require_once __DIR__ . '/hierarchy.shw.php';
require_once __DIR__ . '/selected_form.shw.php';
