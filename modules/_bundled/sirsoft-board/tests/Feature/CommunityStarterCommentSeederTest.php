<?php

namespace Modules\Sirsoft\Board\Tests\Feature;

require_once __DIR__.'/../ModuleTestCase.php';
require_once dirname(__DIR__, 2).'/database/seeders/Install/CommunityStarterContentSeeder.php';
require_once dirname(__DIR__, 2).'/database/seeders/Install/CommunityStarterCommentSeeder.php';

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Sirsoft\Board\Database\Seeders\BoardTypeSeeder;
use Modules\Sirsoft\Board\Database\Seeders\Install\CommunityStarterCommentSeeder;
use Modules\Sirsoft\Board\Database\Seeders\Install\CommunityStarterContentSeeder;
use Modules\Sirsoft\Board\Models\Board;
use Modules\Sirsoft\Board\Models\Comment;
use Modules\Sirsoft\Board\Models\Post;
use Modules\Sirsoft\Board\Services\BoardService;
use Modules\Sirsoft\Board\Tests\ModuleTestCase;

class CommunityStarterCommentSeederTest extends ModuleTestCase
{
    /**
     * @var array<int, array{slug: string, title: string, content: string}>
     */
    private const COMMENTS = [
        [
            'slug' => 'notice',
            'title' => '그누보드7 커뮤니티 베타 운영 안내',
            'content' => '운영 시작 축하드립니다. 기대됩니다.',
        ],
        [
            'slug' => 'free',
            'title' => '커뮤니티는 이렇게 활용해보세요',
            'content' => '좋은 방향 같습니다. 자주 들르겠습니다.',
        ],
        [
            'slug' => 'free',
            'title' => '개선 의견과 제안을 들려주세요',
            'content' => '이런 공간이 필요했는데 반갑네요.',
        ],
        [
            'slug' => 'qna',
            'title' => '게시글은 어떻게 작성하면 좋을까요?',
            'content' => '저도 이 부분이 궁금했습니다.',
        ],
        [
            'slug' => 'qna',
            'title' => '문제나 오류는 어디에 알려야 하나요?',
            'content' => '오류 제보는 여기 남기면 되는군요, 감사합니다.',
        ],
    ];

    public function test_it_creates_starter_comments_once_for_the_starter_posts(): void
    {
        $this->seedStarterPosts();

        app(CommunityStarterCommentSeeder::class)->run();
        $countAfterFirstRun = Comment::query()->count();

        app(CommunityStarterCommentSeeder::class)->run();

        $this->assertSame(5, $countAfterFirstRun);
        $this->assertSame(5, Comment::query()->count());

        foreach (self::COMMENTS as $commentData) {
            $post = $this->starterPost($commentData['slug'], $commentData['title']);

            $this->assertSame(1, Comment::query()
                ->where('board_id', $post->board_id)
                ->where('post_id', $post->id)
                ->where('content', $commentData['content'])
                ->count());
        }
    }

    public function test_it_skips_safely_when_no_admin_author_exists(): void
    {
        $this->seedStarterPosts();

        DB::table('user_roles')->delete();
        User::query()->delete();

        app(CommunityStarterCommentSeeder::class)->run();

        $this->assertSame(0, Comment::query()->count());
    }

    public function test_post_detail_api_includes_starter_comments(): void
    {
        $this->seedStarterPosts();
        app(CommunityStarterCommentSeeder::class)->run();

        $post = $this->starterPost('free', '커뮤니티는 이렇게 활용해보세요');
        $this->grantGuestPermissions('free', ['posts.read', 'comments.read']);

        $response = $this->getJson("/api/modules/sirsoft-board/boards/free/posts/{$post->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.comments.0.content', '좋은 방향 같습니다. 자주 들르겠습니다.');
    }

    private function seedStarterPosts(): void
    {
        $this->createStarterBoards();

        app(CommunityStarterContentSeeder::class)->run();
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

    /**
     * @param  array<int, string>  $permissionKeys
     */
    private function grantGuestPermissions(string $slug, array $permissionKeys): void
    {
        $guestRole = Role::query()->where('identifier', 'guest')->firstOrFail();

        foreach ($permissionKeys as $key) {
            $permission = Permission::query()->firstOrCreate(
                ['identifier' => "sirsoft-board.{$slug}.{$key}"],
                ['name' => ['ko' => $key, 'en' => $key], 'type' => 'user']
            );

            $guestRole->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $reflection = new \ReflectionClass(\App\Http\Middleware\PermissionMiddleware::class);
        $property = $reflection->getProperty('guestRoleCache');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    private function starterPost(string $slug, string $title): Post
    {
        $board = Board::query()->where('slug', $slug)->firstOrFail();

        return Post::query()
            ->where('board_id', $board->id)
            ->where('title', $title)
            ->firstOrFail();
    }
}
