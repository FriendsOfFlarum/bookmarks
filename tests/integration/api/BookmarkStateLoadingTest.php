<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use FoF\Bookmarks\Tests\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * What reporting `bookmarked` on posts costs.
 *
 * Bookmarks are looked up for the posts a response serializes, and for all of them
 * at once. A discussion endpoint serializes few posts, or none, so it must not load
 * the posts of every discussion it returns in order to look up bookmarks for them.
 *
 * Discussion 3 is long (40 posts), so that loading every post of a discussion shows
 * up as 40 rows rather than as a rounding error.
 */
class BookmarkStateLoadingTest extends TestCase
{
    private const LONG_DISCUSSION_POSTS = 40;

    protected function setUp(): void
    {
        parent::setUp();

        $posts = [];

        for ($number = 1; $number <= self::LONG_DISCUSSION_POSTS; $number++) {
            $posts[] = ['id' => 100 + $number, 'number' => $number, 'discussion_id' => 3, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Long discussion post.</p></t>'];
        }

        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 3, 'title' => 'Long discussion', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 101, 'last_post_id' => 100 + self::LONG_DISCUSSION_POSTS, 'last_post_number' => self::LONG_DISCUSSION_POSTS, 'comment_count' => self::LONG_DISCUSSION_POSTS],
            ],
            Post::class => $posts,
        ]);
    }

    /**
     * How many post rows the given queries fetched whole, to be built into models.
     *
     * Flarum builds typed posts in `Post::newFromBuilder()`, which fires no `retrieved`
     * event, so they cannot be counted as they are built. Instead, every query that
     * selected whole rows from `posts` is run again to count what it returned. Queries
     * that select only IDs, such as a discussion's post ID list, are not counted.
     *
     * @param array<int, array{query: string, bindings: array<mixed>}> $queries
     */
    private function postRowsLoaded(array $queries): int
    {
        $table = preg_quote($this->database()->getTablePrefix().'posts', '/');
        $rows = 0;

        foreach ($queries as $query) {
            if (preg_match('/^select (?:\*|[`"]?'.$table.'[`"]?\.\*)(?:, [^()]*?)? from [`"]?'.$table.'[`"]?(?:\s|$)/i', $query['query'])) {
                $rows += count($this->database()->select($query['query'], $query['bindings']));
            }
        }

        return $rows;
    }

    /**
     * The discussion list serializes no posts unless it is asked to include some, so
     * it has no posts to load: neither the posts of the discussions it lists nor their
     * bookmarks.
     */
    #[Test]
    public function discussion_list_loads_no_posts_it_does_not_serialize(): void
    {
        $this->bookmarkPost(101, 2);

        [$response, $queries] = $this->sendLoggingQueries(
            $this->request('GET', '/api/discussions', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([], $this->postBookmarks($response), 'The discussion list was not expected to serialize any post.');

        $this->assertSame(0, $this->postRowsLoaded($queries), 'The discussion list loaded posts it does not serialize.');
        $this->assertSame(0, $this->bookmarkQueries($queries), 'The discussion list looked up bookmarks without serializing a post.');
    }

    /**
     * A discussion page serializes the discussion's first and last post. However long
     * the discussion, those are the only posts it should load, and showing it should
     * cost the same queries as showing a short one.
     */
    #[Test]
    public function discussion_page_loads_only_the_posts_it_serializes(): void
    {
        $this->bookmarkPost(101, 2);

        // The first request in a test also warms what later ones reuse, such as the
        // settings, so it is spent on another discussion rather than measured.
        $this->send($this->request('GET', '/api/discussions/2', ['authenticatedAs' => 2]));

        [, $short] = $this->sendLoggingQueries(
            $this->request('GET', '/api/discussions/1', ['authenticatedAs' => 2])
        );

        [$response, $long] = $this->sendLoggingQueries(
            $this->request('GET', '/api/discussions/3', ['authenticatedAs' => 2])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([101, 140], array_keys($this->postBookmarks($response)), 'The discussion page was expected to serialize its first and last post.');

        $this->assertSame(2, $this->postRowsLoaded($long), 'The discussion page loaded posts it does not serialize.');
        $this->assertCount(count($short), $long, 'Showing a 40-post discussion should cost the same queries as showing a 2-post one.');
    }

    /**
     * The discussion page saves the reader's position through the discussion update
     * endpoint as they scroll. Its response serializes no posts, so it has none to load.
     */
    #[Test]
    public function saving_the_reading_position_loads_no_posts(): void
    {
        [$response, $queries] = $this->sendLoggingQueries(
            $this->request('PATCH', '/api/discussions/3', [
                'authenticatedAs' => 2,
                'json' => ['data' => ['attributes' => ['lastReadPostNumber' => self::LONG_DISCUSSION_POSTS]]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([], $this->postBookmarks($response), 'Saving the reading position was not expected to serialize any post.');

        $this->assertSame(0, $this->postRowsLoaded($queries), 'Saving the reading position loaded posts it does not serialize.');
    }

    /**
     * The first and last posts of every listed discussion have their bookmarks looked
     * up together, in one query, and no other post is loaded to do it.
     */
    #[Test]
    public function included_posts_have_their_bookmarks_looked_up_in_one_query(): void
    {
        $this->bookmarkPost(1, 2);
        $this->bookmarkPost(4, 2);
        $this->bookmarkPost(140, 2);

        [$response, $queries] = $this->sendLoggingQueries(
            $this->request('GET', '/api/discussions', ['authenticatedAs' => 2])
                ->withQueryParams(['include' => 'firstPost,lastPost'])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertSame(6, $this->postRowsLoaded($queries), 'Only the first and last posts of the three discussions should be loaded.');
        $this->assertSame(1, $this->bookmarkQueries($queries), 'The bookmarks of all six posts should be looked up in one query.');
        $this->assertSame([1 => true, 2 => false, 3 => false, 4 => true, 101 => false, 140 => true], $this->postBookmarks($response));
    }

    /**
     * A guest has no bookmarks, so a guest's request has none to look up.
     */
    #[Test]
    public function guest_requests_look_up_no_bookmarks(): void
    {
        $this->bookmarkPost(1, 2);

        [$response, $queries] = $this->sendLoggingQueries(
            $this->request('GET', '/api/posts')
                ->withQueryParams(['filter' => ['discussion' => 1]])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertSame([1 => false, 2 => false], $this->postBookmarks($response));

        $this->assertSame(0, $this->bookmarkQueries($queries), 'A guest request looked up bookmarks.');
    }
}
