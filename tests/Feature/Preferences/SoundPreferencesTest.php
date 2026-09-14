<?php

namespace Tests\Feature\Preferences;

use App\Models\User;
use App\Support\Preferences\SoundOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Sounds are per-user preferences: which file plays for a slot, and whether that
 * slot makes any noise at all. The success slot is the newest of them, so it has
 * to reach users whose stored preferences predate it.
 */
class SoundPreferencesTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
    }

    public function test_the_success_slot_has_a_catalog_and_a_default(): void
    {
        $this->assertContains('success', SoundOptions::CATEGORIES);
        $this->assertSame('filling-your-inbox-success', SoundOptions::defaultFor('success'));

        $this->assertSame(
            [
                'filling-your-inbox-success',
                'freesound_community-success-1-6297',
                'universfield-level-up-success-03-199576',
                'universfield-success-notification-037-485898',
            ],
            SoundOptions::ids('success'),
        );

        // The new warning sound sits alongside the two that were already there.
        $this->assertContains('glass-breaking-warning', SoundOptions::ids('warning'));
    }

    public function test_every_catalogued_sound_file_actually_exists(): void
    {
        foreach (SoundOptions::CATEGORIES as $category) {
            foreach (SoundOptions::ids($category) as $id) {
                $this->assertFileExists(
                    public_path("sounds/{$id}.mp3"),
                    "The {$category} sound '{$id}' is offered in preferences but has no file.",
                );
            }
        }
    }

    public function test_a_user_whose_preferences_predate_the_success_slot_still_gets_it(): void
    {
        $user = $this->ctx['user'];

        // Exactly what an existing row looks like: sound settings, no success keys.
        $user->preferences = [
            'notifications' => [
                'sound' => [
                    'notification_enabled' => true,
                    'notification_choice' => 'notification2',
                    'warning_enabled' => false,
                    'warning_choice' => 'warning1',
                ],
            ],
        ];
        $user->save();

        $sound = data_get($user->fresh()->getAllPreferences(), 'notifications.sound');

        $this->assertTrue($sound['success_enabled']);
        $this->assertSame('filling-your-inbox-success', $sound['success_choice']);

        // Their own choices survive the merge.
        $this->assertFalse($sound['warning_enabled']);
        $this->assertSame('notification2', $sound['notification_choice']);
    }

    public function test_it_saves_a_success_sound_choice(): void
    {
        $this->put(route('preferences.update'), [
            'notifications' => [
                'sound' => [
                    'success_enabled' => true,
                    'success_choice' => 'universfield-level-up-success-03-199576',
                ],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            'universfield-level-up-success-03-199576',
            $this->ctx['user']->fresh()->getPreference('notifications.sound.success_choice'),
        );
    }

    public function test_it_rejects_a_success_sound_that_is_not_in_the_catalog(): void
    {
        $this->put(route('preferences.update'), [
            'notifications' => [
                'sound' => ['success_choice' => 'warning1'],
            ],
        ])->assertSessionHasErrors('notifications.sound.success_choice');
    }

    public function test_a_muted_slot_is_stored_as_off(): void
    {
        $this->put(route('preferences.update'), [
            'notifications' => [
                'sound' => ['success_enabled' => false],
            ],
        ])->assertSessionHasNoErrors();

        // The frontend composable only stays silent on an explicit false, so the
        // stored value has to be the boolean and not a dropped key.
        $this->assertFalse(
            $this->ctx['user']->fresh()->getPreference('notifications.sound.success_enabled'),
        );
    }
}
