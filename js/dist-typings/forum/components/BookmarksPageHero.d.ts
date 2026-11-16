import Hero, { type IHeroAttrs } from 'flarum/forum/components/Hero';
import ItemList from 'flarum/common/utils/ItemList';
import type Mithril from 'mithril';
export interface IBookmarksPageHeroAttrs extends IHeroAttrs {
}
export default class BookmarksPageHero<CustomAttrs extends IBookmarksPageHeroAttrs = IBookmarksPageHeroAttrs> extends Hero<CustomAttrs> {
    className(): string;
    bodyItems(): ItemList<Mithril.Children>;
    contentItems(): ItemList<Mithril.Children>;
}
