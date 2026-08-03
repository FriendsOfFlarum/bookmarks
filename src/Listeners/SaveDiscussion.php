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

use Carbon\Carbon;
use Flarum\Discussion\Event\Saving;

class SaveDiscussion
{
    public function handle(Saving $event): void
    {
        if (! isset($event->data['attributes']['bookmarked'])) {
            return;
        }

        $event->actor->assertRegistered();

        $state = $event->discussion->stateFor($event->actor);

        $state->bookmarked_at = $event->data['attributes']['bookmarked'] ? Carbon::now() : null;
        $state->save();
    }
}
