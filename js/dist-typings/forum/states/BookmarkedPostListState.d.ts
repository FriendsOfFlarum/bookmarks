import type Post from 'flarum/common/models/Post';
import PaginatedListState, { type PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';
/**
 * Paginated list of the actor's bookmarked posts, newest first.
 *
 * Only comment posts are requested: the bookmark button is only ever attached to
 * `CommentPost`, and event posts (renames, locks) have no content worth listing.
 */
export default class BookmarkedPostListState extends PaginatedListState<Post> {
    constructor();
    get type(): string;
    protected requestParams(): PaginatedListRequestParams;
    /**
     * `getAllItems` is protected on the base class, so it is republished here for the page
     * that renders this list.
     */
    items(): Post[];
}
