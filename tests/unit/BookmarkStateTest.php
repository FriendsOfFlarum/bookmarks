<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks\Tests\unit;

use Flarum\Testing\unit\TestCase;
use FoF\Bookmarks\BookmarkState;

class BookmarkStateTest extends TestCase
{
    /**
     * The model exists purely to eager-load rows of the pivot table, so the table name
     * is the whole of its contract.
     *
     * @test
     */
    public function bookmark_state_uses_the_pivot_table(): void
    {
        $this->assertSame('post_user_bookmark', (new BookmarkState())->getTable());
    }
}
