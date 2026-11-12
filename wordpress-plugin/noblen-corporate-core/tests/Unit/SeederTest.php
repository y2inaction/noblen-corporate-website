<?php
use Noblen\Core\Clearance\Approvals;
use Noblen\Core\Content\Types;
use Noblen\Core\Seed\DraftSeeder;

class SeederTest extends NoblenTestCase {

	public function test_seed_creates_names_only_drafts_and_is_idempotent(): void {
		$this->as_user( $this->user( 'administrator', 'seed-admin' ) );
		$first = DraftSeeder::seed();
		$this->assertSame( count( DraftSeeder::COMPANIES ) + count( DraftSeeder::PRODUCTS ), $first );
		$this->assertSame( 0, DraftSeeder::seed(), 'second run creates nothing' );
		foreach ( array( Types::COMPANY, Types::PRODUCT ) as $type ) {
			foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
				if ( ! in_array( $p->post_title, array_merge( DraftSeeder::COMPANIES, DraftSeeder::PRODUCTS ), true ) ) {
					continue;
				}
				$this->assertSame( 'draft', $p->post_status );
				$this->assertSame( '', trim( $p->post_content ) );
				$this->assertNull( Approvals::latest( $p->ID ), 'no approval exists' );
			}
		}
	}

	public function test_seed_requires_the_settings_capability(): void {
		$this->as_user( $this->editor() );
		$this->assertSame( 0, DraftSeeder::seed() );
	}

	public function test_seeded_names_never_include_other_marketplace_names(): void {
		$all = array_merge( DraftSeeder::COMPANIES, DraftSeeder::PRODUCTS );
		$this->assertContains( 'OnlineMart', $all );
		$this->assertCount( 7, $all );
	}
}
