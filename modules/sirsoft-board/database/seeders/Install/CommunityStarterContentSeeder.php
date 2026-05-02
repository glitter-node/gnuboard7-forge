<?php

namespace Modules\Sirsoft\Board\Database\Seeders\Install;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Sirsoft\Board\Http\Requests\StorePostRequest;
use Modules\Sirsoft\Board\Models\Board;
use Modules\Sirsoft\Board\Models\Post;
use Modules\Sirsoft\Board\Services\PostService;

class CommunityStarterContentSeeder extends Seeder
{
    private const MARKER_PREFIX = 'sirsoft-board.install.community-starter.v1.';

    /**
     * @var array<int, array<string, mixed>>
     */
    private const POSTS = [
        [
            'slug' => 'notice',
            'title' => '그누보드7 커뮤니티 베타 운영 안내',
            'content' => <<<'HTML'
<p>그누보드7 커뮤니티 베타를 시작합니다.</p>
<p>이 공간은 공지사항, 자유로운 의견, 질문과 답변을 함께 나누기 위해 준비되었습니다. 베타 기간에는 게시판 사용 경험과 개선 의견을 우선적으로 확인하며 안정적으로 운영해 나가겠습니다.</p>
<p>서비스 이용 중 발견한 문제나 제안은 질문게시판 또는 자유게시판에 남겨주세요. 운영진이 확인 후 필요한 내용을 안내하겠습니다.</p>
HTML,
            'is_notice' => true,
        ],
        [
            'slug' => 'free',
            'title' => '커뮤니티는 이렇게 활용해보세요',
            'content' => <<<'HTML'
<p>자유게시판은 회원들이 편하게 이야기를 나누는 공간입니다.</p>
<p>그누보드7을 사용하면서 알게 된 팁, 운영 경험, 커뮤니티를 더 잘 활용하는 방법을 공유해보세요. 가벼운 인사나 사용 후기처럼 부담 없는 주제도 좋습니다.</p>
HTML,
            'is_notice' => false,
        ],
        [
            'slug' => 'free',
            'title' => '개선 의견과 제안을 들려주세요',
            'content' => <<<'HTML'
<p>커뮤니티가 더 편해지려면 실제 사용자의 의견이 필요합니다.</p>
<p>메뉴 구성, 글쓰기 흐름, 게시판 분류, 알림 방식처럼 사용하면서 어색하거나 더 좋아질 수 있는 부분을 자유롭게 남겨주세요. 작은 제안도 베타 운영에 큰 도움이 됩니다.</p>
HTML,
            'is_notice' => false,
        ],
        [
            'slug' => 'qna',
            'title' => '게시글은 어떻게 작성하면 좋을까요?',
            'content' => <<<'HTML'
<p>좋은 게시글은 다른 회원이 내용을 빠르게 이해할 수 있게 도와줍니다.</p>
<p>제목에는 핵심 주제를 적고, 본문에는 상황, 시도한 방법, 기대한 결과를 함께 적어주세요. 질문이라면 사용 중인 환경이나 오류 메시지를 덧붙이면 답변을 받기 쉽습니다.</p>
HTML,
            'is_notice' => false,
        ],
        [
            'slug' => 'qna',
            'title' => '문제나 오류는 어디에 알려야 하나요?',
            'content' => <<<'HTML'
<p>사용 중 문제가 보이면 질문게시판에 상황을 남겨주세요.</p>
<p>발생한 화면, 재현 순서, 기대한 동작과 실제 결과를 함께 적으면 확인이 빠릅니다. 민감한 정보는 공개 글에 포함하지 말고, 필요한 경우 운영 안내에 따라 별도로 전달해주세요.</p>
HTML,
            'is_notice' => false,
        ],
    ];

    public function run(): void
    {
        $author = $this->resolveAuthor();

        if (! $author) {
            $this->command?->warn('커뮤니티 starter content를 작성할 관리자 계정을 찾지 못해 생성을 건너뜁니다.');

            return;
        }

        $previousUser = Auth::user();
        Auth::login($author);

        try {
            foreach (self::POSTS as $postData) {
                $this->createPostIfMissing($postData, $author);
            }
        } finally {
            if ($previousUser) {
                Auth::login($previousUser);
            } else {
                Auth::logout();
            }
        }
    }

    private function resolveAuthor(): ?User
    {
        return User::query()
            ->whereHas('roles', function ($query) {
                $query->where('identifier', 'admin');
            })
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $postData
     *
     * @throws ValidationException
     */
    private function createPostIfMissing(array $postData, User $author): void
    {
        $board = Board::query()->where('slug', $postData['slug'])->first();

        if (! $board) {
            $this->command?->warn("게시판 [{$postData['slug']}]을 찾지 못해 starter content 생성을 건너뜁니다.");

            return;
        }

        $exists = Post::query()
            ->withTrashed()
            ->where('board_id', $board->id)
            ->where('title', $postData['title'])
            ->exists();

        if ($exists) {
            return;
        }

        $payload = [
            'title' => $postData['title'],
            'content' => $postData['content'],
            'content_mode' => 'html',
            'is_notice' => (bool) $postData['is_notice'],
            'is_secret' => false,
            'status' => 'published',
        ];

        $this->validatePostPayload($postData['slug'], $payload);

        app(PostService::class)->createPost($postData['slug'], array_merge($payload, [
            'user_id' => $author->id,
            'author_name' => $author->name,
            'ip_address' => '127.0.0.1',
            'trigger_type' => 'system',
            'action_logs' => [[
                'action' => 'starter_content',
                'marker' => self::MARKER_PREFIX.$postData['slug'],
            ]],
            'view_count' => 0,
        ]), options: ['skip_notification' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    private function validatePostPayload(string $slug, array $payload): void
    {
        $request = StorePostRequest::create('/api/modules/sirsoft-board/boards/'.$slug.'/posts', 'POST', $payload);
        $route = new Route(['POST'], '/api/modules/sirsoft-board/boards/{slug}/posts', []);
        $route->bind($request);
        $route->setParameter('slug', $slug);
        $request->setRouteResolver(fn () => $route);

        Validator::make(
            $payload,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        )->validate();
    }
}
