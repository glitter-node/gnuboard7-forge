<?php

namespace Modules\Sirsoft\Board\Database\Seeders\Install;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
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

        foreach (self::DEFAULT_BOARDS as $boardData) {
            if (! Board::where('slug', $boardData['slug'])->exists()) {
                $boardService->createBoard($boardData);
            }
        }

        $this->createNoticeBootstrapPost();

        if ($admin) {
            Auth::logout();
        }
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
