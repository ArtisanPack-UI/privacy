<script setup lang="ts">
/**
 * PolicyReconsentBanner — Vue companion to the Livewire re-consent banner.
 *
 * Render in the application's main layout and feed it the active policy
 * payload (from a shared store, an Inertia prop, or a Livewire bridge).
 * The component renders nothing when `policy` is null.
 *
 * @since 1.0.0
 */

import { computed, ref, watch } from 'vue';
import { getPrivacyClient, type PrivacyClientOptions } from '../privacy';

export interface PolicyReconsentPayload {
	version: string;
	regulation?: string | null;
	url?: string;
}

interface PolicyReconsentBannerProps extends PrivacyClientOptions {
	policy: PolicyReconsentPayload | null;
	className?: string;
	buttonClassName?: string;
	titleLabel?: string;
	descriptionLabel?: string;
	reviewLabel?: string;
	acceptLabel?: string;
	dismissLabel?: string;
	policyUrl?: string;
	reconsentUrl?: string;
}

const props = withDefaults( defineProps<PolicyReconsentBannerProps>(), {
	className: 'privacy-reconsent-banner',
	buttonClassName: 'privacy-reconsent-banner__btn',
	titleLabel: 'Our privacy policy has been updated',
	descriptionLabel: 'Please review the updated policy and confirm that you accept the new terms.',
	reviewLabel: 'Review policy',
	acceptLabel: 'Accept updated policy',
	dismissLabel: 'Remind me later',
	policyUrl: '/privacy/policy',
	reconsentUrl: '/privacy/reconsent',
} );

const emit = defineEmits<{
	( e: 'accept', version: string ): void;
	( e: 'dismiss' ): void;
}>();

const dismissed = ref( false );
const saving = ref( false );

watch(
	() => props.policy?.version,
	() => {
		dismissed.value = false;
	},
);

const visible = computed( () => null !== props.policy && ! dismissed.value );

async function accept(): Promise<void> {
	if ( null === props.policy ) {
		return;
	}
	saving.value = true;
	try {
		const csrf =
			typeof document !== 'undefined'
				? document.querySelector<HTMLMetaElement>( 'meta[name="csrf-token"]' )?.content
				: undefined;
		await fetch( props.reconsentUrl, {
			method: 'POST',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json',
				...( csrf ? { 'X-CSRF-TOKEN': csrf } : {} ),
			},
			credentials: 'same-origin',
			body: JSON.stringify( {
				version: props.policy.version,
				regulation: props.policy.regulation ?? null,
			} ),
		} );
		dismissed.value = true;
		emit( 'accept', props.policy.version );
		const client = getPrivacyClient();
		void client.load();
	} finally {
		saving.value = false;
	}
}

function dismiss(): void {
	dismissed.value = true;
	emit( 'dismiss' );
}
</script>

<template>
	<div
		v-if="visible && policy"
		role="alertdialog"
		aria-modal="false"
		aria-labelledby="privacy-reconsent-title"
		:class="className"
		data-privacy-reconsent
		:data-policy-version="policy.version"
	>
		<div class="privacy-reconsent-banner__inner">
			<slot name="header" />
			<h2 id="privacy-reconsent-title">{{ titleLabel }}</h2>
			<slot name="description">
				<p>{{ descriptionLabel }}</p>
			</slot>
			<p class="privacy-reconsent-banner__version">
				Version: <strong>{{ policy.version }}</strong>
			</p>
			<div class="privacy-reconsent-banner__actions">
				<a
					:class="buttonClassName"
					:href="policy.url ?? policyUrl"
					target="_blank"
					rel="noopener noreferrer"
				>
					{{ reviewLabel }}
				</a>
				<button type="button" :class="buttonClassName" :disabled="saving" @click="dismiss">
					{{ dismissLabel }}
				</button>
				<button
					type="button"
					:class="`${ buttonClassName } btn-primary`"
					:disabled="saving"
					@click="accept"
				>
					{{ acceptLabel }}
				</button>
			</div>
			<slot name="footer" />
		</div>
	</div>
</template>
