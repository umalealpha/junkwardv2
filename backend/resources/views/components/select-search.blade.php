
{{--<div x-data="{--}}
{{--	data: @js($options),--}}
{{--	emptyOptionsMessage: @js($emptyOptionsMessage ?? 'No results match your search.'),--}}
{{--	focusedOptionIndex: null,--}}
{{--	trackBy:@js($trackBy),--}}
{{--	title:@js($label),--}}
{{--	name:@js($attributes->get('id')),--}}
{{--	open:false,--}}
{{--	options: {},--}}
{{--	placeholder: @js($attributes->get('placeholder') ?? 'Select an option'),--}}
{{--	listner: @js($attributes->get('listner') ?? '0'),--}}
{{--	search: '',--}}
{{--	value: @entangle($attributes->wire('model')),--}}
{{--	toggleListboxVisibility: function () {--}}
{{--		if (this.open) return this.closeListbox()--}}
{{--		this.focusedOptionIndex = Object.keys(this.options).indexOf(this.value)--}}
{{--		if (this.focusedOptionIndex < 0) this.focusedOptionIndex = 0--}}
{{--		this.open = true--}}
{{--		this.$nextTick(() => {--}}
{{--			this.$refs.search.focus()--}}
{{--			this.$refs.listbox.children[this.focusedOptionIndex].scrollIntoView({--}}
{{--				block: 'center'--}}
{{--			})--}}
{{--		})--}}
{{--	},--}}
{{--	closeListbox: function () {--}}
{{--		this.open = false--}}
{{--		this.focusedOptionIndex = null--}}
{{--		this.search = ''--}}
{{--	},--}}

{{--	focusNextOption: function () {--}}
{{--		if (this.focusedOptionIndex === null) return this.focusedOptionIndex = Object.keys(this.options).length - 1--}}
{{--		if (this.focusedOptionIndex + 1 >= Object.keys(this.options).length) return--}}
{{--		this.focusedOptionIndex++--}}
{{--		this.$refs.listbox.children[this.focusedOptionIndex].scrollIntoView({--}}
{{--			block: 'center',--}}
{{--		})--}}
{{--	},--}}

{{--	focusPreviousOption: function () {--}}
{{--		if (this.focusedOptionIndex === null) return this.focusedOptionIndex = 0--}}
{{--		if (this.focusedOptionIndex <= 0) return--}}
{{--		this.focusedOptionIndex----}}
{{--		this.$refs.listbox.children[this.focusedOptionIndex].scrollIntoView({--}}
{{--			block: 'center',--}}
{{--		})--}}
{{--	},--}}

{{--	init: function () {--}}
{{--		this.options = this.data--}}
{{--		if (!(this.value in this.options)) this.value = null--}}
{{--		this.$watch('search', ((value) => {--}}
{{--			if (!this.open || !value) return this.options = this.data--}}
{{--			this.options = Object.keys(this.data)--}}
{{--				.filter((key) => this.data[key][this.title].toLowerCase().includes(value.toLowerCase()))--}}
{{--				.reduce((options, key) => {--}}
{{--					options[key] = this.data[key]--}}
{{--					return options--}}
{{--				}, {})--}}
{{--		}))--}}
{{--	},--}}

{{--	selectOption: function () {--}}
{{--		if (!this.open) return this.toggleListboxVisibility()--}}
{{--		if(this.value == Object.keys(this.options)[this.focusedOptionIndex]){--}}
{{--			this.value ='';--}}
{{--		}else{--}}
{{--			this.value = Object.keys(this.options)[this.focusedOptionIndex];--}}
{{--		}--}}
{{--		this.closeListbox();--}}


{{--	},--}}
{{--	rebuild(event){--}}
{{--		if(event.detail.key==this.listner){--}}
{{--			this.options=event.detail.data;--}}
{{--			this.data=event.detail.data;--}}
{{--		}--}}
{{--	}--}}
{{--}"--}}

{{--@click.away="closeListbox()"--}}
{{--@keydown.escape="closeListbox()"--}}
{{--x-init="init()"--}}
{{--class="relative"--}}
{{--x-on:dropdown-changed.window="rebuild($event)"--}}
{{-->--}}
{{--	<span class="inline-block w-full rounded-md">--}}
{{--		<button type="button"--}}
{{--			  x-ref="button"--}}
{{--			  @click="toggleListboxVisibility()"--}}
{{--			  :aria-expanded="open"--}}
{{--			  aria-haspopup="listbox"--}}
{{--                class="relative z-0 w-full py-2.5 pl-3 pr-10 p-3 text-left transition duration-150 ease-in-out bg-gray-100 border border-gray-300 rounded-md cursor-default focus:outline-none focus:shadow-outline-blue focus:border-blue-300 sm:text-sm sm:leading-5"--}}

{{--                class="relative z-0 w-full py-2.5 pl-3 pr-10 p-3 text-left ease-in-out bg-gray-100 border border-gray-300 rounded-md cursor-default focus:outline-none focus:shadow-outline-blue focus:border-blue-300 sm:text-sm sm:leading-5"--}}
{{--              {{ $disabled ? 'disabled' : '' }}--}}
{{--		>--}}
{{--			<span--}}
{{--					x-show="! open"--}}
{{--					x-text="value in options ? options[value][title] : placeholder"--}}
{{--					:class="{ 'text-gray-500': ! (value in options) }"--}}
{{--					class="block truncate text-gray-800 pt-3.5 pr-3.5"--}}
{{--			></span>--}}

{{--			<input--}}
{{--					x-ref="search"--}}
{{--					x-show="open"--}}
{{--					x-model="search"--}}
{{--					@keydown.enter.stop.prevent="selectOption()"--}}
{{--					@keydown.arrow-up.prevent="focusPreviousOption()"--}}
{{--					@keydown.arrow-down.prevent="focusNextOption()"--}}
{{--					type="search"--}}
{{--					{!! $attributes->merge(['class' => 'bg-transparent p-0.5 px-1 rounded-md appearance-none outline-none h-full w-full text-gray-800']) !!}--}}
{{--                    {{ $disabled ? 'disabled' : '' }}--}}

{{--			/>--}}

{{--			<span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">--}}
{{--				<svg class="w-5 h-5 text-gray-400" viewBox="0 0 20 20" fill="none" stroke="currentColor">--}}
{{--					<path d="M7 7l3-3 3 3m0 6l-3 3-3-3" stroke-width="1.5" stroke-linecap="round"--}}
{{--						  stroke-linejoin="round"></path>--}}
{{--				</svg>--}}
{{--			</span>--}}
{{--		</button>--}}
{{--	</span>--}}
{{--	<div--}}
{{--			x-show="open"--}}
{{--			x-transition:leave="transition ease-in duration-100"--}}
{{--			x-transition:leave-start="opacity-100"--}}
{{--			x-transition:leave-end="opacity-0"--}}
{{--			x-cloak--}}
{{--			class="absolute z-10 w-full mt-1 bg-gray-100 rounded-md shadow-lg"--}}
{{--	>--}}
{{--		<ul--}}
{{--				x-ref="listbox"--}}
{{--				@keydown.enter.stop.prevent="selectOption()"--}}
{{--				@keydown.arrow-up.prevent="focusPreviousOption()"--}}
{{--				@keydown.arrow-down.prevent="focusNextOption()"--}}
{{--				role="listbox"--}}
{{--				:aria-activedescendant="focusedOptionIndex ? name + 'Option' + focusedOptionIndex : null"--}}
{{--				tabindex="-1"--}}
{{--				class="py-1 overflow-auto text-base leading-6 rounded-md shadow-xs max-h-60 focus:outline-none sm:text-sm sm:leading-5"--}}
{{--		>--}}
{{--			<template x-for="(key, index) in Object.keys(options)" :key="index">--}}
{{--				<li--}}
{{--						:id="name + 'Option' + focusedOptionIndex"--}}
{{--						@click="selectOption()"--}}
{{--						@mouseenter="focusedOptionIndex = index"--}}
{{--						@mouseleave="focusedOptionIndex = null"--}}
{{--						role="option"--}}
{{--						:aria-selected="focusedOptionIndex === index"--}}
{{--						:class="{ 'text-white bg-indigo-600': index === focusedOptionIndex, 'text-gray-900': index !== focusedOptionIndex }"--}}
{{--						class="relative py-2 pl-3 text-gray-900 cursor-default select-none pr-9"--}}
{{--				>--}}
{{--						<span x-text="Object.values(options)[index] ? Object.values(options)[index][title] :''"--}}
{{--							  :class="{ 'font-semibold': index === focusedOptionIndex, 'font-normal': index !== focusedOptionIndex }"--}}
{{--							  class="block font-normal truncate"--}}
{{--						></span>--}}

{{--					<span--}}
{{--							x-show="key === value"--}}
{{--							:class="{ 'text-white': index === focusedOptionIndex, 'text-indigo-600': index !== focusedOptionIndex }"--}}
{{--							class="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600"--}}
{{--					>--}}
{{--							<svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">--}}
{{--								<path fill-rule="evenodd"--}}
{{--									  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"--}}
{{--									  clip-rule="evenodd"/>--}}
{{--							</svg>--}}
{{--						</span>--}}
{{--				</li>--}}
{{--			</template>--}}

{{--			<div--}}
{{--					x-show="! Object.keys(options).length"--}}
{{--					x-text="emptyOptionsMessage"--}}
{{--					class="px-3 py-2 text-gray-900 cursor-default select-none"></div>--}}
{{--		</ul>--}}
{{--	</div>--}}
{{--</div>--}}

{{--@once--}}
{{--  <link--}}
{{--      href="https://cdn.jsdelivr.net/npm/tailwindcss/dist/tailwind.min.css"--}}
{{--      rel="stylesheet"--}}
{{--    />--}}
{{--@endonce--}}

@props([
    'options' => [],
    'model' => $attributes->wire('model')->value ?? $attributes->get('name'),
    'disabled' => false

])
<select {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'form-select form-select-solid']) !!}>
    <option value="">- Select -</option>
    @foreach($options as $id => $data)
        <option value="{{ $id }}">{{ $data['name'] }}</option>
    @endforeach
</select>


