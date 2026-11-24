<section aria-labelledby="testimonials-heading">
    <header class="max-w-xl">
        <h2 id="testimonials-heading" class="text-lg font-semibold text-fp-hi">{{ __('Testimonials') }}</h2>
        <p class="mt-1 text-base text-fp-mid sm:text-sm">
            {{ __('Configure how testimonials are collected and displayed for this project.') }}
        </p>
    </header>

    <form method="post" action="{{ route('settings.update') }}" class="mt-6 max-w-xl space-y-6">
        @csrf
        @method('patch')

        <div class="flex items-start gap-x-3 rounded-xl p-4 ring-1 ring-white/10">
            <input id="testimonial_rating" name="testimonial_rating" type="checkbox" value="1"
                       @checked($errors->any() ? old('testimonial_rating') : ($testimonialRating && $testimonialRating->value))
                       class="mt-1 size-4 shrink-0 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
            <div class="min-w-0">
                <label for="testimonial_rating" class="text-sm font-semibold text-fp-hi">
                    {{ __('Show rating stars in testimonials') }}
                </label>
                <p class="mt-0.5 text-sm text-fp-mid">
                    {{ __('Let customers leave a star rating alongside their testimonial.') }}
                </p>
                <x-input-error class="mt-2" :messages="$errors->get('testimonial_rating')" />
            </div>
        </div>

        <div class="flex items-start gap-x-3 rounded-xl p-4 ring-1 ring-white/10">
            <input id="testimonial_automation" name="testimonial_automation" type="checkbox" value="1"
                       @checked($errors->any() ? old('testimonial_automation') : ($testimonialAutomation && $testimonialAutomation->value))
                       class="mt-1 size-4 shrink-0 rounded-sm border-white/10 text-fp-accent focus:ring-fp-accent">
            <div class="min-w-0">
                <label for="testimonial_automation" class="text-sm font-semibold text-fp-hi">
                    {{ __('Request a testimonial when a post is completed') }}
                </label>
                <p class="mt-0.5 text-sm text-fp-mid">
                    {{ __('Automatically email the contact when their post is marked complete. Sent at most once per contact, or once a month has passed since their last completed post.') }}
                </p>
                <x-input-error class="mt-2" :messages="$errors->get('testimonial_automation')" />
            </div>
        </div>

        <div>
            <label for="testimonial_email_body" class="text-sm font-semibold text-fp-hi">
                {{ __('Request email body') }}
            </label>
            <p class="mt-0.5 text-sm text-fp-mid">
                {{ __('Customize the testimonial request email. Use') }}
                <code class="rounded-sm bg-fp-elevated px-1 py-0.5 font-mono text-xs text-fp-mid">{name}</code>
                {{ __('for the name of the customer and') }}
                <code class="rounded-sm bg-fp-elevated px-1 py-0.5 font-mono text-xs text-fp-mid">{testimonialButton}</code>
                {{ __('to place the testimonial button.') }}
            </p>
            <textarea id="testimonial_email_body" name="testimonial_email_body" rows="10"
                      class="mt-2 block w-full rounded-lg border-0 bg-fp-surface px-3 py-2 text-base text-fp-hi ring-1 ring-white/10 placeholder:text-fp-low focus:ring-2 focus:ring-fp-accent sm:text-sm"
                      placeholder="{{ __('Enter your email content here…') }}"
                      v-pre>{{ old('testimonial_email_body', $testimonialEmailBody?->value ?? '') }}</textarea>
            <x-input-error class="mt-2" :messages="$errors->get('testimonial_email_body')" />
        </div>

        <div class="flex items-center gap-x-4">
            <x-primary-button>{{ __('Save changes') }}</x-primary-button>
        </div>
    </form>
</section>
