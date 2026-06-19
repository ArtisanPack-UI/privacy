<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create( 'privacy_consents', function ( Blueprint $table ): void {
			$table->id();
			$table->morphs( 'consentable' );
			$table->string( 'category' );
			$table->boolean( 'granted' )->default( false );
			$table->string( 'regulation' )->nullable();
			$table->string( 'ip_address', 45 )->nullable();
			$table->text( 'user_agent' )->nullable();
			$table->json( 'metadata' )->nullable();
			$table->timestamp( 'expires_at' )->nullable();
			$table->timestamp( 'withdrawn_at' )->nullable();
			$table->timestamps();

			$table->index( [ 'consentable_type', 'consentable_id', 'category' ], 'privacy_consents_owner_category_idx' );
			$table->index( 'category' );
			$table->index( 'expires_at' );
		} );
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists( 'privacy_consents' );
	}
};
