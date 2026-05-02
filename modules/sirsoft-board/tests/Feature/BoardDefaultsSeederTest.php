<?php

namespace Modules\Sirsoft\Board\Tests\Feature;

require_once __DIR__.'/../ModuleTestCase.php';
require_once dirname(__DIR__, 2).'/database/seeders/Install/BoardDefaultsSeeder.php';

use Modules\Sirsoft\Board\Database\Seeders\BoardTypeSeeder;
use Modules\Sirsoft\Board\Database\Seeders\Install\BoardDefaultsSeeder;
use Modules\Sirsoft\Board\Models\Board;
use Modules\Sirsoft\Board\Models\Post;
use Modules\Sirsoft\Board\Services\BoardService;
use Modules\Sirsoft\Board\Tests\ModuleTestCase;

class BoardDefaultsSeederTest extends ModuleTestCase
{
    public function test_it_creates_minimal_board_defaults_idempotently(): void
    {
        $this->seed(BoardTypeSeeder::class);
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $freeBoard = app(BoardService::class)->createBoard([
            'slug' => 'free',
            'name' => ['ko' => '사용자 자유게시판', 'en' => 'User Free Board'],
            'description' => ['ko' => '사용자 생성 게시판', 'en' => 'User created board'],
            'type' => 'basic',
        ]);

        app(BoardDefaultsSeeder::class)->run();
        app(BoardDefaultsSeeder::class)->run();

        $noticeBoard = Board::query()->where('slug', 'notice')->first();

        $this->assertNotNull($noticeBoard);
        $this->assertSame($freeBoard->id, Board::query()->where('slug', 'free')->value('id'));
        $this->assertSame(
            ['ko' => '사용자 자유게시판', 'en' => 'User Free Board'],
            Board::query()->where('slug', 'free')->value('name')
        );
        $this->assertSame(1, Board::query()->where('slug', 'notice')->count());
        $this->assertSame(1, Board::query()->where('slug', 'free')->count());
        $this->assertSame(1, Post::query()->where('board_id', $noticeBoard->id)->count());
        $this->assertSame(
            1,
            Post::query()
                ->where('board_id', $noticeBoard->id)
                ->whereJsonContains('action_logs', [[
                    'action' => 'bootstrap',
                    'marker' => 'sirsoft-board.install.notice-bootstrap.v1',
                ]])
                ->count()
        );
    }
}
