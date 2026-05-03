<?php

namespace Modules\Sirsoft\Board\Tests\Feature;

// ModuleTestCase를 수동으로 require (autoload 전에 로드 필요)
require_once __DIR__.'/../ModuleTestCase.php';

use App\Extension\HookManager;
use App\Listeners\NotificationHookListener;
use App\Models\NotificationDefinition;
use App\Models\Role;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Sirsoft\Board\Database\Seeders\BoardNotificationDefinitionSeeder;
use Modules\Sirsoft\Board\Listeners\BoardNotificationDataListener;
use Modules\Sirsoft\Board\Tests\BoardTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * 댓글/대댓글 알림 통합 테스트
 *
 * 댓글 생성 API → comment.after_create 훅 → GenericNotification 흐름을 검증합니다.
 */
class CommentNotificationTest extends BoardTestCase
{
    private array $hookSnapshot = [];

    private array $filterSnapshot = [];

    protected function getTestBoardSlug(): string
    {
        return 'comment-notification';
    }

    protected function getDefaultBoardAttributes(string $slug): array
    {
        return [
            'slug' => $slug,
            'name' => ['ko' => '댓글 알림 테스트 게시판', 'en' => 'Comment Notification Board'],
            'is_active' => true,
            'use_comment' => true,
            'use_file_upload' => false,
            'secret_mode' => 'disabled',
            'notify_author' => true,
            'max_comment_depth' => 2,
            'blocked_keywords' => [],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://example.test']);
        Notification::fake();

        $this->grantUserRolePermissions(['posts.read', 'posts.write', 'comments.read', 'comments.write']);
        $this->seedBoardNotificationDefinitions();
        $this->snapshotHooks();
        $this->registerCommentNotificationHooks();
    }

    protected function tearDown(): void
    {
        $this->restoreHooks();

        parent::tearDown();
    }

    #[Test]
    public function test_post_author_gets_notification_on_new_comment(): void
    {
        $postAuthor = $this->createMember('post-author@example.test', '게시글작성자');
        $commenter = $this->createMember('commenter@example.test', '댓글작성자');
        $this->enableBoardNotifications($postAuthor);

        $postId = $this->createTestPost([
            'title' => '알림 대상 게시글',
            'user_id' => $postAuthor->id,
            'author_name' => $postAuthor->name,
        ]);

        $response = $this->actingAs($commenter)
            ->postJson("/api/modules/sirsoft-board/boards/{$this->board->slug}/posts/{$postId}/comments", [
                'content' => '확인 부탁드립니다.',
            ]);

        $response->assertStatus(201);

        Notification::assertSentTo($postAuthor, GenericNotification::class, function (GenericNotification $notification) use ($postId) {
            $data = $notification->getData();
            $payload = $notification->toArray(User::find($data['recipient_id'] ?? null) ?? new User());

            return $notification->getType() === 'new_comment'
                && ($data['comment_author'] ?? '') === '댓글작성자'
                && ($data['post_title'] ?? '') === '알림 대상 게시글'
                && ($data['post_url'] ?? '') === "https://example.test/board/{$this->board->slug}/{$postId}"
                && ($payload['click_url'] ?? '') === "https://example.test/board/{$this->board->slug}/{$postId}";
        });

        Notification::assertNotSentTo($commenter, GenericNotification::class);
    }

    #[Test]
    public function test_post_author_does_not_get_notification_for_self_comment(): void
    {
        $postAuthor = $this->createMember('self-author@example.test', '게시글작성자');
        $this->enableBoardNotifications($postAuthor);

        $postId = $this->createTestPost([
            'title' => '셀프 댓글 게시글',
            'user_id' => $postAuthor->id,
            'author_name' => $postAuthor->name,
        ]);

        $response = $this->actingAs($postAuthor)
            ->postJson("/api/modules/sirsoft-board/boards/{$this->board->slug}/posts/{$postId}/comments", [
                'content' => '작성자가 직접 남긴 댓글입니다.',
            ]);

        $response->assertStatus(201);

        Notification::assertNotSentTo($postAuthor, GenericNotification::class);
    }

    #[Test]
    public function test_parent_comment_author_gets_notification_on_reply(): void
    {
        $postAuthor = $this->createMember('reply-post-author@example.test', '게시글작성자');
        $parentAuthor = $this->createMember('parent-author@example.test', '부모댓글작성자');
        $replyAuthor = $this->createMember('reply-author@example.test', '대댓글작성자');
        $this->enableBoardNotifications($postAuthor);
        $this->enableBoardNotifications($parentAuthor);

        $postId = $this->createTestPost([
            'title' => '대댓글 알림 게시글',
            'user_id' => $postAuthor->id,
            'author_name' => $postAuthor->name,
        ]);
        $parentCommentId = $this->createTestComment($postId, [
            'user_id' => $parentAuthor->id,
            'author_name' => $parentAuthor->name,
            'content' => '부모 댓글입니다.',
            'depth' => 0,
        ]);

        $response = $this->actingAs($replyAuthor)
            ->postJson("/api/modules/sirsoft-board/boards/{$this->board->slug}/posts/{$postId}/comments", [
                'content' => '대댓글입니다.',
                'parent_id' => $parentCommentId,
            ]);

        $response->assertStatus(201);

        Notification::assertSentTo($postAuthor, GenericNotification::class, fn (GenericNotification $notification) => $notification->getType() === 'new_comment');
        Notification::assertSentTo($parentAuthor, GenericNotification::class, function (GenericNotification $notification) use ($postId) {
            $data = $notification->getData();

            return $notification->getType() === 'reply_comment'
                && ($data['comment_author'] ?? '') === '대댓글작성자'
                && ($data['post_url'] ?? '') === "https://example.test/board/{$this->board->slug}/{$postId}";
        });
        Notification::assertNotSentTo($replyAuthor, GenericNotification::class);
    }

    #[Test]
    public function test_duplicate_recipient_is_not_notified_twice_when_parent_author_is_post_author(): void
    {
        $postAuthor = $this->createMember('duplicate-author@example.test', '게시글작성자');
        $replyAuthor = $this->createMember('duplicate-reply-author@example.test', '대댓글작성자');
        $this->enableBoardNotifications($postAuthor);

        $postId = $this->createTestPost([
            'title' => '중복 방지 게시글',
            'user_id' => $postAuthor->id,
            'author_name' => $postAuthor->name,
        ]);
        $parentCommentId = $this->createTestComment($postId, [
            'user_id' => $postAuthor->id,
            'author_name' => $postAuthor->name,
            'content' => '게시글 작성자의 부모 댓글입니다.',
            'depth' => 0,
        ]);

        $response = $this->actingAs($replyAuthor)
            ->postJson("/api/modules/sirsoft-board/boards/{$this->board->slug}/posts/{$postId}/comments", [
                'content' => '중복 없이 한 번만 알려야 합니다.',
                'parent_id' => $parentCommentId,
            ]);

        $response->assertStatus(201);

        Notification::assertSentToTimes($postAuthor, GenericNotification::class, 1);
        Notification::assertSentTo($postAuthor, GenericNotification::class, fn (GenericNotification $notification) => $notification->getType() === 'new_comment');
    }

    #[Test]
    public function test_notification_url_points_to_post_detail_route(): void
    {
        $postAuthor = $this->createMember('url-author@example.test', '게시글작성자');
        $commenter = $this->createMember('url-commenter@example.test', '댓글작성자');
        $this->enableBoardNotifications($postAuthor);

        $postId = $this->createTestPost([
            'title' => 'URL 검증 게시글',
            'user_id' => $postAuthor->id,
            'author_name' => $postAuthor->name,
        ]);

        $response = $this->actingAs($commenter)
            ->postJson("/api/modules/sirsoft-board/boards/{$this->board->slug}/posts/{$postId}/comments", [
                'content' => 'URL 검증 댓글입니다.',
            ]);

        $response->assertStatus(201);

        Notification::assertSentTo($postAuthor, GenericNotification::class, function (GenericNotification $notification) use ($postId) {
            $payload = $notification->toArray(User::factory()->make(['locale' => 'ko']));

            return $notification->getType() === 'new_comment'
                && ($payload['click_url'] ?? '') === "https://example.test/board/{$this->board->slug}/{$postId}";
        });
    }

    private function seedBoardNotificationDefinitions(): void
    {
        $this->app->make(BoardNotificationDefinitionSeeder::class)->run();

        $definitionIds = NotificationDefinition::query()
            ->whereIn('type', ['new_comment', 'reply_comment'])
            ->pluck('id');

        DB::table('notification_templates')
            ->whereIn('definition_id', $definitionIds)
            ->where('channel', 'mail')
            ->update(['is_active' => false]);
    }

    private function registerCommentNotificationHooks(): void
    {
        HookManager::clearAction('sirsoft-board.comment.after_create');
        HookManager::clearFilter('sirsoft-board.notification.extract_data');

        HookManager::addFilter(
            'sirsoft-board.notification.extract_data',
            [$this->app->make(BoardNotificationDataListener::class), 'extractData'],
            20
        );

        $this->app->make(NotificationHookListener::class)->registerDynamicHooks();
    }

    private function snapshotHooks(): void
    {
        $this->hookSnapshot = HookManager::getHooks();
        $this->filterSnapshot = HookManager::getFilters();
    }

    private function restoreHooks(): void
    {
        $reflection = new \ReflectionClass(HookManager::class);

        foreach (['hooks' => $this->hookSnapshot, 'filters' => $this->filterSnapshot] as $propertyName => $value) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue(null, $value);
        }
    }

    private function createMember(string $email, string $name): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => $name,
        ]);

        $role = Role::where('identifier', 'user')->first();
        if ($role) {
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function enableBoardNotifications(User $user): void
    {
        DB::table('board_user_notification_settings')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'notify_post_complete' => true,
                'notify_post_reply' => true,
                'notify_comment' => true,
                'notify_reply_comment' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
