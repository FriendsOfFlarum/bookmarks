import type { PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';
import PostListState from 'flarum/forum/states/PostListState';
/**
 * Paginated list of the actor's bookmarked posts, newest first.
 *
 * Core's `PostListState` already provides everything but the `include` handling below: it
 * defaults to `filter.type: 'comment'` — the bookmark button is only ever attached to
 * `CommentPost`, and event posts (renames, locks) have no content worth listing — and to
 * `sort: '-createdAt'`.
 */
export default class BookmarkedPostListState extends PostListState {
    constructor();
    requestParams(): PaginatedListRequestParams;
}
