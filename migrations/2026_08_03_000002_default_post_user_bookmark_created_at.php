<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Builder;

/*
 * Brings a pre-existing `post_user_bookmark` table in line with the one this extension
 * creates.
 *
 * clarkwinkelmann/flarum-ext-post-bookmarks created the table with Laravel's
 * `timestamps()` helper, giving two `NOT NULL` columns with no default. This extension
 * inserts through a relationship that writes neither, relying on
 * `DEFAULT CURRENT_TIMESTAMP` instead, so `created_at` needs a default and the unused
 * `updated_at` has to become nullable.
 *
 * Raw statements are used because changing a column with Laravel 8's schema builder needs
 * doctrine/dbal to reflect the whole table, which fails on the enum columns elsewhere in
 * a typical Flarum database.
 */
return [
    'up' => function (Builder $schema) {
        if (!$schema->hasTable('post_user_bookmark')) {
            return;
        }

        $connection = $schema->getConnection();
        $table = $connection->getTablePrefix().'post_user_bookmark';

        $connection->statement(
            "ALTER TABLE `$table` MODIFY `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP"
        );

        if ($schema->hasColumn('post_user_bookmark', 'updated_at')) {
            $connection->statement(
                "ALTER TABLE `$table` MODIFY `updated_at` TIMESTAMP NULL DEFAULT NULL"
            );
        }
    },
    'down' => function (Builder $schema) {
        /*
         * Intentionally a no-op: reverting the column definitions would make inserts fail
         * again, and the extension may be re-enabled.
         */
    },
];
