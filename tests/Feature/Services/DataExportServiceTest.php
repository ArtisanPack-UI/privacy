<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Concerns\HasPersonalData;
use ArtisanPackUI\Privacy\Events\DataExportCollecting;
use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Services\DataExportService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

beforeEach( function (): void {
	Relation::morphMap( [ 'export_subject' => ExportSubject::class ] );
	Schema::create( 'export_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'email' )->nullable();
		$table->string( 'name' )->nullable();
	} );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'export_subjects' );
} );

class ExportSubject extends Model
{
	use HasPersonalData;

	public $timestamps = false;

	protected $table = 'export_subjects';

	protected $guarded = [];

	protected array $personalDataFields = [ 'email', 'name' ];

	public function getMorphClass(): string
	{
		return 'export_subject';
	}
}

it( 'exports a subject as JSON including personal data', function (): void {
	$subject = ExportSubject::create( [ 'email' => 'jacob@example.com', 'name' => 'Jacob' ] );

	$json = app( DataExportService::class )->export( $subject, DataExportService::FORMAT_JSON );
	$data = json_decode( $json, true );

	expect( $data )->toBeArray();
	expect( $data['personal_data']['email'] )->toBe( 'jacob@example.com' );
	expect( $data['personal_data']['name'] )->toBe( 'Jacob' );
	expect( $data )->toHaveKey( 'consent_history' );
	expect( $data )->toHaveKey( 'activity_log' );
} );

it( 'exports as CSV with section/key/value rows', function (): void {
	$subject = ExportSubject::create( [ 'email' => 'jacob@example.com', 'name' => 'Jacob' ] );

	$csv = app( DataExportService::class )->export( $subject, DataExportService::FORMAT_CSV );

	expect( $csv )->toContain( 'section,key,value' );
	expect( $csv )->toContain( 'jacob@example.com' );
	expect( $csv )->toContain( 'Jacob' );
} );

it( 'exports as XML', function (): void {
	$subject = ExportSubject::create( [ 'email' => 'jacob@example.com' ] );

	$xml = app( DataExportService::class )->export( $subject, DataExportService::FORMAT_XML );

	expect( $xml )->toContain( '<?xml' );
	expect( $xml )->toContain( '<export>' );
	expect( $xml )->toContain( 'jacob@example.com' );
} );

it( 'throws for an unsupported format', function (): void {
	$subject = ExportSubject::create( [ 'email' => 'jacob@example.com' ] );

	app( DataExportService::class )->export( $subject, 'pdf' );
} )->throws( InvalidArgumentException::class );

it( 'dispatches DataExportCollecting and allows listeners to mutate the payload', function (): void {
	$subject = ExportSubject::create( [ 'email' => 'jacob@example.com' ] );

	Event::listen( DataExportCollecting::class, static function ( DataExportCollecting $event ): void {
		$event->data['analytics'] = [ 'visits' => 42 ];
	} );

	$json = app( DataExportService::class )->export( $subject, DataExportService::FORMAT_JSON );
	$data = json_decode( $json, true );

	expect( $data['analytics']['visits'] )->toBe( 42 );
} );

it( 'includes consent history', function (): void {
	$subject = ExportSubject::create( [ 'email' => 'jacob@example.com' ] );

	Consent::query()->create( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'category'         => 'analytics',
		'granted'          => true,
	] );

	$history = app( DataExportService::class )->getConsentHistory( $subject );

	expect( $history )->toHaveCount( 1 );
	expect( $history[0]['category'] )->toBe( 'analytics' );
	expect( $history[0]['granted'] )->toBeTrue();
} );

it( 'writes export to file with a signed download URL and an expiry marker', function (): void {
	Storage::fake( 'local' );
	config()->set( 'artisanpack.privacy.export.disk', 'local' );

	$subject = ExportSubject::create( [ 'email' => 'jacob@example.com' ] );

	$result = app( DataExportService::class )->exportToFile( $subject, DataExportService::FORMAT_JSON );

	expect( $result )->toHaveKeys( [ 'path', 'url', 'expires_at' ] );
	Storage::disk( 'local' )->assertExists( $result['path'] );
	Storage::disk( 'local' )->assertExists( $result['path'] . '.expires' );
} );
