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

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use FoF\Bookmarks\Tests\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * `bookmarked` on posts that a response includes rather than lists: a discussion's
 * first and last post, and the replies to a post (flarum/mentions' `mentionedBy`,
 * which the post endpoints include by default).
 *
 * The frontend keeps a single record per post, so a wrong value on an included copy
 * overwrites the right one from the post stream, and the post's bookmark button and
 * badge then show it as not bookmarked.
 *
 * Discussion 3 holds two posts by user 3, both replies to post 1.
 */
class IncludedPostsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-mentions');

        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 3, 'title' => 'Replies', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 3, 'first_post_id' => 5, 'last_post_id' => 6, 'last_post_number' => 2, 'comment_count' => 2],
            ],
            Post::class => [
                ['id' => 5, 'number' => 1, 'discussion_id' => 3, 'created_at' => Carbon::now(), 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>First reply to post 1.</p></t>'],
                ['id' => 6, 'number' => 2, 'discussion_id' => 3, 'created_at' => Carbon::now(), 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>Second reply to post 1.</p></t>'],
            ],
            'post_mentions_post' => [
                ['post_id' => 5, 'mentions_post_id' => 1],
                ['post_id' => 6, 'mentions_post_id' => 1],
            ],
        ]);
    }

    /**
     * A discussion page includes the discussion's first and last post.
     */
    #[Test]
    public function discussion_page_reports_its_first_and_last_post_as_bookmarked(): void
    {
        $this->bookmarkPost(1, 2);
        $this->bookmarkPost(2, 2);

        $response = $this->send(
            $this->request('GET', '/api/discussions/1', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([1 => true, 2 => true], $this->postBookmarks($response));
    }

    /**
     * The discussion list includes each discussion's first and last post when asked to.
     * Only the actor's own bookmarks are reported.
     */
    #[Test]
    public function discussion_list_reports_its_included_first_and_last_posts_as_bookmarked(): void
    {
        $this->bookmarkPost(1, 2);
        $this->bookmarkPost(4, 2);
        $this->bookmarkPost(3, 3);

        $response = $this->send(
            $this->request('GET', '/api/discussions', ['authenticatedAs' => 2])
                ->withQueryParams(['include' => 'firstPost,lastPost'])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([1 => true, 2 => false, 3 => false, 4 => true, 5 => false, 6 => false], $this->postBookmarks($response));
    }

    /**
     * The post stream includes the replies to each post it lists. A reply posted in
     * another discussion is only ever included.
     */
    #[Test]
    public function post_stream_reports_included_replies_as_bookmarked(): void
    {
        $this->bookmarkPost(6, 2);

        $response = $this->send(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['discussion' => 1], 'page' => ['near' => 1]])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([1 => false, 2 => false, 5 => false, 6 => true], $this->postBookmarks($response));
    }

    /**
     * A single post, as fetched when one is linked to or arrives in real time. Only the
     * actor's own bookmarks are reported.
     */
    #[Test]
    public function single_post_reports_its_included_replies_as_bookmarked(): void
    {
        $this->bookmarkPost(6, 2);
        $this->bookmarkPost(5, 3);

        $response = $this->send(
            $this->request('GET', '/api/posts/1', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([1 => false, 5 => false, 6 => true], $this->postBookmarks($response));
    }

    /**
     * A user's profile lists their posts. The frontend names the `user` and `discussion`
     * includes and sends `withDefaultIncludes` to keep the endpoint's defaults, replies
     * among them (Flarum 2.0.2 and later). `mentionedBy` is named here too, so that the
     * request includes the replies on any 2.x release.
     */
    #[Test]
    public function profile_post_list_reports_included_replies_as_bookmarked(): void
    {
        $this->bookmarkPost(3, 2);
        $this->bookmarkPost(6, 2);

        $response = $this->send(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 2])
                ->withQueryParams([
                    'filter' => ['author' => 'normal', 'type' => 'comment'],
                    'sort' => '-createdAt',
                    'include' => 'user,discussion,mentionedBy',
                    'withDefaultIncludes' => '1',
                ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([1 => false, 2 => false, 3 => true, 4 => false, 5 => false, 6 => true], $this->postBookmarks($response));
    }

    /**
     * Listed and included posts have their bookmarks looked up together, in one query.
     */
    #[Test]
    public function listed_and_included_posts_have_their_bookmarks_looked_up_in_one_query(): void
    {
        $this->bookmarkPost(2, 2);
        $this->bookmarkPost(6, 2);

        [$response, $queries] = $this->sendLoggingQueries(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['discussion' => 1], 'page' => ['near' => 1]])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([1 => false, 2 => true, 5 => false, 6 => true], $this->postBookmarks($response));
        $this->assertSame(1, $this->bookmarkQueries($queries), 'The bookmarks of the listed and included posts should be looked up in one query.');
    }
}
