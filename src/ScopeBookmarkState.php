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

use Flarum\Http\RequestUtil;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Constrains an eager-loaded `bookmarkState` relationship to the requesting actor.
 *
 * A post's bookmark state is per-user, but the relationship itself is defined on the
 * post. `Extend\ApiController::loadWhere()` hands its callback the request, which is
 * what allows the constraint to be applied at eager-load time — one query for the
 * whole page, and no global state to hold the actor.
 */
class ScopeBookmarkState
{
    /**
     * @param Relation|Builder $query
     */
    public function __invoke($query, ?ServerRequestInterface $request = null): void
    {
        $actor = $request ? RequestUtil::getActor($request) : null;

        // Guests never have bookmarks. Matching on a null user_id keeps the relationship
        // empty for them rather than leaking whichever rows happen to exist.
        $query->where('user_id', $actor && $actor->exists ? $actor->id : null);
    }
}
