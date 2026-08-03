<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks\Listeners;

use Flarum\Post\Event\Saving;

class SavePost
{
    public function handle(Saving $event): void
    {
        if (!isset($event->data['attributes']['bookmarked'])) {
            return;
        }

        $event->actor->assertRegistered();

        $bookmarks = $event->post->bookmarks();

        if ($event->data['attributes']['bookmarked']) {
            // `syncWithoutDetaching` is idempotent, so bookmarking an already-bookmarked
            // post is a no-op rather than a duplicate key error.
            $bookmarks->syncWithoutDetaching([$event->actor->id]);
        } else {
            $bookmarks->detach($event->actor->id);
        }
    }
}
