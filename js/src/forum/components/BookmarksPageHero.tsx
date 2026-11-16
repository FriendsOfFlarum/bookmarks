import app from 'flarum/forum/app';
import Hero, { type IHeroAttrs } from 'flarum/forum/components/Hero';
import Icon from 'flarum/common/components/Icon';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';

export interface IBookmarksPageHeroAttrs extends IHeroAttrs {}

export default class BookmarksPageHero<CustomAttrs extends IBookmarksPageHeroAttrs = IBookmarksPageHeroAttrs> extends Hero<CustomAttrs> {
  className(): string {
    return 'BookmarksPageHero';
  }

  bodyItems(): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add('content', <div className="containerNarrow">{this.contentItems().toArray()}</div>, 80);

    return items;
  }

  contentItems(): ItemList<Mithril.Children> {
    const items = new ItemList<Mithril.Children>();

    items.add(
      'title',
      <h1 className="Hero-title">
        <Icon name="fas fa-bookmark" /> {app.translator.trans('fof-bookmarks.forum.page.hero.title')}
      </h1>,
      100
    );

    items.add('subtitle', <div className="Hero-subtitle">{app.translator.trans('fof-bookmarks.forum.page.hero.subtitle')}</div>, 90);

    return items;
  }
}
