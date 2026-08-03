import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import LinkButton from 'flarum/common/components/LinkButton';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Page, { type IPageAttrs } from 'flarum/common/components/Page';
import Placeholder from 'flarum/common/components/Placeholder';
import listItems from 'flarum/common/helpers/listItems';
import type Post from 'flarum/common/models/Post';
import ItemList from 'flarum/common/utils/ItemList';
import classList from 'flarum/common/utils/classList';
import extractText from 'flarum/common/utils/extractText';
import app from 'flarum/forum/app';
import CommentPost from 'flarum/forum/components/CommentPost';
import DiscussionList from 'flarum/forum/components/DiscussionList';
import IndexPage from 'flarum/forum/components/IndexPage';
import DiscussionListState from 'flarum/forum/states/DiscussionListState';
import type Mithril from 'mithril';
import BookmarkedPostListState from '../states/BookmarkedPostListState';

export type BookmarksTab = 'discussions' | 'posts';

const TABS: BookmarksTab[] = ['discussions', 'posts'];

const TAB_ICONS: Record<BookmarksTab, string> = {
  discussions: 'far fa-comments',
  posts: 'far fa-comment',
};

/**
 * The bookmarks page, listing the actor's bookmarked discussions and posts under one
 * route with a tab for each.
 *
 * Discussions reuse core's `DiscussionList` via the `is:bookmarked` gambit, so sorting
 * and pagination behave exactly as they do on the index. Posts use their own list state
 * driven by the `bookmarked` filter.
 */
export default class BookmarksPage extends Page {
  private tab: BookmarksTab = 'discussions';
  private discussions!: DiscussionListState;
  private posts!: BookmarkedPostListState;

  oninit(vnode: Mithril.Vnode<IPageAttrs, this>): void {
    super.oninit(vnode);

    // Guests have no bookmarks, so there is nothing here for them.
    if (!app.session.user) {
      m.route.set(app.route('index'));

      return;
    }

    // `LinkButton` defaults to `force: true`, so switching tabs re-runs the route and
    // this method. The active tab is therefore read from the URL rather than tracked as
    // mutable state, which also makes /bookmarks?tab=posts work as a direct link.
    this.tab = m.route.param('tab') === 'posts' ? 'posts' : 'discussions';

    this.discussions = new DiscussionListState({ q: 'is:bookmarked' });
    this.posts = new BookmarkedPostListState();

    this.loadCurrentTab();

    app.history.push('fof-bookmarks', extractText(app.translator.trans('fof-bookmarks.forum.page.title')));
    app.setTitle(extractText(app.translator.trans('fof-bookmarks.forum.page.title')));
  }

  view(): Mithril.Children {
    return (
      <div className="BookmarksPage IndexPage">
        {IndexPage.prototype.hero()}
        <div className="container">
          <div className="sideNavContainer">
            <nav className="IndexPage-nav sideNav">
              <ul>{listItems(IndexPage.prototype.sidebarItems().toArray())}</ul>
            </nav>
            <div className="IndexPage-results sideNavOffset">
              <div className="IndexPage-toolbar">
                <ul className="IndexPage-toolbar-view">{listItems(this.tabItems().toArray())}</ul>
              </div>
              {this.content()}
            </div>
          </div>
        </div>
      </div>
    );
  }

  private tabItems(): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    TABS.forEach((tab, index) => {
      const active = this.tab === tab;

      items.add(
        tab,
        // `Button` styles are opt-in: core's Button applies no class of its own, and the
        // toolbar list this sits in does not style its children the way `sideNav` does.
        //
        // The `active` attr drives LinkButton's own bookkeeping, while core's Button LESS
        // styles the `active` *class*, so both are set.
        <LinkButton className={classList('Button', { active })} href={this.tabHref(tab)} active={active} icon={TAB_ICONS[tab]}>
          {app.translator.trans(`fof-bookmarks.forum.page.tab.${tab}`)}
        </LinkButton>,
        // Preserve declaration order in the item list.
        100 - index
      );
    });

    return items;
  }

  private tabHref(tab: BookmarksTab): string {
    return app.route('fof-bookmarks', tab === 'discussions' ? {} : { tab });
  }

  private content(): Mithril.Children {
    return this.tab === 'discussions' ? <DiscussionList state={this.discussions} /> : this.postsContent();
  }

  private postsContent(): Mithril.Children {
    if (this.posts.isInitialLoading()) {
      return <LoadingIndicator />;
    }

    if (this.posts.isEmpty()) {
      return <Placeholder text={app.translator.trans('fof-bookmarks.forum.page.empty.posts')} />;
    }

    return (
      <div className="PostsUserPage">
        <ul className="PostsUserPage-list">
          {this.posts.items().map((post: Post) => (
            <li key={post.id()}>
              <div className="PostsUserPage-discussion">
                {app.translator.trans('core.forum.user.in_discussion_text', {
                  discussion: <Link href={app.route.post(post)}>{post.discussion().title()}</Link>,
                })}
              </div>
              <CommentPost post={post} />
            </li>
          ))}
        </ul>
        {this.postsPagination()}
      </div>
    );
  }

  private postsPagination(): Mithril.Children {
    if (this.posts.isLoadingNext()) {
      return <LoadingIndicator />;
    }

    if (!this.posts.hasNext()) return null;

    return (
      <div className="PostsUserPage-loadMore">
        <Button className="Button" onclick={() => this.posts.loadNext().then(() => m.redraw())}>
          {app.translator.trans('core.forum.user.posts_load_more_button')}
        </Button>
      </div>
    );
  }

  /**
   * Each list is only fetched when its tab is first opened, so opening the page does not
   * pay for results the user may never look at.
   */
  private loadCurrentTab(): void {
    const state = this.tab === 'discussions' ? this.discussions : this.posts;

    if (state.isInitialLoading() || state.hasItems()) return;

    state.refresh().then(() => m.redraw());
  }
}
