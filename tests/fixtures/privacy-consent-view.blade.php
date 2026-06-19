@foreach(($privacyConsent ?? []) as $category => $granted)
{{ $category }}:{{ $granted ? 'true' : 'false' }}
@endforeach
