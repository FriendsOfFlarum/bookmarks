<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks\Tests\integration\api\posts;

use FoF\Bookmarks\Tests\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Reading and writing the `bookmarked` attribute on posts.
 */
class BookmarkTest extends TestCase
{
    #[Test]
    public function post_is_not_bookmarked_by_default(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/posts/1', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($this->json($response)['data']['attributes']['bookmarked']);
    }

    #[Test]
    public function existing_bookmark_is_reported_as_bookmarked(): void
    {
        $this->bookmarkPost(1, 2);

        $response = $this->send(
            $this->request('GET', '/api/posts/1', ['authenticatedAs' => 2])
        );

        $this->assertTrue($this->json($response)['data']['attributes']['bookmarked']);
    }

    /**
     * The bookmark relationship is per-user, so another user's bookmark must never be
     * reported as the actor's own.
     */
    #[Test]
    public function bookmarks_are_per_user(): void
    {
        $this->bookmarkPost(1, 2);

        $response = $this->send(
            $this->request('GET', '/api/posts/1', ['authenticatedAs' => 3])
        );

        $this->assertFalse($this->json($response)['data']['attributes']['bookmarked']);
    }

    #[Test]
    public function guest_never_sees_a_bookmark(): void
    {
        $this->bookmarkPost(1, 2);

        $response = $this->send(
            $this->request('GET', '/api/posts/1')
        );

        $this->assertFalse($this->json($response)['data']['attributes']['bookmarked']);
    }

    /**
     * Every post in a listing must report the actor's own state, not the first row's.
     */
    #[Test]
    public function listing_reports_state_per_post(): void
    {
        $this->bookmarkPost(2, 2);

        $response = $this->send(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['discussion' => 1], 'sort' => 'number'])
        );

        $bookmarked = [];

        foreach ($this->json($response)['data'] as $post) {
            $bookmarked[$post['id']] = $post['attributes']['bookmarked'];
        }

        $this->assertFalse($bookmarked['1']);
        $this->assertTrue($bookmarked['2']);
    }

    /**
     * The same listing seen by a user with no bookmarks at all.
     */
    #[Test]
    public function listing_reports_no_state_for_another_user(): void
    {
        $this->bookmarkPost(1, 2);
        $this->bookmarkPost(2, 2);

        $response = $this->send(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 3])
                ->withQueryParams(['filter' => ['discussion' => 1]])
        );

        foreach ($this->json($response)['data'] as $post) {
            $this->assertFalse($post['attributes']['bookmarked'], "Post {$post['id']} leaked another user's bookmark");
        }
    }

    #[Test]
    public function user_can_bookmark_a_post(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/posts/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['attributes' => ['bookmarked' => true]]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($this->json($response)['data']['attributes']['bookmarked']);

        $this->assertEquals(1, $this->database()->table('post_user_bookmark')
            ->where('post_id', 1)->where('user_id', 2)->count());
    }

    #[Test]
    public function user_can_remove_a_bookmark(): void
    {
        $this->bookmarkPost(1, 2);

        $response = $this->send(
            $this->request('PATCH', '/api/posts/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['attributes' => ['bookmarked' => false]]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($this->json($response)['data']['attributes']['bookmarked']);

        $this->assertEquals(0, $this->database()->table('post_user_bookmark')
            ->where('post_id', 1)->where('user_id', 2)->count());
    }

    /**
     * Bookmarking twice must not fail on the pivot's composite primary key.
     */
    #[Test]
    public function bookmarking_twice_is_idempotent(): void
    {
        $this->bookmarkPost(1, 2);

        $response = $this->send(
            $this->request('PATCH', '/api/posts/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['attributes' => ['bookmarked' => true]]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, $this->database()->table('post_user_bookmark')
            ->where('post_id', 1)->where('user_id', 2)->count());
    }

    #[Test]
    public function removing_a_bookmark_that_does_not_exist_is_harmless(): void
    {
        $response = $this->send(
            $this->request('PATCH', '/api/posts/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['attributes' => ['bookmarked' => false]]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertFalse($this->json($response)['data']['attributes']['bookmarked']);
    }

    /**
     * Removing a bookmark must only affect the actor's own row.
     */
    #[Test]
    public function removing_a_bookmark_leaves_other_users_alone(): void
    {
        $this->bookmarkPost(1, 2);
        $this->bookmarkPost(1, 3);

        $this->send(
            $this->request('PATCH', '/api/posts/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['attributes' => ['bookmarked' => false]]],
            ])
        );

        $this->assertEquals(0, $this->database()->table('post_user_bookmark')
            ->where('post_id', 1)->where('user_id', 2)->count());
        $this->assertEquals(1, $this->database()->table('post_user_bookmark')
            ->where('post_id', 1)->where('user_id', 3)->count());
    }

    /**
     * The relationship writes no timestamp of its own: `created_at` is `NOT NULL` and
     * filled by the database default, as core's `post_likes` table does. If that default
     * were ever lost the insert would fail outright.
     */
    #[Test]
    public function bookmarking_records_when_the_bookmark_was_made(): void
    {
        $this->send(
            $this->request('PATCH', '/api/posts/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['attributes' => ['bookmarked' => true]]],
            ])
        );

        $row = $this->database()->table('post_user_bookmark')
            ->where('post_id', 1)->where('user_id', 2)->first();

        $this->assertNotNull($row->created_at);
        $this->assertNotSame('0000-00-00 00:00:00', $row->created_at);
    }

    #[Test]
    public function guest_cannot_bookmark(): void
    {
        $response = $this->send(
            $this->requestWithCsrfToken(
                $this->request('PATCH', '/api/posts/1', [
                    'json' => ['data' => ['attributes' => ['bookmarked' => true]]],
                ])
            )
        );

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('post_user_bookmark')->count());
    }

    /**
     * A request that does not mention `bookmarked` must leave an existing bookmark alone.
     */
    #[Test]
    public function unrelated_update_does_not_clear_the_bookmark(): void
    {
        $this->bookmarkPost(1, 2);

        $this->send(
            $this->request('PATCH', '/api/posts/1', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['attributes' => ['content' => 'Edited content.']]],
            ])
        );

        $this->assertEquals(1, $this->database()->table('post_user_bookmark')
            ->where('post_id', 1)->where('user_id', 2)->count());
    }

    /**
     * Deleting a post must take its bookmarks with it, via the pivot's cascade.
     */
    #[Test]
    public function deleting_a_post_removes_its_bookmarks(): void
    {
        $this->bookmarkPost(2, 2);

        $response = $this->send(
            $this->request('DELETE', '/api/posts/2', ['authenticatedAs' => 1])
        );

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals(0, $this->database()->table('post_user_bookmark')
            ->where('post_id', 2)->count());
    }
}
