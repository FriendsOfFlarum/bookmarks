import Component from 'flarum/common/Component';
import type Discussion from 'flarum/common/models/Discussion';
import type Post from 'flarum/common/models/Post';
import type Mithril from 'mithril';
export interface BookmarkButtonAttrs {
    /**
     * The discussion or post being bookmarked. Both expose a `bookmarked` attribute and
     * are saved the same way, so one button serves both.
     */
    subject: Discussion | Post;
    /**
     * Which set of labels to use. Discussions read differently depending on whether the
     * control sits in a dropdown or stands on its own in the sidebar.
     */
    labels: 'dropdownButton' | 'independentButton' | 'postButton';
    className?: string;
    /**
     * Show an alert confirming the change, with a link to the bookmarks page. Used by the
     * post button, which can sit far down a long page.
     */
    alert?: boolean;
}
export default class BookmarkButton extends Component<BookmarkButtonAttrs> {
    /**
     * Guards against a second click while the first save is still in flight, which would
     * otherwise send a contradictory request.
     */
    private saving;
    view(): Mithril.Children;
    private toggle;
    private showAlert;
}
