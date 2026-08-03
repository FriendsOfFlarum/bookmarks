<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks\Tests\integration\api\discussions;

use FoF\Bookmarks\Tests\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Reading and writing the `bookmarked` attribute on discussions.
 */
class BookmarkTest extends TestCase
{
    #[Test]
    public function discussion_is_not_bookmarked_by_default(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/discussions/1', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($this->json($response)['data']['attributes']['bookmarked']);
    }

    #[Test]
    public function existing_bookmark_is_reported_as_bookmarked(): void
    {
        $this->bookmarkDiscussion(1, 2);

        $response = $this->send(
            $this->request('GET', '/api/discussions/1', ['authenticatedAs' => 2])
        );

        $this->assertTrue($this->json($response)['data']['attributes']['bookmarked']);
    }

    /**
     * A state row with a null `bookmarked_at` is the "read but never bookmarked" case,
     * which must not be reported as a bookmark.
     */
    #[Test]
    public function state_row_without_timestamp_is_not_bookmarked(): void
    {
        $this->bookmarkDiscussion(1, 2, null);

        $response = $this->send(
            $this->request('GET', '/api/discussions/1', ['authenticatedAs' => 2])
        );

        $this->assertFalse($this->json($response)['data']['attributes']['bookmarked']);
    }

    #[Test]
    public function bookmarks_are_per_user(): void
    {
        $this->bookmarkDiscussion(1, 2);

        $response = $this->send(
            $this->request('GET', '/api/discussions/1', ['authenticatedAs' => 3])
        );

        $this->assertFalse($this->json($response)['data']['attributes']['bookmarked']);
    }

    #[Test]
    public function guest_never_sees_a_bookmark(): void
    {
        $this->bookmarkDiscussion(1, 2);

        $response = $this->send(
            $this->request('GET', '/api/discussions/1')
        );

        $this->assertFalse($this->json($response)['data']['attributes']['bookmarked']);
    }

    #[Test]
    public function user_can_bookmark_a_discussion(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/discussions/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['attributes' => ['bookmarked' => true]]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($this->json($response)['data']['attributes']['bookmarked']);

        $this->assertNotNull(
            $this->database()->table('discussion_user')
                ->where('discussion_id', 1)->where('user_id', 2)
                ->value('bookmarked_at')
        );
    }

    #[Test]
    public function user_can_remove_a_bookmark(): void
    {
        $this->bookmarkDiscussion(1, 2);

        $response = $this->send(
            $this->request('PATCH', '/api/discussions/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['attributes' => ['bookmarked' => false]]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($this->json($response)['data']['attributes']['bookmarked']);

        $this->assertNull(
            $this->database()->table('discussion_user')
                ->where('discussion_id', 1)->where('user_id', 2)
                ->value('bookmarked_at')
        );
    }

    /**
     * Bookmarking must not disturb the other state this row carries for core.
     */
    #[Test]
    public function bookmarking_preserves_read_state(): void
    {
        $this->database()->table('discussion_user')->insert([
            'discussion_id' => 1,
            'user_id' => 2,
            'last_read_post_number' => 2,
            'last_read_at' => '2026-01-05 00:00:00',
        ]);

        $this->send(
            $this->request('PATCH', '/api/discussions/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['attributes' => ['bookmarked' => true]]],
            ])
        );

        $state = $this->database()->table('discussion_user')
            ->where('discussion_id', 1)->where('user_id', 2)
            ->first();

        $this->assertEquals(2, $state->last_read_post_number);
        $this->assertNotNull($state->bookmarked_at);
    }

    /**
     * A CSRF token is supplied so that the request reaches the listener: without one it
     * is rejected by middleware, which would pass this test without proving anything
     * about who is allowed to bookmark.
     */
    #[Test]
    public function guest_cannot_bookmark(): void
    {
        $response = $this->send(
            $this->requestWithCsrfToken(
                $this->request('PATCH', '/api/discussions/1', [
                    'json' => ['data' => ['attributes' => ['bookmarked' => true]]],
                ])
            )
        );

        $this->assertEquals(401, $response->getStatusCode());

        $this->assertEquals(
            0,
            $this->database()->table('discussion_user')->whereNotNull('bookmarked_at')->count()
        );
    }

    /**
     * A request that does not mention `bookmarked` must leave an existing bookmark alone.
     */
    #[Test]
    public function unrelated_update_does_not_clear_the_bookmark(): void
    {
        $this->bookmarkDiscussion(1, 2);

        $this->send(
            $this->request('PATCH', '/api/discussions/1', [
                'authenticatedAs' => 1,
                'json' => ['data' => ['attributes' => ['title' => 'Renamed by admin']]],
            ])
        );

        $this->assertNotNull(
            $this->database()->table('discussion_user')
                ->where('discussion_id', 1)->where('user_id', 2)
                ->value('bookmarked_at')
        );
    }
}
