import { BooleanGambit } from 'flarum/common/query/IGambit';
export default class BookmarkedGambit extends BooleanGambit {
    key(): string;
    filterKey(): string;
    enabled(): boolean;
}
