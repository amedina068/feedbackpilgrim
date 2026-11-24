<?php

namespace Tests\Unit\Rules;

use App\Rules\Public\ContactRules;
use App\Rules\Public\PostRules;
use App\Rules\Public\ReleaseNoteRules;
use App\Rules\Public\TagRules;
use App\Rules\Public\TestimonialRules;
use Tests\TestCase;

class PublicRulesTest extends TestCase
{
    private function stringify(array $rules): string
    {
        $flat = [];
        array_walk_recursive($rules, function ($rule) use (&$flat) {
            $flat[] = is_object($rule) ? (string) $rule : (string) $rule;
        });

        return implode(' | ', $flat);
    }

    public function test_post_rules_scope_tag_existence_to_client(): void
    {
        $store = PostRules::store(7);

        $this->assertSame('required|string|max:255', $store['title']);
        $this->assertSame('required|string', $store['content']);
        $this->assertStringContainsString('client_id', $this->stringify($store['tags.*']));

        $update = PostRules::update(7);
        $this->assertSame('sometimes|string|max:255', $update['title']);
    }

    public function test_release_note_rules_match_controller(): void
    {
        $store = ReleaseNoteRules::store(3);
        $this->assertSame('required|string|max:255', $store['title']);
        $this->assertSame('required|string', $store['content']);
        $this->assertStringContainsString('client_id', $this->stringify($store['tags.*']));
    }

    public function test_testimonial_rules_match_controller(): void
    {
        $store = TestimonialRules::store(3);
        $this->assertSame('required|string', $store['description']);
        $this->assertSame('required|string|max:255', $store['username']);
        $this->assertStringContainsString('client_id', $this->stringify($store['contact_id']));

        $update = TestimonialRules::update(3);
        $this->assertSame('sometimes|string|max:255', $update['username']);
        $this->assertSame('sometimes|date', $update['approved_at']);
    }

    public function test_contact_update_applies_unique_ignore_only_when_id_given(): void
    {
        $withId = ContactRules::update(5, 42);
        $this->assertStringContainsString('client_id', $this->stringify($withId['email']));
        // Sanctum unique stringifies the ignored id into the rule.
        $this->assertStringContainsString('42', $this->stringify($withId['email']));

        $withoutId = ContactRules::update(5, null);
        $this->assertStringNotContainsString('42', $this->stringify($withoutId['email']));
    }

    public function test_tag_rules_unique_per_client_and_ignore_self(): void
    {
        $store = TagRules::store(9);
        $storeStr = $this->stringify($store['title']);
        $this->assertStringContainsString('unique', $storeStr);
        $this->assertStringContainsString('client_id', $storeStr);

        $update = TagRules::update(9, 13);
        $this->assertStringContainsString('13', $this->stringify($update['title']));
    }
}
