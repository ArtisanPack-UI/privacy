<?php

/**
 * ComplianceReport Livewire component — admin compliance reporting panel.
 *
 * Renders consent, data subject request, and breach notification metrics
 * for a date range with optional regulation filtering. Supports CSV and
 * PDF (printable HTML) export.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Livewire\Admin;

use ArtisanPackUI\Privacy\Services\ComplianceReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin compliance reporting panel.
 *
 * Usage:
 *
 *   <livewire:privacy-admin-compliance-report />
 *
 * @since 1.0.0
 */
class ComplianceReport extends Component
{
	/**
	 * Inclusive start of the reporting window (Y-m-d).
	 *
	 * @var string
	 */
	#[Url( as: 'from', except: '' )]
	public string $from = '';

	/**
	 * Inclusive end of the reporting window (Y-m-d).
	 *
	 * @var string
	 */
	#[Url( as: 'to', except: '' )]
	public string $to = '';

	/**
	 * Optional regulation filter (`''` matches all).
	 *
	 * @var string
	 */
	#[Url( as: 'regulation', except: '' )]
	public string $regulation = '';

	/**
	 * Authorize the gate and seed default range on mount.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function mount(): void
	{
		if ( '' === $this->from ) {
			$this->from = now()->subDays( 30 )->toDateString();
		}

		if ( '' === $this->to ) {
			$this->to = now()->toDateString();
		}
	}

	/**
	 * Authorize the gate on every hydration so filter state updates can't
	 * bypass the policy check after mount.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function booted(): void
	{
		$this->authorizeAdmin();
	}

	/**
	 * Aggregated report payload for the current filter state.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	#[Computed]
	public function report(): array
	{
		return app( ComplianceReportService::class )->generate(
			$this->resolveFrom(),
			$this->resolveTo(),
			'' === $this->regulation ? null : $this->regulation,
		);
	}

	/**
	 * Returns the enabled regulation keys for the filter dropdown.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	#[Computed]
	public function regulations(): array
	{
		$configured = (array) config( 'artisanpack.privacy.regulations', [] );

		$enabled = [];
		foreach ( $configured as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( true === ( $value['enabled'] ?? false ) ) {
				$enabled[] = (string) $key;
			}
		}

		return $enabled;
	}

	/**
	 * Stream the report as a CSV file.
	 *
	 * @since 1.0.0
	 *
	 * @return StreamedResponse
	 */
	public function exportCsv(): StreamedResponse
	{
		$payload  = $this->report;
		$filename = sprintf(
			'privacy-compliance-%s-to-%s.csv',
			$this->resolveFrom()->toDateString(),
			$this->resolveTo()->toDateString(),
		);

		return response()->streamDownload( static function () use ( $payload ): void {
			$out = fopen( 'php://output', 'wb' );

			fputcsv( $out, [ 'section', 'metric', 'value' ] );

			foreach ( [ 'consents', 'requests', 'breaches' ] as $section ) {
				foreach ( (array) ( $payload[ $section ] ?? [] ) as $metric => $value ) {
					if ( is_array( $value ) ) {
						foreach ( $value as $subKey => $subValue ) {
							fputcsv( $out, [
								$section,
								"{$metric}.{$subKey}",
								is_scalar( $subValue ) ? (string) $subValue : (string) json_encode( $subValue ),
							] );
						}

						continue;
					}

					fputcsv( $out, [ $section, $metric, (string) $value ] );
				}
			}

			fclose( $out );
		}, $filename, [ 'Content-Type' => 'text/csv' ] );
	}

	/**
	 * Stream the report as a printable HTML document (PDF via browser print).
	 *
	 * @since 1.0.0
	 *
	 * @return StreamedResponse
	 */
	public function exportPdf(): StreamedResponse
	{
		$payload  = $this->report;
		$filename = sprintf(
			'privacy-compliance-%s-to-%s.html',
			$this->resolveFrom()->toDateString(),
			$this->resolveTo()->toDateString(),
		);

		$html = view( 'privacy::admin.compliance-report-print', [
			'report' => $payload,
			'from'   => $this->resolveFrom(),
			'to'     => $this->resolveTo(),
		] )->render();

		return response()->streamDownload( static function () use ( $html ): void {
			echo $html;
		}, $filename, [ 'Content-Type' => 'text/html' ] );
	}

	/**
	 * Render the component view.
	 *
	 * @since 1.0.0
	 *
	 * @return View
	 */
	public function render(): View
	{
		return view( 'privacy::livewire.admin.compliance-report' );
	}

	/**
	 * Resolves the configured admin gate name (defaulting to `manage-privacy`)
	 * and runs `Gate::authorize` against it.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function authorizeAdmin(): void
	{
		$gate = (string) config( 'artisanpack.privacy.admin.gate', 'manage-privacy' );

		if ( '' === $gate ) {
			$gate = 'manage-privacy';
		}

		Gate::authorize( $gate );
	}

	/**
	 * Resolve the start of the reporting window from `$from`.
	 *
	 * @since 1.0.0
	 *
	 * @return Carbon
	 */
	protected function resolveFrom(): Carbon
	{
		return '' === $this->from
			? now()->subDays( 30 )->startOfDay()
			: Carbon::parse( $this->from )->startOfDay();
	}

	/**
	 * Resolve the end of the reporting window from `$to`.
	 *
	 * @since 1.0.0
	 *
	 * @return Carbon
	 */
	protected function resolveTo(): Carbon
	{
		return '' === $this->to
			? now()->endOfDay()
			: Carbon::parse( $this->to )->endOfDay();
	}
}
