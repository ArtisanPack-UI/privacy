@php
	/**
	 * Breach notification — supervisory authority template (GDPR Article 33).
	 *
	 * Variables:
	 *   $breach       BreachNotification instance.
	 *   $organization Configured organization metadata (name, address, website, contact).
	 *   $dpo          Configured DPO contact (name, email, phone).
	 */
@endphp
# {{ __( 'Personal Data Breach Notification' ) }}

**{{ __( 'Reference' ) }}:** {{ $breach->reference_number }}
**{{ __( 'Severity' ) }}:** {{ ucfirst( $breach->severity ) }}
**{{ __( 'Status' ) }}:** {{ ucfirst( $breach->status ) }}

## {{ __( 'Reporting organization' ) }}

- **{{ __( 'Name' ) }}:** {{ $organization['name'] ?? '—' }}
- **{{ __( 'Address' ) }}:** {{ $organization['address'] ?? '—' }}
- **{{ __( 'Website' ) }}:** {{ $organization['website'] ?? '—' }}
- **{{ __( 'Contact' ) }}:** {{ $organization['contact'] ?? '—' }}

## {{ __( 'Data Protection Officer' ) }}

- **{{ __( 'Name' ) }}:** {{ $dpo['name'] ?? '—' }}
- **{{ __( 'Email' ) }}:** {{ $dpo['email'] ?? '—' }}
- **{{ __( 'Phone' ) }}:** {{ $dpo['phone'] ?? '—' }}

## {{ __( 'Nature of the breach' ) }}

{{ $breach->description }}

@if( $breach->cause )
**{{ __( 'Cause' ) }}:** {{ $breach->cause }}
@endif

## {{ __( 'Timeline' ) }}

- **{{ __( 'Discovered at' ) }}:** {{ optional( $breach->discovered_at )->toIso8601String() ?? '—' }}
@if( $breach->occurred_at )
- **{{ __( 'Likely occurred at' ) }}:** {{ $breach->occurred_at->toIso8601String() }}
@endif

## {{ __( 'Categories of personal data affected' ) }}

@foreach( (array) ( $breach->data_types_affected ?? [] ) as $type )
- {{ $type }}
@endforeach

## {{ __( 'Scale' ) }}

- **{{ __( 'Approximate number of records affected' ) }}:** {{ $breach->records_affected ?? __( 'Unknown' ) }}

## {{ __( 'Likely consequences' ) }}

{{ __( 'The likely consequences of the breach include potential exposure of the data categories listed above. Affected individuals may face risks proportionate to the sensitivity of the data and the volume of records involved.' ) }}

## {{ __( 'Measures taken or proposed' ) }}

{{ $breach->remediation ?? __( 'Remediation measures are being investigated and will be communicated in a follow-up notification once available.' ) }}
