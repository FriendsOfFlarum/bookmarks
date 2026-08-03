import type Post from 'flarum/common/models/Post';
import PaginatedListState, { type PaginatedListRequestParams } from 'flarum/common/states/PaginatedListState';

/**
 * Paginated list of the actor's bookmarked posts, newest first.
 *
 * Only comment posts are requested: the bookmark button is only ever attached to
 * `CommentPost`, and event posts (renames, locks) have no content worth listing.
 */
export default class BookmarkedPostListState extends PaginatedListState<Post> {
  constructor() {
    super({}, 1, 20);
  }

  get type(): string {
    return 'posts';
  }

  protected requestParams(): PaginatedListRequestParams {
    return {
      // Core types filter values as strings, and they are serialized into the query
      // string either way, so the flag is sent as '1' rather than a boolean.
      filter: {
        bookmarked: '1',
        type: 'comment',
      },
      sort: '-createdAt',
      // No `include` is sent on purpose.
      //
      // `AbstractSerializeController::extractInclude()` resolves to the client's list
      // *or* the controller's default — never both. Naming relationships here would
      // therefore replace ListPostsController's defaults (`user`, `user.groups`,
      // `editedUser`, `hiddenUser`, `discussion`) along with every relationship other
      // extensions register through `addInclude()`, such as fof/geoip's `ip_info`.
      // Those extensions then render against a relationship that was never loaded.
    };
  }

  /**
   * `getAllItems` is protected on the base class, so it is republished here for the page
   * that renders this list.
   */
  public items(): Post[] {
    return this.getAllItems();
  }
}
