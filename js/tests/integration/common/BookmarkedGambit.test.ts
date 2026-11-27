import bootstrapForum from '@flarum/jest-config/src/bootstrap/forum';
import app from 'flarum/forum/app';
import extend from '../../../src/common/extend';

beforeAll(() => {
  bootstrapForum();
  app.bootExtensions({ 'fof-bookmarks': { extend } });
  app.boot();
  app.translator.addTranslations({
    'core.lib.gambits.group_keys.is': '是',
    'fof-bookmarks.lib.gambits.bookmarked.key': '已收藏',
  });
});

describe.each(['discussions', 'posts'])('%s on a translated forum', (type) => {
  it('turns the translated gambit into the bookmarked filter', () => {
    expect(app.search.gambits.apply(type, { q: '是:已收藏' })).toStrictEqual({ q: '', bookmarked: true });
  });

  it('still turns the English is:bookmarked into the bookmarked filter', () => {
    expect(app.search.gambits.apply(type, { q: 'is:bookmarked' })).toStrictEqual({ q: '', bookmarked: true });
  });

  it('still turns the negated English -is:bookmarked into the negated filter', () => {
    expect(app.search.gambits.apply(type, { q: '-is:bookmarked' })).toStrictEqual({ q: '', '-bookmarked': true });
  });
});
