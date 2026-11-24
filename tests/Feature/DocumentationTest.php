<?php

namespace Tests\Feature;

use App\Support\Guides;
use Tests\TestCase;

class DocumentationTest extends TestCase
{
    public function test_guides_index_lists_categories_and_guides(): void
    {
        $response = $this->get('/documentation');

        $response->assertOk();
        $response->assertSee('Guides');
        $response->assertSee('Getting started');
        $response->assertSee('Quick start');
        $response->assertSee('Webhooks');
        $response->assertSee(route('documentation.guide', 'quick-start'));
    }

    public function test_each_guide_renders_its_markdown(): void
    {
        foreach (Guides::all() as $guide) {
            $response = $this->get(route('documentation.guide', $guide['slug']));

            $response->assertOk();
            $response->assertSee($guide['title']);
        }
    }

    public function test_guide_page_links_back_to_index_and_other_guides(): void
    {
        $response = $this->get(route('documentation.guide', 'slack-notifications'));

        $response->assertOk();
        $response->assertSee('Incoming Webhook');
        $response->assertSee(route('documentation.guide', 'webhooks'));
        $response->assertSee(route('documentation'));
    }

    public function test_unknown_guide_returns_404(): void
    {
        $this->get('/documentation/guides/does-not-exist')->assertNotFound();
    }

    public function test_documentation_page_loads(): void
    {
        $response = $this->get('/documentation/reference');

        $response->assertOk();
        $response->assertSee('Public REST API');
        $response->assertSee('Authentication');
        $response->assertSee('List posts');
        $response->assertSee('/posts/{post}');
        $response->assertSee('Schemas');
        $response->assertDontSee('elements-api', false);
    }

    public function test_documentation_explains_both_keys_and_the_paginator(): void
    {
        $response = $this->get('/documentation/reference');

        $response->assertOk();
        $response->assertSee('Start the widget');
        $response->assertSee('YOUR_PUBLISHABLE_KEY');
        $response->assertSee('next_page_url');
        $response->assertDontSee('envelopes');
    }

    public function test_documentation_renders_multi_language_code_examples(): void
    {
        $response = $this->get('/documentation/reference');

        $response->assertOk();
        $response->assertSee('cURL');
        $response->assertSee('JavaScript');
        $response->assertSee('Python');
        $response->assertSee('Ruby');
        $response->assertSee('PHP');
        $response->assertSee('Http::withToken');
        $response->assertSee('await fetch', false);
        $response->assertSee('import requests');
        $response->assertSee('Net::HTTP', false);
    }

    public function test_openapi_spec_is_served(): void
    {
        $response = $this->get('/documentation/openapi.json');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/json');

        $body = $response->json();

        $this->assertSame('1.0.0', $body['info']['version'] ?? null);
        $this->assertNotEmpty($body['paths'] ?? []);
        $this->assertArrayHasKey('apiToken', $body['components']['securitySchemes'] ?? []);
        $this->assertArrayHasKey('publishableKey', $body['components']['securitySchemes'] ?? []);
    }

    public function test_openapi_spec_covers_every_public_resource(): void
    {
        $paths = array_keys($this->get('/documentation/openapi.json')->json('paths'));

        $expected = [
            '/api/v1/init',
            '/api/public/v1/init',
            '/api/public/v1/posts',
            '/api/public/v1/posts/{post}',
            '/api/public/v1/release-notes',
            '/api/public/v1/release-notes/{release_note}',
            '/api/public/v1/contacts',
            '/api/public/v1/contacts/{contact}',
            '/api/public/v1/testimonials',
            '/api/public/v1/testimonials/{testimonial}',
            '/api/public/v1/tags',
            '/api/public/v1/tags/{tag}',
        ];

        foreach ($expected as $path) {
            $this->assertContains($path, $paths, "OpenAPI spec missing path: {$path}");
        }
    }

    public function test_openapi_spec_can_be_downloaded(): void
    {
        $response = $this->get('/documentation/openapi/download');

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=feedbackpilgrim-openapi.json');
    }

    public function test_the_postman_collection_can_be_downloaded(): void
    {
        $response = $this->get(route('documentation.postman.download'));

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=feedbackpilgrim.postman_collection.json');
        $collection = json_decode($response->getFile()->getContent(), true);

        $this->assertSame('{{token}}', $collection['auth']['bearer'][0]['value']);
    }

    public function test_the_old_hand_made_postman_collection_is_gone(): void
    {
        $this->assertFileDoesNotExist(public_path('FeedbackPilgrimPublicAPI.postman_collection.json'));
    }
}
