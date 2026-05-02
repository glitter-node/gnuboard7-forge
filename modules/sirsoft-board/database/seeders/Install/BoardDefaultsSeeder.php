<?php

namespace Modules\Sirsoft\Board\Database\Seeders\Install;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Sirsoft\Board\Http\Requests\StoreBoardRequest;
use Modules\Sirsoft\Board\Models\Board;
use Modules\Sirsoft\Board\Models\Post;
use Modules\Sirsoft\Board\Services\BoardService;
use Modules\Sirsoft\Board\Services\PostService;

class BoardDefaultsSeeder extends Seeder
{
    private const NOTICE_BOOTSTRAP_MARKER = 'sirsoft-board.install.notice-bootstrap.v1';

    private const DEFAULT_BOARDS = [
        [
            'slug' => 'notice',
            'name' => ['ko' => '공지사항', 'en' => 'Notice'],
            'description' => ['ko' => '기본 공지 게시판', 'en' => 'Default notice board'],
            'type' => 'basic',
            'use_comment' => false,
            'use_reply' => false,
            'use_report' => false,
            'use_file_upload' => false,
            'permissions' => [
                'posts_write' => ['roles' => ['admin']],
            ],
        ],
        [
            'slug' => 'free',
            'name' => ['ko' => '자유게시판', 'en' => 'Free Board'],
            'description' => ['ko' => '기본 커뮤니티 게시판', 'en' => 'Default community board'],
            'type' => 'basic',
            'use_comment' => true,
            'use_reply' => false,
            'use_report' => true,
            'use_file_upload' => true,
        ],
        [
            'slug' => 'qna',
            'name' => ['ko' => '질문게시판', 'en' => 'Q&A Board'],
            'description' => ['ko' => '질문과 답변을 나누는 게시판', 'en' => 'Board for questions and answers'],
            'type' => 'basic',
            'use_comment' => true,
            'use_reply' => true,
            'max_reply_depth' => 3,
            'max_comment_depth' => 5,
            'use_report' => true,
            'use_file_upload' => false,
            'secret_mode' => 'enabled',
            'categories' => ['일반문의', '기술문의', '기타'],
        ],
    ];

    public function run(): void
    {
        $admin = User::whereHas('roles', function ($query) {
            $query->where('identifier', 'admin');
        })->first();

        if ($admin) {
            Auth::login($admin);
        }

        $boardService = app(BoardService::class);
        $adminIds = User::whereHas('roles', function ($query) {
            $query->where('identifier', 'admin');
        })->pluck('uuid')->toArray();

        foreach (self::DEFAULT_BOARDS as $boardData) {
            if (! Board::where('slug', $boardData['slug'])->exists()) {
                $boardData = $this->prepareBoardData($boardData, $adminIds);
                $this->validateBoardData($boardData);
                $boardService->createBoard($boardData);
            }
        }

        $this->createNoticeBootstrapPost();

        if ($admin) {
            Auth::logout();
        }
    }

    /**
     * @param  array<string, mixed>  $boardData
     * @param  array<int, string>  $adminIds
     * @return array<string, mixed>
     */
    private function prepareBoardData(array $boardData, array $adminIds): array
    {
        $settings = g7_module_settings('sirsoft-board', 'basic_defaults', []);

        $defaults = [
            'is_active' => true,
            'type' => $settings['type'] ?? 'basic',
            'per_page' => $settings['per_page'] ?? 20,
            'per_page_mobile' => $settings['per_page_mobile'] ?? 15,
            'order_by' => $settings['order_by'] ?? 'created_at',
            'order_direction' => $settings['order_direction'] ?? 'DESC',
            'secret_mode' => $settings['secret_mode'] ?? 'disabled',
            'use_comment' => $settings['use_comment'] ?? true,
            'use_reply' => $settings['use_reply'] ?? true,
            'max_reply_depth' => $settings['max_reply_depth'] ?? 5,
            'max_comment_depth' => $settings['max_comment_depth'] ?? 10,
            'use_file_upload' => $settings['use_file_upload'] ?? false,
            'comment_order' => $settings['comment_order'] ?? 'ASC',
            'notify_admin_on_post' => $settings['notify_admin_on_post'] ?? true,
            'notify_author' => $settings['notify_author'] ?? true,
            'show_view_count' => $settings['show_view_count'] ?? true,
            'use_report' => $settings['use_report'] ?? false,
            'new_display_hours' => $settings['new_display_hours'] ?? 24,
            'min_title_length' => $settings['min_title_length'] ?? 2,
            'max_title_length' => $settings['max_title_length'] ?? 200,
            'min_content_length' => $settings['min_content_length'] ?? 2,
            'max_content_length' => $settings['max_content_length'] ?? 10000,
            'min_comment_length' => $settings['min_comment_length'] ?? 2,
            'max_comment_length' => $settings['max_comment_length'] ?? 1000,
            'max_file_size' => $settings['max_file_size'] ?? 10,
            'max_file_count' => $settings['max_file_count'] ?? 5,
            'allowed_extensions' => $settings['allowed_extensions'] ?? [],
            'blocked_keywords' => $settings['blocked_keywords'] ?? [],
            'permissions' => [],
            'board_manager_ids' => $adminIds,
        ];

        return array_merge($defaults, $boardData);
    }

    /**
     * @param  array<string, mixed>  $boardData
     *
     * @throws ValidationException
     */
    private function validateBoardData(array $boardData): void
    {
        $request = new StoreBoardRequest();

        Validator::make(
            $boardData,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        )->validate();
    }

    private function createNoticeBootstrapPost(): void
    {
        $board = Board::query()->where('slug', 'notice')->first();

        if (! $board) {
            return;
        }

        $bootstrapPostExists = Post::query()
            ->where('board_id', $board->id)
            ->whereJsonContains('action_logs', [[
                'action' => 'bootstrap',
                'marker' => self::NOTICE_BOOTSTRAP_MARKER,
            ]])
            ->exists();

        if ($bootstrapPostExists) {
            return;
        }

        $boardHasPosts = Post::query()
            ->where('board_id', $board->id)
            ->exists();

        if ($boardHasPosts) {
            return;
        }

        app(PostService::class)->createPost('notice', [
            'title' => 'Welcome to the notice board',
            'content' => '<p>This board is ready for site-wide announcements.</p>',
            'content_mode' => 'html',
            'user_id' => Auth::id(),
            'author_name' => Auth::user()?->name,
            'ip_address' => '127.0.0.1',
            'is_notice' => true,
            'is_secret' => false,
            'status' => 'published',
            'trigger_type' => 'system',
            'action_logs' => [[
                'action' => 'bootstrap',
                'marker' => self::NOTICE_BOOTSTRAP_MARKER,
            ]],
            'view_count' => 0,
        ], options: ['skip_notification' => true]);
    }
}
