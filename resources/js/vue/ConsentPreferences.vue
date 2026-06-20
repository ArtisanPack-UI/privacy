<script setup lang="ts">
/**
 * ConsentPreferences — Vue preferences panel.
 *
 * @since 1.0.0
 */

import { computed, reactive, ref, watch } from 'vue';
import { useConsent } from './useConsent';
import type { ConsentMap } from '../privacy';

interface ConsentPreferencesProps {
	className?: string;
	showDescriptions?: boolean;
	showCookieList?: boolean;
	saveLabel?: string;
	savedLabel?: string;
}

const props = withDefaults( defineProps<ConsentPreferencesProps>(), {
	className: 'privacy-consent-preferences',
	showDescriptions: true,
	showCookieList: false,
	saveLabel: 'Save preferences',
	savedLabel: 'Your preferences have been saved.',
} );

const emit = defineEmits<{ ( e: 'saved', consents: ConsentMap ): void }>();

const { state, loading, setConsents } = useConsent();
const selected = reactive<ConsentMap>( {} );
const initial = ref<ConsentMap>( {} );
const saving = ref( false );
const saved = ref( false );

watch(
	() => state.value?.categories,
	( categories ) => {
		if ( ! categories ) {
			return;
		}
		const snapshot: ConsentMap = {};
		Object.entries( categories ).forEach( ( [ key, config ] ) => {
			snapshot[ key ] = true === config.required || state.value?.consents?.[ key ] === true;
		} );
		Object.keys( snapshot ).forEach( ( key ) => {
			selected[ key ] = snapshot[ key ];
		} );
		initial.value = { ...snapshot };
	},
	{ immediate: true },
);

const dirty = computed( () =>
	Object.entries( selected ).some( ( [ key, value ] ) => initial.value[ key ] !== value ),
);

function toggle( key: string, granted: boolean ): void {
	const required = true === state.value?.categories?.[ key ]?.required;
	selected[ key ] = required ? true : granted;
	saved.value = false;
}

async function save(): Promise<void> {
	saving.value = true;
	try {
		const result = await setConsents( { ...selected } );
		initial.value = { ...selected };
		saved.value = true;
		emit( 'saved', result.consents );
	} finally {
		saving.value = false;
	}
}
</script>

<template>
	<div :class="props.className" :aria-busy="loading">
		<p
			v-if="saved"
			role="status"
			aria-live="polite"
			class="privacy-consent-preferences__notice"
		>
			{{ props.savedLabel }}
		</p>

		<ul
			v-if="state"
			role="group"
			aria-label="Cookie categories"
			class="privacy-consent-preferences__list"
		>
			<li
				v-for="( config, key ) in state.categories"
				:key="key"
				class="privacy-consent-preferences__item"
			>
				<div>
					<label :for="`privacy-preferences-vue-${ key }`">
						<strong>{{ config.name ?? key }}</strong>
						<em v-if="config.required"> (required)</em>
					</label>
					<p v-if="props.showDescriptions && config.description" class="privacy-consent-preferences__description">
						{{ config.description }}
					</p>
					<ul
						v-if="props.showCookieList && Array.isArray( config.cookies ) && config.cookies.length"
						class="privacy-consent-preferences__cookies"
					>
						<li v-for="cookie in config.cookies" :key="cookie">{{ cookie }}</li>
					</ul>
				</div>
				<input
					:id="`privacy-preferences-vue-${ key }`"
					type="checkbox"
					:checked="selected[ key ] ?? config.required === true"
					:disabled="config.required === true || saving"
					@change="( event ) => toggle( key, ( event.target as HTMLInputElement ).checked )"
				/>
			</li>
		</ul>

		<div class="privacy-consent-preferences__actions">
			<button type="button" :disabled="! dirty || saving" @click="save">
				{{ props.saveLabel }}
			</button>
		</div>
	</div>
</template>
