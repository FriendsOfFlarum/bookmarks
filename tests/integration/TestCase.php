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

use Carbon\Carbon;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase as BaseTestCase;

/**
 * Shared fixtures for the bookmark tests.
 *
 * User 1 is the admin provided by the installer, user 2 is `normalUser()`, and user 3
 * exists so that "one user's bookmark is not another user's" can be asserted.
 *
 * Two discussions each hold two comment posts.
 */
abstract class TestCase extends BaseTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-bookmarks');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                [
                    'id' => 3,
                    'username' => 'other',
                    'password' => '$2y$10$LO59tiT7uggl6Oe23o/O6.utnF6ipngYjvMvaxo1TciKqBttDNKim', // "too-obscure"
                    'email' => 'other@machine.local',
                    'is_email_confirmed' => 1,
                ],
            ],
            'discussions' => [
                ['id' => 1, 'title' => 'First discussion', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 1, 'last_post_id' => 2, 'last_post_number' => 2, 'comment_count' => 2],
                ['id' => 2, 'title' => 'Second discussion', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 3, 'last_post_id' => 4, 'last_post_number' => 2, 'comment_count' => 2],
            ],
            'posts' => [
                ['id' => 1, 'number' => 1, 'discussion_id' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>First discussion, first post.</p></t>'],
                ['id' => 2, 'number' => 2, 'discussion_id' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>First discussion, second post.</p></t>'],
                ['id' => 3, 'number' => 1, 'discussion_id' => 2, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Second discussion, first post.</p></t>'],
                ['id' => 4, 'number' => 2, 'discussion_id' => 2, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Second discussion, second post.</p></t>'],
            ],
        ]);
    }

    /**
     * Bookmark a discussion for a user, writing the row exactly as the extensions this
     * one replaces did: a timestamp on core's `discussion_user` state table.
     */
    protected function bookmarkDiscussion(int $discussionId, int $userId, ?string $at = '2026-02-01 12:00:00'): void
    {
        $this->database()->table('discussion_user')->insert([
            'discussion_id' => $discussionId,
            'user_id' => $userId,
            'bookmarked_at' => $at,
        ]);
    }

    /**
     * Bookmark a post for a user, writing the row exactly as the extension this one
     * replaces did.
     */
    protected function bookmarkPost(int $postId, int $userId, string $at = '2026-02-01 12:00:00'): void
    {
        $this->database()->table('post_user_bookmark')->insert([
            'post_id' => $postId,
            'user_id' => $userId,
            'created_at' => $at,
        ]);
    }

    /**
     * Decode a JSON:API response body.
     *
     * The body is a stream, so it is rewound first: without that, a second call on the
     * same response reads from the already-consumed end and yields nothing.
     *
     * @return array<string, mixed>
     */
    protected function json(\Psr\Http\Message\ResponseInterface $response): array
    {
        $body = $response->getBody();
        $body->rewind();

        return json_decode($body->getContents(), true);
    }
}
