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

use Flarum\Api\Context;
use Flarum\Api\Endpoint\Endpoint;
use Flarum\Api\Resource;
use Flarum\Extend;
use FoF\Bookmarks\Tests\integration\TestCase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;

/**
 * The post `bookmarked` attribute reads an eager-loaded relationship, so that a listing
 * of N posts costs one query rather than N.
 *
 * These tests assert the relationship is actually loaded before serialization. If it
 * ever stops being loaded the attribute still returns the right answer, but silently
 * does so one query at a time, which is the regression worth catching.
 */
class EagerLoadingTest extends TestCase
{
    /**
     * Capture the models an endpoint is about to serialize.
     *
     * `Extend\ApiController::prepareDataForSerialization()` is gone in Flarum 2.0; the
     * equivalent is an endpoint `after` hook, which receives the data on its way to the
     * serializer and must hand it back.
     *
     * The captured value is returned by reference so a test can assert on it after the
     * request has been sent.
     *
     * @param class-string<\Flarum\Api\Resource\AbstractResource> $resource
     * @param string|string[] $endpoints
     */
    private function captureSerializedData(string $resource, string|array $endpoints, mixed &$captured): void
    {
        // Extenders must be registered before anything boots the application, and touching
        // the database boots it, so this has to be called before any fixture write.
        $this->extend(
            (new Extend\ApiResource($resource))
                ->endpoint($endpoints, function (Endpoint $endpoint) use (&$captured): Endpoint {
                    return $endpoint->after(function (Context $context, mixed $data) use (&$captured): mixed {
                        $captured = $data;

                        return $data;
                    });
                })
        );
    }

    #[Test]
    public function post_listing_eager_loads_bookmark_state(): void
    {
        /** @var Collection|null $posts */
        $posts = null;

        $this->captureSerializedData(Resource\PostResource::class, 'index', $posts);

        $this->bookmarkPost(1, 2);

        $this->send(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['discussion' => 1]])
        );

        $this->assertNotNull($posts, 'Serialization callback did not run');
        $this->assertGreaterThan(0, $posts->count());

        foreach ($posts as $post) {
            $this->assertTrue(
                $post->relationLoaded('bookmarkState'),
                "bookmarkState was not eager-loaded for post {$post->id}"
            );
        }
    }

    /**
     * The relationship must be loaded even when the client does not ask for it via
     * `include`, because the serializer reads it unconditionally.
     *
     */
    #[Test]
    public function bookmark_state_is_loaded_without_an_explicit_include(): void
    {
        /** @var Collection|null $posts */
        $posts = null;

        $this->captureSerializedData(Resource\PostResource::class, 'index', $posts);

        $this->send(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 2])
                ->withQueryParams(['include' => 'user'])
        );

        $this->assertNotNull($posts, 'Serialization callback did not run');

        foreach ($posts as $post) {
            $this->assertTrue($post->relationLoaded('bookmarkState'));
        }
    }

    /**
     * A discussion page serializes its posts through the same attribute, so the nested
     * relationship has to be loaded there too.
     *
     */
    #[Test]
    public function discussion_page_eager_loads_bookmark_state_on_posts(): void
    {
        $discussion = null;

        $this->captureSerializedData(Resource\DiscussionResource::class, 'show', $discussion);

        $this->bookmarkPost(1, 2);

        $this->send(
            $this->request('GET', '/api/discussions/1', ['authenticatedAs' => 2])
        );

        $this->assertNotNull($discussion, 'Serialization callback did not run');
        $this->assertTrue($discussion->relationLoaded('posts'));

        foreach ($discussion->posts as $post) {
            $this->assertTrue(
                $post->relationLoaded('bookmarkState'),
                "bookmarkState was not eager-loaded for post {$post->id}"
            );
        }
    }

    /**
     * The eager-loaded relationship is constrained to the actor, so a bookmark belonging
     * to someone else must not be present on the loaded relation at all.
     *
     */
    #[Test]
    public function eager_loaded_state_is_scoped_to_the_actor(): void
    {
        /** @var Collection|null $posts */
        $posts = null;

        $this->captureSerializedData(Resource\PostResource::class, 'index', $posts);

        $this->bookmarkPost(1, 3);

        $this->send(
            $this->request('GET', '/api/posts', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['discussion' => 1]])
        );

        $this->assertNotNull($posts, 'Serialization callback did not run');

        foreach ($posts as $post) {
            $this->assertNull(
                $post->bookmarkState,
                "Post {$post->id} exposed another user's bookmark state"
            );
        }
    }
}
