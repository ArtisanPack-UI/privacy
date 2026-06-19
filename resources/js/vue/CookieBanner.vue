<script setup lang="ts">
/**
 * CookieBanner — Vue cookie consent banner.
 *
 * @since 1.0.0
 */

import { computed, reactive, ref, watch } from 'vue';
import { useConsent } from './useConsent';
import type { ConsentMap } from '../privacy';

interface CookieBannerProps {
	className?: string;
	acceptLabel?: string;
	rejectLabel?: string;
	customiseLabel?: string;
	saveLabel?: string;
}

const props = withDefaults( defineProps<CookieBannerProps>(), {
	className: 'privacy-cookie-banner',
	acceptLabel: 'Accept all',
	rejectLabel: 'Reject all',
	customiseLabel: 'Customise',
	saveLabel: 'Save selection',
} );

const emit = defineEmits<{
	( e: 'close' ): void;
	( e: 'updated', consents: ConsentMap ): void;
}>();

const { state, loading, setConsents } = useConsent();
const showPreferences = ref( false );
const dismissed = ref( false );
const saving = ref( false );
const selected = reactive<ConsentMap>( {} );

const requiredKeys = computed( () =>
	Object.entries( state.value?.categories ?? {} )
		.filter( ( [ , config ] ) => true === config.required )
		.map( ( [ key ] ) => key ),
);

watch(
	() => state.value?.categories,
	( categories ) => {
		if ( ! categories ) {
			return;
		}
		Object.entries( categories ).forEach( ( [ key, config ] ) => {
			if ( ! ( key in selected ) ) {
				selected[ key ] = true === config.required ? true : state.value?.consents?.[ key ] === true;
			} else if ( true === config.required ) {
				selected[ key ] = true;
			}
		} );
	},
	{ immediate: true },
);

const hasMadeChoice = computed( () => {
	if ( ! state.value ) {
		return false;
	}
	return Object.entries( state.value.consents ).some(
		( [ key, value ] ) => ! requiredKeys.value.includes( key ) && true === value,
	);
} );

const isVisible = computed( () => ! loading.value && null !== state.value && ! dismissed.value && ! hasMadeChoice.value );

function close(): void {
	dismissed.value = true;
	emit( 'close' );
}

function buildAllTrueMap(): ConsentMap {
	const map: ConsentMap = {};
	Object.keys( state.value?.categories ?? {} ).forEach( ( key ) => {
		map[ key ] = true;
	} );
	return map;
}

function buildRejectMap(): ConsentMap {
	const map: ConsentMap = {};
	Object.entries( state.value?.categories ?? {} ).forEach( ( [ key, config ] ) => {
		map[ key ] = true === config.required;
	} );
	return map;
}

async function submit( consents: ConsentMap ): Promise<void> {
	saving.value = true;
	try {
		const result = await setConsents( consents );
		emit( 'updated', result.consents );
		close();
	} finally {
		saving.value = false;
	}
}

function toggle( category: string, granted: boolean ): void {
	if ( requiredKeys.value.includes( category ) ) {
		selected[ category ] = true;
		return;
	}
	selected[ category ] = granted;
}
</script>

<template>
	<div
		v-if="isVisible"
		role="dialog"
		aria-modal="false"
		aria-label="Cookie consent"
		:class="props.className"
	>
		<div class="privacy-cookie-banner__inner">
			<slot name="header">
				<h2>We value your privacy</h2>
			</slot>
			<slot name="description">
				<p>
					We use cookies to make our site work and to understand how it is used.
					You can choose which categories you allow.
				</p>
			</slot>

			<ul
				v-if="showPreferences"
				role="group"
				aria-label="Cookie categories"
				class="privacy-cookie-banner__categories"
			>
				<li
					v-for="( config, key ) in state?.categories ?? {}"
					:key="key"
					class="privacy-cookie-banner__category"
				>
					<label :for="`privacy-banner-vue-${ key }`">
						<span>
							<strong>{{ config.name ?? key }}</strong>
							<em v-if="config.required"> (required)</em>
						</span>
						<small v-if="config.description">{{ config.description }}</small>
					</label>
					<input
						:id="`privacy-banner-vue-${ key }`"
						type="checkbox"
						:checked="selected[ key ] ?? config.required === true"
						:disabled="config.required === true || saving"
						@change="( event ) => toggle( key, ( event.target as HTMLInputElement ).checked )"
					/>
				</li>
			</ul>

			<div class="privacy-cookie-banner__actions">
				<template v-if="showPreferences">
					<button type="button" :disabled="saving" @click="submit( selected )">
						{{ props.saveLabel }}
					</button>
				</template>
				<template v-else>
					<button type="button" :disabled="saving" @click="showPreferences = true">
						{{ props.customiseLabel }}
					</button>
					<button type="button" :disabled="saving" @click="submit( buildRejectMap() )">
						{{ props.rejectLabel }}
					</button>
					<button type="button" :disabled="saving" @click="submit( buildAllTrueMap() )">
						{{ props.acceptLabel }}
					</button>
				</template>
			</div>

			<slot name="footer" />
		</div>
	</div>
</template>
