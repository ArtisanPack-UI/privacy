<?php

declare( strict_types=1 );

use ArtisanPackUI\Privacy\Models\BreachNotification;
use ArtisanPackUI\Privacy\Models\Consent;
use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Services\ComplianceReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\Support\TestSubject;

beforeEach( function (): void {
	Schema::create( 'test_subjects', function ( Blueprint $table ): void {
		$table->id();
		$table->string( 'name' )->nullable();
	} );
	config()->set( 'artisanpack.privacy.regulations.gdpr.breach_notification_hours', 72 );

	$this->service = app( ComplianceReportService::class );
} );

afterEach( function (): void {
	Schema::dropIfExists( 'test_subjects' );
	Carbon::setTestNow();
} );

function makeReportConsent( array $overrides = [] ): Consent
{
	$subject = TestSubject::create();

	return Consent::query()->create( array_merge( [
		'consentable_type' => $subject->getMorphClass(),
		'consentable_id'   => $subject->getKey(),
		'category'         => 'analytics',
		'granted'          => true,
		'regulation'       => 'gdpr',
	], $overrides ) );
}

function makeReportRequest( array $overrides = [] ): DataRequest
{
	$subject = TestSubject::create();

	return DataRequest::query()->create( array_merge( [
		'requestable_type' => $subject->getMorphClass(),
		'requestable_id'   => $subject->getKey(),
		'type'             => DataRequest::TYPE_ACCESS,
		'status'           => DataRequest::STATUS_PENDING,
		'regulation'       => 'gdpr',
	], $overrides ) );
}

it( 'aggregates consent grant/withdrawal counts and per-category totals', function (): void {
	makeReportConsent( [ 'category' => 'analytics', 'granted' => true ] );
	makeReportConsent( [ 'category' => 'analytics', 'granted' => true, 'withdrawn_at' => now() ] );
	makeReportConsent( [ 'category' => 'marketing', 'granted' => true ] );
	makeReportConsent( [ 'category' => 'marketing', 'granted' => false ] );
	makeReportConsent( [ 'category' => 'marketing', 'granted' => true, 'expires_at' => now()->subDay() ] );

	$report = $this->service->generate( now()->subDay(), now()->addDay() );

	expect( $report['consents']['total'] )->toBe( 5 );
	expect( $report['consents']['granted'] )->toBe( 4 );
	expect( $report['consents']['withdrawn'] )->toBe( 1 );
	expect( $report['consents']['expired'] )->toBe( 1 );
	expect( $report['consents']['by_category'] )->toBe( [
		'analytics' => 2,
		'marketing' => 3,
	] );
	expect( $report['consents']['grant_rate'] )->toBe( 80.0 );
	expect( $report['consents']['withdrawal_rate'] )->toBe( 20.0 );
} );

it( 'computes request volume, completion, deadline compliance, and percentiles', function (): void {
	Carbon::setTestNow( Carbon::parse( '2026-03-10 12:00:00' ) );

	makeReportRequest( [
		'type'         => DataRequest::TYPE_ACCESS,
		'status'       => DataRequest::STATUS_COMPLETED,
		'created_at'   => now()->subDays( 5 ),
		'due_at'       => now()->addDays( 10 ),
		'completed_at' => now()->subDays( 4 ),
	] );

	makeReportRequest( [
		'type'         => DataRequest::TYPE_DELETION,
		'status'       => DataRequest::STATUS_COMPLETED,
		'created_at'   => now()->subDays( 20 ),
		'due_at'       => now()->subDays( 10 ),
		'completed_at' => now()->subDays( 9 ),
	] );

	makeReportRequest( [
		'type'       => DataRequest::TYPE_EXPORT,
		'status'     => DataRequest::STATUS_PENDING,
		'created_at' => now()->subDays( 10 ),
		'due_at'     => now()->subDay(),
	] );

	$report = $this->service->generate( now()->subDays( 30 ), now() );

	expect( $report['requests']['total'] )->toBe( 3 );
	expect( $report['requests']['completed'] )->toBe( 2 );
	expect( $report['requests']['overdue'] )->toBe( 1 );
	expect( $report['requests']['deadline_compliance_percent'] )->toBe( 50.0 );
	expect( $report['requests']['by_type'] )->toMatchArray( [
		'access'   => 1,
		'deletion' => 1,
		'export'   => 1,
	] );
	expect( $report['requests']['percentiles_seconds']['p50'] )->toBeGreaterThan( 0 );
} );

it( 'reports breach severity distribution and notification-window compliance', function (): void {
	Carbon::setTestNow( Carbon::parse( '2026-03-10 12:00:00' ) );

	BreachNotification::factory()->create( [
		'severity'              => BreachNotification::SEVERITY_HIGH,
		'discovered_at'         => now()->subDays( 5 ),
		'authority_notified_at' => now()->subDays( 5 )->addHours( 24 ),
	] );

	BreachNotification::factory()->create( [
		'severity'              => BreachNotification::SEVERITY_HIGH,
		'discovered_at'         => now()->subDays( 5 ),
		'authority_notified_at' => now()->subDays( 5 )->addHours( 96 ),
	] );

	BreachNotification::factory()->create( [
		'severity'              => BreachNotification::SEVERITY_LOW,
		'discovered_at'         => now()->subDay(),
		'authority_notified_at' => null,
	] );

	$report = $this->service->generate( now()->subDays( 7 ), now() );

	expect( $report['breaches']['total'] )->toBe( 3 );
	expect( $report['breaches']['by_severity'] )->toMatchArray( [
		'high' => 2,
		'low'  => 1,
	] );
	expect( $report['breaches']['authority_notified_on_time'] )->toBe( 1 );
	expect( $report['breaches']['authority_notified_late'] )->toBe( 1 );
	expect( $report['breaches']['authority_notification_pending'] )->toBe( 1 );
	expect( $report['breaches']['authority_notification_compliance_percent'] )->toBe( 50.0 );
} );

it( 'filters by regulation when supplied', function (): void {
	makeReportConsent( [ 'regulation' => 'gdpr' ] );
	makeReportConsent( [ 'regulation' => 'ccpa' ] );

	$report = $this->service->generate( now()->subDay(), now()->addDay(), 'gdpr' );

	expect( $report['consents']['total'] )->toBe( 1 );
	expect( $report['meta']['regulation'] )->toBe( 'gdpr' );
} );
