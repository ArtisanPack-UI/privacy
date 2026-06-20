<?php

/**
 * StoreDataRequestRequest — validation for the JSON data-request endpoint.
 *
 * @package    ArtisanPack_UI
 * @subpackage Privacy
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Privacy\Http\Requests;

use ArtisanPackUI\Privacy\Models\DataRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the {type, reason?} payload submitted by the React/Vue forms.
 *
 * @since 1.0.0
 */
class StoreDataRequestRequest extends FormRequest
{
	/**
	 * Only authenticated visitors may file requests against their own account.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function authorize(): bool
	{
		return null !== $this->user();
	}

	/**
	 * Validation rules.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public function rules(): array
	{
		return [
			'type'   => [ 'required', 'string', 'in:' . implode( ',', $this->allowedTypes() ) ],
			'reason' => [ 'nullable', 'string', 'max:1000' ],
		];
	}

	/**
	 * Allowed request type constants. Honours the
	 * `artisanpack.privacy.data_requests.allowed_types` config so operators
	 * can disable a type (e.g. deletion) and have the validator reject it
	 * regardless of what the UI offers.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	protected function allowedTypes(): array
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
}
