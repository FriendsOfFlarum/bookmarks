<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks\Filter;

use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Illuminate\Database\Query\Builder;

class BookmarkedFilter implements FilterInterface
{
    public function getFilterKey(): string
    {
        return 'bookmarked';
    }

    public function filter(SearchState $state, array|string $value, bool $negate): void
    {
        $actor = $state->getActor();

        $state->getQuery()->whereIn('id', function (Builder $query) use ($actor) {
            $query->select('post_id')
                ->from('post_user_bookmark')
                ->where('user_id', $actor->id);
        }, 'and', $negate);
    }
}
