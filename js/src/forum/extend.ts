import commonExtend from '../common/extend';
import Extend from 'flarum/common/extenders';
import Discussion from 'flarum/common/models/Discussion';
import Post from 'flarum/common/models/Post';

export default [
  ...commonExtend,

  // The page is loaded as its own chunk: it is the largest module here and is only ever
  // reached at `/bookmarks`, so importing it eagerly would put it — and the post list state
  // it pulls in — on the critical path of every page load.
  //
  // The chunks this produces are served via `->jsDirectory()` in `extend.php`.
  new Extend.Routes() //
    .add('fof-bookmarks', '/bookmarks', () => import('./pages/BookmarksPage')),

  new Extend.Model(Discussion) //
    .attribute<boolean>('bookmarked'),

  new Extend.Model(Post) //
    .attribute<boolean>('bookmarked'),
];
