# FriendsOfFlarum Bookmarks

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/bookmarks.svg)](https://packagist.org/packages/fof/bookmarks) [![Total Downloads](https://img.shields.io/packagist/dt/fof/bookmarks.svg)](https://packagist.org/packages/fof/bookmarks) [![OpenCollective](https://img.shields.io/badge/opencollective-fof-blue.svg)](https://opencollective.com/fof/donate)

A [Flarum](https://flarum.org) extension that lets users bookmark discussions and posts, and find them again from a single bookmarks page.

---

## What it does

Bookmarks are private to each user, and work rather like subscriptions but without any notifications attached.

### Discussions

- A bookmark control on every discussion, either in the discussion sidebar or in its dropdown menu.
- A **Bookmarked** badge on bookmarked discussions in the discussion list.
- An `is:bookmarked` search gambit, so bookmarks can be combined with any other search — `is:bookmarked tag:support`, for instance.

### Posts

- A bookmark control on every comment, positioned above the post, below it with the quick actions, or in the post's menu.
- An optional **Bookmarked** label above bookmarked posts, for when the control is not in the post header.

### The bookmarks page

A single `/bookmarks` page with a tab for each type. Bookmarked discussions reuse Flarum's own discussion list, so sorting and filtering behave exactly as they do on the index; bookmarked posts are listed newest first, each linked back to the discussion it belongs to.

Deep links work, so `/bookmarks?tab=posts` opens the posts tab directly.

### fof/blog

When [fof/blog](https://github.com/FriendsOfFlarum/blog) is installed, article pages get a bookmark control too. Blog articles are discussions, so they appear under the discussions tab alongside everything else.

---

## Installation

```sh
composer require fof/bookmarks:"*"
```

## Updating

```sh
composer update fof/bookmarks:"*"
php flarum migrate
php flarum cache:clear
```

---

## Migrating from the ClarkWinkelmann extensions

This extension replaces three packages, and **existing bookmarks are preserved** when you switch:

| Replaced package | What it stored |
| --- | --- |
| `clarkwinkelmann/flarum-ext-bookmarks` | discussion bookmarks (v1 of the below) |
| `clarkwinkelmann/flarum-ext-discussion-bookmarks` | discussion bookmarks |
| `clarkwinkelmann/flarum-ext-post-bookmarks` | post bookmarks |

Install as normal:

```sh
composer require fof/bookmarks:"*"
php flarum migrate
php flarum cache:clear
```

Composer removes the old packages automatically, because this one `replace`s them. Enable **FoF Bookmarks** in the admin panel afterwards.

Both storage locations are kept exactly as they were — `discussion_user.bookmarked_at` and the `post_user_bookmark` table — so the migrations detect existing data and leave it untouched. Nothing is copied or moved, and no bookmark is lost.

Two things do not carry over:

- **Settings.** The old extensions used their own setting keys, so reconfigure the three settings below after enabling.
- **URLs.** The old `/bookmarked-discussions` and `/bookmarked-posts` pages are replaced by `/bookmarks`. No redirects are provided.

If you have custom CSS targeting the old extensions, note that class names have changed.

### Uninstalling

Disabling or uninstalling deliberately leaves your bookmark data in place, since `discussion_user.bookmarked_at` may hold data created by an extension that predates this one.

If you genuinely want the data gone, remove it by hand:

```sql
DROP TABLE `post_user_bookmark`;
ALTER TABLE `discussion_user` DROP COLUMN `bookmarked_at`;

DELETE FROM `migrations` WHERE `extension` = 'fof-bookmarks';
```

**All three statements matter.** Flarum records which migrations have run, and because this extension's `down` handlers intentionally do nothing, those records survive an uninstall. Leaving them behind means a later reinstall considers the migrations already applied and never recreates the table or column, so the extension breaks on enable. Deleting the rows — all of them, whatever the current set happens to be — lets the migrations run again from scratch.

This is destructive and irreversible — take a database backup first.

---

## Settings

| Setting | Default | Description |
| --- | --- | --- |
| Show the discussion bookmark control in the sidebar | On | When off, the control moves to the discussion dropdown menu. |
| Post bookmark button position | Above post (header) | Also available below the post with the quick actions, or in the post menu. |
| Label bookmarked posts | Off | Shows a label above bookmarked posts. Only applies when the button is not in the header. |

---

## Credits

This extension combines and continues two extensions by [Clark Winkelmann](https://clarkwinkelmann.com/) — `flarum-ext-discussion-bookmarks` and `flarum-ext-post-bookmarks` — both of which were released as open source and are no longer maintained. FriendsOfFlarum is grateful for that work, on which this extension is based.

---

## Links

[![OpenCollective](https://img.shields.io/badge/donate-friendsofflarum-44AEE5?style=for-the-badge&logo=open-collective)](https://opencollective.com/fof/donate)

- [Packagist](https://packagist.org/packages/fof/bookmarks)
- [GitHub](https://github.com/FriendsOfFlarum/bookmarks)
- [Issues](https://github.com/FriendsOfFlarum/bookmarks/issues)

An extension by [FriendsOfFlarum](https://github.com/FriendsOfFlarum).

## License

This extension is licensed under the MIT License. See the [LICENSE.md](https://github.com/FriendsOfFlarum/bookmarks/blob/1.x/LICENSE.md) file for details.
