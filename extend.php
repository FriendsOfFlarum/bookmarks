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

use Flarum\Api\Controller;
use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Api\Serializer\PostSerializer;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\Event\Saving as DiscussionSaving;
use Flarum\Discussion\Search\DiscussionSearcher;
use Flarum\Discussion\UserState;
use Flarum\Extend;
use Flarum\Post\Event\Saving as PostSaving;
use Flarum\Post\Filter\PostFilterer;
use Flarum\Post\Post;
use Flarum\User\User;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Api\Schema;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->route('/bookmarks', 'fof-bookmarks'),
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    // Discussion bookmarks are stored as a timestamp on core's `discussion_user`
    // state table, so they ride along with the state relationship core already loads.
    // Core's UserState only declares `last_read_at` as a date, so the column has to be
    // cast here to come back as a Carbon instance rather than a raw string.
    (new Extend\Model(UserState::class))
        ->cast('bookmarked_at', 'datetime'),

    // @TODO: Replace with the new implementation https://docs.flarum.org/2.x/extend/api#extending-api-resources
    (new Extend\ApiSerializer(DiscussionSerializer::class))
        ->attribute('bookmarked', function (DiscussionSerializer $serializer, Discussion $discussion): bool {
            return $discussion->state ? !is_null($discussion->state->bookmarked_at) : false;
        }),

    (new Extend\Event())
        ->listen(DiscussionSaving::class, Listeners\SaveDiscussion::class)
        ->listen(PostSaving::class, Listeners\SavePost::class),

    (new Extend\SimpleFlarumSearch(DiscussionSearcher::class))
        ->addGambit(Search\Gambit\BookmarkedGambit::class),

    // Post bookmarks have no core-provided state row, so they use their own pivot
    // table plus an actor-scoped relationship to expose the current state.
    (new Extend\Model(Post::class))
        ->belongsToMany('bookmarks', User::class, 'post_user_bookmark', 'post_id', 'user_id')
        ->hasOne('bookmarkState', BookmarkState::class, 'post_id'),

    // @TODO: Replace with the new implementation https://docs.flarum.org/2.x/extend/api#extending-api-resources
    (new Extend\ApiSerializer(PostSerializer::class))
        ->attribute('bookmarked', function (PostSerializer $serializer, Post $post): bool {
            // `bookmarkState` is eager-loaded and scoped to the actor by every
            // post-serializing controller below, so this is a relation read rather than a
            // query. The relation is null when the actor has not bookmarked the post.
            //
            // `getRelation()` is used in preference to the magic property because
            // flarum/phpstan types hasOne relations added by `Extend\Model` as
            // non-nullable, which makes a null check on the property look redundant.
            return $post->relationLoaded('bookmarkState') && $post->getRelation('bookmarkState') !== null;
        }),

    // @TODO: Replace with the new implementation https://docs.flarum.org/2.x/extend/api#extending-api-resources
    (new Extend\ApiController(Controller\ShowDiscussionController::class))
        ->load('posts.bookmarkState')
        ->loadWhere('posts.bookmarkState', new ScopeBookmarkState()),
    // @TODO: Replace with the new implementation https://docs.flarum.org/2.x/extend/api#extending-api-resources
    (new Extend\ApiController(Controller\ListPostsController::class))
        ->load('bookmarkState')
        ->loadWhere('bookmarkState', new ScopeBookmarkState()),
    // @TODO: Replace with the new implementation https://docs.flarum.org/2.x/extend/api#extending-api-resources
    (new Extend\ApiController(Controller\ShowPostController::class))
        ->load('bookmarkState')
        ->loadWhere('bookmarkState', new ScopeBookmarkState()),
    // @TODO: Replace with the new implementation https://docs.flarum.org/2.x/extend/api#extending-api-resources
    (new Extend\ApiController(Controller\CreatePostController::class))
        ->load('bookmarkState')
        ->loadWhere('bookmarkState', new ScopeBookmarkState()),
    // @TODO: Replace with the new implementation https://docs.flarum.org/2.x/extend/api#extending-api-resources
    (new Extend\ApiController(Controller\UpdatePostController::class))
        ->load('bookmarkState')
        ->loadWhere('bookmarkState', new ScopeBookmarkState()),

    (new Extend\Filter(PostFilterer::class))
        ->addFilter(Filter\BookmarkedFilter::class),

    // Defaults are registered so every setting has a usable value in the forum payload
    // before an admin has ever opened the extension page.
    (new Extend\Settings())
        ->default('fof-bookmarks.independentButton', '1')
        ->default('fof-bookmarks.postButtonPosition', 'header')
        ->default('fof-bookmarks.postHeaderBadge', '0')
        ->serializeToForum('fof-bookmarks.independentButton', 'fof-bookmarks.independentButton', 'boolval')
        ->serializeToForum('fof-bookmarks.postButtonPosition', 'fof-bookmarks.postButtonPosition')
        ->serializeToForum('fof-bookmarks.postHeaderBadge', 'fof-bookmarks.postHeaderBadge', 'boolval'),
];
