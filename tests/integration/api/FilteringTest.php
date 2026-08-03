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

use FoF\Bookmarks\Tests\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Finding bookmarks: the `is:bookmarked` discussion gambit and the `bookmarked` post
 * filter that back the bookmarks page.
 */
class FilteringTest extends TestCase
{
    /**
     * @return array<string> IDs of the returned resources
     */
    private function ids(\Psr\Http\Message\ResponseInterface $response): array
    {
        return array_map(function (array $resource): string {
            return $resource['id'];
        }, $this->json($response)['data']);
    }

    /*
     * Flarum 2.0 removed gambits from the backend: `is:bookmarked` is now translated into
     * `filter[bookmarked]` by the frontend gambit, so these exercise the filter the API
     * actually exposes.
     */

    #[Test]
    public function bookmarked_filter_returns_only_bookmarked_discussions(): void
    {
        $this->bookmarkDiscussion(2, 2);

        $response = $this->send(
            $this->request('GET', '/api/discussions', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['bookmarked' => true]])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['2'], $this->ids($response));
    }

    #[Test]
    public function bookmarked_discussion_filter_can_be_negated(): void
    {
        $this->bookmarkDiscussion(2, 2);

        $response = $this->send(
            $this->request('GET', '/api/discussions', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['-bookmarked' => true]])
        );

        $this->assertEquals(['1'], $this->ids($response));
    }

    /**
     * A state row exists but was never bookmarked, so the filter must not match it.
     */
    #[Test]
    public function bookmarked_discussion_filter_ignores_null_timestamps(): void
    {
        $this->bookmarkDiscussion(1, 2, null);
        $this->bookmarkDiscussion(2, 2);

        $response = $this->send(
            $this->request('GET', '/api/discussions', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['bookmarked' => true]])
        );

        $this->assertEquals(['2'], $this->ids($response));
    }

    #[Test]
    public function bookmarked_discussion_filter_is_scoped_to_the_actor(): void
    {
        $this->bookmarkDiscussion(1, 2);
        $this->bookmarkDiscussion(2, 3);

        $response = $this->send(
            $this->request('GET', '/api/discussions', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['bookmarked' => true]])
        );

        $this->assertEquals(['1'], $this->ids($response));
    }

    #[Test]
    public function bookmarked_discussion_filter_returns_nothing_for_a_guest(): void
    {
        $this->bookmarkDiscussion(1, 2);

        $response = $this->send(
            $this->request('GET', '/api/discussions')
                ->withQueryParams(['filter' => ['bookmarked' => true]])
        );

        $this->assertEquals([], $this->ids($response));
    }

    #[Test]
    public function bookmarked_filter_returns_only_bookmarked_posts(): void
    {
        $this->bookmarkPost(1, 2);
        $this->bookmarkPost(4, 2);

        $response = $this->send(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['bookmarked' => true], 'sort' => 'createdAt'])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEqualsCanonicalizing(['1', '4'], $this->ids($response));
    }

    #[Test]
    public function bookmarked_post_filter_is_scoped_to_the_actor(): void
    {
        $this->bookmarkPost(1, 2);
        $this->bookmarkPost(4, 3);

        $response = $this->send(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['bookmarked' => true]])
        );

        $this->assertEquals(['1'], $this->ids($response));
    }

    #[Test]
    public function bookmarked_post_filter_can_be_negated(): void
    {
        $this->bookmarkPost(1, 2);

        $response = $this->send(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['-bookmarked' => true]])
        );

        $this->assertNotContains('1', $this->ids($response));
        $this->assertContains('2', $this->ids($response));
    }

    #[Test]
    public function bookmarked_post_filter_returns_nothing_for_a_guest(): void
    {
        $this->bookmarkPost(1, 2);

        $response = $this->send(
            $this->request('GET', '/api/posts')
                ->withQueryParams(['filter' => ['bookmarked' => true]])
        );

        $this->assertEquals([], $this->ids($response));
    }

    /**
     * The frontend sends the flag as the string '1' rather than a boolean, because core
     * types filter values as strings. The backend must treat it the same way.
     */
    #[Test]
    public function bookmarked_post_filter_accepts_a_string_flag(): void
    {
        $this->bookmarkPost(1, 2);

        $response = $this->send(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['bookmarked' => '1', 'type' => 'comment'], 'sort' => '-createdAt'])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['1'], $this->ids($response));
    }

    /**
     * The bookmarks page must not send an `include` parameter.
     *
     * `extractInclude()` resolves to the client's list *or* the controller's default,
     * never both, so naming relationships would drop the ones other extensions register
     * via `addInclude()` — leaving them to render against relationships that were never
     * loaded.
     */
    #[Test]
    public function post_listing_returns_the_controller_default_relationships(): void
    {
        $this->bookmarkPost(1, 2);

        $response = $this->send(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['bookmarked' => '1', 'type' => 'comment'], 'sort' => '-createdAt'])
        );

        $relationships = $this->json($response)['data'][0]['relationships'];

        // Defaults declared by ListPostsController, which an explicit `include` would replace.
        $this->assertArrayHasKey('user', $relationships);
        $this->assertArrayHasKey('discussion', $relationships);
    }
}
