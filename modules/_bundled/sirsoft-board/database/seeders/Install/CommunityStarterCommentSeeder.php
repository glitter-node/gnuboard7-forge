<?php

namespace Modules\Sirsoft\Board\Database\Seeders\Install;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\Sirsoft\Board\Http\Requests\StoreCommentRequest;
use Modules\Sirsoft\Board\Models\Board;
use Modules\Sirsoft\Board\Models\Comment;
use Modules\Sirsoft\Board\Models\Post;
use Modules\Sirsoft\Board\Services\CommentService;

class CommunityStarterCommentSeeder extends Seeder
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

    public function run(): void
    {
        $author = $this->resolveAuthor();

        if (! $author) {
            $this->command?->warn('커뮤니티 starter comment를 작성할 관리자 계정을 찾지 못해 생성을 건너뜁니다.');

            return;
        }

        $previousUser = Auth::user();
        Auth::login($author);

        try {
            foreach (self::COMMENTS as $commentData) {
                $this->createCommentIfMissing($commentData, $author);
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
     * @param  array{slug: string, title: string, content: string}  $commentData
     *
     * @throws ValidationException
     */
    private function createCommentIfMissing(array $commentData, User $author): void
    {
        $board = Board::query()->where('slug', $commentData['slug'])->first();

        if (! $board) {
            $this->command?->warn("게시판 [{$commentData['slug']}]을 찾지 못해 starter comment 생성을 건너뜁니다.");

            return;
        }

        $post = Post::query()
            ->where('board_id', $board->id)
            ->where('title', $commentData['title'])
            ->first();

        if (! $post) {
            $this->command?->warn("게시글 [{$commentData['title']}]을 찾지 못해 starter comment 생성을 건너뜁니다.");

            return;
        }

        $exists = Comment::query()
            ->withTrashed()
            ->where('board_id', $board->id)
            ->where('post_id', $post->id)
            ->where('content', $commentData['content'])
            ->exists();

        if ($exists) {
            return;
        }

        $payload = [
            'content' => $commentData['content'],
            'post_id' => $post->id,
            'is_secret' => false,
            'status' => 'published',
        ];

        $this->validateCommentPayload($commentData['slug'], $post->id, $payload, $author);

        app(CommentService::class)->createComment($commentData['slug'], array_merge($payload, [
            'user_id' => $author->id,
            'author_name' => $author->name,
            'ip_address' => '127.0.0.1',
            'trigger_type' => 'system',
            'action_logs' => [[
                'action' => 'starter_comment',
            ]],
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    private function validateCommentPayload(string $slug, int $postId, array $payload, User $author): void
    {
        $request = StoreCommentRequest::create('/api/modules/sirsoft-board/boards/'.$slug.'/posts/'.$postId.'/comments', 'POST', $payload);
        $route = new Route(['POST'], '/api/modules/sirsoft-board/boards/{slug}/posts/{postId}/comments', []);
        $route->bind($request);
        $route->setParameter('slug', $slug);
        $route->setParameter('postId', $postId);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $author);

        Validator::make(
            $payload,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        )->validate();
    }
}
