<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
	<meta charset="utf-8" />
	<title>{{ __( 'Privacy compliance report' ) }}</title>
	<style>
		body { font-family: sans-serif; color: #111; margin: 24px; }
		h1 { font-size: 22px; margin-bottom: 4px; }
		h2 { font-size: 16px; margin-top: 24px; }
		table { border-collapse: collapse; width: 100%; margin-top: 8px; }
		th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; font-size: 13px; }
		th { background: #f5f5f5; }
		.muted { color: #555; font-size: 12px; }
		.section { page-break-inside: avoid; }
	</style>
</head>
<body>
	<h1>{{ __( 'Privacy compliance report' ) }}</h1>
	<p class="muted">
		{{ __( 'Window' ) }}: {{ $from->toIso8601String() }} → {{ $to->toIso8601String() }}<br />
		{{ __( 'Generated at' ) }}: {{ $report['meta']['generated_at'] }}
		@if( ! empty( $report['meta']['regulation'] ) )
			· {{ __( 'Regulation' ) }}: {{ strtoupper( $report['meta']['regulation'] ) }}
		@endif
	</p>

	@foreach( [ 'consents' => __( 'Consents' ), 'requests' => __( 'Data subject requests' ), 'breaches' => __( 'Breaches' ) ] as $key => $title )
		<section class="section">
			<h2>{{ $title }}</h2>
			<table>
				<thead>
					<tr>
						<th>{{ __( 'Metric' ) }}</th>
						<th>{{ __( 'Value' ) }}</th>
					</tr>
				</thead>
				<tbody>
					@foreach( (array) ( $report[ $key ] ?? [] ) as $metric => $value )
						<tr>
							<td>{{ $metric }}</td>
							<td>
								@if( is_array( $value ) )
									<ul style="margin: 0; padding-left: 18px;">
										@foreach( $value as $subKey => $subValue )
											<li>{{ $subKey }}: {{ is_scalar( $subValue ) ? $subValue : json_encode( $subValue ) }}</li>
										@endforeach
									</ul>
								@else
									{{ $value }}
								@endif
							</td>
						</tr>
					@endforeach
				</tbody>
			</table>
		</section>
	@endforeach
</body>
</html>
