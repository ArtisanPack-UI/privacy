@extends( 'privacy::admin.layout', [ 'title' => __( 'Breach' ) . ' ' . $breach->reference_number ] )

@section( 'content' )
	<livewire:privacy-admin-breach-detail :breach-id="$breach->id" />
@endsection
