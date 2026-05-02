<?php

namespace Modules\Sirsoft\Board\Tests\Feature\User;

// ModuleTestCase를 수동으로 require (autoload 전에 로드 필요)
require_once __DIR__.'/../../ModuleTestCase.php';

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Sirsoft\Board\Enums\PostStatus;
use Modules\Sirsoft\Board\Models\Board;
use Modules\Sirsoft\Board\Tests\ModuleTestCase;

/**
 * 최근 게시글 API 테스트
 *
 * 홈 페이지에서 사용할 최근 게시글 통합 조회 API의 동작을 검증합니다.
 *
 * @group board
 * @group board-recent-posts
 */
class BoardRecentPostsApiTest extends ModuleTestCase
{
    /**
     * 테스트 환경 설정
     */
    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('ko');

        // 기존 활성 게시판 비활성화
        Board::where('is_active', true)->update(['is_active' => false]);

        // 캐시 클리어
        Cache::flush();
    }

    /**
     * 테스트 종료 후 정리
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * 최근 게시글 API가 올바른 구조로 응답하는지 테스트
     */
    public function test_recent_posts_returns_correct_structure(): void
    {
        // When: 최근 게시글 API 호출
        $response = $this->withHeader('Accept-Language', 'ko')
            ->getJson('/api/modules/sirsoft-board/boards/posts/recent');

        // Then: 올바른 구조 반환
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }

    /**
     * 기본 limit이 5개인지 테스트
     */
    public function test_recent_posts_default_limit_is_five(): void
    {
        // Given: 게시판과 게시글 생성
        $this->createBoardWithPosts(10);

        // When: limit 없이 API 호출
        $response = $this->withHeader('Accept-Language', 'ko')
            ->getJson('/api/modules/sirsoft-board/boards/posts/recent');

        // Then: 5개 반환
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertLessThanOrEqual(5, count($data));
    }

    /**
     * limit 파라미터가 동작하는지 테스트
     */
    public function test_recent_posts_respects_limit_parameter(): void
    {
        // Given: 게시판과 게시글 생성
        $this->createBoardWithPosts(15);

        // When: limit=10으로 API 호출
        $response = $this->getJson('/api/modules/sirsoft-board/boards/posts/recent?limit=10');

        // Then: 10개 이하 반환
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertLessThanOrEqual(10, count($data));
    }

    /**
     * limit 최대값이 20인지 테스트
     */
    public function test_recent_posts_max_limit_is_twenty(): void
    {
        // Given: 게시판과 게시글 생성
        $this->createBoardWithPosts(30);

        // When: limit=100으로 API 호출
        $response = $this->getJson('/api/modules/sirsoft-board/boards/posts/recent?limit=100');

        // Then: 20개 이하 반환
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertLessThanOrEqual(20, count($data));
    }

    /**
     * 게시판이 없을 때 빈 배열을 반환하는지 테스트
     */
    public function test_recent_posts_returns_empty_when_no_boards(): void
    {
        // Given: 게시판 없음

        // When: API 호출
        $response = $this->withHeader('Accept-Language', 'ko')
            ->getJson('/api/modules/sirsoft-board/boards/posts/recent');

        // Then: 빈 배열 반환
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    /**
     * 최신순으로 정렬되는지 테스트
     */
    public function test_recent_posts_sorted_by_created_at_desc(): void
    {
        // Given: 게시판과 게시글 생성
        $this->createBoardWithPosts(5);

        // When: API 호출
        $response = $this->withHeader('Accept-Language', 'ko')
            ->getJson('/api/modules/sirsoft-board/boards/posts/recent');

        // Then: 최신순 정렬 확인
        $response->assertStatus(200);
        $data = $response->json('data');

        if (count($data) > 1) {
            // 최신순 정렬: 게시글 0(now)이 가장 먼저, 게시글 1(1분 전)이 다음
            // createBoardWithPosts()에서 제목이 "테스트 게시글 {i}"이고 created_at=now()->subMinutes($i)
            $this->assertStringContainsString('게시글 0', $data[0]['title']);
            $this->assertStringContainsString('게시글 1', $data[1]['title']);
        }
    }

    /**
     * 결과가 캐시되는지 테스트
     */
    public function test_recent_posts_are_cached(): void
    {
        // Given: 게시판 생성
        $this->createBoardWithPosts(3);

        // When: 첫 번째 API 호출
        $response1 = $this->withHeader('Accept-Language', 'ko')
            ->getJson('/api/modules/sirsoft-board/boards/posts/recent?limit=5');
        $response1->assertStatus(200);

        // 캐시 키 확인 (ModuleCacheDriver 접두사 `g7:module.sirsoft-board:` + key)
        $this->assertTrue(Cache::has('g7:module.sirsoft-board:recent_posts_ko_5'));

        // When: 두 번째 API 호출
        $response2 = $this->withHeader('Accept-Language', 'ko')
            ->getJson('/api/modules/sirsoft-board/boards/posts/recent?limit=5');
        $response2->assertStatus(200);

        // Then: 같은 결과 반환
        $this->assertEquals($response1->json('data'), $response2->json('data'));
    }

    /**
     * 비밀글이 포함되어 is_secret 필드가 반환되는지 테스트
     */
    public function test_recent_posts_includes_secret_posts_with_is_secret_field(): void
    {
        // Given: 비밀글 포함 게시글 생성
        $this->createBoardWithPosts(3, includingSecret: true);

        // When: API 호출
        $response = $this->withHeader('Accept-Language', 'ko')
            ->getJson('/api/modules/sirsoft-board/boards/posts/recent');

        // Then: is_secret 필드 포함 및 비밀글 반환
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data);

        // 모든 게시글에 is_secret 필드가 있어야 함
        foreach ($data as $post) {
            $this->assertArrayHasKey('is_secret', $post);
        }

        // 비밀글이 포함되어 있어야 함
        $secretPosts = array_filter($data, fn ($post) => $post['is_secret'] === true);
        $this->assertNotEmpty($secretPosts, '비밀글이 응답에 포함되어야 합니다');
    }

    /**
     * 비밀글도 제목이 정상적으로 표시되는지 테스트
     */
    public function test_secret_post_title_is_visible_in_recent_posts(): void
    {
        // Given: 비밀글이 있는 게시판 생성
        $board = Board::factory()->create([
            'is_active' => true,
            'name' => ['ko' => '비밀게시판', 'en' => 'Secret Board'],
        ]);

        DB::table('board_posts')->insert([
            'board_id' => $board->id,
            'title' => '비밀 문의입니다',
            'content' => '비밀 내용',
            'author_name' => '홍길동',
            'is_secret' => true,
            'status' => PostStatus::Published->value,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // When: API 호출
        $response = $this->getJson('/api/modules/sirsoft-board/boards/posts/recent');

        // Then: 비밀글 제목이 보여야 함 (마스킹 안 됨)
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $secretPost = $data[0];
        $this->assertTrue($secretPost['is_secret']);
        $this->assertEquals('비밀 문의입니다', $secretPost['title']);
    }

    /**
     * 최근 게시글 응답에 created_at(요일 포함 포맷)과 created_at_formatted(표시용) 필드가 포함되는지 확인
     */
    public function test_recent_posts_includes_created_at_and_created_at_formatted(): void
    {
        // Given: 게시판과 게시글 생성
        $this->createBoardWithPosts(1);

        // When: API 호출
        $response = $this->withHeader('Accept-Language', 'ko')
            ->getJson('/api/modules/sirsoft-board/boards/posts/recent');

        // Then: 응답에 created_at/created_at_formatted 필드 포함
        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertNotEmpty($data);
        $item = $data[0];

        // created_at: 요일 포함 전체 날짜 포맷
        $this->assertArrayHasKey('created_at', $item);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} [가-힣]+요일 \d{2}:\d{2}$/', $item['created_at']);

        // created_at_formatted: 표시용 포맷 (비어있지 않은 문자열)
        $this->assertArrayHasKey('created_at_formatted', $item);
        $this->assertNotEmpty($item['created_at_formatted']);
    }

    /**
     * 최근 게시글 날짜 표시가 요청 locale에 맞게 반환되는지 확인
     */
    public function test_recent_posts_dates_are_localized_by_request_locale(): void
    {
        // Given: 캐시된 응답에서도 locale이 섞이지 않도록 현재 글 생성
        $this->createBoardWithPosts(1);

        // When: 한국어 요청 후 영어 요청
        $koResponse = $this->withHeader('Accept-Language', 'ko')
            ->getJson('/api/modules/sirsoft-board/boards/posts/recent?limit=1');
        $enResponse = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/modules/sirsoft-board/boards/posts/recent?limit=1');

        // Then: locale별 날짜 문자열과 캐시 키가 분리되어야 함
        $koResponse->assertStatus(200);
        $enResponse->assertStatus(200);

        $koItem = $koResponse->json('data.0');
        $enItem = $enResponse->json('data.0');

        $this->assertStringContainsString('요일', $koItem['created_at']);
        $this->assertStringNotContainsString('요일', $enItem['created_at']);
        $this->assertMatchesRegularExpression('/(just now|minute|hour|day|month|year)/', $enItem['created_at_formatted']);
        $this->assertDoesNotMatchRegularExpression('/[가-힣]/', $enItem['created_at_formatted']);

        $this->assertTrue(Cache::has('g7:module.sirsoft-board:recent_posts_ko_1'));
        $this->assertTrue(Cache::has('g7:module.sirsoft-board:recent_posts_en_1'));
    }

    /**
     * 게시판과 게시글을 생성하는 헬퍼
     *
     * @param int $postCount 생성할 게시글 수
     * @param bool $includingSecret 비밀글 포함 여부
     * @return Board 생성된 게시판
     */
    private function createBoardWithPosts(int $postCount, bool $includingSecret = false): Board
    {
        $board = Board::factory()->create([
            'is_active' => true,
        ]);

        for ($i = 0; $i < $postCount; $i++) {
            $isSecret = $includingSecret && ($i % 2 === 0);

            DB::table('board_posts')->insert([
                'board_id' => $board->id,
                'title' => "테스트 게시글 {$i}",
                'content' => "게시글 내용 {$i}",
                'author_name' => '작성자',
                'view_count' => rand(0, 100),
                'is_secret' => $isSecret,
                'status' => PostStatus::Published->value,
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subMinutes($i),
                'updated_at' => now()->subMinutes($i),
            ]);
        }

        return $board;
    }
}
