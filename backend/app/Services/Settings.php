<?php

namespace App\Services;

use App\Models\ContentBlock;
use App\Models\SiteSetting;

class Settings
{
    private ?array $values = null;

    public function all(): array
    {
        return $this->values ??= SiteSetting::all()->pluck('value', 'key')->all();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    public function ready(): bool
    {
        $s = $this->all();

        return $this->bookingEnabled()
            && in_array($s['timezone'] ?? '', timezone_identifiers_list(), true)
            && preg_match('/^[A-Z]{3}$/', $s['currency'] ?? '')
            && ! empty($s['country']) && ! empty($s['address']);
    }

    public function bookingEnabled(): bool
    {
        return in_array($this->get('booking_enabled', false), [true, 1, '1'], true);
    }

    public function policies(): array
    {
        return ['cancellation_hours' => (int) $this->get('cancellation_hours', 24), 'reschedule_hours' => (int) $this->get('reschedule_hours', 24), 'text' => ContentBlock::where('key', 'policy')->where('published', true)->first()?->value];
    }
}
