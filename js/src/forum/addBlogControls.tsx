import { extend } from 'flarum/common/extend';
import type Discussion from 'flarum/common/models/Discussion';
import app from 'flarum/forum/app';
import BookmarkButton from './components/BookmarkButton';

/**
 * fof/blog's article page does not reuse Flarum's discussion sidebar, so the bookmark
 * control has to be added to its own content list.
 *
 * The import below is type-only, so this file adds no runtime dependency on fof/blog:
 * the class is looked up through the compat registry and the extension bails out when
 * blog is not installed.
 */
type BlogItem = typeof import('@fof/blog/forum/pages/BlogItem').default;

const COMPAT_KEY = 'fof/blog/pages/BlogItem';

export default function addBlogControls(): void {
  const registry = flarum.core.compat as Record<string, unknown>;

  if (!(COMPAT_KEY in registry)) return;

  const BlogItemPage = registry[COMPAT_KEY] as BlogItem;

  extend(BlogItemPage.prototype, 'contentItems', function (items) {
    if (!app.session.user) return;

    // `article` is protected on BlogItem, but this runs as an extension of the class.
    const article = (this as unknown as { article: Discussion | null }).article;

    if (!article) return;

    items.add(
      'bookmark',
      <div className="FlarumBlog-Article-Content-Bookmark-Button">
        <BookmarkButton subject={article} labels="independentButton" className="Button Button--bookmark" />
      </div>,
      // Just below the edit controls an admin may see.
      78
    );
  });
}
