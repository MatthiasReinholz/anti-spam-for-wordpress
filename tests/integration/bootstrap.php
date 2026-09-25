<?php
/** WP-CLI's eval-file wrapper keeps the actual runner in normal PHP file scope. */
if (!defined('ASFW_FILE')) {
    throw new RuntimeException('The plugin must be active in the isolated test environment.');
}
require dirname(ASFW_FILE) . '/tests/integration/run.php';
