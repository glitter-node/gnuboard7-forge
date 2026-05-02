<?php

namespace Modules\Sirsoft\Board\Tests\Feature;

require_once __DIR__.'/../ModuleTestCase.php';
require_once dirname(__DIR__, 2).'/database/seeders/Install/CommunityStarterContentSeeder.php';

use Modules\Sirsoft\Board\Database\Seeders\BoardTypeSeeder;
use Modules\Sirsoft\Board\Database\Seeders\Install\CommunityStarterContentSeeder;
use Modules\Sirsoft\Board\Models\Board;
use Modules\Sirsoft\Board\Models\Post;
use Modules\Sirsoft\Board\Services\BoardService;
use Modules\Sirsoft\Board\Tests\ModuleTestCase;

class CommunityStarterContentSeederTest extends ModuleTestCase
{
    public function test_it_creates_starter_posts_once_for_the_starter_boards(): void
    {
        $this->createStarterBoards();

        app(CommunityStarterContentSeeder::class)->run();
        app(CommunityStarterContentSeeder::class)->run();

        $this->assertSame(1, $this->postCount('notice', '그누보드7 커뮤니티 베타 운영 안내'));
        $this->assertSame(1, $this->postCount('free', '커뮤니티는 이렇게 활용해보세요'));
        $this->assertSame(1, $this->postCount('free', '개선 의견과 제안을 들려주세요'));
        $this->assertSame(1, $this->postCount('qna', '게시글은 어떻게 작성하면 좋을까요?'));
        $this->assertSame(1, $this->postCount('qna', '문제나 오류는 어디에 알려야 하나요?'));

        $this->assertTrue(
            Post::query()
                ->where('board_id', $this->board('notice')->id)
                ->where('title', '그누보드7 커뮤니티 베타 운영 안내')
                ->value('is_notice')
        );
    }

    public function test_homepage_recent_posts_can_consume_starter_content(): void
    {
        $this->createStarterBoards();

        app(CommunityStarterContentSeeder::class)->run();

        $recentPosts = app(BoardService::class)->getRecentPosts(20);
        $byTitle = collect($recentPosts)->keyBy('title');

        $this->assertGreaterThanOrEqual(5, count($recentPosts));
        $this->assertSame('notice', $byTitle['그누보드7 커뮤니티 베타 운영 안내']['board_slug']);
        $this->assertSame('free', $byTitle['커뮤니티는 이렇게 활용해보세요']['board_slug']);
        $this->assertSame('free', $byTitle['개선 의견과 제안을 들려주세요']['board_slug']);
        $this->assertSame('qna', $byTitle['게시글은 어떻게 작성하면 좋을까요?']['board_slug']);
        $this->assertSame('qna', $byTitle['문제나 오류는 어디에 알려야 하나요?']['board_slug']);
    }

    private function createStarterBoards(): void
    {
        $this->seed(BoardTypeSeeder::class);

        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $boardService = app(BoardService::class);
        foreach ([
            ['slug' => 'notice', 'name' => ['ko' => '공지사항', 'en' => 'Notice'], 'use_comment' => false, 'use_reply' => false, 'use_report' => false],
            ['slug' => 'free', 'name' => ['ko' => '자유게시판', 'en' => 'Free Board'], 'use_comment' => true, 'use_reply' => false, 'use_report' => true],
            ['slug' => 'qna', 'name' => ['ko' => '질문게시판', 'en' => 'Q&A Board'], 'use_comment' => true, 'use_reply' => true, 'use_report' => true, 'secret_mode' => 'enabled', 'categories' => ['일반문의', '기술문의', '기타']],
        ] as $boardData) {
            if (Board::query()->where('slug', $boardData['slug'])->exists()) {
                continue;
            }

            $boardService->createBoard(array_merge([
                'description' => ['ko' => $boardData['name']['ko'].' 설명', 'en' => $boardData['name']['en'].' description'],
                'type' => 'basic',
                'is_active' => true,
            ], $boardData));
        }
    }

    private function postCount(string $slug, string $title): int
    {
        return Post::query()
            ->where('board_id', $this->board($slug)->id)
            ->where('title', $title)
            ->count();
    }

    private function board(string $slug): Board
    {
        return Board::query()->where('slug', $slug)->firstOrFail();
    }
}
