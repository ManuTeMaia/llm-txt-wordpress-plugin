<?php
/**
 * Plugin Name:       LLMs.txt
 * Plugin URI:        https://manutemaia.com/
 * Description:       Generates and serves /llms.txt and /llms-full.txt for agents, following the llmstxt.org specification.
 * Version:           1.2.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            ManuTeMaia
 * Author URI:        https://manutemaia.com/
 * Update URI:        https://manutemaia.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       llms-txt
 * Domain Path:       /languages
 *
 * @package LlmsTxt
 */

defined( 'ABSPATH' ) || exit;

define( 'LLMS_TXT_VERSION', '1.2.0' );
define( 'LLMS_TXT_FILE', __FILE__ );
define( 'LLMS_TXT_DIR', plugin_dir_path( __FILE__ ) );

require_once __DIR__ . '/includes/Autoload.php';
LlmsTxt\Autoload::register();

register_activation_hook( __FILE__, array( LlmsTxt\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( LlmsTxt\Deactivator::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( LlmsTxt\Plugin::class, 'boot' ) );
