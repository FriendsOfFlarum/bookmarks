<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks\Search\Gambit;

use Flarum\Search\AbstractRegexGambit;
use Flarum\Search\SearchState;
use Illuminate\Database\Query\Builder;

class BookmarkedGambit extends AbstractRegexGambit
{
    protected function getGambitPattern(): string
    {
        return 'is:bookmarked';
    }

    protected function conditions(SearchState $search, array $matches, $negate)
    {
        $actor = $search->getActor();

        $method = $negate ? 'whereNotIn' : 'whereIn';

        $search->getQuery()->$method('discussions.id', function (Builder $query) use ($actor) {
            $query->select('discussion_id')
                ->from('discussion_user')
                ->where('user_id', $actor->id)
                ->whereNotNull('bookmarked_at');
        });
    }
}
