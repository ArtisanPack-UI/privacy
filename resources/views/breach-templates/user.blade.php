@php
	/**
	 * Breach notification — affected user template (GDPR Article 34).
	 *
	 * Variables:
	 *   $breach       BreachNotification instance.
	 *   $user         Per-user payload (email, optional name, optional data scope).
	 *   $organization Configured organization metadata (name, contact, website).
	 */
@endphp
# {{ __( 'Important: a data incident may affect your account' ) }}

@if( ! empty( $user['name'] ?? null ) )
{{ __( 'Hello' ) }} {{ $user['name'] }},
@else
{{ __( 'Hello,' ) }}
@endif

{{ __( 'We are writing to let you know about a personal data incident that may have affected your information.' ) }}

## {{ __( 'What happened' ) }}

{{ $breach->description }}

## {{ __( 'What information was involved' ) }}

@php
	$scope = ! empty( $user['data'] ?? null )
		? (array) $user['data']
		: (array) ( $breach->data_types_affected ?? [] );
@endphp
@foreach( $scope as $type )
- {{ $type }}
@endforeach

## {{ __( 'What we are doing' ) }}

{{ $breach->remediation ?? __( 'Our team is actively investigating the incident and putting additional safeguards in place to prevent recurrence.' ) }}

## {{ __( 'What you can do' ) }}

- {{ __( 'Review your account for any unfamiliar activity.' ) }}
- {{ __( 'Change your password and enable two-factor authentication where available.' ) }}
- {{ __( 'Be wary of unsolicited messages that reference this notification.' ) }}

## {{ __( 'How to contact us' ) }}

{{ __( 'If you have questions, please contact us using the information below.' ) }}

- **{{ __( 'Organization' ) }}:** {{ $organization['name'] ?? '—' }}
- **{{ __( 'Contact' ) }}:** {{ $organization['contact'] ?? '—' }}
- **{{ __( 'Website' ) }}:** {{ $organization['website'] ?? '—' }}

{{ __( 'We are sorry for any concern this may cause and appreciate your patience while we work to make this right.' ) }}

— {{ $organization['name'] ?? __( 'Our team' ) }}
