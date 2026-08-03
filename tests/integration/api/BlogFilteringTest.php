<?php

/*
 * This file is part of fof/bookmarks.
 *
 * Copyright (c) 2026 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Bookmarks\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase as BaseTestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * Bookmarked blog articles must appear in the bookmarks list.
 *
 * fof/blog hides blog-tagged discussions from every `DiscussionSearcher` query via its
 * `HideBlogPostsFromAllDiscussionsPage` mutator, which only stands down when a blog or
 * fulltext filter is active. The bookmarks list is neither, so without the mutator this
 * extension registers, a bookmarked article silently vanishes from the list — the
 * bookmark is saved, but never shown.
 *
 * This test class does not extend the shared TestCase because it needs blog's own tag
 * fixtures and settings rather than the plain discussion fixtures.
 */
class BlogFilteringTest extends BaseTestCase
{
    use RetrievesAuthorizedUsers;

    /**
     * The tag id used as the blog tag, matching the `blog_tags` setting below.
     */
    private const BLOG_TAG = 24;

    protected function setUp(): void
    {
        parent::setUp();

        // fof/blog requires both tags and lock, so they have to be enabled alongside it.
        $this->extension('flarum-tags', 'flarum-lock', 'fof-blog', 'fof-bookmarks');

        // Blog hiding is opt-in, so it has to be switched on for this to be a regression
        // test rather than a no-op. These go through `setting()` rather than the
        // `prepareDatabase` settings table because the settings repository caches at boot,
        // which happens before the fixture rows would be written.
        $this->setting('blog_filter_discussion_list', '1');
        $this->setting('blog_tags', (string) self::BLOG_TAG);

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            Tag::class => [
                ['id' => 1, 'name' => 'General', 'slug' => 'general', 'position' => 0],
                ['id' => self::BLOG_TAG, 'name' => 'Blog', 'slug' => 'blog', 'position' => 1],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Normal discussion', 'slug' => 'normal-discussion', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 1, 'last_post_id' => 1, 'last_post_number' => 1, 'comment_count' => 1],
                ['id' => 2, 'title' => 'Blog article', 'slug' => 'blog-article', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 2, 'last_post_id' => 2, 'last_post_number' => 1, 'comment_count' => 1],
            ],
            Post::class => [
                ['id' => 1, 'number' => 1, 'discussion_id' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Normal.</p></t>'],
                ['id' => 2, 'number' => 1, 'discussion_id' => 2, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Article.</p></t>'],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 2, 'tag_id' => self::BLOG_TAG],
            ],
        ]);
    }

    private function bookmark(int $discussionId, int $userId): void
    {
        $this->database()->table('discussion_user')->insert([
            'discussion_id' => $discussionId,
            'user_id' => $userId,
            'bookmarked_at' => '2026-02-01 12:00:00',
        ]);
    }

    /**
     * @return string[]
     */
    private function ids(ResponseInterface $response): array
    {
        $body = $response->getBody();
        $body->rewind();

        return array_map(
            fn (array $resource): string => $resource['id'],
            json_decode($body->getContents(), true)['data']
        );
    }

    private function listBookmarked(): ResponseInterface
    {
        return $this->send(
            $this->request('GET', '/api/discussions', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['bookmarked' => true], 'sort' => 'createdAt'])
        );
    }

    #[Test]
    public function bookmarked_blog_article_is_listed(): void
    {
        $this->bookmark(2, 2);

        $this->assertEquals(['2'], $this->ids($this->listBookmarked()));
    }

    #[Test]
    public function bookmarked_articles_and_discussions_are_listed_together(): void
    {
        $this->bookmark(1, 2);
        $this->bookmark(2, 2);

        $this->assertEquals(['1', '2'], $this->ids($this->listBookmarked()));
    }

    /**
     * The exemption is scoped to the bookmarked filter, so an ordinary discussion listing
     * must still hide blog articles.
     */
    #[Test]
    public function unfiltered_discussion_list_still_hides_blog_articles(): void
    {
        $response = $this->send(
            $this->request('GET', '/api/discussions', ['authenticatedAs' => 2])
        );

        $this->assertEquals(['1'], $this->ids($response));
    }

    /**
     * Bookmarks are still per-user: another user's bookmarked article must not leak.
     */
    #[Test]
    public function bookmarked_filter_remains_scoped_to_the_actor(): void
    {
        $this->bookmark(2, 1);

        $this->assertEquals([], $this->ids($this->listBookmarked()));
    }

    /**
     * `filter[-bookmarked]` asks for everything except bookmarks, so the article must stay
     * hidden — re-admitting it there would undo the negation.
     */
    #[Test]
    public function negated_filter_does_not_re_admit_bookmarked_articles(): void
    {
        $this->bookmark(1, 2);
        $this->bookmark(2, 2);

        $response = $this->send(
            $this->request('GET', '/api/discussions', ['authenticatedAs' => 2])
                ->withQueryParams(['filter' => ['-bookmarked' => true]])
        );

        $this->assertEquals([], $this->ids($response));
    }

    /**
     * Re-admitting bookmarked articles adds a fresh OR branch to a query whose visibility
     * scope was applied before the mutator ran, so that branch has to re-assert visibility
     * itself. A bookmark on a discussion the actor cannot see must not resurrect it.
     */
    #[Test]
    public function invisible_discussion_is_not_re_admitted_by_its_bookmark(): void
    {
        $this->database()->table('discussions')->where('id', 2)->update(['is_private' => true]);

        $this->bookmark(2, 2);

        $this->assertEquals([], $this->ids($this->listBookmarked()));
    }
}
