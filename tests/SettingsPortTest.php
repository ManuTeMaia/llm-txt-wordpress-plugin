<?php
/**
 * Settings survive an upgrade and a reinstall, and can be moved as JSON.
 *
 * @package LlmsTxt
 */

declare( strict_types=1 );

namespace LlmsTxt\Tests;

use LlmsTxt\Lifecycle\Uninstall_Gate;
use LlmsTxt\Settings;
use LlmsTxt\Settings_Port;
use LlmsTxt\Update\Checker;
use PHPUnit\Framework\TestCase;

final class SettingsPortTest extends TestCase {
	public function test_upgrade_keeps_sections_and_does_not_opt_into_data_deletion(): void {
		$clean = ( new Settings() )->sanitize(
			array(
				'title_mode'   => 'custom',
				'title'        => 'Terav',
				'summary_mode' => 'custom',
				'summary'      => 'Keep this summary.',
				'sections'     => array(
					array(
						'id'       => 'product',
						'type'     => 'static',
						'heading'  => 'Product and access',
						'markdown' => "- [Overview](https://terav.vet/): Features",
					),
				),
			)
		);

		self::assertFalse( $clean['delete_data_on_uninstall'] );
		self::assertSame( '', $clean['update_manifest_url'] );
		self::assertSame( 'Terav', $clean['title'] );
		self::assertSame( 'Keep this summary.', $clean['summary'] );
		self::assertSame( 'Product and access', $clean['sections'][0]['heading'] );
		self::assertStringContainsString( 'https://terav.vet/', $clean['sections'][0]['markdown'] );
	}

	public function test_export_and_import_round_trip(): void {
		$settings = ( new Settings() )->sanitize(
			array(
				'title_mode'   => 'custom',
				'title'        => 'Terav',
				'summary_mode' => 'custom',
				'summary'      => 'Veterinary assistant.',
				'sections'     => array(
					array(
						'heading'  => 'Legal and contact',
						'type'     => 'static',
						'markdown' => '- [Privacy](https://terav.vet/privacy-policy/)',
					),
				),
			)
		);
		$port = new Settings_Port( new Settings() );
		$json = $port->to_json( $settings, '1.2.0' );
		$restored = $port->parse( $json );

		self::assertStringContainsString( '"plugin": "llms-txt"', $json );
		self::assertSame( 'Legal and contact', $restored['sections'][0]['heading'] );
		self::assertSame( 'Terav', $restored['title'] );
	}

	public function test_import_rejects_a_foreign_json_file(): void {
		$this->expectException( \InvalidArgumentException::class );
		( new Settings_Port( new Settings() ) )->parse( '{"plugin":"other","settings":{}}' );
	}

	public function test_uninstall_keeps_data_unless_the_owner_opts_in(): void {
		self::assertFalse( Uninstall_Gate::should_delete( null ) );
		self::assertFalse( Uninstall_Gate::should_delete( array() ) );
		self::assertFalse( Uninstall_Gate::should_delete( array( 'delete_data_on_uninstall' => false ) ) );
		self::assertTrue( Uninstall_Gate::should_delete( array( 'delete_data_on_uninstall' => true ) ) );
		self::assertTrue( Uninstall_Gate::should_delete( array( 'delete_data_on_uninstall' => '1' ) ) );
	}

	public function test_update_manifest_is_ignored_until_a_newer_package_is_published(): void {
		$manifest = 'https://manutemaia.com/llms-txt.json';
		self::assertNull( Checker::offer( '1.2.0', $manifest, null ) );
		self::assertNull( Checker::offer( '1.2.0', 'http://manutemaia.com/llms-txt.json', '{"version":"9.0.0","download_url":"https://manutemaia.com/llms-txt.zip","sha256":"' . str_repeat( 'ab', 32 ) . '"}' ) );
		self::assertNull( Checker::offer( '1.2.0', $manifest, '{"version":"9.0.0","download_url":"https://evil.example/llms-txt.zip","sha256":"' . str_repeat( 'ab', 32 ) . '"}' ) );
		self::assertNull( Checker::offer( '1.2.0', $manifest, '{"version":"9.0.0","download_url":"https://manutemaia.com/llms-txt.zip"}' ) );
		$offer = Checker::offer( '1.2.0', $manifest, '{"version":"1.3.0","download_url":"https://manutemaia.com/llms-txt-1.3.0.zip","sha256":"' . str_repeat( 'ab', 32 ) . '","requires_php":"8.1"}' );
		self::assertIsArray( $offer );
		self::assertSame( '1.3.0', $offer['new_version'] );
		self::assertSame( 'https://manutemaia.com/llms-txt-1.3.0.zip', $offer['package'] );
	}
}
