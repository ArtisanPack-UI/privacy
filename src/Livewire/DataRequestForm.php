<?php

/**
 * DataRequestForm Livewire component — collects data subject requests.
 *
 * Renders a form for the four supported request types (access, export,
 * deletion, rectification) with optional reason capture, validates the
 * input, files the request through {@see DataRequestService}, and renders
 * a success state with the verification status.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Livewire;

use ArtisanPackUI\Privacy\Models\DataRequest;
use ArtisanPackUI\Privacy\Services\DataRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Livewire data subject request form.
 *
 * @since 1.0.0
 */
class DataRequestForm extends Component
{
	/**
	 * Allowed request types in the order they should appear in the UI.
	 *
	 * @var array<int, string>
	 */
	#[Locked]
	public array $requestTypes = [];

	/**
	 * Whether the `reason` field is required.
	 *
	 * @var bool
	 */
	#[Locked]
	public bool $requireReason = false;

	/**
	 * Selected request type.
	 *
	 * @var string|null
	 */
	public ?string $type = null;

	/**
	 * Optional reason explaining the request.
	 *
	 * @var string
	 */
	public string $reason = '';

	/**
	 * True once a request has been submitted successfully.
	 *
	 * @var bool
	 */
	public bool $submitted = false;

	/**
	 * Whether identity verification is required for the most recent submit.
	 *
	 * @var bool
	 */
	public bool $verificationSent = false;

	/**
	 * Mount the component.
	 *
	 * @since 1.0.0
	 *
	 * @param  array<int, string>|null  $requestTypes   Limit the selectable request types.
	 * @param  bool|null                $requireReason  Force the reason field to be required.
	 *
	 * @return void
	 */
	public function mount( ?array $requestTypes = null, ?bool $requireReason = null ): void
	{
		$allowed = $this->allowedRequestTypes();

		$this->requestTypes = null === $requestTypes
			? $allowed
			: array_values( array_intersect( $allowed, array_map( 'strval', $requestTypes ) ) );

		$this->requireReason = $requireReason ?? false;
	}

	/**
	 * Submit the request: validates, persists, fires events, and flips the
	 * component to its success state.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function submit(): void
	{
		$subject = Auth::user();

		if ( ! $subject instanceof Model ) {
			$this->addError( 'type', __( 'You must be signed in to submit a privacy request.' ) );

			return;
		}

		$this->validate( $this->rules(), $this->messages() );

		$service = app( DataRequestService::class );
		$reason  = '' === $this->reason ? null : $this->reason;

		try {
			$request = match ( $this->type ) {
				DataRequest::TYPE_ACCESS        => $service->createAccessRequest( $subject, $reason ),
				DataRequest::TYPE_EXPORT        => $service->createExportRequest( $subject, $reason ),
				DataRequest::TYPE_DELETION      => $service->createDeletionRequest( $subject, $reason ),
				DataRequest::TYPE_RECTIFICATION => $service->createRectificationRequest( $subject, $reason ),
				default                         => null,
			};
		} catch ( Throwable $e ) {
			report( $e );

			$this->addError( 'type', __( 'We could not file your request right now. Please try again.' ) );

			return;
		}

		if ( null === $request ) {
			$this->addError( 'type', __( 'Unsupported request type.' ) );

			return;
		}

		$this->submitted        = true;
		$this->verificationSent = (bool) config( 'artisanpack.privacy.data_requests.require_verification', true );

		$this->dispatch(
			'privacy:data-request-submitted',
			id: $request->id,
			type: $request->type,
		);
	}

	/**
	 * Reset the form back to its blank state so the visitor can file another request.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function startNewRequest(): void
	{
		$this->type             = null;
		$this->reason           = '';
		$this->submitted        = false;
		$this->verificationSent = false;
		$this->resetErrorBag();
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
		return view( 'privacy::livewire.data-request-form' );
	}

	/**
	 * Returns the request types the package allows submissions for, honouring
	 * the `artisanpack.privacy.data_requests.allowed_types` config so the
	 * server-side allowlist matches whatever JSON endpoint/StoreDataRequestRequest
	 * accepts.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function allowedRequestTypes(): array
	{
		$configured = (array) config( 'artisanpack.privacy.data_requests.allowed_types', [
			DataRequest::TYPE_ACCESS,
			DataRequest::TYPE_EXPORT,
			DataRequest::TYPE_DELETION,
			DataRequest::TYPE_RECTIFICATION,
		] );

		$known = [
			DataRequest::TYPE_ACCESS,
			DataRequest::TYPE_EXPORT,
			DataRequest::TYPE_DELETION,
			DataRequest::TYPE_RECTIFICATION,
		];

		return array_values( array_intersect( $known, array_map( 'strval', $configured ) ) );
	}

	/**
	 * Validation rules for the form.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<int, string>>
	 */
	protected function rules(): array
	{
		return [
			'type'   => [ 'required', 'string', 'in:' . implode( ',', $this->requestTypes ) ],
			'reason' => $this->requireReason
				? [ 'required', 'string', 'max:1000' ]
				: [ 'nullable', 'string', 'max:1000' ],
		];
	}

	/**
	 * Custom validation error messages.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	protected function messages(): array
	{
		return [
			'type.required'   => __( 'Please choose a request type.' ),
			'type.in'         => __( 'That request type is not available.' ),
			'reason.required' => __( 'A reason is required for this request.' ),
			'reason.max'      => __( 'Please keep the reason under 1000 characters.' ),
		];
	}
}
