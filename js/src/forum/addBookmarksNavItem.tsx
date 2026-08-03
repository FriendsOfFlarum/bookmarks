import { extend } from 'flarum/common/extend';
import LinkButton from 'flarum/common/components/LinkButton';
import app from 'flarum/forum/app';
import IndexPage from 'flarum/forum/components/IndexPage';

export default function addBookmarksNavItem(): void {
  extend(IndexPage.prototype, 'navItems', (items) => {
    if (!app.session.user) return;

    items.add(
      'fof-bookmarks',
      <LinkButton href={app.route('fof-bookmarks')} icon="fas fa-bookmark">
        {app.translator.trans('fof-bookmarks.forum.page.link')}
      </LinkButton>
    );
  });
}
