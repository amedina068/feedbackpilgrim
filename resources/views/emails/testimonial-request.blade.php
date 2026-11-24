@component('mail::message')

{{ $contentBeforeButton }}

@component('mail::button', ['url' => $testimonialUrl])
Leave a Testimonial
@endcomponent

{{ $contentAfterButton }}

@endcomponent