<?php

/**
 * UpdateConsentRequest — validation for the consent update endpoint.
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

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Accepts either a single-category update (`{ "category": "analytics", "granted": true }`)
 * or a bulk update (`{ "consents": { "analytics": true, "marketing": false } }`).
 *
 * @since 1.0.0
 */
class UpdateConsentRequest extends FormRequest
{
	/**
	 * Authorization gate — public endpoint, anyone may submit a consent change.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function authorize(): bool
	{
		return true;
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
			'category'   => [ 'nullable', 'string', 'max:255' ],
			'granted'    => [ 'nullable', 'boolean' ],
			'consents'   => [ 'nullable', 'array' ],
			'consents.*' => [ 'boolean' ],
		];
	}

	/**
	 * Cross-field validation — at least one shape must be supplied, and a
	 * single-category update needs both `category` and `granted`.
	 *
	 * @since 1.0.0
	 *
	 * @param  Validator  $validator Validator instance.
	 *
	 * @return void
	 */
	public function withValidator( Validator $validator ): void
	{
		$validator->after( function ( Validator $validator ): void {
			$hasCategory = $this->has( 'category' ) && '' !== $this->input( 'category' );
			$hasGranted  = $this->has( 'granted' );
			$hasBulk     = $this->has( 'consents' );

			if ( ! $hasBulk && ! ( $hasCategory && $hasGranted ) ) {
				$validator->errors()->add(
					'consents',
					'Either a `consents` map or both `category` and `granted` must be supplied.',
				);

				return;
			}

			if ( $hasCategory && ! $hasGranted ) {
				$validator->errors()->add( 'granted', 'The granted field is required when category is supplied.' );
			}

			if ( $hasGranted && ! $hasCategory ) {
				$validator->errors()->add( 'category', 'The category field is required when granted is supplied.' );
			}
		} );
	}

	/**
	 * Returns the normalised consent map: category => granted.
	 *
	 * Accepts both the single-category form and the bulk form and merges
	 * them into one map for the controller to act on.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, bool>
	 */
	public function normalisedConsents(): array
	{
		$consents = [];

		if ( $this->has( 'consents' ) ) {
			foreach ( (array) $this->input( 'consents', [] ) as $category => $granted ) {
				if ( is_string( $category ) ) {
					$consents[ $category ] = (bool) $granted;
				}
			}
		}

		if ( $this->has( 'category' ) && $this->has( 'granted' ) ) {
			$consents[ (string) $this->input( 'category' ) ] = (bool) $this->input( 'granted' );
		}

		return $consents;
	}
}
