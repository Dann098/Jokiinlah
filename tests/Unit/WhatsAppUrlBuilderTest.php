<?php

namespace Tests\Unit;

use App\Models\SiteSetting;
use App\Services\WhatsAppUrlBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppUrlBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_normalizes_indonesian_number_and_encodes_message(): void
    {
        SiteSetting::query()->create(['key' => 'whatsapp_number', 'value' => '0812-3456-7890', 'type' => 'string', 'group' => 'contact', 'is_public' => true]);

        $url = app(WhatsAppUrlBuilder::class)->build('Halo & konsultasi?');

        $this->assertSame('https://wa.me/6281234567890?text=Halo%20%26%20konsultasi%3F', $url);
    }

    public function test_builder_returns_null_for_invalid_number(): void
    {
        config(['jokiinlah.whatsapp_number' => '123']);

        $this->assertNull(app(WhatsAppUrlBuilder::class)->build('Halo'));
    }
}
