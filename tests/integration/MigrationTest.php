<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks\Tests\integration;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase as BaseTestCase;

/**
 * The schema this extension expects, and the upgrade path from the extensions it
 * replaces.
 *
 * Both migrations are written to be safe on a forum that already has bookmark data from
 * clarkwinkelmann/flarum-ext-bookmarks, -discussion-bookmarks or -post-bookmarks, since
 * `fof/bookmarks` replaces all three. This suite asserts the schema is what the rest of
 * the extension relies on, and that re-running the migrations never destroys data.
 */
class MigrationTest extends BaseTestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-bookmarks');
    }

    /**
     * @test
     */
    public function discussion_user_has_a_nullable_bookmarked_at_column(): void
    {
        $schema = $this->database()->getSchemaBuilder();

        $this->assertTrue($schema->hasColumn('discussion_user', 'bookmarked_at'));

        $column = $this->database()
            ->getDoctrineSchemaManager()
            ->listTableDetails('discussion_user')
            ->getColumn('bookmarked_at');

        $this->assertFalse($column->getNotnull(), 'bookmarked_at must be nullable');
    }

    /**
     * The gambit filters on this column for every bookmarks page view, so the index
     * matters.
     *
     * @test
     */
    public function bookmarked_at_is_indexed(): void
    {
        $indexes = $this->database()
            ->getDoctrineSchemaManager()
            ->listTableIndexes('discussion_user');

        $indexed = false;

        foreach ($indexes as $index) {
            if (in_array('bookmarked_at', $index->getColumns(), true)) {
                $indexed = true;
            }
        }

        $this->assertTrue($indexed, 'bookmarked_at should be indexed');
    }

    /**
     * @test
     */
    public function post_user_bookmark_table_has_the_expected_shape(): void
    {
        $schema = $this->database()->getSchemaBuilder();

        $this->assertTrue($schema->hasTable('post_user_bookmark'));

        foreach (['post_id', 'user_id', 'created_at'] as $column) {
            $this->assertTrue(
                $schema->hasColumn('post_user_bookmark', $column),
                "post_user_bookmark is missing $column"
            );
        }
    }

    /**
     * `created_at` defaults to the current timestamp, following core's `post_likes`
     * table. That is what lets the relationship be declared with the typed
     * `belongsToMany()` extender instead of a closure calling `withTimestamps()`, so the
     * default is load-bearing rather than a convenience.
     *
     * @test
     */
    public function post_user_bookmark_created_at_defaults_to_the_current_timestamp(): void
    {
        $column = $this->database()
            ->getDoctrineSchemaManager()
            ->listTableDetails('post_user_bookmark')
            ->getColumn('created_at');

        $this->assertEqualsIgnoringCase('CURRENT_TIMESTAMP', (string) $column->getDefault());
    }

    /**
     * The pivot is keyed on both columns, which is what makes a repeated bookmark a
     * no-op rather than a duplicate row.
     *
     * @test
     */
    public function post_user_bookmark_is_keyed_on_both_columns(): void
    {
        $primary = $this->database()
            ->getDoctrineSchemaManager()
            ->listTableDetails('post_user_bookmark')
            ->getPrimaryKey();

        $this->assertNotNull($primary);
        $this->assertEqualsCanonicalizing(['post_id', 'user_id'], $primary->getColumns());
    }

    /**
     * Deleting a post or user must take the pivot rows with it rather than leaving
     * orphans behind.
     *
     * @test
     */
    public function post_user_bookmark_cascades_on_delete(): void
    {
        $foreignKeys = $this->database()
            ->getDoctrineSchemaManager()
            ->listTableForeignKeys('post_user_bookmark');

        $this->assertCount(2, $foreignKeys);

        foreach ($foreignKeys as $foreignKey) {
            $this->assertEquals(
                'CASCADE',
                strtoupper((string) $foreignKey->onDelete()),
                'Expected ON DELETE CASCADE for '.implode(',', $foreignKey->getLocalColumns())
            );
        }
    }

    /**
     * Disabling the extension must not drop the column, because it may hold bookmarks
     * created by one of the extensions this one replaces. Both `down` handlers are
     * deliberately no-ops.
     *
     * @test
     */
    public function rolling_back_preserves_existing_data(): void
    {
        $this->database()->table('users')->insert($this->normalUser());
        $this->database()->table('discussions')->insert([
            'id' => 1, 'title' => 'Kept', 'created_at' => '2026-01-01 00:00:00', 'user_id' => 2, 'comment_count' => 0,
        ]);
        $this->database()->table('posts')->insert([
            'id' => 1, 'number' => 1, 'discussion_id' => 1, 'created_at' => '2026-01-01 00:00:00',
            'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Kept.</p></t>',
        ]);

        $this->database()->table('discussion_user')->insert([
            'discussion_id' => 1, 'user_id' => 2, 'bookmarked_at' => '2026-02-01 12:00:00',
        ]);
        $this->database()->table('post_user_bookmark')->insert([
            'post_id' => 1, 'user_id' => 2, 'created_at' => '2026-02-01 12:00:00',
        ]);

        $migrator = $this->app()->getContainer()->make(\Flarum\Database\Migrator::class);
        $extension = $this->app()->getContainer()->make(\Flarum\Extension\ExtensionManager::class)
            ->getExtension('fof-bookmarks');

        $migrator->reset($extension->getPath().'/migrations', $extension);

        // The column and table survive, and so do the rows in them.
        $schema = $this->database()->getSchemaBuilder();

        $this->assertTrue(
            $schema->hasColumn('discussion_user', 'bookmarked_at'),
            'Rolling back must not drop bookmarked_at: it may hold data from a replaced extension'
        );
        $this->assertTrue(
            $schema->hasTable('post_user_bookmark'),
            'Rolling back must not drop post_user_bookmark: it may hold data from a replaced extension'
        );

        $this->assertNotNull(
            $this->database()->table('discussion_user')
                ->where('discussion_id', 1)->where('user_id', 2)->value('bookmarked_at')
        );
        $this->assertEquals(
            1,
            $this->database()->table('post_user_bookmark')->where('post_id', 1)->count()
        );
    }
}
