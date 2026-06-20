<!DOCTYPE html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{{ __( 'Verify Your Data Request' ) }}</title>
	<style>
		body { font-family: system-ui, -apple-system, sans-serif; background: #f4f4f5; margin: 0; padding: 2rem; color: #18181b; }
		.card { max-width: 32rem; margin: 4rem auto; background: #fff; border-radius: 0.75rem; padding: 2rem; box-shadow: 0 8px 24px rgba( 0, 0, 0, 0.06 ); }
		h1 { margin-top: 0; font-size: 1.5rem; }
		.muted { color: #71717a; font-size: 0.875rem; }
		button { background: #18181b; color: #fff; padding: 0.625rem 1.25rem; border: 0; border-radius: 0.5rem; font-weight: 600; cursor: pointer; }
		button:disabled { background: #a1a1aa; cursor: not-allowed; }
		.status { padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
		.status-success { background: #dcfce7; color: #14532d; }
		.status-error { background: #fee2e2; color: #7f1d1d; }
	</style>
</head>
<body>
	<div class="card">
		<h1>{{ __( 'Verify Your Data Request' ) }}</h1>

		@if ( session( 'status' ) )
			<div class="status status-success">{{ session( 'status' ) }}</div>
		@endif

		@if ( $errors->any() )
			<div class="status status-error">{{ $errors->first() }}</div>
		@endif

		<p class="muted">
			{{ __( 'Request #:id of type ":type" filed on :date.', [
				'id'   => $dataRequest->id,
				'type' => $dataRequest->type,
				'date' => optional( $dataRequest->created_at )->toDayDateTimeString() ?? '',
			] ) }}
		</p>

		@if ( null !== $dataRequest->verified_at )
			<p>{{ __( 'This request has already been verified.' ) }}</p>
		@elseif ( $expired )
			<p>{{ __( 'This verification link has expired. Please submit a new data request.' ) }}</p>
		@else
			<form method="POST" action="{{ route( 'privacy.verification.verify', [ 'token' => $token ] ) }}">
				@csrf
				<p>{{ __( 'Confirm you submitted this request to continue.' ) }}</p>
				<button type="submit">{{ __( 'Confirm Identity' ) }}</button>
			</form>
		@endif
	</div>
</body>
</html>
