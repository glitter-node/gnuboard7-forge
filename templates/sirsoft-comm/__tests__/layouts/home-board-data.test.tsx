import React from 'react';
import { describe, expect, it, afterEach } from 'vitest';
import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import {
  createLayoutTest,
  createMockComponentRegistryWithBasics,
  screen,
} from '@core/template-engine/__tests__/utils/layoutTestUtils';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const templateRoot = path.resolve(__dirname, '..', '..');

function readJson<T = any>(relativePath: string): T {
  return JSON.parse(readFileSync(path.join(templateRoot, relativePath), 'utf-8')) as T;
}

function loadTemplateTranslations(locale: 'en' | 'ko') {
  const manifest = readJson<Record<string, any>>(`lang/${locale}.json`);

  return Object.fromEntries(
    Object.entries(manifest).map(([key, value]) => {
      if (value && typeof value === 'object' && typeof value.$partial === 'string') {
        return [key, readJson(`lang/${value.$partial}`)];
      }

      return [key, value];
    })
  );
}

function resolvePartials<T = any>(value: T): T {
  if (Array.isArray(value)) {
    return value.map((item) => resolvePartials(item)) as T;
  }

  if (!value || typeof value !== 'object') {
    return value;
  }

  const record = value as Record<string, any>;
  if (typeof record.partial === 'string') {
    return resolvePartials(readJson(path.join('layouts', record.partial)));
  }

  return Object.fromEntries(
    Object.entries(record).map(([key, child]) => [key, resolvePartials(child)])
  ) as T;
}

function createRegistry() {
  const registry = createMockComponentRegistryWithBasics();

  registry.register('basic', 'Icon', ({ name, className }) => (
    <span className={className} data-icon={name} />
  ));
  registry.register('layout', 'Container', ({ children, className }) => (
    <div className={className}>{children}</div>
  ));

  return registry;
}

function createHomeLayoutTest(locale: 'en' | 'ko' = 'ko') {
  const homeLayout = resolvePartials(readJson('layouts/home.json'));
  const testUtils = createLayoutTest(homeLayout, {
    componentRegistry: createRegistry(),
    templateId: 'sirsoft-comm',
    locale,
    translations: loadTemplateTranslations(locale),
  });

  return testUtils;
}

