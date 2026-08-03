<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks\Tests\integration;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Settings must reach the forum payload with a usable value before an admin has ever
 * opened the extension page, otherwise the frontend has to guess.
 */
class SettingsTest extends BaseTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-bookmarks');
    }

    /**
     * @return array<string, mixed>
     */
    private function forumAttributes(): array
    {
        $response = $this->send($this->request('GET', '/api', ['authenticatedAs' => 1]));

        $body = $response->getBody();
        $body->rewind();

        return json_decode($body->getContents(), true)['data']['attributes'];
    }

    #[Test]
    public function discussion_button_defaults_to_the_sidebar(): void
    {
        $attributes = $this->forumAttributes();

        $this->assertArrayHasKey('fof-bookmarks.independentButton', $attributes);
        $this->assertTrue($attributes['fof-bookmarks.independentButton']);
    }

    /**
     * Without a default this serializes as null, leaving the admin dropdown blank and
     * forcing the frontend to invent a fallback.
     *
     */
    #[Test]
    public function post_button_position_defaults_to_the_header(): void
    {
        $attributes = $this->forumAttributes();

        $this->assertArrayHasKey('fof-bookmarks.postButtonPosition', $attributes);
        $this->assertSame('header', $attributes['fof-bookmarks.postButtonPosition']);
    }

    #[Test]
    public function post_header_badge_defaults_to_off(): void
    {
        $attributes = $this->forumAttributes();

        $this->assertArrayHasKey('fof-bookmarks.postHeaderBadge', $attributes);
        $this->assertFalse($attributes['fof-bookmarks.postHeaderBadge']);
    }

    #[Test]
    public function settings_are_overridable(): void
    {
        $this->setting('fof-bookmarks.independentButton', '0');
        $this->setting('fof-bookmarks.postButtonPosition', 'menu');
        $this->setting('fof-bookmarks.postHeaderBadge', '1');

        $attributes = $this->forumAttributes();

        $this->assertFalse($attributes['fof-bookmarks.independentButton']);
        $this->assertSame('menu', $attributes['fof-bookmarks.postButtonPosition']);
        $this->assertTrue($attributes['fof-bookmarks.postHeaderBadge']);
    }
}
