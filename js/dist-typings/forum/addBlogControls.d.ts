/**
 * fof/blog's article page does not reuse Flarum's discussion sidebar, so the bookmark
 * control has to be added to its own content list.
 *
 * The target is given to `extend()` as an `ext:` module path rather than a class. That
 * defers the extension through `flarum.reg.onLoad()`, so it is applied if and when
 * fof/blog loads and is simply never applied when blog is not installed — which is what
 * keeps blog an optional dependency without a runtime import.
 */
export default function addBlogControls(): void;
