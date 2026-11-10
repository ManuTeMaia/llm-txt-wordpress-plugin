<?php
/**
 * PHPUnit bootstrap. WordPress is not loaded; these tests cover the pure pieces.
 *
 * @package LlmsTxt
 */

declare( strict_types=1 );

define( 'ABSPATH', sys_get_temp_dir() . '/llms-txt-abspath/' );

require dirname( __DIR__ ) . '/includes/Autoload.php';
LlmsTxt\Autoload::register();
