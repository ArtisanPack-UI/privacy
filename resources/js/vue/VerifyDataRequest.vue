<script setup lang="ts">
/**
 * VerifyDataRequest — Vue identity-verification component.
 *
 * Mirrors the React/Livewire flow: posts the token to the package
 * verification endpoint and surfaces success / expired / error state.
 *
 * @since 1.0.0
 */

import { ref } from 'vue';

interface VerifyDataRequestProps {
	token: string;
	endpoint?: string;
	csrfToken?: string;
	fetchImpl?: typeof fetch;
	className?: string;
	heading?: string;
}

const props = withDefaults( defineProps<VerifyDataRequestProps>(), {
	className: 'privacy-verify-data-request',
	heading: 'Verify your data request',
} );

const emit = defineEmits<{
	( e: 'verified' ): void;
}>();

type Phase = 'idle' | 'pending' | 'success' | 'expired' | 'error';

const phase = ref<Phase>( 'idle' );
const message = ref( '' );

function resolveCsrfToken(): string | undefined {
	if ( typeof document === 'undefined' ) {
		return undefined;
	}

	const meta = document.querySelector<HTMLMetaElement>( 'meta[name="csrf-token"]' );

	return meta?.content;
}

async function submit() {
	if ( phase.value === 'pending' || phase.value === 'success' ) {
		return;
	}

	phase.value = 'pending';
	message.value = '';

	const endpoint = props.endpoint ?? `/privacy/verify/${ encodeURIComponent( props.token ) }`;
	const csrfToken = props.csrfToken ?? resolveCsrfToken();
	const fetchImpl = props.fetchImpl ?? fetch;

	try {
		const headers: Record<string, string> = {
			Accept: 'application/json',
			'X-Requested-With': 'XMLHttpRequest',
		};

		if ( csrfToken ) {
			headers[ 'X-CSRF-TOKEN' ] = csrfToken;
		}

		const response = await fetchImpl( endpoint, {
			method: 'POST',
			headers,
			credentials: 'same-origin',
		} );

		if ( response.status === 410 || response.status === 422 ) {
			phase.value = 'expired';
			message.value = 'This verification link has expired. Please submit a new request.';
			return;
		}

		if ( ! response.ok ) {
			phase.value = 'error';
			message.value = 'We could not verify this request. Please try again.';
			return;
		}

		phase.value = 'success';
		message.value = 'Your identity has been verified and your request is now being processed.';
		emit( 'verified' );
	} catch ( e ) {
		phase.value = 'error';
		message.value = 'We could not reach the verification endpoint.';
	}
}
</script>

<template>
	<div :class="className">
		<h2 v-if="heading" :class="`${ className }__heading`">{{ heading }}</h2>

		<p v-if="phase === 'success'" :class="`${ className }__success`">{{ message }}</p>
		<p v-else-if="phase === 'expired'" :class="`${ className }__error`">{{ message }}</p>
		<p v-else-if="phase === 'error'" :class="`${ className }__error`">{{ message }}</p>

		<button
			v-if="phase !== 'success' && phase !== 'expired'"
			type="button"
			:disabled="phase === 'pending'"
			:class="`${ className }__button`"
			@click="submit"
		>
			{{ phase === 'pending' ? 'Verifying…' : 'Confirm Identity' }}
		</button>
	</div>
</template>
