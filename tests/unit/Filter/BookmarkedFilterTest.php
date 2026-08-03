<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks\Tests\unit\Filter;

use Flarum\Testing\unit\TestCase;
use FoF\Bookmarks\Filter\BookmarkedFilter;
use PHPUnit\Framework\Attributes\Test;

class BookmarkedFilterTest extends TestCase
{
    /**
     * The key is what the frontend sends as `filter[bookmarked]`, so it is part of the
     * extension's public API.
     *
     */
    #[Test]
    public function filter_key_is_bookmarked(): void
    {
        $this->assertSame('bookmarked', (new BookmarkedFilter())->getFilterKey());
    }
}
