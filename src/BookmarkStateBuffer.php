<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks;

use Closure;
use Flarum\Api\Context;
use Flarum\Post\Post;
use Flarum\User\User;
use WeakMap;

/**
 * Looks up which of the posts a response serializes the actor has bookmarked,
 * together rather than one post at a time.
 *
 * A post's `bookmarked` field does not look its value up straight away. It adds the
 * post to its response's buffer and returns a closure, which the serializer calls
 * once it has added every resource on the current level of the document. The first
 * of those closures looks up every post in the buffer at once, so a level of the
 * document costs one query at most, however many posts it holds. That covers listed
 * and included posts alike (a discussion's first and last post, the replies
 * flarum/mentions includes, ...), and a post that is not serialized is never looked up.
 *
 * Each buffer belongs to one serializer, so it lives as long as the response that
 * serializer builds. One process can build many responses, for different actors: queue
 * workers do, and flarum/realtime serializes a post once per recipient. No response
 * sees another's bookmarks, or a bookmark looked up before it changed.
 */
class BookmarkStateBuffer
{
    /**
     * @var WeakMap<object, self>|null
     */
    private static ?WeakMap $responses = null;

    /**
     * Posts whose bookmark is yet to be looked up.
     *
     * @var array<int, true>
     */
    private array $pending = [];

    /**
     * Whether the actor has bookmarked each post looked up so far.
     *
     * @var array<int, bool>
     */
    private array $bookmarked = [];

    private function __construct(
        private readonly User $actor
    ) {
    }

    /**
     * The value of a post's `bookmarked` field: whether the actor has bookmarked it, or
     * a closure that tells once the posts serialized alongside it have been added.
     */
    public static function defer(Post $post, Context $context): bool|Closure
    {
        $actor = $context->getActor();
        $postId = $post->getKey();

        // A guest has no bookmarks, and a post that was never saved has none yet.
        if ($actor->isGuest() || $postId === null) {
            return false;
        }

        $postId = (int) $postId;

        // Without a serializer there is no response to share a lookup with.
        if (! $context->serializer) {
            $buffer = new self($actor);
            $buffer->pending[$postId] = true;

            return $buffer->isBookmarked($postId);
        }

        self::$responses ??= new WeakMap();
        $buffer = self::$responses[$context->serializer] ??= new self($actor);

        if (! isset($buffer->bookmarked[$postId])) {
            $buffer->pending[$postId] = true;
        }

        return fn (): bool => $buffer->isBookmarked($postId);
    }

    private function isBookmarked(int $postId): bool
    {
        if (! isset($this->bookmarked[$postId])) {
            $this->load();
        }

        return $this->bookmarked[$postId];
    }

    /**
     * Look up every pending post in one query.
     */
    private function load(): void
    {
        $postIds = array_keys($this->pending);
        $this->pending = [];

        foreach ($postIds as $postId) {
            $this->bookmarked[$postId] = false;
        }

        $bookmarked = BookmarkState::query()
            ->where('user_id', $this->actor->id)
            ->whereIntegerInRaw('post_id', $postIds)
            ->pluck('post_id');

        foreach ($bookmarked as $postId) {
            $this->bookmarked[(int) $postId] = true;
        }
    }
}
