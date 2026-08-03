import app from 'flarum/common/app';
import { BooleanGambit } from 'flarum/common/query/IGambit';

export default class BookmarkedGambit extends BooleanGambit {
  key() {
    return app.translator.trans('fof-bookmarks.lib.gambits.bookmarked.key', {}, true);
  }

  filterKey() {
    return 'bookmarked';
  }
}
