<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks\Search\Filter;

use Flarum\Search\SearchState;
use Illuminate\Database\Query\Builder;
use Flarum\Search\Filter\FilterInterface;

class BookmarkedFilter implements FilterInterface
{

    public function filter(SearchState $state, array|string $value, bool $negate): void
    {
        $actor = $state->getActor();

        $method = $negate ? 'whereNotIn' : 'whereIn';

        $state->getQuery()->$method('discussions.id', function (Builder $query) use ($actor) {
            $query->select('discussion_id')
                ->from('discussion_user')
                ->where('user_id', $actor->id)
                ->whereNotNull('bookmarked_at');
        });
    }
    public function getFilterKey(): string
    {
        return 'bookmarked';
    }
}