describe('sirsoft-comm home board data bindings', () => {
  let testUtils: ReturnType<typeof createHomeLayoutTest> | null = null;

  afterEach(() => {
    testUtils?.cleanup();
    testUtils = null;
  });

  it('renders public board API data on the homepage', async () => {
    testUtils = createHomeLayoutTest();

    testUtils.mockApi('stats', {
      response: {
        data: {
          users: 11,
          boards: 3,
          posts: 1,
          comments: 0,
        },
      },
    });
    testUtils.mockApi('recent_posts', {
      response: {
        data: [
          {
            id: 6,
            board_slug: 'notice',
            board_name: '공지사항',
            title: 'Welcome to the notice board',
            created_at: '2026-05-03 05:51',
            created_at_formatted: '7분 전',
            comment_count: 0,
            is_secret: false,
            is_new: true,
          },
        ],
      },
    });
    testUtils.mockApi('popular_boards', {
      response: {
        data: [
          { id: 30, name: '공지사항', slug: 'notice', posts_count: 1 },
          { id: 32, name: '질문게시판', slug: 'qna', posts_count: 0 },
          { id: 31, name: '자유게시판', slug: 'free', posts_count: 0 },
        ],
      },
    });
    testUtils.mockApi('home_boards', {
      response: {
        data: [
          { id: 99, name: '기타게시판', slug: 'archive', posts_count: 12, recent_posts: [] },
          { id: 32, name: '질문게시판', slug: 'qna', posts_count: 0, recent_posts: [] },
          { id: 31, name: '자유게시판', slug: 'free', posts_count: 0, recent_posts: [] },
          {
            id: 30,
            name: '공지사항',
            slug: 'notice',
            posts_count: 1,
            recent_posts: [
              {
                id: 6,
                title: 'Welcome to the notice board',
                created_at: '2026-05-03 05:51',
                created_at_formatted: '7분 전',
                comment_count: 0,
                is_notice: true,
                is_secret: false,
                is_new: true,
              },
            ],
          },
        ],
      },
    });

    await testUtils.render();
    testUtils.assertNoValidationErrors();

    expect(screen.getAllByText('공지사항').length).toBeGreaterThan(0);
    expect(screen.getAllByText('자유게시판').length).toBeGreaterThan(0);
    expect(screen.getAllByText('질문게시판').length).toBeGreaterThan(0);
    expect(screen.queryByText('boards.notice')).not.toBeInTheDocument();
    expect(screen.queryByText('boards.free')).not.toBeInTheDocument();
    expect(screen.queryByText('boards.qna')).not.toBeInTheDocument();
    expect(screen.getAllByText('Welcome to the notice board').length).toBeGreaterThan(0);
    expect(screen.getByText('11')).toBeInTheDocument();
    expect(screen.getAllByText('3').length).toBeGreaterThan(0);
    expect(screen.queryByText('기타게시판')).not.toBeInTheDocument();
  });

  it('renders starter board names through the active locale', async () => {
    testUtils = createHomeLayoutTest('en');

    testUtils.mockApi('stats', {
      response: {
        data: {
          users: 11,
          boards: 3,
          posts: 1,
          comments: 0,
        },
      },
    });
    testUtils.mockApi('recent_posts', {
      response: {
        data: [
          {
            id: 6,
            board_slug: 'notice',
            board_name: '공지사항',
            title: 'Welcome to the notice board',
            created_at: '2026-05-03 05:51',
            created_at_formatted: '7 minutes ago',
            comment_count: 0,
            is_secret: false,
            is_new: true,
          },
        ],
      },
    });
    testUtils.mockApi('popular_boards', {
      response: {
        data: [
          { id: 30, name: '공지사항', slug: 'notice', posts_count: 1 },
          { id: 32, name: '질문게시판', slug: 'qna', posts_count: 0 },
          { id: 31, name: '자유게시판', slug: 'free', posts_count: 0 },
        ],
      },
    });
    testUtils.mockApi('home_boards', {
      response: {
        data: [
          { id: 32, name: '질문게시판', slug: 'qna', posts_count: 0, recent_posts: [] },
          { id: 31, name: '자유게시판', slug: 'free', posts_count: 0, recent_posts: [] },
          {
            id: 30,
            name: '공지사항',
            slug: 'notice',
            posts_count: 1,
            recent_posts: [
              {
                id: 6,
                title: 'Welcome to the notice board',
                created_at: '2026-05-03 05:51',
                created_at_formatted: '7 minutes ago',
                comment_count: 0,
                is_notice: true,
                is_secret: false,
                is_new: true,
              },
            ],
          },
        ],
      },
    });

    await testUtils.render();
    testUtils.assertNoValidationErrors();

    expect(screen.getAllByText('Notice').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Free Board').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Q&A Board').length).toBeGreaterThan(0);
    expect(screen.queryByText('boards.notice')).not.toBeInTheDocument();
    expect(screen.queryByText('boards.free')).not.toBeInTheDocument();
    expect(screen.queryByText('boards.qna')).not.toBeInTheDocument();
    expect(screen.queryByText('공지사항')).not.toBeInTheDocument();
    expect(screen.queryByText('자유게시판')).not.toBeInTheDocument();
    expect(screen.queryByText('질문게시판')).not.toBeInTheDocument();
  });

  it('preserves homepage empty states when board arrays are empty', async () => {
    testUtils = createHomeLayoutTest();

    testUtils.mockApi('stats', {
      response: { data: { users: 0, boards: 0, posts: 0, comments: 0 } },
    });
    testUtils.mockApi('recent_posts', { response: { data: [] } });
    testUtils.mockApi('popular_boards', { response: { data: [] } });
    testUtils.mockApi('home_boards', { response: { data: [] } });

    await testUtils.render();
    testUtils.assertNoValidationErrors();

    expect(screen.getByText('첫 대화를 시작할 준비가 되었습니다')).toBeInTheDocument();
    expect(screen.getByText('활동에 따라 인기 게시판이 정렬됩니다')).toBeInTheDocument();
    expect(screen.getByText('설정 후 추천 게시판이 표시됩니다')).toBeInTheDocument();
  });

  it('renders refreshed homepage stats and recent posts after a free-board post is created', async () => {
    testUtils = createHomeLayoutTest();

    testUtils.mockApi('stats', {
      response: {
        data: {
          users: 11,
          boards: 3,
          posts: 2,
          comments: 0,
        },
      },
    });
    testUtils.mockApi('recent_posts', {
      response: {
        data: [
          {
            id: 77,
            board_slug: 'free',
            board_name: '자유게시판',
            title: '새 자유게시판 글',
            created_at: '2026-05-03 07:10',
            created_at_formatted: '방금 전',
            comment_count: 0,
            is_secret: false,
            is_new: true,
          },
        ],
      },
    });
    testUtils.mockApi('popular_boards', {
      response: {
        data: [
          { id: 31, name: '자유게시판', slug: 'free', posts_count: 1 },
          { id: 30, name: '공지사항', slug: 'notice', posts_count: 1 },
          { id: 32, name: '질문게시판', slug: 'qna', posts_count: 0 },
        ],
      },
    });
    testUtils.mockApi('home_boards', {
      response: {
        data: [
          {
            id: 31,
            name: '자유게시판',
            slug: 'free',
            posts_count: 1,
            recent_posts: [
              {
                id: 77,
                title: '새 자유게시판 글',
                created_at: '2026-05-03 07:10',
                created_at_formatted: '방금 전',
                comment_count: 0,
                is_notice: false,
                is_secret: false,
                is_new: true,
              },
            ],
          },
          { id: 32, name: '질문게시판', slug: 'qna', posts_count: 0, recent_posts: [] },
          { id: 30, name: '공지사항', slug: 'notice', posts_count: 1, recent_posts: [] },
        ],
      },
    });

    await testUtils.render();
    testUtils.assertNoValidationErrors();

    expect(screen.getAllByText('자유게시판').length).toBeGreaterThan(0);
    expect(screen.getAllByText('새 자유게시판 글').length).toBeGreaterThan(0);
    expect(screen.getAllByText('방금 전').length).toBeGreaterThan(0);
    expect(screen.getAllByText('2').length).toBeGreaterThan(0);
    expect(screen.queryByText('첫 대화를 시작할 준비가 되었습니다')).not.toBeInTheDocument();
  });
});
