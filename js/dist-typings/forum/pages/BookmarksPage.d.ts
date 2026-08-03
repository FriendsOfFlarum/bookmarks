import Page, { type IPageAttrs } from 'flarum/common/components/Page';
import type Mithril from 'mithril';
export type BookmarksTab = 'discussions' | 'posts';
/**
 * The bookmarks page, listing the actor's bookmarked discussions and posts under one
 * route with a tab for each.
 *
 * Discussions reuse core's `DiscussionList` via the `is:bookmarked` gambit, so sorting
 * and pagination behave exactly as they do on the index. Posts use their own list state
 * driven by the `bookmarked` filter.
 */
export default class BookmarksPage extends Page {
    private tab;
    private discussions;
    private posts;
    oninit(vnode: Mithril.Vnode<IPageAttrs, this>): void;
    view(): Mithril.Children;
    private tabItems;
    private tabHref;
    private content;
    private postsContent;
    private postsPagination;
    /**
     * Each list is only fetched when its tab is first opened, so opening the page does not
     * pay for results the user may never look at.
     */
    private loadCurrentTab;
}
