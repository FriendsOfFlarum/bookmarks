<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks\Tests\unit\Search\Filter;

use Flarum\Testing\unit\TestCase;
use FoF\Bookmarks\Search\Filter\DiscussionBookmarkedFilter;
use FoF\Bookmarks\Search\Filter\PostBookmarkedFilter;
use PHPUnit\Framework\Attributes\Test;

class BookmarkedFilterTest extends TestCase
{
    /**
     * The key is what the frontend sends as `filter[bookmarked]`, so it is part of the
     * extension's public API.
     *
     * Both searchers deliberately expose the same key: the frontend gambit is registered
     * for the `discussions` and `posts` model types with a single class, which resolves to
     * one filter key on each.
     */
    #[Test]
    public function both_filters_use_the_bookmarked_key(): void
    {
        $this->assertSame('bookmarked', (new DiscussionBookmarkedFilter())->getFilterKey());
        $this->assertSame('bookmarked', (new PostBookmarkedFilter())->getFilterKey());
    }
}
